<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('bank_name', 120)->nullable();
            $table->string('account_number', 64)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();
            $table->string('currency', 3)->default('EUR');

            $table->foreignId('branch_id')->nullable()
                  ->constrained('branches')->nullOnDelete();
            $table->foreignId('coa_account_id')
                  ->constrained('chart_of_accounts')->restrictOnDelete();

            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
