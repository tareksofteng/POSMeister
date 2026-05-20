<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation for the campaign engine. The actual channel adapters (SMS,
 * WhatsApp, email) are NOT wired here — only the persistence layer and
 * a Laravel-queue-friendly status machine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('type', ['birthday', 'comeback', 'sms', 'whatsapp', 'email']);
            $table->enum('status', ['draft', 'scheduled', 'queued', 'sent', 'cancelled', 'failed'])->default('draft');

            $table->text('message_body')->nullable();
            $table->json('audience_filter')->nullable();   // segment criteria
            $table->json('settings')->nullable();          // channel-specific options

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedInteger('delivered_count')->default(0);

            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
