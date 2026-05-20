<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_loyalty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()
                  ->constrained('customers')->cascadeOnDelete();

            $table->decimal('current_points',           14, 2)->default(0);
            $table->decimal('lifetime_points_earned',   14, 2)->default(0);
            $table->decimal('lifetime_points_redeemed', 14, 2)->default(0);
            $table->decimal('lifetime_spent',           14, 2)->default(0);
            $table->unsignedInteger('lifetime_visits')->default(0);

            $table->enum('tier', ['silver', 'gold', 'platinum', 'vip'])->default('silver');
            $table->timestamp('tier_changed_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();

            $table->timestamps();

            $table->index('tier');
            $table->index('last_activity_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_loyalty_profiles');
    }
};
