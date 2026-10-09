<?php

namespace Tests\Feature;

use App\Exceptions\FlowException;
use App\Exceptions\StaleCard;
use App\Exceptions\WipLimitReached;
use App\Models\Card;
use App\Models\CardMove;
use App\Models\Column;
use App\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_kartu_baru_bernomor_urut_per_proyek_dan_masuk_paling_bawah(): void
    {
        $project = $this->project();
        $other = $this->project();
        $backlog = $this->column($project, 'Backlog');

        $a = $this->board()->createCard($backlog, $project->owner, ['title' => 'A']);
        $b = $this->board()->createCard($backlog, $project->owner, ['title' => 'B']);
        $x = $this->board()->createCard($this->column($other, 'Backlog'), $other->owner, ['title' => 'X']);

        $this->assertSame([1, 2], [$a->number, $b->number]);
        $this->assertSame(1, $x->number);
        $this->assertTrue($a->position < $b->position);
        $this->assertNull($a->started_at);
        $this->assertSame(2, $project->fresh()->version - 0);
    }

    public function test_waktu_mulai_dan_selesai_mengikuti_jenis_kolom(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'Login']);

        $this->travel(1)->days();
        $card = $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by);
        $startedAt = $card->started_at;
        $this->assertNotNull($startedAt);
        $this->assertNull($card->completed_at);

        $this->travel(2)->days();
        $card = $this->board()->move($card, $this->column($project, 'Selesai'), $by);
        $this->assertNotNull($card->completed_at);
        $this->assertEqualsWithDelta(2.0, $card->cycleTimeInDays(), 0.01);

        // Dibuka ulang: selesai dikosongkan, waktu mulai tetap.
        $card = $this->board()->move($card, $this->column($project, 'Review'), $by);
        $this->assertNull($card->completed_at);
        $this->assertTrue($card->started_at->eq($startedAt));

        // Kembali ke antrean pun waktu mulainya tidak di-reset.
        $card = $this->board()->move($card, $this->column($project, 'Backlog'), $by);
        $this->assertTrue($card->started_at->eq($startedAt));
    }

    public function test_kartu_yang_lompat_langsung_ke_selesai_tidak_punya_waktu_mulai(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Typo']);
        $card = $this->board()->move($card, $this->column($project, 'Selesai'), $project->owner);

        $this->assertNull($card->started_at);
        $this->assertNotNull($card->completed_at);
        $this->assertNull($card->cycleTimeInDays());
    }

    public function test_kolom_penuh_menolak_kartu_baru_tapi_kartunya_tetap_bisa_diurutkan(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $doing = $this->column($project, 'Dikerjakan'); // batas 3
        $cards = collect(range(1, 4))->map(fn ($i) => $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => "K{$i}"]));

        foreach ($cards->take(3) as $card) {
            $this->board()->move($card, $doing, $by);
        }

        try {
            $this->board()->move($cards[3], $doing, $by);
            $this->fail('Kartu keempat seharusnya ditolak.');
        } catch (WipLimitReached $e) {
            $this->assertStringContainsString('Dikerjakan sudah penuh (3/3)', $e->getMessage());
        }

        $this->assertSame($this->column($project, 'Backlog')->id, $cards[3]->fresh()->column_id);
        $this->assertSame(1, CardMove::where('card_id', $cards[3]->id)->count(), 'hanya catatan pembuatan');

        // Mengubah urutan di dalam kolom yang penuh tidak dihitung sebagai kartu masuk.
        $this->board()->move($cards[2], $doing, $by, $cards[0]->id);
        $this->assertSame([$cards[2]->id, $cards[0]->id, $cards[1]->id], $this->idsIn($doing));
    }

    public function test_tidak_bisa_membuat_kartu_langsung_di_kolom_yang_penuh(): void
    {
        $project = $this->project();
        $review = $this->column($project, 'Review'); // batas 2
        $this->board()->createCard($review, $project->owner, ['title' => 'A']);
        $this->board()->createCard($review, $project->owner, ['title' => 'B']);

        $this->expectException(WipLimitReached::class);
        $this->board()->createCard($review, $project->owner, ['title' => 'C']);
    }

    public function test_menurunkan_batas_tidak_mengusir_kartu_tapi_menolak_yang_baru(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $doing = $this->column($project, 'Dikerjakan');
        foreach (['A', 'B', 'C'] as $title) {
            $this->board()->createCard($doing, $by, ['title' => $title]);
        }
        $waiting = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'D']);

        $this->board()->setWipLimit($doing, $by, 2);
        $this->assertCount(3, $this->idsIn($doing));

        $this->expectException(WipLimitReached::class);
        $this->board()->move($waiting, $doing->fresh(), $by);
    }

    public function test_kolom_tanpa_batas_menerima_berapa_pun(): void
    {
        $project = $this->project();
        $doing = $this->column($project, 'Dikerjakan');
        $this->board()->setWipLimit($doing, $project->owner, null);

        foreach (range(1, 6) as $i) {
            $this->board()->createCard($doing, $project->owner, ['title' => "K{$i}"]);
        }

        $this->assertCount(6, $this->idsIn($doing));
    }

    public function test_kartu_ditaruh_sebelum_kartu_tujuan_dan_urutan_dinomori_ulang(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $backlog = $this->column($project, 'Backlog');
        [$a, $b, $c] = collect(['A', 'B', 'C'])->map(fn ($t) => $this->board()->createCard($backlog, $by, ['title' => $t]))->all();

        $this->board()->move($c, $backlog, $by, $a->id);
        $this->assertSame([$c->id, $a->id, $b->id], $this->idsIn($backlog));
        $this->assertSame([0, 1, 2], Card::where('column_id', $backlog->id)->ordered()->pluck('position')->all());

        // Paling bawah kalau tanpa kartu tujuan.
        $this->board()->move($c, $backlog, $by);
        $this->assertSame([$a->id, $b->id, $c->id], $this->idsIn($backlog));
    }

    public function test_kartu_tujuan_yang_sudah_pindah_kolom_membuat_kartu_ditaruh_paling_bawah(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $backlog = $this->column($project, 'Backlog');
        $ready = $this->column($project, 'Siap dikerjakan');
        $a = $this->board()->createCard($ready, $by, ['title' => 'A']);
        $b = $this->board()->createCard($ready, $by, ['title' => 'B']);
        $moving = $this->board()->createCard($backlog, $by, ['title' => 'C']);

        // Layar Raka masih menampilkan B di "Siap dikerjakan", padahal Dina sudah memindahkannya.
        $this->board()->move($b, $this->column($project, 'Dikerjakan'), $by);
        $this->board()->move($moving, $ready, $by, $b->id);

        $this->assertSame([$a->id, $moving->id], $this->idsIn($ready));
    }

    public function test_setiap_perpindahan_tercatat_di_buku_besar(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'A']);
        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by);
        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by); // urutan saja
        $this->board()->move($card, $this->column($project, 'Selesai'), $by);
        $this->board()->archive($card, $by);

        $moves = CardMove::where('card_id', $card->id)->orderBy('id')->get()
            ->map(fn ($m) => [$m->fromColumn?->name, $m->toColumn?->name])->all();

        $this->assertSame([
            [null, 'Backlog'],
            ['Backlog', 'Dikerjakan'],
            ['Dikerjakan', 'Selesai'],
            ['Selesai', null],
        ], $moves);
    }

    public function test_dua_editor_dengan_versi_sama_yang_kedua_ditolak(): void
    {
        $project = $this->project();
        $dina = $project->owner;
        $raka = $this->join($project, $this->user(['name' => 'Raka']));
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $dina, ['title' => 'Lama']);
        $openedAt = $card->lock_version;

        $this->board()->updateCard($card, $raka, $openedAt, ['title' => 'Versi Raka']);

        try {
            $this->board()->updateCard($card, $dina, $openedAt, ['title' => 'Versi Dina']);
            $this->fail('Simpanan kedua seharusnya ditolak.');
        } catch (StaleCard $e) {
            $this->assertStringContainsString('Raka baru saja mengubah', $e->getMessage());
        }

        $this->assertSame('Versi Raka', $card->fresh()->title);
    }

    public function test_simpan_tanpa_perubahan_tidak_menaikkan_versi(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Sama']);

        $card = $this->board()->updateCard($card, $project->owner, 0, ['title' => 'Sama']);

        $this->assertSame(0, $card->lock_version);
    }

    public function test_kartu_hanya_bisa_ditugaskan_ke_anggota_yang_bisa_mengerjakan(): void
    {
        $project = $this->project();
        $viewer = $this->join($project, $this->user(), ProjectMember::VIEWER);
        $outsider = $this->user();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);

        foreach ([$viewer, $outsider] as $user) {
            try {
                $this->board()->updateCard($card->fresh(), $project->owner, $card->fresh()->lock_version, ['assignee_id' => $user->id]);
                $this->fail('Seharusnya ditolak.');
            } catch (FlowException) {
            }
        }

        $member = $this->join($project, $this->user());
        $card = $this->board()->updateCard($card->fresh(), $project->owner, 0, ['assignee_id' => $member->id]);
        $this->assertSame($member->id, $card->assignee_id);
    }

    public function test_label_proyek_lain_diabaikan(): void
    {
        $project = $this->project();
        $other = $this->project();
        $mine = $project->labels()->create(['name' => 'Bug', 'color' => 'red']);
        $theirs = $other->labels()->create(['name' => 'Bug', 'color' => 'red']);

        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A'], [$mine->id, $theirs->id]);

        $this->assertSame([$mine->id], $card->labels()->pluck('labels.id')->all());
    }

    public function test_memulihkan_arsip_tetap_tunduk_pada_batas_wip(): void
    {
        $project = $this->project();
        $by = $project->owner;
        $review = $this->column($project, 'Review'); // batas 2
        $old = $this->board()->createCard($review, $by, ['title' => 'Lama']);
        $this->board()->archive($old, $by);
        $this->board()->createCard($review, $by, ['title' => 'B']);
        $this->board()->createCard($review, $by, ['title' => 'C']);

        $this->expectException(WipLimitReached::class);
        $this->board()->restore($old, $by);
    }

    public function test_kartu_terblokir_mencatat_lama_terblokirnya(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Dikerjakan'), $project->owner, ['title' => 'A']);

        $this->board()->block($card, $project->owner, 'Menunggu akses API dari klien');
        $this->assertTrue($card->fresh()->isBlocked());

        $this->travel(3)->hours();
        $this->board()->unblock($card, $project->owner);

        $this->assertFalse($card->fresh()->isBlocked());
        $this->assertEquals(3.0, $card->activities()->where('type', 'unblocked')->first()->data['hours']);
    }

    public function test_aturan_kolom(): void
    {
        $project = $this->project();
        $by = $project->owner;

        $qa = $this->board()->addColumn($project, $by, 'Uji keamanan', Column::ACTIVE, 2);
        $this->assertSame(
            ['Backlog', 'Siap dikerjakan', 'Dikerjakan', 'Review', 'QA', 'Menunggu merge', 'Uji keamanan', 'Selesai'],
            $project->columns()->pluck('name')->all(),
            'kolom baru masuk sebelum Selesai'
        );

        $this->board()->createCard($qa, $by, ['title' => 'A']);
        $this->assertThrows(fn () => $this->board()->deleteColumn($qa, $by), FlowException::class);
        $this->assertThrows(fn () => $this->board()->renameColumn($qa, $by, 'Uji keamanan', Column::QUEUE), FlowException::class);

        $done = $this->column($project, 'Selesai');
        $this->assertThrows(fn () => $this->board()->deleteColumn($done, $by), FlowException::class);
        $this->assertThrows(fn () => $this->board()->renameColumn($done, $by, 'Beres', Column::ACTIVE), FlowException::class);

        $this->board()->deleteColumn($this->column($project, 'Backlog'), $by);
        $this->assertSame(7, $project->columns()->count());
    }

    public function test_urutan_kolom_harus_lengkap(): void
    {
        $project = $this->project();
        $ids = $project->columns()->pluck('id')->all();

        $this->assertThrows(fn () => $this->board()->reorderColumns($project, $project->owner, array_slice($ids, 1)), FlowException::class);

        $this->board()->reorderColumns($project, $project->owner, array_reverse($ids));
        $this->assertSame(array_reverse($ids), $project->columns()->pluck('id')->all());
    }

    private function idsIn(Column $column): array
    {
        return Card::where('column_id', $column->id)->onBoard()->ordered()->pluck('id')->all();
    }
}
