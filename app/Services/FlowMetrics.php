<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Card;
use App\Models\CardMove;
use App\Models\Column;
use App\Models\Epic;
use App\Models\Project;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Metrik alur kerja, semuanya dihitung dari data yang sudah dicatat Board:
 * started_at/completed_at di kartu, dan card_moves untuk riwayat per hari.
 *
 * Tidak ada yang disimpan; proyek seukuran tim kecil punya ratusan sampai beberapa ribu
 * perpindahan, cukup dihitung saat halaman dibuka.
 *
 * Persentil memakai metode nearest-rank: P85 adalah nilai terkecil yang >= 85% data.
 * Hasilnya selalu salah satu nilai asli, jadi bisa dibaca sebagai "85% kartu selesai
 * dalam X hari atau kurang", tanpa interpolasi.
 */
class FlowMetrics
{
    private Carbon $now;

    public function __construct(?Carbon $now = null)
    {
        $this->now = $now ?? now();
    }

    /**
     * Kartu yang selesai dalam $days hari terakhir dan punya waktu mulai.
     *
     * @return Collection<int, array{card: Card, days: float}>
     */
    public function cycleTimes(Project $project, int $days = 90): Collection
    {
        return Card::where('project_id', $project->id)
            ->whereNotNull('started_at')
            ->where('completed_at', '>=', $this->now->copy()->subDays($days))
            ->orderBy('completed_at')
            ->get()
            ->map(fn (Card $card) => ['card' => $card, 'days' => $card->cycleTimeInDays()]);
    }

    /** @return array{p50: ?float, p85: ?float, p95: ?float, count: int} */
    public function cycleTimeSummary(Project $project, int $days = 90): array
    {
        $values = $this->cycleTimes($project, $days)->pluck('days')->all();

        return [
            'p50' => self::percentile($values, 50),
            'p85' => self::percentile($values, 85),
            'p95' => self::percentile($values, 95),
            'count' => count($values),
        ];
    }

    /**
     * Jumlah kartu selesai per minggu (Senin-Minggu), $weeks minggu terakhir termasuk minggu ini.
     *
     * @return array<int, array{start: Carbon, count: int}>
     */
    public function weeklyThroughput(Project $project, int $weeks = 12): array
    {
        $start = $this->now->copy()->startOfWeek()->subWeeks($weeks - 1);
        $done = Card::where('project_id', $project->id)
            ->where('completed_at', '>=', $start)
            ->pluck('completed_at');

        $buckets = [];
        for ($i = 0; $i < $weeks; $i++) {
            $buckets[] = ['start' => $start->copy()->addWeeks($i), 'count' => 0];
        }
        foreach ($done as $completedAt) {
            $index = (int) floor($start->diffInDays($completedAt) / 7);
            if (isset($buckets[$index])) {
                $buckets[$index]['count']++;
            }
        }

        return $buckets;
    }

    /**
     * Cumulative flow diagram: isi tiap kolom di akhir setiap hari, $days hari terakhir.
     * Dibangun dengan memutar ulang card_moves dari awal proyek.
     *
     * Kartu yang diarsipkan dari kolom "done" tetap dihitung di kolom itu: mengarsipkan pekerjaan
     * yang sudah selesai adalah bersih-bersih, bukan membatalkan. Kalau dihapus, pita "Selesai"
     * akan turun dan diagramnya berhenti kumulatif. Kartu yang diarsipkan dari kolom lain
     * dianggap dibatalkan dan keluar dari diagram.
     *
     * @return array{columns: Collection<int, Column>, days: array<int, array{date: Carbon, counts: array<int, int>}>}
     */
    public function cumulativeFlow(Project $project, int $days = 42): array
    {
        $columns = $project->columns()->get();
        $doneIds = $columns->where('kind', Column::DONE)->pluck('id')->all();
        $end = $this->now->copy()->endOfDay();
        $first = $this->now->copy()->subDays($days - 1)->startOfDay();

        $moves = CardMove::where('project_id', $project->id)
            ->where('moved_at', '<=', $end)
            ->orderBy('moved_at')->orderBy('id')
            ->get(['card_id', 'to_column_id', 'moved_at']);

        $location = [];
        $cursor = 0;
        $total = $moves->count();
        $series = [];

        for ($day = $first->copy(); $day->lte($end); $day->addDay()) {
            $dayEnd = $day->copy()->endOfDay();
            while ($cursor < $total && $moves[$cursor]->moved_at->lte($dayEnd)) {
                $move = $moves[$cursor++];
                if ($move->to_column_id !== null) {
                    $location[$move->card_id] = $move->to_column_id;
                } elseif (! in_array($location[$move->card_id] ?? null, $doneIds, true)) {
                    unset($location[$move->card_id]);
                }
            }

            $counts = array_fill_keys($columns->pluck('id')->all(), 0);
            foreach ($location as $columnId) {
                if (isset($counts[$columnId])) {
                    $counts[$columnId]++;
                }
            }
            $series[] = ['date' => $day->copy(), 'counts' => $counts];
        }

        return ['columns' => $columns, 'days' => $series];
    }

