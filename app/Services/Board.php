<?php

namespace App\Services;

use App\Exceptions\FlowException;
use App\Exceptions\StaleCard;
use App\Exceptions\WipLimitReached;
use App\Models\Activity;
use App\Models\Card;
use App\Models\CardMove;
use App\Models\ChecklistItem;
use App\Models\Column;
use App\Models\Epic;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Semua perubahan papan lewat kelas ini.
 *
 * Aturannya:
 *  - Setiap operasi tulis mengunci baris proyek dulu (SELECT ... FOR UPDATE). Jadi perubahan
 *    di satu papan dilayani bergantian, dan pemeriksaan batas WIP selalu melihat hitungan
 *    yang benar. Satu titik kunci juga berarti urutan kunci selalu sama: tidak ada deadlock
 *    antara "buat kartu" dan "pindah kartu". Papan berisi segelintir orang, jadi antreannya
 *    tidak terasa.
 *  - Batas WIP berlaku untuk kartu yang MASUK ke kolom. Kalau batasnya diturunkan di bawah
 *    isi kolom saat ini, kartu yang sudah ada tetap di sana; kolom itu hanya menolak kartu baru
 *    sampai isinya turun.
 *  - started_at diisi saat kartu pertama kali masuk kolom "active" atau "wait" dan tidak pernah di-reset.
 *    completed_at diisi saat masuk kolom "done" dan dikosongkan kalau kartu dibuka ulang.
 *    Kartu yang langsung lompat dari antrean ke selesai tidak punya started_at: ikut dihitung
 *    di throughput, tapi tidak di cycle time (tidak ada waktu kerja yang bisa diukur).
 *  - Setiap perpindahan antarkolom dicatat di card_moves. Metrik dibangun ulang dari situ.
 *  - Perpindahan mundur dari kolom kerja/tunggu/selesai ke kolom kerja/tunggu ditandai sebagai revisi
 *    (rework), beserta alasannya. Mundur ke antrean bukan revisi: itu menunda, bukan mengulang.
 *  - projects.version naik di setiap perubahan, supaya browser tahu kapan papan perlu dimuat ulang.
 */
class Board
{
    public const DEFAULT_COLUMNS = [
        ['Backlog', Column::QUEUE, null],
        ['Siap dikerjakan', Column::QUEUE, 5],
        ['Dikerjakan', Column::ACTIVE, 3],
        ['Review', Column::ACTIVE, 2],
        ['QA', Column::ACTIVE, 2],
        ['Menunggu merge', Column::WAIT, null],
        ['Selesai', Column::DONE, null],
    ];

    public function createProject(User $owner, string $name, string $key, ?string $description = null): Project
    {
        return DB::transaction(function () use ($owner, $name, $key, $description) {
            $project = Project::create([
                'name' => $name,
                'key' => strtoupper($key),
                'description' => $description,
                'owner_id' => $owner->id,
            ]);
            $project->members()->attach($owner->id, ['role' => ProjectMember::OWNER]);

            foreach (self::DEFAULT_COLUMNS as $i => [$columnName, $kind, $limit]) {
                $project->columns()->create([
                    'name' => $columnName,
                    'kind' => $kind,
                    'wip_limit' => $limit,
                    'position' => $i,
                ]);
            }

            return $project;
        });
    }

    /** @param  array{title: string, description?: ?string, priority?: int, assignee_id?: ?int, due_on?: ?string}  $attributes */
    public function createCard(Column $column, User $by, array $attributes, array $labelIds = []): Card
    {
        return DB::transaction(function () use ($column, $by, $attributes, $labelIds) {
            $project = $this->lockProject($column->project_id);
            $column = $this->freshColumn($project, $column->id);
            $this->ensureRoom($column);
            $this->ensureEpic($project, $attributes['epic_id'] ?? null);

            $now = now();
            $project->increment('card_seq');

            $card = new Card($attributes);
            $card->forceFill([
                'project_id' => $project->id,
                'column_id' => $column->id,
                'number' => $project->card_seq,
                'position' => (int) $column->cards()->max('position') + 1,
                'created_by' => $by->id,
                'lock_version' => 0,
                'column_entered_at' => $now,
                'started_at' => $column->isInProgress() || $column->isDone() ? $now : null,
                'completed_at' => $column->isDone() ? $now : null,
            ])->save();
            $card->labels()->sync($this->labelsOf($project, $labelIds));

            $this->recordMove($card, null, $column->id, $by);
            $this->log($card, $by, 'created', ['column' => $column->name]);
            $this->touch($project);

            return $card;
        });
    }

