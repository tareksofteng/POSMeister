<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_connectors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['woocommerce', 'shopify', 'custom']);
            $table->string('api_url', 255);
            $table->string('api_key', 255)->nullable();
            $table->string('api_secret', 255)->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_connectors');
    }
};
