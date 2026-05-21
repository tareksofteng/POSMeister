<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds an approval workflow on top of the existing payslip lifecycle.
 *
 * The existing `status` enum (pending / paid / partially_paid / cancelled)
 * stays untouched — it represents the *payment* state. Approval is a
 * separate axis: draft → submitted → approved → (paid). The accounting
 * observer is now guarded so it only posts when both approval_status is
 * "approved" AND status is "paid".
 *
 * `is_locked = true` marks the payslip as immutable after posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->enum('approval_status', ['draft', 'submitted', 'approved', 'rejected'])
                  ->default('draft')->after('payment_method');

            $table->timestamp('submitted_at')->nullable()->after('approval_status');
            $table->foreignId('submitted_by')->nullable()->after('submitted_at')
                  ->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('submitted_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                  ->constrained('users')->nullOnDelete();

            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->foreignId('rejected_by')->nullable()->after('rejected_at')
                  ->constrained('users')->nullOnDelete();
            $table->string('rejection_reason', 500)->nullable()->after('rejected_by');

            $table->boolean('is_locked')->default(false)->after('rejection_reason');

            $table->decimal('advance_deducted', 12, 2)->default(0)->after('is_locked');

            $table->index(['approval_status', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropIndex(['approval_status', 'status']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'approval_status', 'submitted_at',
                'approved_at', 'rejected_at',
                'rejection_reason', 'is_locked', 'advance_deducted',
            ]);
        });
    }
};
