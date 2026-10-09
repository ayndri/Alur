<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Epic;
use App\Models\Project;
use App\Services\Board;
use App\Services\FlowMetrics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EpicController extends Controller
{
    public function __construct(private Board $board)
    {
    }

    public function index(Request $request, Project $project, FlowMetrics $metrics)
    {
        return view('epics.index', [
            'project' => $project,
            'rows' => $metrics->epicProgress($project),
            'unassigned' => $project->cards()->onBoard()->whereNull('epic_id')->whereNull('completed_at')->count(),
            'role' => $request->user()->roleIn($project),
        ]);
    }

    public function show(Request $request, Project $project, Epic $epic, FlowMetrics $metrics)
    {
        abort_unless($epic->project_id === $project->id, 404);

        $cards = Card::where('epic_id', $epic->id)
            ->where(fn ($q) => $q->whereNull('archived_at')->orWhereNotNull('completed_at'))
            ->with(['column', 'assignee', 'labels'])
            ->get();
        $project->load('columns');

        return view('epics.show', [
            'project' => $project,
            'epic' => $epic,
            'row' => $metrics->epicProgress($project)->firstWhere('epic.id', $epic->id),
            'byColumn' => $project->columns->map(fn ($column) => [
                'column' => $column,
                'cards' => $cards->where('column_id', $column->id)->sortBy([['archived_at', 'asc'], ['position', 'asc']])->values(),
            ]),
            'cycle' => $metrics->cycleTimeSummary($project),
            'role' => $request->user()->roleIn($project),
        ]);
    }

    public function store(Request $request, Project $project)
    {
        $epic = $this->board->createEpic($project, $request->user(), $this->validated($request));

        return redirect()->route('epics.show', [$project, $epic])->with('success', "Epic {$epic->name} dibuat. Tambahkan kartu di bawah, atau pilih epic ini dari halaman kartu.");
    }

    public function update(Request $request, Project $project, Epic $epic)
    {
        abort_unless($epic->project_id === $project->id, 404);
        $this->board->updateEpic($epic, $request->user(), $this->validated($request));

        return back()->with('success', 'Epic disimpan.');
    }

    public function destroy(Request $request, Project $project, Epic $epic)
    {
        abort_unless($epic->project_id === $project->id, 404);
        $this->board->deleteEpic($epic, $request->user());

        return redirect()->route('epics.index', $project)->with('success', "Epic {$epic->name} dihapus. Kartunya tetap ada di papan.");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:2000',
            'color' => ['required', Rule::in(Epic::COLORS)],
            'target_on' => 'nullable|date',
        ]);
    }
}
