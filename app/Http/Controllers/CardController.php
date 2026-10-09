<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\CardMove;
use App\Models\ChecklistItem;
use App\Models\Column;
use App\Models\Project;
use App\Services\Board;
use App\Services\FlowMetrics;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    public function __construct(private Board $board)
    {
    }

    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'column_id' => ['required', Rule::exists('board_columns', 'id')->where('project_id', $project->id)],
            'title' => 'required|string|max:200',
            'epic_id' => 'nullable|integer',
        ]);

        $card = $this->board->createCard(Column::findOrFail($data['column_id']), $request->user(), [
            'title' => $data['title'],
            'epic_id' => $data['epic_id'] ?? null,
        ]);

        return $request->expectsJson()
            ? response()->json(['number' => $card->number, 'version' => $project->fresh()->version], 201)
            : back()->with('success', "{$project->key}-{$card->number} ditambahkan.");
    }

    public function show(Request $request, Project $project, Card $card, FlowMetrics $metrics)
    {
        $card->setRelation('project', $project);
        $card->load(['column', 'epic', 'assignee', 'creator', 'labels', 'checklist' => fn ($q) => $q->orderBy('position')->orderBy('id'), 'comments.user', 'activities.user']);
        $project->load(['members', 'labels', 'columns', 'epics']);

        $cycle = $metrics->cycleTimeSummary($project);

        return view($request->boolean('fragment') ? 'cards._detail' : 'cards.show', [
            'project' => $project,
            'card' => $card,
            'cycle' => $cycle,
            'ageStatus' => $card->completed_at ? null : FlowMetrics::ageStatus($card->ageInDays(), $cycle),
            'journey' => $metrics->journey($card),
            'role' => $request->user()->roleIn($project),
        ]);
    }

    public function update(Request $request, Project $project, Card $card)
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string|max:5000',
            'priority' => ['required', 'integer', Rule::in(array_keys(Card::PRIORITY_LABELS))],
            'assignee_id' => 'nullable|integer',
            'due_on' => 'nullable|date',
            'epic_id' => 'nullable|integer',
            'labels' => 'array',
            'labels.*' => 'integer',
            'lock_version' => 'required|integer',
        ]);

        $this->board->updateCard(
            $card,
            $request->user(),
            (int) $data['lock_version'],
            collect($data)->only(['title', 'description', 'priority', 'assignee_id', 'due_on', 'epic_id'])->all(),
            $data['labels'] ?? [],
        );

        return back()->with('success', 'Kartu disimpan.');
    }

    /** Dari drag-and-drop (JSON) maupun form "Pindahkan ke" di detail kartu. */
    public function move(Request $request, Project $project, Card $card)
    {
        $data = $request->validate([
            'column_id' => ['required', Rule::exists('board_columns', 'id')->where('project_id', $project->id)],
            'before_id' => 'nullable|integer',
            'reason' => ['nullable', Rule::in(array_keys(CardMove::REASONS))],
            'note' => 'nullable|string|max:300',
        ]);

        $column = Column::findOrFail($data['column_id']);
        $this->board->move($card, $column, $request->user(), $data['before_id'] ?? null, $data['reason'] ?? null, $data['note'] ?? null);

        return $request->expectsJson()
            ? response()->json(['version' => $project->fresh()->version])
            : back()->with('success', "{$project->key}-{$card->number} dipindah ke {$column->name}.");
    }

    public function block(Request $request, Project $project, Card $card)
    {
        $data = $request->validate(['reason' => 'required|string|max:300'], [
            'reason.required' => 'Tulis apa yang ditunggu, supaya orang lain tahu cara membantu.',
        ]);
        $this->board->block($card, $request->user(), $data['reason']);

        return back();
    }

    public function unblock(Request $request, Project $project, Card $card)
    {
        $this->board->unblock($card, $request->user());

        return back();
    }

    public function archive(Request $request, Project $project, Card $card)
    {
        $this->board->archive($card, $request->user());

        return redirect()->route('projects.show', $project)
            ->with('success', "{$project->key}-{$card->number} diarsipkan.");
    }

    public function restore(Request $request, Project $project, Card $card)
    {
        $this->board->restore($card, $request->user());

        return back()->with('success', "{$project->key}-{$card->number} kembali ke papan.");
    }

    public function comment(Request $request, Project $project, Card $card)
    {
        $data = $request->validate(['body' => 'required|string|max:3000']);
        $this->board->comment($card, $request->user(), $data['body']);

        return back();
    }

    public function addChecklistItem(Request $request, Project $project, Card $card)
    {
        $data = $request->validate(['body' => 'required|string|max:200']);
        $this->board->addChecklistItem($card, $data['body']);

        return back();
    }

    public function toggleChecklistItem(Request $request, Project $project, Card $card, ChecklistItem $item)
    {
        $this->board->toggleChecklistItem($this->itemOf($card, $item));

        return $request->expectsJson() ? response()->noContent() : back();
    }

    public function removeChecklistItem(Project $project, Card $card, ChecklistItem $item)
    {
        $this->board->removeChecklistItem($this->itemOf($card, $item));

        return back();
    }

    private function itemOf(Card $card, ChecklistItem $item): ChecklistItem
    {
        abort_unless($item->card_id === $card->id, 404);

        return $item;
    }
}
