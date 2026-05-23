<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only log of sync conflicts. We capture the original incoming
 * payload + the server's reason so an admin can resolve manually.
 * Common causes: stock collision (two devices oversold), customer
 * record dirty-renamed, tax rule mid-shift, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('sync_batches')->nullOnDelete();
            $table->string('device_id', 80)->nullable();
            $table->string('entity', 32);                     // 'sale', 'payment', etc.
            $table->string('idempotency_key', 80)->nullable();
            $table->string('reason', 64);                     // 'duplicate', 'stock', 'validation', 'other'
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->enum('resolution', ['open', 'accepted', 'rejected', 'manual'])->default('open');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['entity', 'resolution']);
            $table->index('device_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_conflicts');
    }
};
