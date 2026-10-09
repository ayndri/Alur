<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use App\Services\FlowMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LandingController extends Controller
{
    public const DEMO_EMAIL = 'dewi@alur.test';
    public const DEMO_PROJECT = 'KLN';

    public function show(Request $request, FlowMetrics $metrics)
    {
        // Pengguna yang sudah masuk tetap boleh melihat halaman depan (mis. dari tautan di sidebar);
        // tombol-tombolnya diganti jadi "Kembali ke papan".
        // Angka di landing diambil langsung dari papan demo, bukan ditulis tangan.
        // Kalau papan demo tidak ada (mis. database baru), bagiannya tidak ditampilkan.
        $demo = Project::where('key', self::DEMO_PROJECT)->first();
        $live = null;
        if ($demo) {
            $remaining = $demo->cards()->onBoard()->whereNull('completed_at')->count();
            $live = [
                'project' => $demo,
                'cycle' => $metrics->cycleTimeSummary($demo),
                'remaining' => $remaining,
                'forecast' => $metrics->forecast($demo, $remaining),
                'blocked' => $demo->cards()->onBoard()->whereNotNull('blocked_at')->count(),
                'breakdown' => $metrics->timeBreakdown($demo),
                'columns' => $demo->columns()->get(),
                // Epic yang punya target dulu: di situ terlihat apakah targetnya realistis.
                'epics' => $metrics->epicProgress($demo)->filter(fn ($row) => $row['total'] > 0)
                    ->sortBy(fn ($row) => $row['epic']->target_on ? 0 : 1)->take(3)->values(),
            ];
        }

        return view('landing', [
            'live' => $live,
            'hasDemo' => User::where('email', self::DEMO_EMAIL)->exists(),
            'me' => $request->user(),
        ]);
    }

    /** Masuk sebagai pemilik papan demo tanpa mengetik password. */
    public function demo(Request $request)
    {
        $user = User::where('email', self::DEMO_EMAIL)->firstOrFail();
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('projects.show', self::DEMO_PROJECT);
    }
}
