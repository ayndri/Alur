<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Epic extends Model
{
    // Warna epic memakai kunci palet yang sama dengan label, supaya kontrasnya sudah terjaga.
    public const COLORS = Label::COLORS;

    protected $fillable = ['project_id', 'name', 'description', 'color', 'target_on', 'created_by'];

    protected function casts(): array
    {
        return ['target_on' => 'date'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Kartu yang masih di papan. Kartu arsip dari kolom selesai tetap dihitung lewat allCards(). */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->whereNull('archived_at');
    }

    public function allCards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
