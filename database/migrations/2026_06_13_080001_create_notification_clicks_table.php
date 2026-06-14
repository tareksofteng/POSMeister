<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Append-only log of every push interaction. The Service Worker fires a
 * beacon to /api/push/click on `notificationclick`; we record what was
 * clicked, by whom, from which device, and which (if any) action button
 * the user picked. Feeds the click-through rate metric on the push
 * analytics widget.
 *
 * Notifications can be soft-deleted from smart_notifications without
 * losing this trail — that's why notification_id is nullOnDelete rather
 * than cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_id')->nullable()
                  ->constrained('smart_notifications')->nullOnDelete();
            $table->foreignId('user_id')->nullable()
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()
                  ->constrained('push_subscriptions')->nullOnDelete();

            $table->string('code', 64)->nullable();      // mirrored from notification for analytics
            $table->string('action', 80)->nullable();    // empty when body clicked, otherwise route name
            $table->boolean('dismissed')->default(false);

            $table->timestamp('clicked_at')->useCurrent();
            $table->timestamps();

            $table->index('clicked_at');
            $table->index(['code', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_clicks');
    }
};
