<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_cost', 12, 2);              // EK-Preis (purchase cost)
            $table->decimal('vat_rate', 5, 2)->default(19.00);
            $table->decimal('vat_amount', 12, 2)->default(0); // qty × unit_cost × vat_rate/100
            $table->decimal('line_total', 12, 2)->default(0); // qty × unit_cost + vat_amount
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_items');
    }
};
