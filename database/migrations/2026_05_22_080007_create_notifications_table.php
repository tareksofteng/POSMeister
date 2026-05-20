<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->enum('channel', ['sms', 'whatsapp', 'email', 'in_app']);

            $table->string('recipient_type', 32);              // customer, user, supplier
            $table->unsignedBigInteger('recipient_id');
            $table->string('recipient_address', 200)->nullable(); // phone / email / device-id

            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->json('payload')->nullable();               // variables resolved at queue time
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->enum('status', ['queued', 'sending', 'sent', 'failed', 'read'])->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['recipient_type', 'recipient_id']);
            $table->index(['status', 'channel']);
            $table->index('reference_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
