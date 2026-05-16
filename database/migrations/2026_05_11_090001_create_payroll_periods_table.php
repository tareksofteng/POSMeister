<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('label', 60);                // e.g. "Mai 2026" or "2026-05"
            $table->date('period_start');
            $table->date('period_end');
            $table->enum('status', ['draft', 'generated', 'finalized', 'closed'])->default('draft');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['period_start', 'period_end']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
