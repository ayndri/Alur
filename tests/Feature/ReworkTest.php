<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Card;
use App\Models\CardMove;
use App\Models\Project;
use App\Services\FlowMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReworkTest extends TestCase
{
    use RefreshDatabase;

    public function test_mundur_dari_kolom_kerja_ke_kolom_kerja_dicatat_sebagai_revisi_beserta_alasannya(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $card = $this->board()->createCard($this->column($project, 'QA'), $by, ['title' => 'A']);

        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by, null, 'bug', 'Crash di Android 10');

        $move = CardMove::where('card_id', $card->id)->latest('id')->first();
        $this->assertTrue($move->rework);
        $this->assertSame('bug', $move->reason);
        $this->assertSame('Crash di Android 10', $move->note);
        $this->assertTrue(Activity::where('card_id', $card->id)->where('type', 'moved')->first()->data['rework']);
    }

    public function test_yang_bukan_revisi(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $card = $this->board()->createCard($this->column($project, 'Siap dikerjakan'), $by, ['title' => 'A']);

        $this->board()->move($card, $this->column($project, 'Backlog'), $by, null, 'bug');       // antrean ke antrean
        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by, null, 'bug');    // maju
        $this->board()->move($card, $this->column($project, 'Siap dikerjakan'), $by, null, 'bug'); // ditunda, bukan diulang

        $this->assertSame(0, CardMove::where('card_id', $card->id)->where('rework', true)->count());
        $this->assertSame(0, CardMove::where('card_id', $card->id)->whereNotNull('reason')->count(), 'alasan dibuang kalau bukan revisi');
    }

    public function test_membuka_ulang_kartu_selesai_adalah_revisi(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Selesai'), $project->owner, ['title' => 'A']);

        $this->board()->move($card, $this->column($project, 'Review'), $project->owner, null, 'spec');

        $this->assertTrue(CardMove::where('card_id', $card->id)->latest('id')->first()->rework);
    }

    public function test_kolom_tunggu_menandai_kartu_sudah_dimulai(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Menunggu merge'), $project->owner, ['title' => 'A']);

        $this->assertNotNull($card->started_at);
    }

    public function test_pembagian_waktu_revisi_dan_tunggu(): void
    {
        $this->travelTo(Carbon::parse('2026-09-07 09:00'));
        $project = $this->project();
        $this->walk($project, [
            [0, 'Dikerjakan'],
            [2, 'Review'],
            [3, 'QA'],
            [4, 'Dikerjakan', 'bug'], // kembali dari QA
            [5, 'Review'],
            [6, 'QA'],                 // kembali mencapai QA: putaran revisi 2 hari
            [7, 'Menunggu merge'],
            [9, 'Selesai'],
        ]);

        $b = (new FlowMetrics(now()))->timeBreakdown($project);
        $share = $b['columns']->mapWithKeys(fn ($r) => [$r['column']->name => round($r['share'], 3)])->all();

        $this->assertSame(1, $b['cards']);
        $this->assertSame(['Dikerjakan' => 0.333, 'Review' => 0.222, 'QA' => 0.222, 'Menunggu merge' => 0.222], $share);
        $this->assertEqualsWithDelta(7 / 9, $b['work_share'], 0.001);
        $this->assertEqualsWithDelta(2 / 9, $b['wait_share'], 0.001);
        $this->assertSame(1, $b['rework']['cards']);
        $this->assertSame(2.0, $b['rework']['median_days']);
        $this->assertSame(['bug' => 1], $b['rework']['reasons']);
        $this->assertSame(['QA' => 1], $b['rework']['from']);
    }

    public function test_masa_terblokir_dipindah_dari_kerja_ke_tunggu(): void
    {
        $this->travelTo(Carbon::parse('2026-09-07 09:00'));
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Dikerjakan'), $project->owner, ['title' => 'A']);
        $this->travel(1)->days();
        $this->board()->block($card, $project->owner, 'Menunggu API');
        $this->travel(1)->days();
        $this->board()->unblock($card, $project->owner);
        $this->travel(2)->days();
        $this->board()->move($card, $this->column($project, 'Selesai'), $project->owner);

        $b = (new FlowMetrics(now()))->timeBreakdown($project);

        $this->assertEqualsWithDelta(0.25, $b['blocked_share'], 0.001);
        $this->assertEqualsWithDelta(0.75, $b['work_share'], 0.001);
    }

    public function test_perjalanan_kartu(): void
    {
        $this->travelTo(Carbon::parse('2026-09-07 09:00'));
        $project = $this->project();
        $card = $this->walk($project, [[0, 'Dikerjakan'], [2, 'QA'], [3, 'Dikerjakan', 'bug']]);

        $journey = (new FlowMetrics(now()))->journey($card->fresh());

        $this->assertSame(['Backlog', 'Dikerjakan', 'QA', 'Dikerjakan'], $journey->map(fn ($s) => $s['column']->name)->all());
        $this->assertSame([false, false, false, true], $journey->pluck('rework')->all());
        $this->assertTrue($journey->last()['ongoing']);
    }

    public function test_alasan_revisi_lewat_http(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'QA'), $project->owner, ['title' => 'A']);
        $this->actingAs($project->owner);

        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $this->column($project, 'Dikerjakan')->id, 'reason' => 'ngawur'])
            ->assertStatus(422)->assertJsonValidationErrors('reason');

        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $this->column($project, 'Dikerjakan')->id, 'reason' => 'review', 'note' => 'Nama variabel'])
            ->assertOk();
        $this->assertSame('review', CardMove::where('card_id', $card->id)->latest('id')->value('reason'));

        $this->get(route('cards.show', [$project, $card]))->assertOk()->assertSee('Perjalanan kartu')->assertSee('Dikembalikan 1×');
        $this->get(route('flow.show', $project))->assertOk()->assertSee('Ke mana waktu kartu habis');
    }

    /** Kartu dibuat di Backlog lalu dipindah sesuai [hari ke-, kolom, alasan revisi?]. */
    private function walk(Project $project, array $steps): Card
    {
        $start = now()->copy();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Kartu']);
        foreach ($steps as $step) {
            $this->travelTo($start->copy()->addDays($step[0])->addMinute());
            $this->board()->move($card, $this->column($project, $step[1]), $project->owner, null, $step[2] ?? null);
        }

        return $card;
    }
}
