<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materialised digest rows (one per user per period). Lets the
 * frontend render "Today's summary" cheaply and lets a future
 * email job pick up unsent digests from a single table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_digests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('period', ['daily', 'weekly'])->default('daily');
            $table->date('for_date');
            $table->json('summary');                    // computed payload
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period', 'for_date'], 'digest_unique');
            $table->index('for_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digests');
    }
};
