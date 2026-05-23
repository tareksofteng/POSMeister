<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger for backup runs. Each run captures one DB dump (and optionally
 * a storage tarball) on the local filesystem. Restore is intentionally
 * a manual / supervised operation, but the rows here track which file
 * to restore from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['database', 'storage', 'full'])->default('database');
            $table->enum('status', ['running', 'success', 'failed'])->default('running');
            $table->string('file_path', 255)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->string('note', 255)->nullable();
            $table->text('error')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
