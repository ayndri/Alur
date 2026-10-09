<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Services\Board;
use App\Services\FlowMetrics;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /** Beranda: proyekku dan kartu yang sedang ditugaskan kepadaku di semua proyek. */
    public function index(Request $request)
    {
        $user = $request->user();

        $projects = $user->projects()
            ->withCount([
                'cards as open_count' => fn ($q) => $q->onBoard()->whereNull('completed_at'),
                'cards as blocked_count' => fn ($q) => $q->onBoard()->whereNotNull('blocked_at'),
            ])
            ->orderBy('name')
            ->get();

        $myCards = Card::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('assignee_id', $user->id)
            ->onBoard()
            ->whereNull('completed_at')
            ->with(['project', 'column', 'labels'])
            ->orderByRaw('due_on IS NULL, due_on')
            ->orderByDesc('priority')
            ->get();

        return view('projects.index', compact('projects', 'myCards'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(Request $request, Board $board)
    {
        $request->merge(['key' => strtoupper((string) $request->input('key'))]);
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'key' => 'required|string|regex:/^[A-Z]{2,5}$/|unique:projects,key',
            'description' => 'nullable|string|max:500',
        ], [
            'key.regex' => 'Kode proyek 2 sampai 5 huruf, tanpa angka atau spasi.',
            'key.unique' => 'Kode ini sudah dipakai proyek lain.',
        ]);

        $project = $board->createProject($request->user(), $data['name'], $data['key'], $data['description'] ?? null);

        return redirect()->route('projects.show', $project)
            ->with('success', 'Proyek dibuat dengan lima kolom bawaan. Ubah kolom dan batas WIP di Pengaturan.');
    }

    /** Papan. Dengan ?fragment=1 hanya isi papan yang dikirim, untuk pembaruan otomatis. */
    public function show(Request $request, Project $project, FlowMetrics $metrics)
    {
        $project->load(['members', 'labels', 'epics']);
        $columns = $project->columns()
            ->with(['cards' => fn ($q) => $q->ordered()->with(['assignee', 'labels', 'epic'])->withCount([
                'comments',
                'moves as rework_count' => fn ($q) => $q->where('rework', true),
                'checklist',
                'checklist as checklist_done_count' => fn ($q) => $q->where('done', true),
            ])])
            ->get();

        $data = [
            'project' => $project,
            'columns' => $columns,
            'cycle' => $metrics->cycleTimeSummary($project),
            'role' => $request->user()->roleIn($project),
        ];

        return $request->boolean('fragment')
            ? view('projects._board', $data)
            : view('projects.show', $data);
    }

    /** Dipanggil browser tiap beberapa detik; papan dimuat ulang hanya kalau angkanya berubah. */
    public function version(Project $project)
    {
        return response()->json(['version' => $project->version]);
    }

    public function settings(Project $project)
    {
        $project->load(['members', 'labels', 'columns' => fn ($q) => $q->withCount('cards')]);
        $invitations = $project->invitations()->usable()->with('creator')->latest()->get();

        return view('projects.settings', compact('project', 'invitations'));
    }

    public function update(Request $request, Project $project)
    {
        $project->update($request->validate([
            'name' => 'required|string|max:80',
            'description' => 'nullable|string|max:500',
        ]));
        $project->increment('version');

        return back()->with('success', 'Proyek diperbarui.');
    }

    public function activity(Project $project)
    {
        $activities = $project->activities()
            ->with(['user', 'card'])
            ->latest('id')
            ->paginate(40);

        return view('projects.activity', compact('project', 'activities'));
    }

    public function archived(Project $project)
    {
        $cards = $project->cards()->whereNotNull('archived_at')
            ->with(['column', 'assignee', 'labels'])
            ->latest('archived_at')
            ->paginate(30);

        return view('projects.archived', compact('project', 'cards'));
    }

    public function leave(Request $request, Project $project)
    {
        $user = $request->user();
        if ($user->roleIn($project) === ProjectMember::OWNER && $this->ownerCount($project) === 1) {
            return back()->with('error', 'Kamu satu-satunya pemilik. Jadikan anggota lain pemilik dulu sebelum keluar.');
        }

        $project->members()->detach($user->id);
        $project->cards()->where('assignee_id', $user->id)->whereNull('completed_at')->update(['assignee_id' => null]);
        $project->increment('version');

        return redirect()->route('projects.index')->with('success', "Kamu keluar dari {$project->name}.");
    }

    private function ownerCount(Project $project): int
    {
        return ProjectMember::where('project_id', $project->id)->where('role', ProjectMember::OWNER)->count();
    }
}
