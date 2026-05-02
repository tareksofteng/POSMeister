<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('quotation_number', 30)->unique();        // QT-2026-00001
            $table->date('quotation_date');
            $table->date('valid_until')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();

            // Customer — either registered FK or contact-only fields
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_email', 150)->nullable();
            $table->string('customer_address', 255)->nullable();

            $table->enum('quotation_type', ['retail', 'wholesale'])->default('retail');

            // Monetary totals
            $table->decimal('subtotal',        12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('vat_amount',      12, 2)->default(0);
            $table->decimal('freight_amount',  12, 2)->default(0);
            $table->decimal('grand_total',     12, 2)->default(0);

            $table->text('terms')->nullable();
            $table->text('note')->nullable();

            // draft → sent → accepted/rejected → converted (to sale)
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->foreignId('converted_sale_id')->nullable()->constrained('sales')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('description', 255)->nullable();          // ad-hoc service line when product is null
            $table->decimal('quantity',    10, 2);
            $table->decimal('unit_price',  12, 2);
            $table->decimal('tax_rate',     5, 2)->default(19.00);
            $table->decimal('vat_amount',  12, 2)->default(0);
            $table->decimal('line_total',  12, 2)->default(0);
            $table->boolean('is_service')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
