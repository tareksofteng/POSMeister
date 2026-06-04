<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | product_serials
 |--------------------------------------------------------------------------
 |
 | One row per physical device. The serial_number column is globally
 | unique — duplicate IMEIs / serial tags are rejected at the database
 | layer (so a race between two cashiers receiving the same stock can
 | never both win). Branch is denormalised onto the row so single-branch
 | queries (the common case) stay fast without a join through the
 | purchase_items.
 |
 | The "links" to purchase / sale / return rows are nullable foreign keys
 | rather than a polymorphic morph because tooling (CSV exports, BI
 | dashboards) reads these tables directly and benefits from explicit
 | columns.
 |
 | Indexing strategy:
 |   - (product_id, status)       → low-stock + inventory dashboards
 |   - (branch_id, status)        → per-branch serialized stock count
 |   - (warranty_expiry_date)     → "warranty expiring soon" notifier
 |   - (customer_id)              → customer.ownedDevices() tab
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_serials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                  ->constrained('products')
                  ->cascadeOnDelete();
            $table->foreignId('branch_id')
                  ->nullable()
                  ->constrained('branches')
                  ->nullOnDelete();

            // The IMEI / serial / asset tag itself — globally unique.
            $table->string('serial_number', 100)->unique();

            // Origin links (set when this serial entered stock)
            $table->foreignId('purchase_id')->nullable();
            $table->foreignId('purchase_item_id')->nullable();
            $table->foreignId('supplier_id')->nullable();

            // Disposal links (set when this serial left stock)
            $table->foreignId('sale_id')->nullable();
            $table->foreignId('sale_item_id')->nullable();
            $table->foreignId('customer_id')->nullable();

            // Return links (set when serial flowed back to supplier or from customer)
            $table->foreignId('purchase_return_id')->nullable();
            $table->foreignId('sales_return_id')->nullable();

            $table->enum('status', [
                'in_stock',          // sitting in inventory, sellable
                'sold',              // delivered to a customer
                'purchase_returned', // sent back to supplier
                'sales_returned',    // returned by customer (decision to refurbish/dispose left to business)
                'reserved',          // soft-hold during quotation / cart
                'damaged',           // unsellable, accounting write-down
                'lost',              // missing — investigation flag
            ])->default('in_stock');

            $table->date('purchase_date')->nullable();
            $table->date('sale_date')->nullable();

            // Warranty (optional — service tracking comes in a later phase)
            $table->unsignedSmallInteger('warranty_months')->nullable();
            $table->date('warranty_expiry_date')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Hot-path indexes — see file header for rationale.
            $table->index(['product_id', 'status']);
            $table->index(['branch_id',  'status']);
            $table->index('warranty_expiry_date');
            $table->index('customer_id');
            $table->index('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serials');
    }
};