    /**
     * Kartu yang sedang dikerjakan beserta umurnya, dibandingkan dengan cycle time historis.
     * "late" = sudah lebih tua dari P85: kartu ini sudah lebih lama dari 85% kartu yang pernah
     * selesai, dan peluangnya selesai tepat waktu mengecil setiap hari.
     *
     * @return Collection<int, array{card: Card, days: float, status: string}>
     */
    public function agingWork(Project $project): Collection
    {
        $summary = $this->cycleTimeSummary($project);

        return Card::where('project_id', $project->id)->onBoard()
            ->whereNotNull('started_at')->whereNull('completed_at')
            ->with(['column', 'assignee'])
            ->get()
            ->map(function (Card $card) use ($summary) {
                $days = $card->ageInDays($this->now);

                return ['card' => $card, 'days' => $days, 'status' => self::ageStatus($days, $summary)];
            })
            ->sortByDesc('days')
            ->values();
    }

    /** "fresh" di bawah P50, "watch" P50 sampai P85, "late" di atas P85, "unknown" kalau belum ada riwayat. */
    public static function ageStatus(?float $days, array $summary): string
    {
        if ($days === null || $summary['p50'] === null || $summary['count'] < 5) {
            return 'unknown';
        }

        return match (true) {
            $days > $summary['p85'] => 'late',
            $days > $summary['p50'] => 'watch',
            default => 'fresh',
        };
    }

    /**
     * Monte Carlo: berapa hari lagi sampai $remaining kartu selesai, kalau tim bekerja
     * seperti $sampleDays hari terakhir. Setiap simulasi mengambil throughput harian acak
     * dari riwayat itu sampai jumlahnya mencapai $remaining.
     *
     * Hasilnya rentang, bukan satu tanggal: "50% kemungkinan dalam X hari, 85% dalam Y hari".
     * Null kalau tim belum menyelesaikan apa pun di periode sampel (tidak ada dasar untuk menebak).
     *
     * distribution: berapa simulasi yang selesai di hari ke-n, untuk digambar sebagai histogram.
     *
     * @return array{p50: int, p85: int, p95: int, samples: int, distribution: array<int, int>}|null
     */
    public function forecast(Project $project, int $remaining, int $sampleDays = 42, int $runs = 5000): ?array
    {
        if ($remaining < 1) {
            return null;
        }

        $since = $this->now->copy()->subDays($sampleDays)->startOfDay();
        $perDay = array_fill(0, $sampleDays, 0);
        $completions = Card::where('project_id', $project->id)
            ->where('completed_at', '>=', $since)
            ->where('completed_at', '<', $this->now->copy()->startOfDay())
            ->pluck('completed_at');
        foreach ($completions as $completedAt) {
            $index = (int) floor($since->diffInDays($completedAt));
            if (isset($perDay[$index])) {
                $perDay[$index]++;
            }
        }
        if (array_sum($perDay) === 0) {
            return null;
        }

        // Benih tetap per proyek dan hari: angka tidak berubah-ubah setiap kali halaman dimuat ulang.
        $random = new Randomizer(new Mt19937(crc32($project->id.'|'.$this->now->toDateString())));
        $last = $sampleDays - 1;
        $results = [];
        for ($run = 0; $run < $runs; $run++) {
            $done = 0;
            $dayCount = 0;
            // Batas 3 tahun supaya tim yang sangat jarang menyelesaikan kartu tidak membuat loop panjang.
            while ($done < $remaining && $dayCount < 1095) {
                $done += $perDay[$random->getInt(0, $last)];
                $dayCount++;
            }
            $results[] = $dayCount;
        }

        $distribution = array_count_values($results);
        ksort($distribution);

        return [
            'p50' => (int) self::percentile($results, 50),
            'p85' => (int) self::percentile($results, 85),
            'p95' => (int) self::percentile($results, 95),
            'samples' => array_sum($perDay),
            'distribution' => $distribution,
        ];
    }

