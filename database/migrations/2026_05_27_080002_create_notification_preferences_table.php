<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user notification preferences.
 *
 *   muted_categories : ["inventory","hrm"]    — skip categories entirely
 *   min_severity     : "warning"               — drop anything below
 *   quiet_hours      : {"from":"22:00","to":"06:00"}
 *   channels         : {"in_app":true,"email":false}
 *   digest           : {"daily":true,"weekly":false}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('muted_categories')->nullable();
            $table->enum('min_severity', ['info', 'success', 'warning', 'danger', 'critical'])->default('info');
            $table->json('quiet_hours')->nullable();
            $table->json('channels')->nullable();
            $table->json('digest')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
