<?php

namespace App\Exceptions;

use App\Models\Column;

class WipLimitReached extends FlowException
{
    public function __construct(public readonly Column $column, int $count)
    {
        parent::__construct("Kolom {$column->name} sudah penuh ({$count}/{$column->wip_limit}). Selesaikan atau pindahkan satu kartu dari sana dulu.");
    }
}
