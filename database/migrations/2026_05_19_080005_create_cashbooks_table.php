<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cashbook is a per-branch cash register registry. One row per branch
 * binds an opening balance + the COA cash account to use for postings.
 * Day-by-day totals are computed from journal_entry_lines on demand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashbooks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('coa_account_id')
                  ->constrained('chart_of_accounts')->restrictOnDelete();

            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['branch_id', 'coa_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashbooks');
    }
};