    /**
     * Pindahkan kartu ke $target, tepat sebelum kartu $beforeId. Kalau $beforeId kosong
     * atau kartu itu sudah tidak ada di kolom tujuan (dipindah orang lain sementara layarmu
     * belum diperbarui), kartu ditaruh paling bawah.
     */
    public function move(Card $card, Column $target, User $by, ?int $beforeId = null, ?string $reason = null, ?string $note = null): Card
    {
        return DB::transaction(function () use ($card, $target, $by, $beforeId, $reason, $note) {
            $project = $this->lockProject($card->project_id);
            $target = $this->freshColumn($project, $target->id);
            $card = Card::whereKey($card->id)->where('project_id', $project->id)->firstOrFail();

            if ($card->archived_at) {
                throw new FlowException('Kartu ini sudah diarsipkan.');
            }

            $from = $card->column_id === $target->id ? $target : $this->freshColumn($project, $card->column_id);
            $changesColumn = $from->id !== $target->id;

            if ($changesColumn) {
                $this->ensureRoom($target);
            }

            $this->placeCard($card, $target, $beforeId);

            if ($changesColumn) {
                $now = now();
                $card->column_id = $target->id;
                $card->column_entered_at = $now;
                if (! $card->started_at && $target->isInProgress()) {
                    $card->started_at = $now;
                }
                $card->completed_at = $target->isDone() ? ($card->completed_at ?? $now) : null;
                $card->save();

                $rework = self::isRework($from, $target);
                $reason = $rework && array_key_exists((string) $reason, CardMove::REASONS) ? $reason : null;
                $note = $rework && $note !== null && trim($note) !== '' ? trim($note) : null;

                $this->recordMove($card, $from->id, $target->id, $by, $rework, $reason, $note);
                $this->log($card, $by, 'moved', array_filter([
                    'from' => $from->name,
                    'to' => $target->name,
                    'rework' => $rework ?: null,
                    'reason' => $reason,
                    'note' => $note,
                ]));
            }

            $this->touch($project);

            return $card;
        });
    }

    /**
     * Simpan perubahan isi kartu. $expectedVersion adalah lock_version saat form dibuka;
     * kalau sudah berbeda, orang lain menyimpan duluan dan perubahan ini ditolak.
     */
    public function updateCard(Card $card, User $by, int $expectedVersion, array $attributes, ?array $labelIds = null): Card
    {
        return DB::transaction(function () use ($card, $by, $expectedVersion, $attributes, $labelIds) {
            $project = $this->lockProject($card->project_id);
            $card = Card::whereKey($card->id)->firstOrFail();

            if ($card->lock_version !== $expectedVersion) {
                $lastEditor = Activity::where('card_id', $card->id)->where('type', 'edited')
                    ->latest('id')->first()?->user;
                throw new StaleCard($lastEditor && $lastEditor->id !== $by->id ? $lastEditor->name : null);
            }

            $card->fill($attributes);
            $changed = array_keys($card->getDirty());

            if (array_key_exists('assignee_id', $attributes) && $card->isDirty('assignee_id')) {
                $this->ensureMember($project, $attributes['assignee_id']);
            }
            if (array_key_exists('epic_id', $attributes) && $card->isDirty('epic_id')) {
                $this->ensureEpic($project, $attributes['epic_id']);
            }

            if ($labelIds !== null) {
                $result = $card->labels()->sync($this->labelsOf($project, $labelIds));
                if (array_filter($result)) {
                    $changed[] = 'labels';
                }
            }

            if ($changed) {
                $card->lock_version++;
                $card->save();
                $this->log($card, $by, 'edited', ['fields' => array_values(array_unique($changed))]);
                $this->touch($project);
            }

            return $card;
        });
    }

