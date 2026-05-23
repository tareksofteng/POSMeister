<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart business alerts (internal-facing).
 *
 * Separate from app_notifications (which is the outgoing channel queue
 * for customer SMS/Email/WhatsApp). This table is the inbox the staff
 * see in the bell icon — risk warnings, deadlines, system health, etc.
 *
 * dedupe_key + audience_user_id is unique so the same alert generated
 * twice within the cooldown window collapses into a single row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smart_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);              // inventory, sales, finance, hrm, system, oms, crm, accounting
            $table->string('code', 64);                  // e.g. 'inventory.low_stock'
            $table->enum('severity', ['info', 'success', 'warning', 'danger', 'critical'])->default('info');
            $table->unsignedTinyInteger('urgency')->default(50);   // 0..100

            $table->string('title', 200);
            $table->text('message');
            $table->json('actions')->nullable();         // [{label, route, params, type}]
            $table->json('meta')->nullable();            // arbitrary context

            $table->string('entity_type', 32)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();

            // Audience: either a specific user or a role tag (admin/manager/...)
            $table->foreignId('audience_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience_role', 32)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            // De-dup key — same key + audience inside cooldown collapses
            $table->string('dedupe_key', 120);
            $table->timestamp('cooldown_until')->nullable();
            $table->unsignedSmallInteger('escalation_level')->default(0);

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('acked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['dedupe_key', 'audience_user_id', 'audience_role'], 'smartnotif_dedupe_unique');
            $table->index(['audience_user_id', 'read_at']);
            $table->index(['audience_role', 'read_at']);
            $table->index(['category', 'severity']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smart_notifications');
    }
};
