<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // Awalan nomor kartu, mis. "KLN" untuk KLN-42.
            $table->string('key', 5)->unique();
            $table->text('description')->nullable();
            $table->foreignId('owner_id')->constrained('users');
            // Nomor kartu terakhir yang dipakai. Dinaikkan di dalam transaksi yang mengunci baris proyek.
            $table->unsignedInteger('card_seq')->default(0);
            // Naik setiap ada perubahan di papan. Browser cukup menanyakan angka ini untuk tahu
            // apakah papannya perlu dimuat ulang (Vercel tidak bisa menahan koneksi WebSocket).
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 10);
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });
        DB::statement("ALTER TABLE project_members ADD CONSTRAINT project_members_role_check CHECK (role IN ('owner', 'member', 'viewer'))");

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('role', 10);
            $table->foreignId('created_by')->constrained('users');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('member', 'viewer'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
