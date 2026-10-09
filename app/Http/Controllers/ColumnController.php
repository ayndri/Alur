<?php

namespace App\Http\Controllers;

use App\Models\Column;
use App\Models\Project;
use App\Services\Board;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ColumnController extends Controller
{
    public function __construct(private Board $board)
    {
    }

    public function store(Request $request, Project $project)
    {
        $data = $this->validated($request);
        $this->board->addColumn($project, $request->user(), $data['name'], $data['kind'], $data['wip_limit'] ?? null);

        return back()->with('success', "Kolom {$data['name']} ditambahkan.");
    }

    public function update(Request $request, Project $project, Column $column)
    {
        abort_unless($column->project_id === $project->id, 404);
        $data = $this->validated($request);

        $this->board->renameColumn($column, $request->user(), $data['name'], $data['kind']);
        $this->board->setWipLimit($column, $request->user(), $data['wip_limit'] ?? null);

        return back()->with('success', "Kolom {$data['name']} disimpan.");
    }

    public function destroy(Request $request, Project $project, Column $column)
    {
        abort_unless($column->project_id === $project->id, 404);
        $this->board->deleteColumn($column, $request->user());

        return back()->with('success', "Kolom {$column->name} dihapus.");
    }

    public function reorder(Request $request, Project $project)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $this->board->reorderColumns($project, $request->user(), $data['ids']);

        return $request->expectsJson() ? response()->noContent() : back()->with('success', 'Urutan kolom disimpan.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:40',
            'kind' => ['required', Rule::in(array_keys(Column::KIND_LABELS))],
            'wip_limit' => 'nullable|integer|min:1|max:99',
        ], [
            'wip_limit.min' => 'Batas WIP minimal 1. Kosongkan untuk tanpa batas.',
        ]);
    }
}
