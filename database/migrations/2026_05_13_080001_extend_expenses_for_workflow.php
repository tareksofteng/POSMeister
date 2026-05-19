<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            // Approval / payment trail
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->string('rejection_reason', 500)->nullable()->after('rejected_at');
            $table->foreignId('paid_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable()->after('paid_by');

            // Recurring expenses
            $table->boolean('is_recurring')->default(false)->after('paid_at');
            $table->enum('recurring_frequency', ['weekly', 'monthly', 'yearly'])->nullable()->after('is_recurring');
            $table->date('next_due_date')->nullable()->after('recurring_frequency');
            $table->date('recurring_end_date')->nullable()->after('next_due_date');
            $table->foreignId('parent_expense_id')->nullable()->after('recurring_end_date')
                  ->constrained('expenses')->nullOnDelete();

            $table->index(['is_recurring', 'next_due_date']);
        });

        Schema::create('expense_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('action', [
                'created', 'updated', 'approved', 'rejected', 'paid', 'reopened', 'deleted',
            ]);
            $table->string('notes', 500)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['expense_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_audit_logs');

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['parent_expense_id']);
            $table->dropForeign(['paid_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['is_recurring', 'next_due_date']);
            $table->dropColumn([
                'approved_by', 'approved_at',
                'rejected_by', 'rejected_at', 'rejection_reason',
                'paid_by', 'paid_at',
                'is_recurring', 'recurring_frequency', 'next_due_date', 'recurring_end_date',
                'parent_expense_id',
            ]);
        });
    }
};
