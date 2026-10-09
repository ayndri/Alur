<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Card extends Model
{
    public const PRIORITY_LABELS = [
        0 => 'Tanpa prioritas',
        1 => 'Rendah',
        2 => 'Tinggi',
        3 => 'Mendesak',
    ];

    // Semua perubahan kolom, posisi, dan waktu mulai/selesai lewat App\Services\Board.
    protected $fillable = ['title', 'description', 'priority', 'assignee_id', 'due_on', 'epic_id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'position' => 'integer',
            'priority' => 'integer',
            'lock_version' => 'integer',
            'due_on' => 'date',
            'blocked_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'column_entered_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(Column::class);
    }

    public function epic(): BelongsTo
    {
        return $this->belongsTo(Epic::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->orderBy('name');
    }

    /** Tanpa orderBy (aman untuk count/max di Postgres); urutan dipasang saat dimuat untuk ditampilkan. */
    public function checklist(): HasMany
    {
        return $this->hasMany(ChecklistItem::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->orderBy('created_at');
    }

    public function moves(): HasMany
    {
        return $this->hasMany(CardMove::class)->orderBy('moved_at')->orderBy('id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function scopeOnBoard(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    /** "KLN-42". Butuh relasi project. */
    public function code(): string
    {
        return $this->project->key.'-'.$this->number;
    }

    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    public function isOverdue(?Carbon $now = null): bool
    {
        return $this->due_on && ! $this->completed_at && $this->due_on->lt(($now ?? now())->startOfDay());
    }

    /** Umur kerja: sejak mulai dikerjakan sampai selesai (atau sampai sekarang kalau belum). */
    public function ageInDays(?Carbon $now = null): ?float
    {
        if (! $this->started_at) {
            return null;
        }

        return $this->started_at->diffInSeconds($this->completed_at ?? $now ?? now()) / 86400;
    }

    public function cycleTimeInDays(): ?float
    {
        return $this->completed_at ? $this->ageInDays() : null;
    }
}
