<?php

namespace Tests;

use App\Models\Column;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\Board;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private int $seq = 0;

    protected function user(array $attrs = []): User
    {
        $n = ++$this->seq;

        return User::create($attrs + [
            'name' => "Pengguna {$n}",
            'email' => "pengguna{$n}@test.local",
            'password' => 'password123',
        ]);
    }

    /** Proyek dengan kolom bawaan Board::DEFAULT_COLUMNS. */
    protected function project(?User $owner = null): Project
    {
        $n = ++$this->seq;

        return $this->board()->createProject($owner ?? $this->user(), "Proyek {$n}", 'P'.$n);
    }

    protected function join(Project $project, User $user, string $role = ProjectMember::MEMBER): User
    {
        $project->members()->attach($user->id, ['role' => $role]);

        return $user;
    }

    protected function column(Project $project, string $name): Column
    {
        return $project->columns()->where('name', $name)->firstOrFail();
    }

    protected function board(): Board
    {
        return app(Board::class);
    }
}
