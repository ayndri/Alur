<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\FlowMetrics;
use Illuminate\Http\Request;

/** Halaman "Alur kerja": menjawab "seberapa cepat kami menyelesaikan kartu, dan di mana kartu tertahan?" */
class FlowController extends Controller
{
    public const RANGES = [30, 60, 90];

    public function show(Request $request, Project $project, FlowMetrics $metrics)
    {
        $days = in_array($request->integer('hari'), self::RANGES, true) ? $request->integer('hari') : 60;
        $remaining = $project->cards()->onBoard()->whereNull('completed_at')->count();

        return view('flow.show', [
            'project' => $project,
            'days' => $days,
            'summary' => $metrics->cycleTimeSummary($project, $days),
            'cycleTimes' => $metrics->cycleTimes($project, $days),
            'throughput' => $metrics->weeklyThroughput($project, (int) ceil($days / 7)),
            'cfd' => $metrics->cumulativeFlow($project, $days),
            'aging' => $metrics->agingWork($project),
            'breakdown' => $metrics->timeBreakdown($project, $days),
            'remaining' => $remaining,
            'forecast' => $metrics->forecast($project, $remaining),
        ]);
    }
}
