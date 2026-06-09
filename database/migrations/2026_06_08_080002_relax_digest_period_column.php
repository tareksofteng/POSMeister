<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | relax_digest_period_column — Phase AB Round 4
 |--------------------------------------------------------------------------
 |
 | The original column was ENUM('daily', 'weekly') which made adding the
 | new 'morning' / 'evening' / 'weekly' variants impossible without a
 | dangerous ALTER TABLE ... MODIFY ENUM (slow on big tables, requires
 | DBA approval). varchar(16) is more flexible: any future period name
 | works without another migration.
 |
 | Existing rows continue to work — 'daily' is still a perfectly valid
 | varchar(16) value, and the digest builder will keep accepting it for
 | one release cycle so any in-flight scheduler tick doesn't blow up.
 |
 | The unique (user_id, period, for_date) constraint is preserved.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_digests')) return;

        // Use DB statement so we hit MySQL's native MODIFY COLUMN rather
        // than doctrine/dbal which adds dependencies for a one-liner.
        \DB::statement("ALTER TABLE notification_digests MODIFY COLUMN period VARCHAR(16) NOT NULL DEFAULT 'morning'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('notification_digests')) return;

        // Best-effort revert. Rows with morning/evening/weekly will
        // collapse into daily so the original ENUM accepts them.
        \DB::statement("UPDATE notification_digests SET period = 'daily' WHERE period NOT IN ('daily', 'weekly')");
        \DB::statement("ALTER TABLE notification_digests MODIFY COLUMN period ENUM('daily', 'weekly') NOT NULL DEFAULT 'daily'");
    }
};
