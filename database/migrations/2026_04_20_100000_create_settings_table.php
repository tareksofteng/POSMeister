<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            // Company identity
            $table->string('company_name', 120)->default('POSmeister');
            $table->text('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('logo')->nullable();            // relative storage path

            // Currency
            $table->string('currency_code', 10)->default('EUR');
            $table->string('currency_symbol', 10)->default('€');

            // Tax / VAT
            $table->decimal('vat_default', 5, 2)->default(19.00);

            // Invoice
            $table->string('invoice_prefix', 20)->default('INV-');
            $table->text('invoice_footer')->nullable();
            $table->string('date_format', 20)->default('d.m.Y');

            $table->timestamps();
        });

        // Seed the single settings row immediately so there is always exactly one row.
        DB::table('settings')->insert([
            'company_name'    => 'POSmeister',
            'currency_code'   => 'EUR',
            'currency_symbol' => '€',
            'vat_default'     => 19.00,
            'invoice_prefix'  => 'INV-',
            'date_format'     => 'd.m.Y',
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
