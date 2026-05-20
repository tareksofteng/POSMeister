<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->enum('source', ['pos', 'web', 'manual', 'ecommerce'])->default('manual');
            $table->enum('status', [
                'pending', 'confirmed', 'packed', 'shipped',
                'delivered', 'cancelled', 'returned',
            ])->default('pending');

            $table->enum('payment_status', ['unpaid', 'partial', 'paid', 'refunded'])->default('unpaid');
            $table->enum('payment_method', ['cod', 'cash', 'card', 'bank', 'wallet', 'other'])->default('cod');

            $table->decimal('subtotal',     14, 2)->default(0);
            $table->decimal('discount',     14, 2)->default(0);
            $table->decimal('shipping_cost',14, 2)->default(0);
            $table->decimal('vat_amount',   14, 2)->default(0);
            $table->decimal('total',        14, 2)->default(0);
            $table->decimal('paid_amount',  14, 2)->default(0);

            $table->string('customer_name',  150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->text('delivery_address')->nullable();
            $table->string('delivery_city',  100)->nullable();
            $table->string('delivery_zip',    20)->nullable();

            $table->text('notes')->nullable();
            $table->string('external_reference', 100)->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('packed_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'placed_at']);
            $table->index(['branch_id', 'status']);
            $table->index(['customer_id', 'placed_at']);
            $table->index('external_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
