<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Devices (browsers, tablets, PWAs) registered to a user. Each row is
 * keyed by a client-generated device_id so the same machine can be
 * recognised across re-logins. Updated on every sync touch so the
 * admin dashboard can see "Tablet 3 last seen 2 minutes ago".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 80)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('label', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('last_ip', 45)->nullable();
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();

            $table->index('user_id');
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sessions');
    }
};
