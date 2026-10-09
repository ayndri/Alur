<?php

namespace App\Http\Controllers;

use App\Exceptions\FlowException;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function update(Request $request, Project $project, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(ProjectMember::ROLE_LABELS))]]);

        return $this->change($project, $user, function (ProjectMember $member) use ($data, $project, $user) {
            $member->update(['role' => $data['role']]);
            // Pengamat tidak bisa mengerjakan kartu: lepaskan tugasnya supaya tidak ada kartu "dipegang" orang yang tak bisa memindahkannya.
            if ($data['role'] === ProjectMember::VIEWER) {
                $project->cards()->where('assignee_id', $user->id)->whereNull('completed_at')->update(['assignee_id' => null]);
            }

            return "{$user->name} sekarang ".strtolower(ProjectMember::ROLE_LABELS[$data['role']]).'.';
        });
    }

    public function destroy(Project $project, User $user)
    {
        return $this->change($project, $user, function (ProjectMember $member) use ($project, $user) {
            $member->delete();
            $project->cards()->where('assignee_id', $user->id)->whereNull('completed_at')->update(['assignee_id' => null]);

            return "{$user->name} dikeluarkan dari proyek.";
        });
    }

    /** Proyek harus selalu punya minimal satu pemilik; pemeriksaannya di dalam kunci supaya dua pemilik tidak saling menurunkan bersamaan. */
    private function change(Project $project, User $user, callable $change)
    {
        $message = DB::transaction(function () use ($project, $user, $change) {
            Project::lockForUpdate()->findOrFail($project->id);
            $member = ProjectMember::where('project_id', $project->id)->where('user_id', $user->id)->firstOrFail();
            $message = $change($member);

            if (! ProjectMember::where('project_id', $project->id)->where('role', ProjectMember::OWNER)->exists()) {
                throw new FlowException('Proyek butuh minimal satu pemilik.');
            }
            $project->increment('version');

            return $message;
        });

        return back()->with('success', $message);
    }
}
