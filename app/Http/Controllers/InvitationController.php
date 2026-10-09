<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Project;
use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Undangan berupa tautan, bukan email: aplikasi demo ini tidak mengirim email, dan tim kecil
 * biasanya membagikan tautan lewat chat. Tautan berlaku 7 hari dan bisa dicabut.
 */
class InvitationController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate(['role' => ['required', Rule::in([ProjectMember::MEMBER, ProjectMember::VIEWER])]]);

        $project->invitations()->create([
            'token' => Str::random(40),
            'role' => $data['role'],
            'created_by' => $request->user()->id,
            'expires_at' => now()->addDays(7),
        ]);

        return back()->with('success', 'Tautan undangan dibuat. Salin dan kirimkan ke orangnya.');
    }

    public function destroy(Project $project, Invitation $invitation)
    {
        abort_unless($invitation->project_id === $project->id, 404);
        $invitation->update(['revoked_at' => now()]);

        return back()->with('success', 'Tautan undangan dicabut.');
    }

    /** Halaman tujuan tautan. Tamu diminta masuk atau daftar dulu, lalu kembali ke sini. */
    public function show(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->with(['project', 'creator'])->first();
        if (! $invitation || ! $invitation->isUsable()) {
            return response()->view('invitations.invalid', [], 410);
        }

        if (! $request->user()) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'alreadyMember' => $request->user()?->roleIn($invitation->project) !== null,
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = Invitation::where('token', $token)->usable()->with('project')->firstOrFail();
        $project = $invitation->project;
        $user = $request->user();

        if ($user->roleIn($project) === null) {
            $project->members()->attach($user->id, ['role' => $invitation->role]);
            $project->activities()->create([
                'user_id' => $user->id,
                'type' => 'member_joined',
                'data' => ['role' => $invitation->role],
                'created_at' => now(),
            ]);
            $project->increment('version');
        }

        return redirect()->route('projects.show', $project)->with('success', "Selamat datang di {$project->name}.");
    }
}
