<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 100);
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->string('currency', 3)->default('EUR');

            $table->unsignedSmallInteger('max_branches')->default(1);
            $table->unsignedSmallInteger('max_users')->default(5);
            $table->unsignedInteger('max_products')->default(1000);
            $table->unsignedInteger('max_invoices_per_month')->default(500);

            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('subscription_plans')->restrictOnDelete();

            $table->enum('status', ['trial', 'active', 'past_due', 'cancelled', 'expired'])->default('active');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->date('cancelled_at')->nullable();

            $table->json('overrides')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // Seed three sensible default tiers (free → starter → professional).
        $plans = [
            ['free',         'Free',         0,    'EUR', 1, 2,   100,   50,  null, 1, 1],
            ['starter',      'Starter',      29,   'EUR', 1, 5,   2000,  500, null, 1, 2],
            ['professional', 'Professional', 99,   'EUR', 5, 20,  20000, 5000,null, 1, 3],
            ['enterprise',   'Enterprise',   299,  'EUR', 50, 200, 200000, 100000, null, 1, 4],
        ];
        foreach ($plans as [$code, $name, $price, $cur, $br, $usr, $prod, $inv, $features, $active, $sort]) {
            DB::table('subscription_plans')->insert([
                'code'                    => $code,
                'name'                    => $name,
                'price_monthly'           => $price,
                'currency'                => $cur,
                'max_branches'            => $br,
                'max_users'               => $usr,
                'max_products'            => $prod,
                'max_invoices_per_month'  => $inv,
                'features'                => $features,
                'is_active'               => $active,
                'sort_order'              => $sort,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
    }
};
