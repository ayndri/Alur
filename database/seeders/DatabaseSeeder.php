<?php

namespace Database\Seeders;

use App\Models\Card;
use App\Models\Invitation;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\Board;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Data demo. Semua riwayat dibuat lewat App\Services\Board (lihat TeamSimulation),
 * jadi urutan kartu, waktu mulai/selesai, dan buku besar perpindahan konsisten
 * dengan aturan aplikasi. Benih acaknya tetap: seeder yang sama menghasilkan riwayat yang sama
 * (relatif terhadap hari seeder dijalankan).
 */
class DatabaseSeeder extends Seeder
{
    public function run(Board $board): void
    {
        $now = now();
        $people = collect([
            'dewi' => 'Dewi Anggraini',
            'raka' => 'Raka Pratama',
            'sinta' => 'Sinta Maharani',
            'bima' => 'Bima Saputra',
            'laras' => 'Laras Wulandari',
            'yoga' => 'Yoga Prasetyo',
            'nadia' => 'Nadia Putri',
        ])->map(fn ($name, $handle) => User::create([
            'name' => $name,
            'email' => "{$handle}@alur.test",
            'password' => 'password123',
        ]));

        $this->clinic($board, $people, $now);
        $this->coffee($board, $people, $now);
    }

    private function clinic(Board $board, $people, Carbon $now): void
    {
        $start = $now->copy()->subWeeks(10)->startOfWeek();
        Carbon::setTestNow($start->copy()->setTime(8, 0));

        $project = $board->createProject($people['dewi'], 'Aplikasi Antrean Klinik', 'KLN',
            'Aplikasi pasien untuk ambil nomor antrean dari rumah, layar TV ruang tunggu, dan panel admin klinik. Target rilis publik akhir bulan depan.');
        foreach (['raka', 'sinta', 'bima', 'nadia'] as $handle) {
            $project->members()->attach($people[$handle]->id, ['role' => ProjectMember::MEMBER]);
        }
        // Kepala klinik: klien yang memantau progres tanpa mengubah papan.
        $project->members()->attach($people['laras']->id, ['role' => ProjectMember::VIEWER]);

        $labels = $this->labels($project, [
            'Backend' => 'blue', 'Mobile' => 'green', 'Desain' => 'violet', 'Infra' => 'slate', 'Bug' => 'red',
        ]);

        (new TeamSimulation(
            $board, $project, $people['dewi'],
            [$people['raka'], $people['sinta'], $people['bima']],
            require __DIR__.'/data/klinik.php',
            $labels,
            new Randomizer(new Mt19937(20261009)),
            tester: $people['nadia'],
        ))->run($start, $now->copy(), initialBacklog: 16, arrivalsPerDay: 0.5);

        $this->epics($board, $project, $people['dewi'], $now, [
            ['Ambil antrean dari rumah', 'blue', null, 'Pasien mengambil nomor antrean dari HP dan tahu kapan harus berangkat, tanpa menunggu berjam-jam di klinik.', [
                'Login pasien', 'Daftar poli', 'wireframe', 'Ambil nomor antrean', 'Validasi NIK', 'nomor antrean yang sedang dipanggil',
                'tinggal 3 orang', 'Batalkan antrean', 'SMS gateway untuk OTP', 'Rate limit', 'nomor antrean dobel', 'ditekan dua kali',
                'OTP tidak terbaca', 'Pilih dokter tertentu', 'kartu antrean', 'Riwayat kunjungan', 'Halaman profil', 'anggota keluarga', 'Onboarding',
            ]],
            ['Layar TV ruang tunggu', 'violet', null, 'Nomor yang dipanggil terbaca dari seluruh ruang tunggu, termasuk saat internet klinik putus.', [
                'Layar TV ruang tunggu', 'layar TV berhenti', 'Mode offline untuk layar TV', 'Bahasa Jawa',
            ]],
            ['Panel admin klinik', 'green', $now->copy()->addWeeks(8), 'Petugas mengatur jadwal dan memanggil pasien dari satu layar; kepala klinik melihat laporan tanpa minta ke IT.', [
                'kelola jadwal dokter', 'panggil pasien berikutnya', 'Ekspor laporan', 'Dashboard kepala klinik', 'Mode darurat',
                'Statistik waktu tunggu', 'Panduan admin', 'Laporan bulanan', 'urutan antrean salah',
            ]],
            ['Rilis ke Play Store', 'amber', $now->copy()->addWeek(), 'Aplikasi pasien terbit di Play Store dengan kebijakan privasi, pemantauan error, dan uji beban.', [
                'Setup CI', 'Sentry', 'Ikon aplikasi', 'Kebijakan privasi', 'Rilis beta', 'Uji beban', 'Tes otomatis', 'Rilis publik', 'Backup database',
            ]],
        ]);

        $this->checklist($project, 'Rilis beta ke internal testing Play Store', [
            ['Kebijakan privasi terpasang di aplikasi', true],
            ['Ikon dan screenshot toko', true],
            ['Uji di 5 perangkat Android', false],
            ['Undang 20 penguji dari staf klinik', false],
        ]);
        $this->checklist($project, 'Multi-cabang: pasien pilih lokasi klinik', [
            ['Rancang skema cabang', false],
            ['Migrasi jadwal lama ke cabang utama', false],
            ['Pilihan cabang di layar pertama', false],
        ]);

        $project->invitations()->create([
            'token' => Str::random(40),
            'role' => ProjectMember::MEMBER,
            'created_by' => $people['dewi']->id,
            'expires_at' => $now->copy()->addDays(7),
        ]);
    }

