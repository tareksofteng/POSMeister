<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Replay-protection table for offline POS sales (and any other write
 * that ships an Idempotency-Key header from the client). The first
 * request for a given key inserts the row and stores the response
 * fingerprint; subsequent requests for the same key short-circuit
 * to that response instead of running the write a second time.
 *
 * Rows older than the retention window can be pruned by the scheduler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('entity_type', 64)->nullable();   // e.g. "sale"
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_hash', 64)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
