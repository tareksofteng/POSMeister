<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable audit of basic_salary changes per employee. Written by the
 * EmployeeService whenever basic_salary is touched. Never deleted; never
 * edited. Provides the timeline for the "Salary changes" section of the
 * employee profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->decimal('previous_salary', 12, 2);
            $table->decimal('new_salary',      12, 2);
            $table->decimal('delta',           12, 2);         // signed

            $table->date('effective_date');
            $table->string('reason', 255)->nullable();

            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['employee_id', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_history');
    }
};
