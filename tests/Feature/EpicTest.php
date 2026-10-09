<?php

namespace Tests\Feature;

use App\Exceptions\FlowException;
use App\Models\Epic;
use App\Models\ProjectMember;
use App\Services\FlowMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EpicTest extends TestCase
{
    use RefreshDatabase;

    public function test_progres_dihitung_dari_kartu_dan_arsip_selesai_tetap_terhitung(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $epic = $this->board()->createEpic($project, $by, ['name' => 'Layar TV', 'color' => 'violet']);
        $in = fn ($column, $title) => $this->board()->createCard($this->column($project, $column), $by, ['title' => $title, 'epic_id' => $epic->id]);

        $in('Backlog', 'Antre');
        $in('Dikerjakan', 'Jalan');
        $done = $in('Dikerjakan', 'Beres');
        $this->board()->move($done, $this->column($project, 'Selesai'), $by);
        $this->board()->archive($done, $by);           // tetap selesai
        $this->board()->archive($in('Backlog', 'Batal'), $by); // dibatalkan, tidak dihitung
        $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'Bukan epic ini']);

        $row = (new FlowMetrics)->epicProgress($project)->first();

        $this->assertSame(['total' => 3, 'done' => 1, 'active' => 1, 'queue' => 1], array_intersect_key($row, array_flip(['total', 'done', 'active', 'queue'])));
    }

    public function test_epic_proyek_lain_ditolak(): void
    {
        $project = $this->project();
        $other = $this->project();
        $foreign = $this->board()->createEpic($other, $other->owner, ['name' => 'Asing', 'color' => 'red']);

        $this->expectException(FlowException::class);
        $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A', 'epic_id' => $foreign->id]);
    }

    public function test_menghapus_epic_melepas_kartunya(): void
    {
        $project = $this->project();
        $epic = $this->board()->createEpic($project, $project->owner, ['name' => 'Sementara', 'color' => 'blue']);
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A', 'epic_id' => $epic->id]);

        $this->actingAs($project->owner)->delete(route('epics.destroy', [$project, $epic]))->assertRedirect(route('epics.index', $project));

        $this->assertNull($card->fresh()->epic_id);
        $this->assertSame(0, Epic::count());
    }

    public function test_status_target(): void
    {
        $now = Carbon::parse('2026-10-01');
        $epic = new Epic(['target_on' => '2026-10-11']); // 10 hari lagi
        $f = fn ($p50, $p85) => ['p50' => $p50, 'p85' => $p85, 'p95' => $p85 + 3, 'samples' => 10];

        $this->assertSame('done', FlowMetrics::targetStatus($epic, 0, null, $now));
        $this->assertSame('safe', FlowMetrics::targetStatus($epic, 5, $f(6, 10), $now));
        $this->assertSame('risky', FlowMetrics::targetStatus($epic, 5, $f(8, 14), $now));
        $this->assertSame('late', FlowMetrics::targetStatus($epic, 5, $f(12, 20), $now));
        $this->assertNull(FlowMetrics::targetStatus(new Epic, 5, $f(6, 10), $now), 'tanpa target');
    }

    public function test_hak_akses_dan_halaman_epic(): void
    {
        $project = $this->project();
        $viewer = $this->join($project, $this->user(), ProjectMember::VIEWER);
        $member = $this->join($project, $this->user());

        $this->actingAs($viewer)->post(route('epics.store', $project), ['name' => 'X', 'color' => 'blue'])->assertForbidden();

        $this->actingAs($member)->post(route('epics.store', $project), ['name' => 'Rilis', 'color' => 'amber', 'target_on' => now()->addWeek()->toDateString()])
            ->assertRedirect();
        $epic = Epic::firstOrFail();
        $this->post(route('cards.store', $project), ['column_id' => $this->column($project, 'Backlog')->id, 'title' => 'Kartu rilis', 'epic_id' => $epic->id]);

        foreach ([$viewer, $member] as $user) {
            $this->actingAs($user);
            $this->get(route('epics.index', $project))->assertOk()->assertSee('Rilis');
            $this->get(route('epics.show', [$project, $epic]))->assertOk()->assertSee('Kartu rilis');
            $this->get(route('projects.show', $project))->assertOk()->assertSee('Epic: Rilis', false);
        }
    }
}
