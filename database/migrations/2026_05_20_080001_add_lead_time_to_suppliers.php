<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            // Average days between order placement and goods arrival. Used by the
            // reorder engine to time procurement so we don't run dry while waiting.
            $table->unsignedSmallInteger('lead_time_days')->default(7)->after('vat_number');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn('lead_time_days');
        });
    }
};
