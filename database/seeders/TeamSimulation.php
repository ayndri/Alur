<?php

namespace Database\Seeders;

use App\Exceptions\WipLimitReached;
use App\Models\Card;
use App\Models\Column;
use App\Models\Project;
use App\Models\User;
use App\Services\Board;
use Illuminate\Support\Carbon;
use Random\Randomizer;

/**
 * Memutar kerja sebuah tim hari demi hari lewat App\Services\Board, dengan jam yang diputar mundur.
 * Hasilnya riwayat yang tunduk pada aturan yang sama dengan pemakaian asli: batas WIP menahan
 * kartu, review yang lambat membuat kartu menumpuk, kartu terblokir menua. Metrik di halaman
 * Alur Kerja jadi punya sesuatu yang nyata untuk dihitung.
 *
 * Model kerjanya sengaja sederhana:
 *  - Setiap orang punya satu hari kerja per hari. Kalau ia memegang dua kartu, masing-masing
 *    hanya maju setengah hari. (Itulah kenapa batas WIP mempercepat penyelesaian.)
 *  - Kartu yang selesai dikerjakan menunggu tempat di Review. Kalau Review penuh, kartunya
 *    tetap di Dikerjakan dan ikut memakan slot WIP. Begitu juga Review ke QA.
 *  - QA dikerjakan satu penguji, paling banyak dua kartu per hari. Sebagian kartu dikembalikan
 *    ke Dikerjakan karena bug; sebagian kecil dikembalikan dari Review untuk diperbaiki.
 *  - Merge hanya dilakukan Selasa dan Kamis sore (jadwal rilis). Kartu yang lolos QA di hari lain
 *    menunggu di "Menunggu merge": itu waktu tunggu murni, tanpa kerja.
 *  - Sesekali kartu terblokir.
 */
class TeamSimulation
{
    private const REVISIONS = [
        'Revisi: pesan error untuk OTP salah belum ada, pasien cuma lihat layar kosong.',
        'Revisi: di layar 5 inci tombolnya tertutup keyboard.',
        'Revisi: belum ada tes untuk kasus jadwal dokter kosong.',
        'Revisi: query-nya masih N+1, coba eager load relasi dokter.',
        'Revisi: teksnya masih campur Inggris, samakan ke Bahasa Indonesia.',
        'Revisi: perlu konfirmasi sebelum aksi ini, terlalu mudah tertekan tidak sengaja.',
    ];

    private const NOTES = [
        'Sudah jalan di staging, tinggal rapikan pesan error.',
        'Ternyata perlu ubah skema sedikit, migrasinya sudah aku siapkan.',
        'Aku pecah jadi dua PR supaya review-nya tidak berat.',
        'Sudah dicoba di HP Android lama, aman.',
        'Butuh 15 menit diskusi soal kasus pasien yang datang tanpa antrean.',
        'Setengah jalan. Bagian API sudah, tampilan belum.',
    ];

    private const BUGS = [
        'Tombol batal tidak merespons di Android 10.',
        'Nomor antrean tidak diperbarui setelah aplikasi dibuka dari latar belakang.',
        'Pesan error muncul dalam bahasa Inggris.',
        'Crash saat jadwal dokter kosong.',
        'Teks terpotong di layar 5 inci.',
        'Tanggal tampil dalam zona waktu UTC.',
    ];

    private const BLOCKERS = [
        'Menunggu kredensial SMS gateway dari vendor',
        'Menunggu data jadwal dokter dari bagian administrasi klinik',
        'Butuh keputusan klien soal alur pembatalan',
        'Server staging mati, menunggu tim infra',
        'Menunggu akses akun Play Console',
    ];

    private Column $backlog;
    private Column $ready;
    private Column $doing;
    private Column $review;
    private Column $qa;
    private Column $merge;
    private Column $done;
    private User $tester;

    /** @var array<int, array{effort: float, assignee: ?int, reviewIn: int, qaIn: int, blockedUntil: ?Carbon}> */
    private array $state = [];

    private int $next = 0;
    private Carbon $clock;
    private Carbon $until;

    /**
     * @param  User[]  $workers  orang yang menarik kartu ke Dikerjakan
     * @param  array<int, array{0: string, 1: string, 2: int, 3: int, 4: ?string}>  $pool
     * @param  array<string, int>  $labels  nama label => id
     */
    public function __construct(
        private Board $board,
        private Project $project,
        private User $lead,
        private array $workers,
        private array $pool,
        private array $labels,
        private Randomizer $random,
        ?User $tester = null,
    ) {
        $columns = $project->columns()->get()->keyBy('name');
        [$this->backlog, $this->ready, $this->doing, $this->review, $this->qa, $this->merge, $this->done] = [
            $columns['Backlog'], $columns['Siap dikerjakan'], $columns['Dikerjakan'], $columns['Review'],
            $columns['QA'], $columns['Menunggu merge'], $columns['Selesai'],
        ];
        $this->tester = $tester ?? $lead;
    }

