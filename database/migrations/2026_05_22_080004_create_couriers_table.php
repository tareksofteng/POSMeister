<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 32)->unique();              // pathao, redx, dhl, ...
            $table->string('api_endpoint', 255)->nullable();
            $table->string('api_key', 255)->nullable();
            $table->string('api_secret', 255)->nullable();
            $table->json('supported_regions')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
