<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Device + session tracking on top of Sanctum's personal_access_tokens.
 * Allows users to see "where am I signed in" and revoke specific sessions
 * without invalidating every token.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('token_id')->nullable();

            $table->string('device', 100)->nullable();
            $table->string('browser', 100)->nullable();
            $table->string('platform', 50)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 80)->nullable();

            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();

            $table->index(['user_id', 'revoked_at']);
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
