<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('labels')->where('project_id', $project->id)],
            'color' => ['required', Rule::in(Label::COLORS)],
        ], [
            'name.unique' => 'Label dengan nama ini sudah ada.',
        ]);

        $project->labels()->create($data);
        $project->increment('version');

        return back()->with('success', "Label {$data['name']} ditambahkan.");
    }

    public function destroy(Project $project, Label $label)
    {
        abort_unless($label->project_id === $project->id, 404);
        $label->delete();
        $project->increment('version');

        return back()->with('success', "Label {$label->name} dihapus dari semua kartu.");
    }
}
