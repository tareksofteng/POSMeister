<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')
                  ->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')
                  ->constrained('chart_of_accounts')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();

            $table->decimal('debit',  14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);

            // Denormalised for fast ledger lookups without joining journal_entries
            $table->date('entry_date');
            $table->string('narration', 500)->nullable();

            $table->unsignedSmallInteger('line_no')->default(1);

            $table->timestamps();

            $table->index(['account_id', 'entry_date']);
            $table->index(['branch_id', 'entry_date']);
            $table->index('journal_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
