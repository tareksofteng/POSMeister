<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row config table. We could have inlined this into the existing
 * Settings table, but loyalty has enough domain-specific knobs that a
 * dedicated table reads cleaner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);

            // 1 point per X currency spent (subtotal-based)
            $table->decimal('earn_per_currency', 8, 4)->default(1);

            // X points = 1 currency at redemption
            $table->unsignedInteger('redeem_points_per_currency')->default(100);

            $table->unsignedInteger('min_redeem_points')->default(100);
            $table->unsignedSmallInteger('points_expiry_months')->default(0); // 0 = never

            // Lifetime spend thresholds for tier promotion (in default currency)
            $table->decimal('tier_silver_min',    14, 2)->default(0);
            $table->decimal('tier_gold_min',      14, 2)->default(500);
            $table->decimal('tier_platinum_min', 14, 2)->default(2000);
            $table->decimal('tier_vip_min',      14, 2)->default(5000);

            // Tier-based purchase discount % (optional)
            $table->decimal('tier_silver_discount',   5, 2)->default(0);
            $table->decimal('tier_gold_discount',     5, 2)->default(2);
            $table->decimal('tier_platinum_discount', 5, 2)->default(5);
            $table->decimal('tier_vip_discount',      5, 2)->default(8);

            $table->boolean('auto_downgrade')->default(false);

            $table->timestamps();
        });

        // Seed the single row immediately so the system has a working default.
        DB::table('loyalty_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_settings');
    }
};
