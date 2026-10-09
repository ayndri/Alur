<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['name', 'key', 'description', 'owner_id'];

    protected function casts(): array
    {
        return [
            'card_seq' => 'integer',
            'version' => 'integer',
        ];
    }

    /** URL proyek memakai kodenya: /p/KLN. */
    public function getRouteKeyName(): string
    {
        return 'key';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(Column::class)->orderBy('position');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    public function labels(): HasMany
    {
        return $this->hasMany(Label::class)->orderBy('name');
    }

    public function epics(): HasMany
    {
        return $this->hasMany(Epic::class)->orderByRaw('target_on IS NULL, target_on')->orderBy('name');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function moves(): HasMany
    {
        return $this->hasMany(CardMove::class);
    }

    /** Kolom "done" yang paling kiri: tujuan tombol "Tandai selesai". */
    public function doneColumn(): ?Column
    {
        return $this->columns->firstWhere('kind', Column::DONE);
    }
}
