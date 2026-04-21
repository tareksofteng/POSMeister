<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('purchase_number', 30)->unique();  // EK-2026-00001
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->date('purchase_date');
            $table->enum('status', ['draft', 'received'])->default('draft');
            $table->string('reference', 100)->nullable();     // supplier invoice / delivery note number
            $table->text('notes')->nullable();

            // Monetary totals
            $table->decimal('subtotal', 12, 2)->default(0);         // Σ qty × unit_cost
            $table->decimal('discount_amount', 12, 2)->default(0);  // header-level discount
            $table->decimal('vat_amount', 12, 2)->default(0);       // Σ per-item VAT
            $table->decimal('freight_amount', 12, 2)->default(0);   // Frachtkosten
            $table->decimal('total_amount', 12, 2)->default(0);     // subtotal + vat - discount + freight

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
