<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | Phase Y — opt-in serial / IMEI / warranty tracking on products
 |--------------------------------------------------------------------------
 |
 | Every product gets a new boolean flag, defaulting to FALSE so the
 | existing inventory workflow keeps working unchanged. A product
 | becomes "serialized" only after a shop owner explicitly ticks the
 | checkbox in the product editor, at which point the rest of the
 | tracking pipeline (product_serials, product_serial_movements,
 | warranty math, sales serial-picker, etc.) kicks in.
 |
 | Once the product has any serial history (purchase or sale row in
 | product_serials), the flag is frozen at the application layer
 | (Product::isSerializationLocked()) — this column is the source of
 | truth, and an immutability check guards the toggle.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_serialized')
                  ->default(false)
                  ->after('is_service')
                  ->comment('When true, stock for this product is tracked by individual serial / IMEI numbers.');
            $table->index('is_serialized');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_serialized']);
            $table->dropColumn('is_serialized');
        });
    }
};
