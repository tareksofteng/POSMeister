<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id', 20)->unique();

            // Personal
            $table->string('first_name', 80);
            $table->string('last_name', 80);
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('emergency_contact', 120)->nullable();
            $table->enum('gender', ['male', 'female', 'other']);
            $table->date('date_of_birth')->nullable();
            $table->enum('blood_group', ['A+','A-','B+','B-','AB+','AB-','O+','O-'])->nullable();
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable();
            $table->string('nationality', 80)->nullable();
            $table->string('religion', 50)->nullable();

            // Address
            $table->string('address', 255)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('country', 80)->nullable()->default('Deutschland');

            // Employment
            $table->date('joining_date');
            $table->enum('employment_type', ['full_time', 'part_time', 'contract', 'intern'])
                  ->default('full_time');
            $table->foreignId('designation_id')->nullable()
                  ->constrained('designations')->nullOnDelete();
            $table->foreignId('department_id')->nullable()
                  ->constrained('departments')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('shift_id')->nullable()
                  ->constrained('shifts')->nullOnDelete();
            $table->decimal('basic_salary', 12, 2)->default(0);

            // Documents
            $table->string('photo', 255)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('passport_number', 50)->nullable();
            $table->string('work_permit_no', 50)->nullable();

            // Status
            $table->enum('status', ['active', 'inactive', 'terminated', 'resigned'])
                  ->default('active');
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
