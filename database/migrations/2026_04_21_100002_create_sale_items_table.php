<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->decimal('unit_price', 12, 2);                    // VK-Preis at time of sale
            $table->decimal('cost_price', 12, 2)->default(0);        // EK-Preis at time of sale (for P&L)
            $table->decimal('tax_rate', 5, 2)->default(19.00);       // MwSt-Satz
            $table->decimal('vat_amount', 12, 2)->default(0);        // qty × unit_price × tax_rate/100
            $table->decimal('line_total', 12, 2)->default(0);        // qty × unit_price (net, before VAT)
            $table->boolean('is_service')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
