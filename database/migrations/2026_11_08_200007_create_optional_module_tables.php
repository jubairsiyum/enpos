<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ----------------------------------------------------------------
        // HRM module
        // ----------------------------------------------------------------
        Schema::create('hrm_departments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('hrm_designations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('name');
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('department_id')->references('id')->on('hrm_departments')->nullOnDelete();
        });

        Schema::create('hrm_employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->string('employee_id')->nullable();
            $table->date('date_of_joining')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->decimal('salary', 15, 2)->default(0);
            $table->enum('salary_type', ['monthly', 'daily', 'hourly'])->default('monthly');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('hrm_attendances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('employee_id');
            $table->date('clock_in_date');
            $table->time('clock_in_time')->nullable();
            $table->time('clock_out_time')->nullable();
            $table->decimal('total_hours', 5, 2)->nullable();
            $table->enum('status', ['present', 'absent', 'leave', 'half_day'])->default('present');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('employee_id')->references('id')->on('hrm_employees')->cascadeOnDelete();
        });

        Schema::create('hrm_leave_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->unsignedSmallInteger('total_leave_count')->default(0);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('hrm_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('leave_type_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('hrm_payrolls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('payment_account_id')->nullable();
            $table->date('payment_date');
            $table->decimal('gross_salary', 15, 2)->default(0);
            $table->decimal('total_deduction', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->json('allowances')->nullable();
            $table->json('deductions')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // ----------------------------------------------------------------
        // Essentials module
        // ----------------------------------------------------------------
        Schema::create('ess_todos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('ess_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('uploaded_by');
            $table->string('title');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('ess_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->datetime('remind_at');
            $table->boolean('is_sent')->default(false);
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // ----------------------------------------------------------------
        // Cheque module
        // ----------------------------------------------------------------
        Schema::create('cheques', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->enum('type', ['received', 'issued'])->default('received');
            $table->string('cheque_number');
            $table->string('bank_name')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->date('cheque_date');
            $table->enum('status', ['pending', 'cleared', 'bounced', 'returned'])->default('pending');
            $table->date('clearing_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'status']);
        });

        // ----------------------------------------------------------------
        // Woocommerce module
        // ----------------------------------------------------------------
        Schema::create('woo_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            $table->string('store_url');
            $table->string('consumer_key')->nullable();
            $table->string('consumer_secret')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('sync_settings')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('woo_product_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('variation_id');
            $table->unsignedBigInteger('woo_product_id');
            $table->unsignedBigInteger('woo_variation_id')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('woo_order_maps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('woo_order_id');
            $table->string('woo_order_status')->nullable();
            $table->timestamps();
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('woo_order_maps');
        Schema::dropIfExists('woo_product_maps');
        Schema::dropIfExists('woo_settings');
        Schema::dropIfExists('cheques');
        Schema::dropIfExists('ess_reminders');
        Schema::dropIfExists('ess_documents');
        Schema::dropIfExists('ess_todos');
        Schema::dropIfExists('hrm_payrolls');
        Schema::dropIfExists('hrm_leaves');
        Schema::dropIfExists('hrm_leave_types');
        Schema::dropIfExists('hrm_attendances');
        Schema::dropIfExists('hrm_employees');
        Schema::dropIfExists('hrm_designations');
        Schema::dropIfExists('hrm_departments');
    }
};