    public function block(Card $card, User $by, string $reason): Card
    {
        return $this->onCard($card, function (Card $card) use ($by, $reason) {
            $card->forceFill(['blocked_reason' => $reason, 'blocked_at' => $card->blocked_at ?? now()])->save();
            $this->log($card, $by, 'blocked', ['reason' => $reason]);
        });
    }

    public function unblock(Card $card, User $by): Card
    {
        return $this->onCard($card, function (Card $card) use ($by) {
            if (! $card->blocked_at) {
                return;
            }
            $hours = round($card->blocked_at->diffInMinutes(now()) / 60, 1);
            $card->forceFill(['blocked_reason' => null, 'blocked_at' => null])->save();
            $this->log($card, $by, 'unblocked', ['hours' => $hours]);
        });
    }

    public function archive(Card $card, User $by): Card
    {
        return $this->onCard($card, function (Card $card) use ($by) {
            if ($card->archived_at) {
                return;
            }
            $card->forceFill(['archived_at' => now()])->save();
            $this->recordMove($card, $card->column_id, null, $by);
            $this->log($card, $by, 'archived');
        });
    }

    /** Kembalikan kartu arsip ke kolom asalnya, paling bawah. Tetap tunduk pada batas WIP. */
    public function restore(Card $card, User $by): Card
    {
        return DB::transaction(function () use ($card, $by) {
            $project = $this->lockProject($card->project_id);
            $card = Card::whereKey($card->id)->firstOrFail();
            if (! $card->archived_at) {
                return $card;
            }

            $column = $this->freshColumn($project, $card->column_id);
            $this->ensureRoom($column);

            $card->forceFill([
                'archived_at' => null,
                'position' => (int) $column->cards()->max('position') + 1,
                'column_entered_at' => now(),
            ])->save();
            $this->recordMove($card, null, $column->id, $by);
            $this->log($card, $by, 'restored', ['column' => $column->name]);
            $this->touch($project);

            return $card;
        });
    }

    public function comment(Card $card, User $by, string $body): void
    {
        $this->onCard($card, function (Card $card) use ($by, $body) {
            $card->comments()->create(['user_id' => $by->id, 'body' => $body]);
            $this->log($card, $by, 'commented');
        });
    }

    public function addChecklistItem(Card $card, string $body): ChecklistItem
    {
        $item = null;
        $this->onCard($card, function (Card $card) use ($body, &$item) {
            $item = $card->checklist()->create([
                'body' => $body,
                'position' => (int) $card->checklist()->max('position') + 1,
            ]);
        });

        return $item;
    }

    public function toggleChecklistItem(ChecklistItem $item): void
    {
        $this->onCard($item->card, fn () => $item->update(['done' => ! $item->done]));
    }

    public function removeChecklistItem(ChecklistItem $item): void
    {
        $this->onCard($item->card, fn () => $item->delete());
    }

    // ---------------------------------------------------------------- epic

    /** @param  array{name: string, description?: ?string, color: string, target_on?: ?string}  $attributes */
    public function createEpic(Project $project, User $by, array $attributes): Epic
    {
        return DB::transaction(function () use ($project, $by, $attributes) {
            $project = $this->lockProject($project->id);
            $epic = $project->epics()->create($attributes + ['created_by' => $by->id]);
            $this->log($project, $by, 'epic_created', ['epic' => $epic->name]);
            $this->touch($project);

            return $epic;
        });
    }

    public function updateEpic(Epic $epic, User $by, array $attributes): Epic
    {
        return DB::transaction(function () use ($epic, $attributes) {
            $project = $this->lockProject($epic->project_id);
            $epic->update($attributes);
            $this->touch($project);

            return $epic;
        });
    }

    /** Kartunya tidak ikut terhapus; hanya dilepas dari epic. */
    public function deleteEpic(Epic $epic, User $by): void
    {
        DB::transaction(function () use ($epic, $by) {
            $project = $this->lockProject($epic->project_id);
            $released = Card::where('epic_id', $epic->id)->update(['epic_id' => null]);
            $epic->delete();
            $this->log($project, $by, 'epic_deleted', ['epic' => $epic->name, 'cards' => $released]);
            $this->touch($project);
        });
    }

