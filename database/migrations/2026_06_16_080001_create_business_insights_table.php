<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Persistent insight log. The deterministic analytics services produce
 * a stream of "this is what changed" cards; we keep the recent ones so
 * the user can mark them resolved / ignored / pinned and so the timeline
 * view can render Today / Yesterday / Last 7 days groupings even after
 * the underlying number went back to normal.
 *
 * Dedupe is by (code, period_key) — an insight code that fires twice in
 * the same day collapses into the same row instead of stacking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_insights', function (Blueprint $table) {
            $table->id();

            // Stable code for the kind of signal — e.g. 'sales.vs_last_week',
            // 'customer.concentration', 'inventory.near_stockout'.
            $table->string('code', 80);

            // Daily / weekly bucket for dedupe — e.g. '2026-06-16' or '2026-W24'.
            $table->string('period_key', 16);

            $table->string('kind', 32);            // sales / customer / inventory / cash / receivables / supplier / system
            $table->string('severity', 16);        // positive / info / warning / danger
            $table->unsignedTinyInteger('confidence')->default(70);   // 0–100

            $table->string('title', 200);
            $table->text('detail')->nullable();
            $table->json('meta')->nullable();      // payload — numbers, comparisons, deltas
            $table->json('action')->nullable();    // optional { label, route, params }

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('audience_role', 32)->nullable();

            // Status — driven by the timeline UI. Default 'active' until
            // the user touches it.
            $table->enum('status', ['active', 'resolved', 'ignored', 'pinned'])->default('active');

            $table->timestamp('observed_at');      // when the underlying data was sampled
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['code', 'period_key', 'branch_id'], 'insights_dedupe_unique');
            $table->index(['status', 'observed_at']);
            $table->index(['kind', 'observed_at']);
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_insights');
    }
};
