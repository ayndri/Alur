<?php

// Dijalankan RaceTest: satu orang membuat satu kartu. Dipakai untuk membuktikan nomor kartu tidak bentrok.

use App\Models\Column;
use App\Models\User;
use App\Services\Board;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

[, $userId, $columnId, $startAt] = $argv;

DB::connection()->getPdo();
$user = User::findOrFail($userId);
$column = Column::findOrFail($columnId);

while (microtime(true) < (float) $startAt) {
    usleep(500);
}

app(Board::class)->createCard($column, $user, ['title' => 'Kartu dari proses '.getmypid()]);
echo 'ok';
