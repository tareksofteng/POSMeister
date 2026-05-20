<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()
                  ->constrained('customers')->cascadeOnDelete();

            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('lifetime_credited', 14, 2)->default(0);
            $table->decimal('lifetime_debited',  14, 2)->default(0);

            $table->boolean('allow_negative')->default(false);
            $table->string('currency', 3)->default('EUR');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallets');
    }
};
