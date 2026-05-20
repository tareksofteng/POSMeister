<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connector_id')->constrained('ecommerce_connectors')->cascadeOnDelete();

            $table->enum('entity', ['products', 'stock', 'customers', 'orders']);
            $table->enum('direction', ['push', 'pull', 'bidirectional'])->default('pull');

            $table->enum('status', ['queued', 'running', 'completed', 'partial', 'failed', 'cancelled'])
                  ->default('queued');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            $table->text('error')->nullable();
            $table->json('summary')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['connector_id', 'entity', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_jobs');
    }
};
