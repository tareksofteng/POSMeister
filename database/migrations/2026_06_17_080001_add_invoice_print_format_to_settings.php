<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Stores the receipt format the operator wants printed when they hit
 * "Print Receipt" anywhere in the product. Three values:
 *
 *   a4     — full-page A4 layout (existing template)
 *   pos80  — 80 mm thermal receipt (standard POS printer)
 *   pos58  — 58 mm thermal receipt (compact handheld units)
 *
 * Default stays at 'a4' so the existing behaviour is preserved for any
 * shop that hasn't picked yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('settings')) return;
        if (Schema::hasColumn('settings', 'invoice_print_format')) return;

        Schema::table('settings', function (Blueprint $table) {
            $table->string('invoice_print_format', 8)->default('a4')->after('date_format');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('settings')) return;
        if (!Schema::hasColumn('settings', 'invoice_print_format')) return;

        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('invoice_print_format');
        });
    }
};
