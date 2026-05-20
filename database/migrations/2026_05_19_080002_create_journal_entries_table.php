<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('entry_number', 32)->unique();
            $table->date('entry_date');
            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();

            // Polymorphic-ish reference to the originating module (sale, purchase, expense, payslip, etc.)
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 64)->nullable();

            $table->string('narration', 500)->nullable();

            $table->decimal('total_debit',  14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);

            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('reversed_by_entry_id')->nullable()
                  ->constrained('journal_entries')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['entry_date', 'status']);
            $table->index(['branch_id', 'entry_date']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
