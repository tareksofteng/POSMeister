<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->unsignedSmallInteger('fiscal_year');         // e.g. 2026
            $table->date('start_date');
            $table->date('end_date');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->decimal('total_budget', 14, 2);
            $table->unsignedTinyInteger('warning_threshold_percent')->default(80);

            $table->enum('status', ['draft', 'active', 'archived'])->default('draft');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'fiscal_year']);
            $table->index(['status', 'fiscal_year']);
        });

        Schema::create('budget_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained('budgets')->cascadeOnDelete();
            $table->foreignId('expense_category_id')
                  ->constrained('expense_categories')->restrictOnDelete();
            $table->decimal('allocated_amount', 14, 2);
            $table->timestamps();

            $table->unique(['budget_id', 'expense_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('budgets');
    }
};
