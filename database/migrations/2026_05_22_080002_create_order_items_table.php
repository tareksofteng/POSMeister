<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity',       12, 2);
            $table->decimal('fulfilled_qty',  12, 2)->default(0);
            $table->decimal('unit_price',     14, 2);
            $table->decimal('cost_price',     14, 2)->default(0);
            $table->decimal('tax_rate',        6, 2)->default(0);
            $table->decimal('line_total',     14, 2);

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
