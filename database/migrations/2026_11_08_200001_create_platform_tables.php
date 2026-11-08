<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->unsignedSmallInteger('max_locations')->default(1);
            $table->unsignedSmallInteger('max_users')->default(5);
            $table->unsignedInteger('max_products')->default(1000);
            $table->json('modules')->nullable(); // allowed optional modules
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        // Businesses (shops)
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('country', 10)->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('zip', 20)->nullable();
            $table->text('address')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_symbol', 10)->default('$');
            $table->enum('currency_position', ['prefix', 'suffix'])->default('prefix');
            $table->decimal('default_sales_tax', 5, 2)->default(0);
            $table->decimal('default_profit_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->unsignedSmallInteger('fy_start_month')->default(1);
            $table->enum('accounting_method', ['fifo', 'lifo', 'avco'])->default('fifo');
            $table->unsignedBigInteger('default_unit_id')->nullable();
            $table->string('logo')->nullable();
            $table->string('custom_domain')->nullable()->unique();
            $table->enum('status', ['active', 'suspended', 'pending', 'cancelled'])->default('active');
            $table->json('enabled_modules')->nullable();
            $table->json('pos_settings')->nullable();
            $table->json('invoice_settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('slug');
            $table->index('status');
        });

        // Subscriptions
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('plan_id');
            $table->enum('status', ['active', 'cancelled', 'expired', 'trial', 'past_due'])->default('trial');
            $table->enum('billing_cycle', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('gateway')->nullable();         // stripe | paddle | manual
            $table->string('gateway_subscription_id')->nullable();
            $table->string('gateway_customer_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('plan_id')->references('id')->on('plans');
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('businesses');
        Schema::dropIfExists('plans');
    }
};

