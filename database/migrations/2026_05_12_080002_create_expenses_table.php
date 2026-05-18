<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_number', 30)->unique();   // EXP-2026-00001
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('expense_category_id')
                  ->constrained('expense_categories')
                  ->restrictOnDelete();

            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');

            $table->enum('payment_method', ['cash', 'card', 'bank_transfer', 'cheque', 'other'])
                  ->default('cash');
            $table->string('reference_no', 100)->nullable();

            $table->string('attachment', 255)->nullable();    // receipt photo or pdf

            $table->enum('status', ['pending', 'approved', 'paid', 'rejected'])->default('pending');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'expense_date']);
            $table->index(['expense_category_id', 'expense_date']);
            $table->index(['status', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
