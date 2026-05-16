<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->enum('type', ['allowance', 'bonus', 'overtime', 'deduction', 'tax']);
            $table->string('name', 120);                  // e.g. "Wohngeld", "Weihnachtsgeld"
            $table->decimal('amount', 12, 2);             // always positive; sign comes from type
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index(['payslip_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
    }
};
