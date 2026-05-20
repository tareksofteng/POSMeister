<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable ledger of every points movement. Never edited or deleted.
 * If a posting was wrong, post a reversing transaction instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->enum('type', ['earn', 'redeem', 'adjust', 'expire', 'reverse']);
            $table->decimal('points', 14, 2);              // signed: positive = credit, negative = debit
            $table->decimal('balance_after', 14, 2);

            $table->string('reference_type', 32)->nullable();  // sale, sale_return, manual
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_number', 64)->nullable();

            $table->string('note', 255)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
