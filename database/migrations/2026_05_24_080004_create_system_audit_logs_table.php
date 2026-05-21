<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * System-wide audit log for sensitive non-HR actions: settings changes,
 * permission changes, login successes/failures, key rotations, etc.
 * Complements the existing hr_audit_logs table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('info');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('note', 255)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_audit_logs');
    }
};
