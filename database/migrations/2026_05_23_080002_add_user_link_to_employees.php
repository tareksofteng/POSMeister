<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nullable link from employees to a login account. Required for the
 * sales-per-cashier and refund-risk analytics, which need to map a
 * sale's `created_by` (user id) back to an employee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('employee_id')
                  ->constrained('users')->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
