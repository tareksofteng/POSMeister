<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | product_serial_movements
 |--------------------------------------------------------------------------
 |
 | Append-only audit log. Every transition on `product_serials.status`
 | writes one row here — purchase, sale, return, transfer, reservation,
 | damage, loss. Rows are never updated or deleted, which makes this
 | table both the legal/compliance record and the source for the
 | "device history" timeline shown on customer profiles.
 |
 | reference_type / reference_id is a deliberate polymorphic pair — the
 | originating document (Purchase, Sale, PurchaseReturn, SalesReturn,
 | Transfer) varies and we never need to filter by it without already
 | knowing the type. Index is on serial_id + created_at since the
 | timeline view always starts "give me this serial's events newest
 | first".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_serial_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_serial_id')
                  ->constrained('product_serials')
                  ->cascadeOnDelete();

            $table->enum('movement_type', [
                'purchase',          // entered stock from supplier
                'sale',              // left stock to customer
                'purchase_return',   // returned to supplier
                'sales_return',      // returned from customer
                'transfer',          // moved between branches
                'reserve',           // soft-held for a quotation / cart
                'unreserve',         // hold released
                'damage',            // marked damaged
                'lost',              // marked lost
            ]);

            // Polymorphic link to the document that caused the movement
            // (Purchase, Sale, PurchaseReturn, SalesReturn, Transfer, ...)
            $table->string('reference_type', 80)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->foreignId('from_branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('to_branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Hot-path index: timeline view for a specific serial.
            $table->index(['product_serial_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_serial_movements');
    }
};
