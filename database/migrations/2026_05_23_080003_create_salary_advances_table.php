<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->date('granted_on');
            $table->decimal('amount', 12, 2);
            $table->decimal('deducted_amount', 12, 2)->default(0);
            $table->enum('status', ['outstanding', 'partially_deducted', 'settled', 'cancelled'])
                  ->default('outstanding');

            // Payslip that fully settled this advance (set when status → settled).
            $table->foreignId('settled_in_payslip_id')->nullable()
                  ->constrained('payslips')->nullOnDelete();

            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index(['branch_id', 'granted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_advances');
    }
};
