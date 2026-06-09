<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | notification_rules — Phase AB Round 3
 |--------------------------------------------------------------------------
 |
 | Configurable per-code overrides for the Smart Notification engine.
 |
 | Detectors emit alerts with hand-tuned defaults (cooldown 240 min,
 | warning → danger when count > 10, etc.). This table lets admins
 | tune those numbers per deployment WITHOUT touching the detector
 | code. The NotificationRuleEngine consults this table inside
 | SmartNotificationService::push() and overlays any non-null field
 | over the detector's defaults before the dedupe/insert.
 |
 | A row with `enabled = false` silences the code completely.
 |
 | Branch scoping:
 |   - `branch_ids = NULL` → applies to every branch
 |   - `branch_ids = [2, 3]` → applies only when the detector emits a
 |     notification carrying one of those branch_ids
 |
 | The `code` column is unique so a missing row means "use detector
 | defaults" (the common case — admins only configure the codes they
 | want to override).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_rules', function (Blueprint $table) {
            $table->id();

            // Detector code (e.g. 'inventory.low_stock'). Unique → one
            // rule per code; admin edits the same row instead of stacking.
            $table->string('code', 64)->unique();

            // Quick on/off without deleting the rule.
            $table->boolean('enabled')->default(true);

            // Cooldown override (minutes). NULL → use detector default.
            $table->unsignedInteger('cooldown_minutes')->nullable();

            // Escalation thresholds — when the detector emits a count-based
            // alert, the engine consults these to decide severity. NULL on
            // any field means the detector's hand-rolled ramp wins.
            $table->unsignedInteger('warning_threshold')->nullable();
            $table->unsignedInteger('danger_threshold')->nullable();
            $table->unsignedInteger('critical_threshold')->nullable();

            // Floor + ceiling on severity. min_severity drops the alert
            // entirely below the configured floor; max_severity clips an
            // escalating alert at a ceiling (e.g. cap to warning).
            $table->enum('min_severity', ['info', 'success', 'warning', 'danger', 'critical'])->nullable();
            $table->enum('max_severity', ['info', 'success', 'warning', 'danger', 'critical'])->nullable();

            // Audience override — admin can target this alert to a specific
            // role instead of the detector's default. NULL = detector default.
            $table->string('audience_role', 32)->nullable();

            // Branch scope — JSON list of branch IDs this rule applies to.
            // NULL = applies to every branch.
            $table->json('branch_ids')->nullable();

            // Free-form notes — admin can document why this rule exists.
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_rules');
    }
};
