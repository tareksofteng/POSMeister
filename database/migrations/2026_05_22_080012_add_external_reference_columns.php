<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-system identifiers so we can map POSmeister rows to records
 * in WooCommerce / Shopify / custom storefronts without ambiguity.
 * Format: "{connector_id}:{remote_id}" (kept as a single string to stay
 * provider-agnostic).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_reference', 100)->nullable()->after('barcode');
            $table->index('external_reference');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('external_reference', 100)->nullable()->after('email');
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['external_reference']);
            $table->dropColumn('external_reference');
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['external_reference']);
            $table->dropColumn('external_reference');
        });
    }
};
