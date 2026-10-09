<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Project;
use App\Services\FlowMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FlowMetricsTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $monday;

    protected function setUp(): void
    {
        parent::setUp();
        // Senin, supaya batas minggu mudah dihitung.
        $this->monday = Carbon::parse('2026-09-07 09:00');
        $this->travelTo($this->monday);
    }

    public function test_persentil_nearest_rank(): void
    {
        $values = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];

        $this->assertSame(5.0, FlowMetrics::percentile($values, 50));
        $this->assertSame(9.0, FlowMetrics::percentile($values, 85));
        $this->assertSame(10.0, FlowMetrics::percentile($values, 95));
        $this->assertSame(7.0, FlowMetrics::percentile([7], 85));
        $this->assertNull(FlowMetrics::percentile([], 50));
    }

    public function test_cycle_time_dari_mulai_dikerjakan_sampai_selesai(): void
    {
        $project = $this->project();
        // Lima kartu dengan cycle time 1, 2, 3, 4, 10 hari.
        foreach ([1, 2, 3, 4, 10] as $days) {
            $this->finishedCard($project, startOffset: 0, days: $days);
        }
        // Kartu yang lompat langsung ke selesai tidak ikut cycle time.
        $this->travelTo($this->monday->copy()->addDays(12));
        $skip = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Typo']);
        $this->board()->move($skip, $this->column($project, 'Selesai'), $project->owner);

        $summary = (new FlowMetrics(now()))->cycleTimeSummary($project);

        $this->assertSame(5, $summary['count']);
        $this->assertSame(3.0, $summary['p50']);
        $this->assertSame(10.0, $summary['p85']);
    }

    public function test_throughput_per_minggu(): void
    {
        $project = $this->project();
        // Dua selesai di minggu pertama, satu di minggu ketiga.
        $this->finishedCard($project, startOffset: 0, days: 1);
        $this->finishedCard($project, startOffset: 2, days: 2);
        $this->finishedCard($project, startOffset: 14, days: 1);

        $this->travelTo($this->monday->copy()->addDays(20));
        $weeks = (new FlowMetrics(now()))->weeklyThroughput($project, 3);

        $this->assertSame(['2026-09-07', '2026-09-14', '2026-09-21'], array_map(fn ($w) => $w['start']->toDateString(), $weeks));
        $this->assertSame([2, 0, 1], array_column($weeks, 'count'));
    }

    public function test_cumulative_flow_memutar_ulang_perpindahan_per_hari(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $ids = $project->columns()->pluck('id', 'name');

        // Hari 1: dua kartu masuk Backlog.
        $a = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'A']);
        $b = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'B']);
        // Hari 2: A dikerjakan.
        $this->travelTo($this->monday->copy()->addDay());
        $this->board()->move($a, $this->column($project, 'Dikerjakan'), $by);
        // Hari 3: A selesai lalu diarsipkan, B dibatalkan (diarsipkan dari Backlog).
        $this->travelTo($this->monday->copy()->addDays(2));
        $this->board()->move($a, $this->column($project, 'Selesai'), $by);
        $this->board()->archive($a, $by);
        $this->board()->archive($b, $by);

        $cfd = (new FlowMetrics(now()))->cumulativeFlow($project, 3);
        $at = fn ($day, $name) => $cfd['days'][$day]['counts'][$ids[$name]];

        $this->assertSame([2, 0, 0], [$at(0, 'Backlog'), $at(0, 'Dikerjakan'), $at(0, 'Selesai')]);
        $this->assertSame([1, 1, 0], [$at(1, 'Backlog'), $at(1, 'Dikerjakan'), $at(1, 'Selesai')]);
        // A tetap terhitung selesai walau diarsipkan; B keluar dari diagram.
        $this->assertSame([0, 0, 1], [$at(2, 'Backlog'), $at(2, 'Dikerjakan'), $at(2, 'Selesai')]);
    }

    public function test_umur_kartu_dibandingkan_dengan_riwayat(): void
    {
        $project = $this->project();
        foreach ([1, 2, 2, 3, 4, 5] as $days) {
            $this->finishedCard($project, startOffset: 0, days: $days);
        }
        // p50 = 2, p85 = 5.
        $this->travelTo($this->monday->copy()->addDays(10));
        $old = $this->board()->createCard($this->column($project, 'Dikerjakan'), $project->owner, ['title' => 'Lama']);
        $this->travelTo($this->monday->copy()->addDays(13));
        $mid = $this->board()->createCard($this->column($project, 'Review'), $project->owner, ['title' => 'Sedang']);
        $this->travelTo($this->monday->copy()->addDays(16));
        $new = $this->board()->createCard($this->column($project, 'Dikerjakan'), $project->owner, ['title' => 'Baru']);

        $aging = (new FlowMetrics(now()))->agingWork($project)->keyBy(fn ($row) => $row['card']->id);

        $this->assertSame('late', $aging[$old->id]['status']);   // 6 hari
        $this->assertSame('watch', $aging[$mid->id]['status']);  // 3 hari
        $this->assertSame('fresh', $aging[$new->id]['status']);  // 0 hari
    }

    public function test_umur_tidak_dinilai_tanpa_cukup_riwayat(): void
    {
        $this->assertSame('unknown', FlowMetrics::ageStatus(10, ['p50' => 1.0, 'p85' => 2.0, 'count' => 3]));
    }

    public function test_perkiraan_monte_carlo(): void
    {
        $project = $this->project();
        // Tepat satu kartu selesai setiap hari selama 42 hari: tidak ada variasi,
        // jadi 10 kartu pasti butuh 10 hari di semua persentil.
        for ($day = 0; $day < 42; $day++) {
            $this->travelTo($this->monday->copy()->addDays($day)->setTime(10, 0));
            $card = $this->board()->createCard($this->column($project, 'Dikerjakan'), $project->owner, ['title' => "H{$day}"]);
            $this->board()->move($card, $this->column($project, 'Selesai'), $project->owner);
        }
        $this->travelTo($this->monday->copy()->addDays(42));
        $metrics = new FlowMetrics(now());

        $forecast = $metrics->forecast($project, 10);
        $this->assertSame(['p50' => 10, 'p85' => 10, 'p95' => 10, 'samples' => 42], array_diff_key($forecast, ['distribution' => 0]));
        $this->assertSame([10 => 5000], $forecast['distribution'], 'semua simulasi selesai di hari ke-10');
        $this->assertNull($metrics->forecast($project, 0));
        $this->assertNull($metrics->forecast($this->project(), 5), 'tanpa riwayat tidak ada perkiraan');
    }

    public function test_perkiraan_stabil_untuk_hari_yang_sama(): void
    {
        $project = $this->project();
        foreach ([0, 0, 3, 5, 9] as $offset) {
            $this->finishedCard($project, startOffset: $offset, days: 1);
        }
        $this->travelTo($this->monday->copy()->addDays(20));

        $first = (new FlowMetrics(now()))->forecast($project, 8);
        $second = (new FlowMetrics(now()))->forecast($project, 8);

        $this->assertSame($first, $second);
        $this->assertTrue($first['p50'] <= $first['p85'] && $first['p85'] <= $first['p95']);
    }

    /** Kartu yang mulai dikerjakan $startOffset hari setelah Senin dan selesai $days hari kemudian. */
    private function finishedCard(Project $project, int $startOffset, int $days, ?int $reviewDays = null): Card
    {
        $by = $project->owner;
        $start = $this->monday->copy()->addDays($startOffset);

        $this->travelTo($start);
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'Kartu']);
        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by);

        if ($reviewDays !== null) {
            $this->travelTo($start->copy()->addDays($days - $reviewDays));
            $this->board()->move($card, $this->column($project, 'Review'), $by);
        }

        $this->travelTo($start->copy()->addDays($days));
        $this->board()->move($card, $this->column($project, 'Selesai'), $by);

        return $card->fresh();
    }
}
