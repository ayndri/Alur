<?php

// Dijalankan RaceTest sebagai proses PHP terpisah: satu orang memindahkan satu kartu ke satu kolom.
// Semua proses menunggu sampai detik yang sama ($argv[4]) lalu menembak bersamaan.

use App\Exceptions\WipLimitReached;
use App\Models\Card;
use App\Models\Column;
use App\Models\User;
use App\Services\Board;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $userId, $cardId, $columnId, $startAt] = $argv;

// Buka koneksi dulu supaya yang balapan benar-benar query-nya, bukan waktu konek.
DB::connection()->getPdo();
$user = User::findOrFail($userId);
$card = Card::findOrFail($cardId);
$column = Column::findOrFail($columnId);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

try {
    app(Board::class)->move($card, $column, $user);
    echo 'ok';
} catch (WipLimitReached) {
    echo 'ditolak';
}