    private function coffee(Board $board, $people, Carbon $now): void
    {
        $start = $now->copy()->subWeeks(3)->startOfWeek();
        Carbon::setTestNow($start->copy()->setTime(9, 0));

        $project = $board->createProject($people['sinta'], 'Situs Kedai Kopi Senja', 'KOPI',
            'Situs satu halaman untuk kedai kopi: menu, lokasi, dan reservasi meja lewat WhatsApp.');
        $project->members()->attach($people['dewi']->id, ['role' => ProjectMember::MEMBER]);
        $project->members()->attach($people['yoga']->id, ['role' => ProjectMember::MEMBER]);

        $labels = $this->labels($project, ['Konten' => 'amber', 'Fitur' => 'green', 'Bug' => 'red']);

        (new TeamSimulation(
            $board, $project, $people['sinta'],
            [$people['dewi'], $people['yoga']],
            [
                ['Foto produk untuk halaman menu', 'Konten', 2, 2, 'Sesi foto Sabtu pagi, cahaya alami. 12 menu utama.'],
                ['Halaman menu dengan harga', 'Fitur', 2, 2, null],
                ['Peta lokasi dan jam buka', 'Fitur', 1, 2, null],
                ['Tombol reservasi meja lewat WhatsApp', 'Fitur', 2, 3, 'Pesan otomatis berisi tanggal, jam, dan jumlah orang.'],
                ['Cerita singkat kedai untuk halaman depan', 'Konten', 1, 1, null],
                ['Optimasi gambar ke WebP', 'Fitur', 1, 1, null],
                ['Judul dan deskripsi untuk mesin pencari', 'Konten', 1, 1, null],
                ['Bug: menu tidak bisa digeser di iPhone', 'Bug', 1, 3, null],
                ['Daftar menu musiman yang bisa diubah sendiri', 'Fitur', 3, 1, null],
                ['Tautan Instagram dan GoFood', 'Konten', 1, 0, null],
                ['Halaman acara: live music Jumat malam', 'Konten', 2, 0, null],
                ['Formulir lamaran barista', 'Fitur', 2, 0, null],
            ],
            $labels,
            new Randomizer(new Mt19937(77)),
        ))->run($start, $now->copy(), initialBacklog: 7, arrivalsPerDay: 0.4);
    }

    /**
     * Epic dibuat lewat Board (tercatat di aktivitas), lalu kartunya dipasangkan berdasarkan potongan judul.
     * Pemasangan ditulis langsung karena hanya metadata; riwayat perpindahan kartu tidak berubah.
     */
    private function epics(Board $board, Project $project, User $by, Carbon $now, array $epics): void
    {
        Carbon::setTestNow($project->created_at->copy()->addHour());
        foreach ($epics as [$name, $color, $target, $description, $titles]) {
            $epic = $board->createEpic($project, $by, [
                'name' => $name, 'color' => $color, 'description' => $description, 'target_on' => $target?->toDateString(),
            ]);
            foreach ($titles as $fragment) {
                Card::where('project_id', $project->id)->whereNull('epic_id')
                    ->where('title', 'ilike', "%{$fragment}%")->update(['epic_id' => $epic->id]);
            }
        }
        Carbon::setTestNow();
    }

    /** @return array<string, int> */
    private function labels(Project $project, array $colors): array
    {
        return collect($colors)->mapWithKeys(fn ($color, $name) => [
            $name => $project->labels()->create(['name' => $name, 'color' => $color])->id,
        ])->all();
    }

    private function checklist(Project $project, string $title, array $items): void
    {
        $card = Card::where('project_id', $project->id)->where('title', $title)->first();
        foreach ($items as $i => [$body, $done]) {
            $card?->checklist()->create(['body' => $body, 'done' => $done, 'position' => $i]);
        }
    }
}
