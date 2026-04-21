<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_number', 30)->unique();             // VK-2026-00001
            $table->date('sale_date');
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Customer — either registered FK or walk-in fields
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();        // walk-in name
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_address', 255)->nullable();
            $table->enum('customer_type', ['registered', 'walkin'])->default('walkin');

            $table->enum('sale_type', ['retail', 'wholesale'])->default('retail');

            // Monetary totals
            $table->decimal('subtotal', 12, 2)->default(0);          // Σ qty × unit_price (net)
            $table->decimal('discount_amount', 12, 2)->default(0);   // header discount
            $table->decimal('vat_amount', 12, 2)->default(0);        // Σ per-item MwSt
            $table->decimal('freight_amount', 12, 2)->default(0);    // Versand / Fracht
            $table->decimal('grand_total', 12, 2)->default(0);       // net - discount + vat + freight

            // Payment
            $table->decimal('cash_paid', 12, 2)->default(0);
            $table->decimal('card_paid', 12, 2)->default(0);
            $table->decimal('total_paid', 12, 2)->default(0);
            $table->decimal('due_amount', 12, 2)->default(0);
            $table->decimal('previous_due', 12, 2)->default(0);

            $table->text('note')->nullable();
            $table->enum('status', ['active', 'cancelled'])->default('active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
