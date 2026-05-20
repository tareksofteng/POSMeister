<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('automation_rules')->cascadeOnDelete();
            $table->timestamp('triggered_at')->useCurrent();
            $table->enum('status', ['matched', 'no_match', 'action_failed', 'error']);
            $table->unsignedInteger('matched_count')->default(0);
            $table->json('action_result')->nullable();
            $table->text('error')->nullable();

            $table->index(['rule_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
    }
};
