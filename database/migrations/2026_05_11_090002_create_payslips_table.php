<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('payslip_number', 30)->unique();   // PS-2026-00001
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Snapshot of the period (so the payslip is self-contained even if the period is edited later)
            $table->date('period_start');
            $table->date('period_end');

            // Attendance summary
            $table->unsignedSmallInteger('days_in_period')->default(0);
            $table->unsignedSmallInteger('days_worked')->default(0);
            $table->unsignedSmallInteger('days_absent')->default(0);
            $table->unsignedSmallInteger('days_leave')->default(0);
            $table->unsignedSmallInteger('days_late')->default(0);
            $table->unsignedSmallInteger('days_half')->default(0);

            // Money, with the basic snapshot at the time of generation
            $table->decimal('basic_salary',     12, 2)->default(0);
            $table->decimal('total_allowances', 12, 2)->default(0);
            $table->decimal('total_bonuses',    12, 2)->default(0);
            $table->decimal('total_overtime',   12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('tax_amount',       12, 2)->default(0);
            $table->decimal('gross_salary',     12, 2)->default(0);
            $table->decimal('net_salary',       12, 2)->default(0);

            // Payment
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card', 'other'])->nullable();
            $table->string('payment_reference', 100)->nullable();

            $table->enum('status', ['pending', 'paid', 'partially_paid', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
