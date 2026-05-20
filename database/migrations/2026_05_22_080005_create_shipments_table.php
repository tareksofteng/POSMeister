<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();

            $table->string('tracking_number', 64)->nullable();
            $table->enum('status', [
                'pending', 'created', 'in_transit', 'out_for_delivery',
                'delivered', 'returned', 'cancelled', 'failed',
            ])->default('pending');
            $table->decimal('shipping_cost', 14, 2)->default(0);
            $table->string('label_url', 500)->nullable();

            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->json('provider_payload')->nullable();
            $table->text('last_error')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'dispatched_at']);
            $table->index('tracking_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
