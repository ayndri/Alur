<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // wait = kartu sudah dikerjakan tapi sedang menunggu orang lain (mis. "Menunggu merge").
        // Ikut dihitung di cycle time, tapi sebagai waktu menunggu, bukan waktu kerja.
        DB::statement('ALTER TABLE board_columns DROP CONSTRAINT board_columns_kind_check');
        DB::statement("ALTER TABLE board_columns ADD CONSTRAINT board_columns_kind_check CHECK (kind IN ('queue', 'active', 'wait', 'done'))");

        Schema::table('card_moves', function (Blueprint $table) {
            // Revisi: kartu dikembalikan ke kolom kerja yang lebih kiri. Ditandai saat itu juga,
            // karena urutan kolom bisa berubah nanti dan arah perpindahan tidak bisa ditebak ulang.
            $table->boolean('rework')->default(false);
            $table->string('reason', 30)->nullable();
            $table->string('note', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('card_moves', function (Blueprint $table) {
            $table->dropColumn(['rework', 'reason', 'note']);
        });
        DB::statement('ALTER TABLE board_columns DROP CONSTRAINT board_columns_kind_check');
        // Jenis "wait" belum ada sebelum migrasi ini; kolomnya kembali jadi kolom kerja.
        DB::table('board_columns')->where('kind', 'wait')->update(['kind' => 'active']);
        DB::statement("ALTER TABLE board_columns ADD CONSTRAINT board_columns_kind_check CHECK (kind IN ('queue', 'active', 'done'))");
    }
};
