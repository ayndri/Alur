<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardMove extends Model
{
    public $timestamps = false;

    /** Alasan revisi yang ditawarkan saat kartu dikembalikan. Kunci disimpan, labelnya untuk tampilan. */
    public const REASONS = [
        'bug' => 'Bug ditemukan saat QA',
        'review' => 'Perlu perbaikan dari review',
        'spec' => 'Kebutuhan berubah',
        'other' => 'Lainnya',
    ];

    protected $fillable = ['project_id', 'card_id', 'from_column_id', 'to_column_id', 'user_id', 'moved_at', 'rework', 'reason', 'note'];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'rework' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function fromColumn(): BelongsTo
    {
        return $this->belongsTo(Column::class, 'from_column_id');
    }

    public function toColumn(): BelongsTo
    {
        return $this->belongsTo(Column::class, 'to_column_id');
    }
}