    // ---------------------------------------------------------------- kolom

    public function setWipLimit(Column $column, User $by, ?int $limit): Column
    {
        return DB::transaction(function () use ($column, $by, $limit) {
            $project = $this->lockProject($column->project_id);
            $column = $this->freshColumn($project, $column->id);

            if ($limit !== null && $limit < 1) {
                throw new FlowException('Batas WIP minimal 1. Kosongkan untuk tanpa batas.');
            }
            if ($column->wip_limit === $limit) {
                return $column;
            }

            $this->log($project, $by, 'wip_changed', ['column' => $column->name, 'from' => $column->wip_limit, 'to' => $limit]);
            $column->update(['wip_limit' => $limit]);
            $this->touch($project);

            return $column;
        });
    }

    public function addColumn(Project $project, User $by, string $name, string $kind, ?int $limit): Column
    {
        return DB::transaction(function () use ($project, $by, $name, $kind, $limit) {
            $project = $this->lockProject($project->id);

            // Kolom baru masuk sebelum kolom "done" pertama, supaya "Selesai" tetap paling kanan.
            $done = $project->columns()->where('kind', Column::DONE)->orderBy('position')->first();
            $position = $kind !== Column::DONE && $done ? $done->position : (int) $project->columns()->max('position') + 1;
            $project->columns()->where('position', '>=', $position)->increment('position');

            $column = $project->columns()->create([
                'name' => $name,
                'kind' => $kind,
                'wip_limit' => $limit,
                'position' => $position,
            ]);
            $this->log($project, $by, 'column_added', ['column' => $name]);
            $this->touch($project);

            return $column;
        });
    }

    public function renameColumn(Column $column, User $by, string $name, string $kind): Column
    {
        return DB::transaction(function () use ($column, $by, $name, $kind) {
            $project = $this->lockProject($column->project_id);
            $column = $this->freshColumn($project, $column->id);

            if ($column->kind === Column::DONE && $kind !== Column::DONE
                && ! $project->columns()->where('kind', Column::DONE)->whereKeyNot($column->id)->exists()) {
                throw new FlowException('Papan butuh minimal satu kolom selesai.');
            }
            if ($column->kind !== $kind && $column->cards()->exists()) {
                // Mengubah jenis kolom yang berisi kartu akan mengubah arti waktu mulai/selesai kartu-kartunya.
                throw new FlowException('Kosongkan kolom ini dulu sebelum mengubah jenisnya.');
            }

            $column->update(['name' => $name, 'kind' => $kind]);
            $this->touch($project);

            return $column;
        });
    }

    /** @param  int[]  $orderedIds */
    public function reorderColumns(Project $project, User $by, array $orderedIds): void
    {
        DB::transaction(function () use ($project, $orderedIds) {
            $project = $this->lockProject($project->id);
            $ids = $project->columns()->pluck('id')->all();

            if (count($orderedIds) !== count($ids) || array_diff($ids, $orderedIds)) {
                throw new FlowException('Susunan kolom sudah berubah. Muat ulang halaman ini.');
            }
            foreach (array_values($orderedIds) as $i => $id) {
                Column::whereKey($id)->update(['position' => $i]);
            }
            $this->touch($project);
        });
    }

    public function deleteColumn(Column $column, User $by): void
    {
        DB::transaction(function () use ($column, $by) {
            $project = $this->lockProject($column->project_id);
            $column = $this->freshColumn($project, $column->id);

            if (Card::where('column_id', $column->id)->exists()) {
                throw new FlowException("Kolom {$column->name} masih berisi kartu (termasuk arsip). Pindahkan dulu.");
            }
            if ($column->isDone() && ! $project->columns()->where('kind', Column::DONE)->whereKeyNot($column->id)->exists()) {
                throw new FlowException('Papan butuh minimal satu kolom selesai.');
            }

            $column->delete();
            $this->log($project, $by, 'column_deleted', ['column' => $column->name]);
            $this->touch($project);
        });
    }

