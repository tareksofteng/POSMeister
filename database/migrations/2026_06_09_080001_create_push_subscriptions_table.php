<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * One row per device a user subscribes from. A single user can have many
 * rows (desktop browser, laptop, Android PWA, tablet). When a device
 * unsubscribes — or the push endpoint goes 410 Gone — we flip is_active
 * to false rather than deleting, so the analytics layer can still
 * report on it.
 *
 * The (endpoint) unique index handles the race where a user grants
 * permission twice in two tabs: the second insert collapses into an
 * update via updateOrCreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // The W3C Push API subscription — endpoint URL is unique per
            // device, the two keys (p256dh + auth) are needed to encrypt
            // the payload. Endpoint is VARCHAR(500) so MySQL can apply
            // the unique index (TEXT columns need a key length); real-
            // world endpoints sit comfortably under 400 chars.
            $table->string('endpoint', 500);
            $table->string('p256dh_key', 200);
            $table->string('auth_token', 80);

            // Light-touch device profile. UA-derived, used for the
            // "Connected devices" list and to mark which device delivered
            // a click.
            $table->string('browser', 32)->nullable();
            $table->string('platform', 32)->nullable();
            $table->string('device_type', 16)->nullable();   // mobile | tablet | desktop
            $table->string('label', 80)->nullable();         // user-editable nickname

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();

            // Failure tracking — when a send returns 404 / 410 / 413 we
            // deactivate; the count lets us decide between transient and
            // permanent failure on the upstream side.
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->string('last_failure_reason', 200)->nullable();

            $table->timestamps();

            $table->unique('endpoint', 'push_endpoint_unique');
            $table->index(['user_id', 'is_active']);
            $table->index(['branch_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
