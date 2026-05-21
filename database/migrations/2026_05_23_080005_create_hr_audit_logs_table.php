<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic audit log for sensitive HR actions: employee status flip,
 * salary change, payslip approval, payslip rejection, attendance
 * correction, manual salary advance. Before/after snapshots stored as
 * JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 64);                  // employee.salary_changed, payslip.approved, ...
            $table->string('entity_type', 64);             // employee, payslip, attendance, salary_advance
            $table->unsignedBigInteger('entity_id');

            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('note', 255)->nullable();

            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['entity_type', 'entity_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_audit_logs');
    }
};
