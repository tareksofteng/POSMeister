<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Single-tenant deploys default to one tenant row. Existing data continues
 * to work because nothing else references tenants yet — this table just
 * gives us the seam to grow into a SaaS multi-tenant deployment later
 * without rewriting every model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 150);
            $table->string('subdomain', 100)->nullable()->unique();
            $table->string('contact_email', 150)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('timezone', 64)->default('UTC');
            $table->string('locale', 5)->default('en');

            $table->enum('status', ['active', 'trial', 'suspended', 'cancelled'])->default('active');
            $table->date('trial_ends_at')->nullable();
            $table->json('settings')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('tenants')->insert([
            'code'         => 'default',
            'name'         => 'POSmeister',
            'currency'     => 'EUR',
            'timezone'     => 'UTC',
            'locale'       => 'en',
            'status'       => 'active',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
