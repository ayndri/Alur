<?php

namespace Tests\Feature;

use App\Models\Card;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Bukti bahwa kunci bekerja di database sungguhan, bukan hanya di urutan kode:
 * sepuluh proses PHP terpisah menulis ke papan yang sama pada saat yang sama.
 */
class RaceTest extends TestCase
{
    // Data harus benar-benar ter-commit supaya terlihat oleh proses lain,
    // jadi tidak bisa memakai transaksi RefreshDatabase.
    use DatabaseMigrations;

    private const RACERS = 10;

    public function test_sepuluh_kartu_berebut_satu_slot_wip_terakhir_hanya_satu_yang_masuk(): void
    {
        $project = $this->project();
        $doing = $this->column($project, 'Dikerjakan');
        $doing->update(['wip_limit' => 3]);
        foreach (['A', 'B'] as $title) {
            $this->board()->createCard($doing, $project->owner, ['title' => $title]);
        }

        $backlog = $this->column($project, 'Backlog');
        $racers = collect(range(1, self::RACERS))->map(function ($i) use ($project, $backlog) {
            $user = $this->join($project, $this->user());

            return [$user, $this->board()->createCard($backlog, $user, ['title' => "Pelari {$i}"])];
        });

        $results = $this->race($racers->map(fn ($r) => ['move.php', $r[0]->id, $r[1]->id, $doing->id]));

        $summary = 'hasil per proses: '.json_encode($results);
        $this->assertSame(1, $results['ok'] ?? 0, $summary);
        $this->assertSame(self::RACERS - 1, $results['ditolak'] ?? 0, $summary);
        $this->assertSame(3, Card::where('column_id', $doing->id)->count());
        $this->assertSame([0, 1, 2], Card::where('column_id', $doing->id)->ordered()->pluck('position')->all());
    }

    public function test_sepuluh_kartu_dibuat_bersamaan_mendapat_nomor_berbeda(): void
    {
        $project = $this->project();
        $backlog = $this->column($project, 'Backlog');
        $users = collect(range(1, self::RACERS))->map(fn () => $this->join($project, $this->user()));

        $results = $this->race($users->map(fn ($user) => ['create.php', $user->id, $backlog->id]));

        $this->assertSame(self::RACERS, $results['ok'] ?? 0, 'hasil per proses: '.json_encode($results));
        $this->assertSame(range(1, self::RACERS), Card::where('project_id', $project->id)->orderBy('number')->pluck('number')->all());
        $this->assertSame(self::RACERS, $project->fresh()->card_seq);
    }

    /** Jalankan setiap skrip sebagai proses terpisah yang mulai di detik yang sama. */
    private function race(Collection $jobs): Collection
    {
        $startAt = microtime(true) + 6;
        $env = array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => config('database.default'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
        ]);

        $processes = $jobs->map(function ($job) use ($startAt, $env) {
            $script = array_shift($job);
            $cmd = [PHP_BINARY, base_path("tests/race/{$script}"), ...array_map('strval', $job), (string) $startAt];
            $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env);

            return [$proc, $pipes];
        });

        return $processes->map(function ($p) {
            [$proc, $pipes] = $p;
            $out = stream_get_contents($pipes[1]);
            $err = stream_get_contents($pipes[2]);
            proc_close($proc);

            // Baris terakhir saja: PHP bisa mencetak peringatan startup ke stdout sebelum hasilnya.
            $last = trim(collect(preg_split('/\R/', trim($out)))->last() ?? '');

            return in_array($last, ['ok', 'ditolak'], true) ? $last : 'error: '.trim($out.' '.$err);
        })->countBy();
    }
}
