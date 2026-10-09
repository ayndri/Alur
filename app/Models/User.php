<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function assignedCards(): HasMany
    {
        return $this->hasMany(Card::class, 'assignee_id');
    }

    /** Peran di proyek ini, atau null kalau bukan anggota. */
    public function roleIn(Project $project): ?string
    {
        if ($project->relationLoaded('members')) {
            return $project->members->firstWhere('id', $this->id)?->pivot->role;
        }

        return ProjectMember::where('project_id', $project->id)
            ->where('user_id', $this->id)
            ->value('role');
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->join('');
    }
}
