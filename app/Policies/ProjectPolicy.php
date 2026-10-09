<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;

/**
 * Tiga peran per proyek:
 *  - viewer (pengamat): melihat papan, kartu, dan metrik. Cocok untuk klien atau atasan.
 *  - member (anggota): membuat, memindah, mengedit, dan mengomentari kartu.
 *  - owner (pemilik): semua di atas, ditambah kolom, batas WIP, label, anggota, dan undangan.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $user->roleIn($project) !== null;
    }

    public function work(User $user, Project $project): bool
    {
        return in_array($user->roleIn($project), [ProjectMember::OWNER, ProjectMember::MEMBER], true);
    }

    public function manage(User $user, Project $project): bool
    {
        return $user->roleIn($project) === ProjectMember::OWNER;
    }
}
