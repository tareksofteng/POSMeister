<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per incoming sync batch. Lets the admin recovery view see
 * "device X sent 12 sales at 14:03, 11 succeeded, 1 conflict" without
 * having to join through the individual sale rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_batches', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 80);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['running', 'succeeded', 'partial', 'failed'])->default('running');
            $table->unsignedInteger('total_count')->default(0);
            $table->unsignedInteger('ok_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('device_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_batches');
    }
};
