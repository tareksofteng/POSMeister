<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);

            // Triggers describe what wakes the rule up. Built-in choices today:
            //   schedule:daily, schedule:hourly,
            //   stock.low, stock.dead,
            //   customer.inactive,
            //   payment.overdue,
            //   supplier.delay,
            //   inventory.negative_risk
            $table->string('trigger', 64);

            $table->json('condition')->nullable();             // optional filter expression
            $table->string('action_type', 32);                 // notify, reorder_suggest, task, risk_flag
            $table->json('action_config')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->unsignedInteger('run_count')->default(0);
            $table->unsignedInteger('match_count')->default(0);

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trigger', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