    // ---------------------------------------------------------------- internal

    private function lockProject(int $id): Project
    {
        return Project::lockForUpdate()->findOrFail($id);
    }

    private function freshColumn(Project $project, int $id): Column
    {
        return Column::where('project_id', $project->id)->findOrFail($id);
    }

    private function ensureRoom(Column $column): void
    {
        if ($column->wip_limit === null) {
            return;
        }
        $count = $column->cards()->count();
        if ($column->isFull($count)) {
            throw new WipLimitReached($column, $count);
        }
    }

    private function ensureMember(Project $project, ?int $userId): void
    {
        if ($userId !== null && ! ProjectMember::where('project_id', $project->id)->where('user_id', $userId)
            ->where('role', '!=', ProjectMember::VIEWER)->exists()) {
            throw new FlowException('Kartu hanya bisa ditugaskan ke anggota proyek yang bisa mengerjakan.');
        }
    }

    private function ensureEpic(Project $project, ?int $epicId): void
    {
        if ($epicId !== null && ! Epic::where('project_id', $project->id)->whereKey($epicId)->exists()) {
            throw new FlowException('Epic itu bukan milik proyek ini.');
        }
    }

    /** Label milik proyek lain dibuang diam-diam; id-nya datang dari form. */
    private function labelsOf(Project $project, array $labelIds): array
    {
        return $project->labels()->whereKey($labelIds)->pluck('id')->all();
    }

    /**
     * Sisipkan kartu ke urutan kolom tujuan lalu nomori ulang 0..n. Kolom papan kanban
     * jarang berisi lebih dari puluhan kartu, jadi menomori ulang lebih sederhana dan lebih
     * mudah dibuktikan benar daripada posisi pecahan. Hanya baris yang berubah yang ditulis.
     */
    private function placeCard(Card $card, Column $target, ?int $beforeId): void
    {
        $order = Card::where('column_id', $target->id)->onBoard()->whereKeyNot($card->id)
            ->ordered()->pluck('position', 'id');

        $ids = $order->keys()->all();
        $index = $beforeId !== null ? array_search($beforeId, $ids, true) : false;
        array_splice($ids, $index === false ? count($ids) : $index, 0, [$card->id]);

        foreach ($ids as $position => $id) {
            if ($id === $card->id) {
                $card->position = $position;
                $card->save();
            } elseif ($order[$id] !== $position) {
                Card::whereKey($id)->update(['position' => $position]);
            }
        }
    }

    private function onCard(Card $card, callable $change): Card
    {
        return DB::transaction(function () use ($card, $change) {
            $project = $this->lockProject($card->project_id);
            $card = Card::whereKey($card->id)->firstOrFail();
            $change($card);
            $this->touch($project);

            return $card;
        });
    }

    /** Mundur dari kolom yang sudah dikerjakan ke kolom kerja/tunggu yang lebih kiri. */
    public static function isRework(Column $from, Column $to): bool
    {
        return $to->position < $from->position
            && in_array($from->kind, [Column::ACTIVE, Column::WAIT, Column::DONE], true)
            && $to->isInProgress();
    }

    private function recordMove(Card $card, ?int $from, ?int $to, User $by, bool $rework = false, ?string $reason = null, ?string $note = null): void
    {
        CardMove::create([
            'project_id' => $card->project_id,
            'card_id' => $card->id,
            'from_column_id' => $from,
            'to_column_id' => $to,
            'user_id' => $by->id,
            'moved_at' => now(),
            'rework' => $rework,
            'reason' => $reason,
            'note' => $note,
        ]);
    }

    private function log(Card|Project $subject, User $by, string $type, array $data = []): void
    {
        Activity::create([
            'project_id' => $subject instanceof Card ? $subject->project_id : $subject->id,
            'card_id' => $subject instanceof Card ? $subject->id : null,
            'user_id' => $by->id,
            'type' => $type,
            'data' => $data ?: null,
            'created_at' => now(),
        ]);
    }

    private function touch(Project $project): void
    {
        $project->increment('version');
    }
}
