<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Label extends Model
{
    // Kunci warna saja yang disimpan; nilai warnanya ditentukan sistem desain, supaya kontrasnya terjaga.
    public const COLORS = ['slate', 'red', 'amber', 'green', 'blue', 'violet'];

    protected $fillable = ['project_id', 'name', 'color'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function cards(): BelongsToMany
    {
        return $this->belongsToMany(Card::class);
    }
}
