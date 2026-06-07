<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 |--------------------------------------------------------------------------
 | branch_switch_logs — append-only audit of every branch context switch
 |--------------------------------------------------------------------------
 |
 | One row per accepted BranchContextController::switch call. Rows are
 | never updated or deleted: this is the compliance trail showing who
 | moved between branch workspaces (cashier swap, admin spot-check,
 | manager reconciliation, etc.).
 |
 | Note: from_branch_id can be NULL — first switch after login has no
 | prior branch, and admins can flip from the "All branches" super
 | workspace which has no id at all.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('branch_switch_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('from_branch_id')
                  ->nullable()
                  ->constrained('branches')
                  ->nullOnDelete();
            $table->foreignId('to_branch_id')
                  ->nullable()
                  ->constrained('branches')
                  ->nullOnDelete();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('to_branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_switch_logs');
    }
};
