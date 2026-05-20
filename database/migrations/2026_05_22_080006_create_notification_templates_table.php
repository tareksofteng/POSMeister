<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();             // invoice, order_shipped, ...
            $table->string('name', 150);
            $table->enum('channel', ['sms', 'whatsapp', 'email', 'in_app']);
            $table->string('subject', 200)->nullable();
            $table->text('body');
            $table->json('variables')->nullable();             // documented placeholders
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
