<?php

namespace App\Exceptions;

class StaleCard extends FlowException
{
    public function __construct(?string $byName = null)
    {
        parent::__construct(($byName ? "{$byName} baru saja mengubah kartu ini" : 'Kartu ini baru saja diubah orang lain')
            .'. Perubahanmu belum disimpan; muat ulang untuk melihat versi terbaru.');
    }
}
