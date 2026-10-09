<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Epic: kelompok kartu dengan satu tujuan ("Layar TV ruang tunggu"). Satu kartu paling banyak
        // masuk satu epic. Progresnya dihitung dari kartu-kartunya, tidak disimpan.
        Schema::create('epics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->text('description')->nullable();
            $table->string('color', 20);
            $table->date('target_on')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });

        Schema::table('cards', function (Blueprint $table) {
            $table->foreignId('epic_id')->nullable()->after('column_id')->constrained()->nullOnDelete();
            $table->index(['epic_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('epic_id');
        });
        Schema::dropIfExists('epics');
    }
};
