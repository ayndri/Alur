<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('board_columns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedSmallInteger('position');
            // queue  = antre, belum dikerjakan (backlog, siap dikerjakan)
            // active = sedang dikerjakan; kartu yang masuk ke sini pertama kali mulai dihitung cycle time-nya
            // done   = selesai; cycle time berhenti di sini
            $table->string('kind', 10);
            // Kosong = tanpa batas. Dijaga oleh Board::move() dengan mengunci baris kolom ini.
            $table->unsignedSmallInteger('wip_limit')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'position']);
        });
        DB::statement("ALTER TABLE board_columns ADD CONSTRAINT board_columns_kind_check CHECK (kind IN ('queue', 'active', 'done'))");
        DB::statement('ALTER TABLE board_columns ADD CONSTRAINT board_columns_wip_limit_check CHECK (wip_limit IS NULL OR wip_limit > 0)');

        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            $table->string('color', 20);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('column_id')->constrained('board_columns');
            $table->unsignedInteger('number');
            $table->string('title', 200);
            $table->text('description')->nullable();
            // Urutan di dalam kolom. Hanya urutan relatifnya yang bermakna; celah boleh ada.
            $table->integer('position');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->date('due_on')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamp('blocked_at')->nullable();
            // Pertama kali masuk kolom "active". Tidak di-reset kalau kartu dikembalikan ke antrean:
            // pekerjaannya sudah dimulai, dan waktu bolak-balik itu bagian dari cycle time.
            $table->timestamp('started_at')->nullable();
            // Terisi selama kartu ada di kolom "done"; dikosongkan lagi kalau kartunya dibuka ulang.
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('column_entered_at');
            $table->timestamp('archived_at')->nullable();
            // Kunci optimistis untuk form edit: dua orang mengedit kartu yang sama tidak saling menimpa diam-diam.
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'number']);
            $table->index(['column_id', 'position']);
            $table->index(['project_id', 'completed_at']);
        });
        DB::statement('ALTER TABLE cards ADD CONSTRAINT cards_priority_check CHECK (priority BETWEEN 0 AND 3)');
        DB::statement('ALTER TABLE cards ADD CONSTRAINT cards_completed_after_start_check CHECK (completed_at IS NULL OR started_at IS NULL OR completed_at >= started_at)');

        Schema::create('card_label', function (Blueprint $table) {
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['card_id', 'label_id']);
        });

        // Buku besar perpindahan kartu. Satu baris per perpindahan antarkolom, termasuk saat dibuat
        // (from kosong) dan diarsipkan (to kosong). Cumulative flow diagram dibangun ulang dari sini.
        Schema::create('card_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->foreignId('to_column_id')->nullable()->constrained('board_columns')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('moved_at');

            $table->index(['project_id', 'moved_at']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->string('body', 200);
            $table->boolean('done')->default(false);
            $table->unsignedSmallInteger('position');
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        // Riwayat yang dibaca manusia: "Raka memindahkan KLN-12 ke Review".
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->jsonb('data')->nullable();
            $table->timestamp('created_at');

            $table->index(['project_id', 'created_at']);
            $table->index(['card_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('card_moves');
        Schema::dropIfExists('card_label');
        Schema::dropIfExists('cards');
        Schema::dropIfExists('labels');
        Schema::dropIfExists('board_columns');
    }
};