    public function run(Carbon $from, Carbon $until, int $initialBacklog, float $arrivalsPerDay): void
    {
        $this->until = $until;

        for ($day = $from->copy()->startOfDay(); $day->lte($until); $day->addDay()) {
            if ($day->isWeekend()) {
                continue;
            }
            $this->clock = $day->copy()->setTime(8, $this->random->getInt(20, 50));
            if (! $this->simulateDay($day, $day->eq($from->copy()->startOfDay()) ? $initialBacklog : $this->arrivals($arrivalsPerDay))) {
                break;
            }
        }

        Carbon::setTestNow();
    }

    /** False kalau jam simulasi sudah melewati $until. */
    private function simulateDay(Carbon $day, int $arrivals): bool
    {
        $this->progressWork($day);

        for ($i = 0; $i < $arrivals && $this->next < count($this->pool); $i++) {
            if (! $this->tick()) {
                return false;
            }
            $this->createFromPool();
        }

        // Ketua tim mengisi "Siap dikerjakan" dari atas backlog.
        while ($this->count($this->ready) < $this->ready->wip_limit && ($card = $this->top($this->backlog))) {
            if (! $this->tick()) {
                return false;
            }
            $this->board->move($card, $this->ready, $this->lead);
        }

        // Yang sudah selesai dikerjakan masuk Review kalau ada tempat.
        foreach ($this->cardsIn($this->doing) as $card) {
            if ($this->state[$card->id]['effort'] <= 0 && ! $card->isBlocked()) {
                if (! $this->tick()) {
                    return false;
                }
                if ($this->tryMove($card, $this->review, $this->userById($this->state[$card->id]['assignee']))) {
                    $this->state[$card->id]['reviewIn'] = $this->random->getInt(0, 2);
                }
            }
        }

        // Merge hanya Selasa dan Kamis: semua yang menunggu ikut rilis sore itu.
        if ($day->isTuesday() || $day->isThursday()) {
            foreach ($this->cardsIn($this->merge) as $card) {
                if (! $this->tick()) {
                    return false;
                }
                $this->board->move($card, $this->done, $this->lead);
            }
        }

        // QA: satu penguji, paling banyak dua kartu tuntas per hari. Sebagian dikembalikan karena bug.
        $tested = 0;
        foreach ($this->cardsIn($this->qa) as $card) {
            if ($tested >= 2) {
                break;
            }
            if ($this->state[$card->id]['qaIn']-- > 0) {
                continue;
            }
            if (! $this->tick()) {
                return false;
            }
            $tested++;
            if ($this->chance(0.28)) {
                $bug = $this->pick(self::BUGS);
                if ($this->tryMove($card, $this->doing, $this->tester, 'bug', $bug)) {
                    $this->state[$card->id]['effort'] = $this->random->getInt(5, 15) / 10;
                    continue;
                }
            }
            $this->board->move($card, $this->merge, $this->tester);
        }

        // Review: sebagian dikembalikan untuk diperbaiki, sisanya ke QA kalau ada tempat.
        foreach ($this->cardsIn($this->review) as $card) {
            if ($this->state[$card->id]['reviewIn']-- > 0) {
                continue;
            }
            if (! $this->tick()) {
                return false;
            }
            $reviewer = $this->reviewerFor($card);
            if ($this->chance(0.15)) {
                $note = $this->pick(self::REVISIONS);
                if ($this->tryMove($card, $this->doing, $reviewer, 'review', preg_replace('/^Revisi: /', '', $note))) {
                    $this->state[$card->id]['effort'] = $this->random->getInt(5, 12) / 10;
                    continue;
                }
            }
            if ($this->tryMove($card, $this->qa, $reviewer)) {
                $this->state[$card->id]['qaIn'] = $this->random->getInt(0, 2);
            }
        }

        // Setiap orang menarik kartu baru kalau pegangannya kurang dari dua dan kolomnya masih muat.
        foreach ($this->random->shuffleArray($this->workers) as $worker) {
            $holding = collect($this->cardsIn($this->doing))->filter(fn ($c) => $c->assignee_id === $worker->id)->count();
            if ($holding >= 2 || ($holding === 1 && $this->chance(0.6))) {
                continue;
            }
            $card = $this->top($this->ready);
            if (! $card || ! $this->tick()) {
                break;
            }
            if ($this->tryMove($card, $this->doing, $worker)) {
                $card = $this->board->updateCard($card->fresh(), $worker, $card->fresh()->lock_version, ['assignee_id' => $worker->id]);
                $this->state[$card->id]['assignee'] = $worker->id;
            }
        }

        // Kejadian kecil: kartu terblokir, catatan progres.
        foreach ($this->cardsIn($this->doing) as $card) {
            if (! $card->isBlocked() && $this->state[$card->id]['effort'] > 0 && $this->chance(0.05)) {
                if (! $this->tick()) {
                    return false;
                }
                $this->board->block($card, $this->userById($card->assignee_id) ?? $this->lead, $this->pick(self::BLOCKERS));
                $this->state[$card->id]['blockedUntil'] = $day->copy()->addWeekdays($this->random->getInt(1, 4));
            } elseif ($card->assignee_id && $this->chance(0.08)) {
                if (! $this->tick()) {
                    return false;
                }
                $this->board->comment($card, $this->userById($card->assignee_id), $this->pick(self::NOTES));
            }
        }

        // Jumat sore: arsipkan kartu yang sudah selesai lebih dari dua minggu.
        if ($day->isFriday()) {
            foreach ($this->cardsIn($this->done) as $card) {
                if ($card->completed_at->lt($day->copy()->subDays(14))) {
                    if (! $this->tick()) {
                        return false;
                    }
                    $this->board->archive($card, $this->lead);
                }
            }
        }

        return true;
    }

