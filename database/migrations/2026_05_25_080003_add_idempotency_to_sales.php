<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds offline-safe replay protection at the row level. A sale carrying
 * an Idempotency-Key header writes it here too, so the unique index
 * catches duplicate posts even if the in-flight idempotency_keys row
 * has been pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'idempotency_key')) {
                $table->string('idempotency_key', 80)->nullable()->after('sale_number');
                $table->unique('idempotency_key', 'sales_idempotency_key_unique');
            }
            if (!Schema::hasColumn('sales', 'offline_synced_at')) {
                $table->timestamp('offline_synced_at')->nullable();
            }
            if (!Schema::hasColumn('sales', 'offline_reference')) {
                $table->string('offline_reference', 64)->nullable();
                $table->index('offline_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'idempotency_key')) {
                $table->dropUnique('sales_idempotency_key_unique');
                $table->dropColumn('idempotency_key');
            }
            if (Schema::hasColumn('sales', 'offline_synced_at')) {
                $table->dropColumn('offline_synced_at');
            }
            if (Schema::hasColumn('sales', 'offline_reference')) {
                $table->dropIndex(['offline_reference']);
                $table->dropColumn('offline_reference');
            }
        });
    }
};
