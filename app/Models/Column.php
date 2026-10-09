<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Column extends Model
{
    public const QUEUE = 'queue';
    public const ACTIVE = 'active';
    public const WAIT = 'wait';
    public const DONE = 'done';

    public const KIND_LABELS = [
        self::QUEUE => 'Antre',
        self::ACTIVE => 'Dikerjakan',
        self::WAIT => 'Menunggu',
        self::DONE => 'Selesai',
    ];

    protected $table = 'board_columns';

    protected $fillable = ['project_id', 'name', 'position', 'kind', 'wip_limit'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'wip_limit' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Kartu yang masih di papan (belum diarsipkan). Sengaja tanpa orderBy supaya count()/max()
     * aman di Postgres; urutkan dengan Card::scopeOrdered() saat menampilkan.
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class)->whereNull('archived_at');
    }

    public function isActive(): bool
    {
        return $this->kind === self::ACTIVE;
    }

    public function isWait(): bool
    {
        return $this->kind === self::WAIT;
    }

    /** Kartu di sini sudah mulai dikerjakan tapi belum selesai: dikerjakan atau menunggu. */
    public function isInProgress(): bool
    {
        return $this->kind === self::ACTIVE || $this->kind === self::WAIT;
    }

    public function isDone(): bool
    {
        return $this->kind === self::DONE;
    }

    /** Butuh hitungan kartu (cards_count atau relasi cards) yang sudah dimuat. */
    public function isFull(int $count): bool
    {
        return $this->wip_limit !== null && $count >= $this->wip_limit;
    }
}