    private function progressWork(Carbon $day): void
    {
        $active = collect($this->cardsIn($this->doing));

        foreach ($active as $card) {
            $state = &$this->state[$card->id];
            if ($card->isBlocked()) {
                if ($state['blockedUntil'] && $state['blockedUntil']->lte($day) && $day->copy()->setTime(8, 5)->lte($this->until)) {
                    $this->clock = $day->copy()->setTime(8, 5);
                    Carbon::setTestNow($this->clock);
                    $this->board->unblock($card, $this->userById($card->assignee_id) ?? $this->lead);
                    $state['blockedUntil'] = null;
                }

                continue;
            }
            // Satu hari kerja per orang, dibagi rata ke kartu yang sedang ia pegang.
            $share = $active->filter(fn ($c) => $c->assignee_id === $card->assignee_id && ! $c->isBlocked())->count();
            $state['effort'] -= 1 / max(1, $share);
        }
    }

    private function createFromPool(): void
    {
        [$title, $label, $effort, $priority, $description] = $this->pool[$this->next++];

        $card = $this->board->createCard($this->backlog, $this->lead, [
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
        ], [$this->labels[$label]]);

        $this->state[$card->id] = [
            // Perkiraan jarang tepat: kerja sebenarnya 0,8 sampai 1,8 kali perkiraan.
            'effort' => $effort * $this->random->getInt(8, 18) / 10,
            'assignee' => null,
            'reviewIn' => 0,
            'qaIn' => 0,
            'blockedUntil' => null,
        ];
    }

    /** Maju 8 sampai 55 menit. False kalau sudah melewati batas simulasi. */
    private function tick(): bool
    {
        $this->clock->addMinutes($this->random->getInt(8, 55));
        if ($this->clock->gt($this->until)) {
            return false;
        }
        Carbon::setTestNow($this->clock);

        return true;
    }

    private function tryMove(Card $card, Column $to, ?User $by, ?string $reason = null, ?string $note = null): bool
    {
        try {
            $this->board->move($card, $to, $by ?? $this->lead, null, $reason, $note);

            return true;
        } catch (WipLimitReached) {
            return false;
        }
    }

    private function arrivals(float $perDay): int
    {
        $whole = (int) floor($perDay);

        return $whole + ($this->chance($perDay - $whole) ? 1 : 0);
    }

    /** @return Card[] */
    private function cardsIn(Column $column): array
    {
        return Card::where('column_id', $column->id)->onBoard()->ordered()->get()->all();
    }

    private function top(Column $column): ?Card
    {
        return Card::where('column_id', $column->id)->onBoard()->ordered()->first();
    }

    private function count(Column $column): int
    {
        return Card::where('column_id', $column->id)->onBoard()->count();
    }

    private function reviewerFor(Card $card): User
    {
        // Penguji QA tidak ikut mereview kode.
        $others = array_values(array_filter([$this->lead, ...$this->workers], fn ($u) => $u->id !== $card->assignee_id));

        return $this->pick($others);
    }

    private function userById(?int $id): ?User
    {
        foreach ([$this->lead, $this->tester, ...$this->workers] as $user) {
            if ($user->id === $id) {
                return $user;
            }
        }

        return null;
    }

    private function chance(float $p): bool
    {
        return $this->random->getInt(0, 9999) / 10000 < $p;
    }

    private function pick(array $items): mixed
    {
        return $items[$this->random->getInt(0, count($items) - 1)];
    }
}
