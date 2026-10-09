<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $project = $this->project();

        $this->get(route('projects.show', $project))->assertRedirect(route('login'));
        $this->postJson(route('cards.store', $project), [])->assertUnauthorized();
    }

    public function test_bukan_anggota_tidak_bisa_melihat_apa_pun(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Rahasia']);

        $this->actingAs($this->user());
        $this->getJson(route('projects.version', $project))->assertForbidden();
        $this->get(route('cards.show', [$project, $card]))->assertForbidden();
        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $card->column_id])->assertForbidden();
    }

    public function test_pengamat_hanya_bisa_melihat(): void
    {
        $project = $this->project();
        $viewer = $this->join($project, $this->user(), ProjectMember::VIEWER);
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);

        $this->actingAs($viewer);
        $this->getJson(route('projects.version', $project))->assertOk();
        $this->postJson(route('cards.store', $project), ['column_id' => $card->column_id, 'title' => 'B'])->assertForbidden();
        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $this->column($project, 'Dikerjakan')->id])->assertForbidden();
        $this->post(route('cards.comment', [$project, $card]), ['body' => 'Halo'])->assertForbidden();
    }

    public function test_anggota_bisa_mengerjakan_kartu_tapi_tidak_mengatur_proyek(): void
    {
        $project = $this->project();
        $member = $this->join($project, $this->user());
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);
        $doing = $this->column($project, 'Dikerjakan');

        $this->actingAs($member);
        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $doing->id])
            ->assertOk()
            ->assertJson(['version' => $project->fresh()->version]);
        $this->assertSame($doing->id, $card->fresh()->column_id);

        $this->get(route('projects.settings', $project))->assertForbidden();
        $this->put(route('columns.update', [$project, $doing]), ['name' => 'X', 'kind' => 'active', 'wip_limit' => 9])->assertForbidden();
        $this->post(route('invitations.store', $project), ['role' => 'member'])->assertForbidden();
    }

    public function test_pindah_ke_kolom_penuh_lewat_drag_and_drop_mendapat_422_dengan_pesan(): void
    {
        $project = $this->project();
        $review = $this->column($project, 'Review'); // batas 2
        $this->board()->createCard($review, $project->owner, ['title' => 'A']);
        $this->board()->createCard($review, $project->owner, ['title' => 'B']);
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'C']);

        $this->actingAs($project->owner)
            ->postJson(route('cards.move', [$project, $card]), ['column_id' => $review->id])
            ->assertStatus(422)
            ->assertJson(['message' => 'Kolom Review sudah penuh (2/2). Selesaikan atau pindahkan satu kartu dari sana dulu.']);
    }

    public function test_pindah_lewat_form_kembali_dengan_pesan(): void
    {
        $project = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);

        $this->actingAs($project->owner)
            ->from(route('cards.show', [$project, $card]))
            ->post(route('cards.move', [$project, $card]), ['column_id' => $this->column($project, 'Dikerjakan')->id])
            ->assertRedirect(route('cards.show', [$project, $card]))
            ->assertSessionHas('success', "{$project->key}-1 dipindah ke Dikerjakan.");
    }

    public function test_kolom_dan_kartu_proyek_lain_tidak_bisa_dipakai(): void
    {
        $project = $this->project();
        $other = $this->project();
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);
        $foreign = $this->board()->createCard($this->column($other, 'Backlog'), $other->owner, ['title' => 'B']);
        $foreign->update(['number' => 99]);

        $this->actingAs($project->owner);
        // Kolom milik proyek lain ditolak validasi.
        $this->postJson(route('cards.move', [$project, $card]), ['column_id' => $this->column($other, 'Dikerjakan')->id])
            ->assertStatus(422)->assertJsonValidationErrors('column_id');
        // Nomor kartu dicari di dalam proyek di URL saja.
        $this->postJson("/p/{$project->key}/99/pindah", ['column_id' => $card->column_id])->assertNotFound();
    }

    public function test_simpanan_basi_ditolak_dan_isian_dikembalikan(): void
    {
        $project = $this->project();
        $member = $this->join($project, $this->user(['name' => 'Raka']));
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'Lama']);
        $this->board()->updateCard($card, $member, 0, ['title' => 'Versi Raka']);

        $this->actingAs($project->owner)
            ->from(route('cards.show', [$project, $card]))
            ->put(route('cards.update', [$project, $card]), [
                'title' => 'Versi Dina', 'priority' => 0, 'lock_version' => 0,
            ])
            ->assertRedirect(route('cards.show', [$project, $card]))
            ->assertSessionHas('error')
            ->assertSessionHasInput('title', 'Versi Dina');

        $this->assertSame('Versi Raka', $card->fresh()->title);
    }

    public function test_pemilik_terakhir_tidak_bisa_menurunkan_dirinya(): void
    {
        $project = $this->project();
        $owner = $project->owner;

        $this->actingAs($owner)
            ->put(route('members.update', [$project, $owner]), ['role' => 'member'])
            ->assertSessionHas('error', 'Proyek butuh minimal satu pemilik.');
        $this->assertSame(ProjectMember::OWNER, $owner->roleIn($project));

        $this->post(route('projects.leave', $project))->assertSessionHas('error');
    }

    public function test_menurunkan_jadi_pengamat_melepas_tugas_kartunya(): void
    {
        $project = $this->project();
        $member = $this->join($project, $this->user());
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $project->owner, ['title' => 'A']);
        $this->board()->updateCard($card, $project->owner, 0, ['assignee_id' => $member->id]);

        $this->actingAs($project->owner)->put(route('members.update', [$project, $member]), ['role' => 'viewer']);

        $this->assertNull($card->fresh()->assignee_id);
    }

    public function test_undangan_menambahkan_anggota_dengan_perannya(): void
    {
        $project = $this->project();
        $invitation = $project->invitations()->create([
            'token' => Str::random(40), 'role' => 'viewer', 'created_by' => $project->owner_id, 'expires_at' => now()->addDay(),
        ]);
        $guest = $this->user();

        $this->actingAs($guest)->post(route('invitations.accept', $invitation->token))
            ->assertRedirect(route('projects.show', $project));
        $this->assertSame(ProjectMember::VIEWER, $guest->roleIn($project));

        // Undangan yang dicabut atau kedaluwarsa tidak bisa dipakai.
        $invitation->update(['revoked_at' => now()]);
        $this->actingAs($this->user())->post(route('invitations.accept', $invitation->token))->assertNotFound();
    }

    public function test_kode_proyek_unik_dan_huruf_saja(): void
    {
        $this->project(); // kode P1
        $this->actingAs($this->user());

        $this->post(route('projects.store'), ['name' => 'A', 'key' => 'p1'])->assertSessionHasErrors('key');
        $this->post(route('projects.store'), ['name' => 'A', 'key' => 'A1'])->assertSessionHasErrors('key');
        $this->post(route('projects.store'), ['name' => 'Baru', 'key' => 'baru'])->assertRedirect('/p/BARU');
    }
}
