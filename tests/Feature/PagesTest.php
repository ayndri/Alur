<?php

namespace Tests\Feature;

use App\Models\ProjectMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Setiap halaman bisa dirender, untuk setiap peran yang boleh membukanya. */
class PagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_tamu(): void
    {
        $this->get('/')->assertOk()->assertSee('berani bilang');
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
        $this->get('/tidak-ada')->assertNotFound()->assertSee('Halaman tidak ditemukan');
    }

    public function test_pengguna_yang_sudah_masuk_tetap_bisa_membuka_landing(): void
    {
        $this->actingAs($this->user())->get('/')->assertOk()
            ->assertSee('Kembali ke papan')->assertDontSee('Coba papan demo');
    }

    public function test_akun_demo_melihat_pita_mode_demo(): void
    {
        $demo = $this->user(['email' => 'dewi@alur.test']);
        $this->actingAs($demo)->get(route('projects.index'))->assertOk()
            ->assertSee('Mode demo.')->assertSee('Keluar dari demo');

        $this->actingAs($this->user())->get(route('projects.index'))->assertOk()
            ->assertDontSee('Mode demo.')->assertSee('Halaman depan Alur');
    }

    public function test_semua_halaman_proyek_terbuka_untuk_pemilik_dan_pengamat(): void
    {
        $project = $this->project();
        $viewer = $this->join($project, $this->user(), ProjectMember::VIEWER);
        $by = $project->owner;
        $project->labels()->create(['name' => 'Bug', 'color' => 'red']);
        $card = $this->board()->createCard($this->column($project, 'Backlog'), $by, ['title' => 'Kartu A'], $project->labels()->pluck('id')->all());
        $this->board()->move($card, $this->column($project, 'Dikerjakan'), $by);
        $this->board()->block($card, $by, 'Menunggu vendor');
        $this->board()->comment($card, $by, 'Catatan');
        $this->board()->addChecklistItem($card, 'Langkah 1');
        $done = $this->board()->createCard($this->column($project, 'Dikerjakan'), $by, ['title' => 'Kartu B']);
        $this->travel(2)->days();
        $this->board()->move($done, $this->column($project, 'Selesai'), $by);
        $this->board()->archive($done, $by);

        foreach ([$by, $viewer] as $user) {
            $this->actingAs($user);
            $this->get(route('projects.index'))->assertOk()->assertSee($project->name);
            $this->get(route('projects.show', $project))->assertOk()->assertSee('Kartu A')->assertSee('Menunggu vendor');
            $this->get(route('projects.show', [$project, 'fragment' => 1]))->assertOk()->assertDontSee('<html', false);
            $this->get(route('cards.show', [$project, $card]))->assertOk()->assertSee('Langkah 1');
            $this->get(route('flow.show', $project))->assertOk()->assertSee('Isi tiap kolom per hari');
            $this->get(route('flow.show', [$project, 'hari' => 30]))->assertOk();
            $this->get(route('projects.activity', $project))->assertOk()->assertSee('terblokir');
            $this->get(route('projects.archived', $project))->assertOk()->assertSee('Kartu B');
        }

        $this->actingAs($by)->get(route('projects.settings', $project))->assertOk()->assertSee('Batas WIP');
        $this->get(route('projects.create'))->assertOk();
    }

    public function test_tautan_undangan(): void
    {
        $project = $this->project();
        $invitation = $project->invitations()->create([
            'token' => Str::random(40), 'role' => 'member', 'created_by' => $project->owner_id, 'expires_at' => now()->addDay(),
        ]);

        $this->get(route('invitations.show', $invitation->token))->assertOk()->assertSee($project->name);
        $this->get(route('invitations.show', 'salah'))->assertStatus(410);
    }

    public function test_tombol_demo_masuk_ke_papan_demo(): void
    {
        $owner = $this->user(['email' => 'dewi@alur.test']);
        $this->board()->createProject($owner, 'Demo', 'KLN');

        $this->post(route('demo'))->assertRedirect('/p/KLN');
        $this->assertAuthenticatedAs($owner);
    }
}