    /**
     * Progres tiap epic, dihitung dari kartunya. Kartu arsip dari kolom selesai tetap dihitung
     * selesai; kartu arsip yang belum selesai dianggap dibatalkan dan tidak dihitung.
     *
     * Perkiraan per epic memakai throughput seluruh tim, jadi artinya "kalau seluruh tim hanya
     * mengerjakan epic ini". Itu skenario paling optimis; kalau skenario itu pun melewati target,
     * targetnya hampir pasti meleset.
     *
     * @return Collection<int, array{epic: Epic, total: int, done: int, active: int, queue: int, blocked: int, forecast: ?array, target: ?string}>
     */
    public function epicProgress(Project $project): Collection
    {
        $epics = $project->epics()->get();
        $cards = Card::where('project_id', $project->id)
            ->whereNotNull('epic_id')
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhereNotNull('completed_at'))
            ->with('column:id,kind')
            ->get(['id', 'epic_id', 'column_id', 'completed_at', 'archived_at', 'blocked_at'])
            ->groupBy('epic_id');

        return $epics->map(function (Epic $epic) use ($project, $cards) {
            $mine = $cards->get($epic->id, collect());
            $done = $mine->whereNotNull('completed_at')->count();
            $open = $mine->whereNull('completed_at');
            $remaining = $open->count();
            $forecast = $remaining > 0 ? $this->forecast($project, $remaining) : null;

            return [
                'epic' => $epic,
                'total' => $mine->count(),
                'done' => $done,
                'active' => $open->filter(fn ($c) => $c->column->kind === Column::ACTIVE)->count(),
                'queue' => $open->filter(fn ($c) => $c->column->kind === Column::QUEUE)->count(),
                'blocked' => $open->whereNotNull('blocked_at')->count(),
                'forecast' => $forecast,
                // Epic tanpa kartu belum punya status apa pun, bukan "selesai".
                'target' => $mine->isEmpty() ? null : self::targetStatus($epic, $remaining, $forecast, $this->now),
            ];
        });
    }

    /**
     * "done" semua kartu selesai, "safe" P85 sebelum target, "risky" hanya P50 yang sebelum target,
     * "late" bahkan P50 melewati target, null kalau tidak ada target atau belum bisa diperkirakan.
     */
    public static function targetStatus(Epic $epic, int $remaining, ?array $forecast, Carbon $now): ?string
    {
        if ($remaining === 0) {
            return 'done';
        }
        if (! $epic->target_on || ! $forecast) {
            return null;
        }
        $daysLeft = $now->copy()->startOfDay()->diffInDays($epic->target_on, false);

        return match (true) {
            $forecast['p85'] <= $daysLeft => 'safe',
            $forecast['p50'] <= $daysLeft => 'risky',
            default => 'late',
        };
    }

    /**
     * Perjalanan satu kartu: setiap persinggahan di kolom, sejak dibuat sampai sekarang (atau selesai).
     *
     * @return Collection<int, array{column: ?Column, from: Carbon, to: Carbon, days: float, rework: bool, reason: ?string, note: ?string, by: ?string, ongoing: bool}>
     */
    public function journey(Card $card): Collection
    {
        $columns = Column::where('project_id', $card->project_id)->get()->keyBy('id');
        $moves = CardMove::where('card_id', $card->id)->orderBy('moved_at')->orderBy('id')->with('user:id,name')->get();
        $end = $card->archived_at ?? $this->now;

        return $moves->values()->map(function (CardMove $move, int $i) use ($moves, $columns, $end) {
            if ($move->to_column_id === null) {
                return null;
            }
            $next = $moves[$i + 1] ?? null;
            $to = $next?->moved_at ?? $end;

            return [
                'column' => $columns->get($move->to_column_id),
                'from' => $move->moved_at,
                'to' => $to,
                'days' => $move->moved_at->diffInSeconds($to) / 86400,
                'rework' => $move->rework,
                'reason' => $move->reason,
                'note' => $move->note,
                'by' => $move->user?->name,
                'ongoing' => $next === null && ! $columns->get($move->to_column_id)?->isDone(),
            ];
        })->filter()->values();
    }

    /**
     * Ke mana cycle time kartu-kartu yang selesai dalam $days hari terakhir habis.
     *
     * Hanya rentang mulai dikerjakan sampai selesai yang dihitung. Waktu kerja = waktu di kolom
     * "active" dikurangi masa terblokir; sisanya (kolom "wait", kembali ke antrean, terblokir) adalah
     * waktu menunggu. Efisiensi alur = waktu kerja / cycle time.
     *
     * Waktu revisi untuk satu pengembalian = sejak kartu dikembalikan sampai ia kembali mencapai kolom
     * tempat ia dikembalikan. Itulah hari yang hilang karena pekerjaan harus diulang.
     */
    public function timeBreakdown(Project $project, int $days = 90): array
    {
        $columns = $project->columns()->get()->keyBy('id');
        $cards = Card::where('project_id', $project->id)
            ->whereNotNull('started_at')
            ->where('completed_at', '>=', $this->now->copy()->subDays($days))
            ->get(['id', 'started_at', 'completed_at']);
        $ids = $cards->pluck('id');

        $moves = CardMove::whereIn('card_id', $ids)->orderBy('moved_at')->orderBy('id')->get()->groupBy('card_id');
        $blocks = Activity::whereIn('card_id', $ids)->whereIn('type', ['blocked', 'unblocked'])
            ->orderBy('created_at')->orderBy('id')->get(['card_id', 'type', 'created_at'])->groupBy('card_id');

        $perColumn = [];
        $perColumnCards = [];
        $work = $wait = $blocked = $total = 0.0;
        $reworkPerCard = [];
        $reasons = [];
        $sentBackFrom = [];

        foreach ($cards as $card) {
            $start = $card->started_at;
            $end = $card->completed_at;
            $cardMoves = ($moves[$card->id] ?? collect())->values();
            $total += $start->diffInSeconds($end);

            // Persinggahan di tiap kolom, dipotong ke rentang [mulai, selesai].
            foreach ($cardMoves as $i => $move) {
                if ($move->to_column_id === null) {
                    continue;
                }
                $from = $move->moved_at->max($start);
                $to = ($cardMoves[$i + 1]->moved_at ?? $end)->min($end);
                if ($to <= $from) {
                    continue;
                }
                $secs = $from->diffInSeconds($to);
                $kind = $columns->get($move->to_column_id)?->kind ?? Column::QUEUE;
                $perColumn[$move->to_column_id] = ($perColumn[$move->to_column_id] ?? 0) + $secs;
                $perColumnCards[$move->to_column_id][$card->id] = ($perColumnCards[$move->to_column_id][$card->id] ?? 0) + $secs;
                if ($kind === Column::ACTIVE) {
                    $work += $secs;
                } else {
                    $wait += $secs;
                }
            }

            // Masa terblokir dipindah dari "kerja" ke "menunggu".
            $since = null;
            foreach ($blocks[$card->id] ?? [] as $event) {
                if ($event->type === 'blocked') {
                    $since ??= $event->created_at;
                } elseif ($since) {
                    $blocked += max(0, $since->max($start)->diffInSeconds($event->created_at->min($end), false));
                    $since = null;
                }
            }

            // Putaran revisi.
            foreach ($cardMoves as $i => $move) {
                if (! $move->rework) {
                    continue;
                }
                $origin = $columns->get($move->from_column_id);
                $back = $cardMoves->slice($i + 1)->first(fn ($m) => $m->to_column_id && $origin
                    && ($columns->get($m->to_column_id)?->position ?? -1) >= $origin->position);
                $reworkPerCard[$card->id] = ($reworkPerCard[$card->id] ?? 0)
                    + $move->moved_at->diffInSeconds(($back?->moved_at ?? $end)->min($end));
                $key = $move->reason ?? 'none';
                $reasons[$key] = ($reasons[$key] ?? 0) + 1;
                $label = $origin?->name ?? 'kolom yang sudah dihapus';
                $sentBackFrom[$label] = ($sentBackFrom[$label] ?? 0) + 1;
            }
        }

        $blocked = min($blocked, $work);
        $work -= $blocked;
        $wait += $blocked;
        arsort($reasons);
        arsort($sentBackFrom);

        $rows = $columns->filter(fn ($c) => isset($perColumn[$c->id]))->map(fn (Column $column) => [
            'column' => $column,
            'days' => $perColumn[$column->id] / 86400,
            'share' => $total > 0 ? $perColumn[$column->id] / $total : 0,
            'median' => self::percentile(array_map(fn ($s) => $s / 86400, array_values($perColumnCards[$column->id])), 50),
            'cards' => count($perColumnCards[$column->id]),
        ])->sortByDesc('share')->values();

        return [
            'cards' => $cards->count(),
            'columns' => $rows,
            'work_share' => $total > 0 ? $work / $total : null,
            'wait_share' => $total > 0 ? $wait / $total : null,
            'blocked_share' => $total > 0 ? $blocked / $total : null,
            'rework' => [
                'cards' => count($reworkPerCard),
                'share' => $cards->count() ? count($reworkPerCard) / $cards->count() : 0,
                'median_days' => self::percentile(array_map(fn ($s) => $s / 86400, array_values($reworkPerCard)), 50),
                'time_share' => $total > 0 ? array_sum($reworkPerCard) / $total : 0,
                'reasons' => $reasons,
                'from' => $sentBackFrom,
            ],
        ];
    }

    /** Nearest-rank. Null untuk data kosong. */
    public static function percentile(array $values, int $p): ?float
    {
        if (! $values) {
            return null;
        }
        sort($values);
        $rank = (int) ceil($p / 100 * count($values));

        return (float) $values[max(0, $rank - 1)];
    }
}
