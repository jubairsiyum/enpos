<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer groups (for pricing tiers)
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->decimal('amount', 5, 2)->default(0); // discount %
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index('business_id');
        });

        // Contacts (customers + suppliers + both)
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->enum('type', ['customer', 'supplier', 'both'])->default('customer');
            $table->string('name');
            $table->string('supplier_business_name')->nullable();
            $table->string('prefix', 10)->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('landline', 20)->nullable();
            $table->string('alternate_number', 20)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->string('dob')->nullable();
            $table->unsignedBigInteger('customer_group_id')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0); // running balance
            $table->decimal('total_purchase', 15, 2)->default(0);
            $table->decimal('total_purchase_paid', 15, 2)->default(0);
            $table->decimal('total_purchase_return', 15, 2)->default(0);
            $table->decimal('total_purchase_return_paid', 15, 2)->default(0);
            $table->decimal('total_sell', 15, 2)->default(0);
            $table->decimal('total_sell_paid', 15, 2)->default(0);
            $table->decimal('total_sell_return', 15, 2)->default(0);
            $table->decimal('total_sell_return_paid', 15, 2)->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_exempt_tax')->default(false);
            $table->string('tax_number')->nullable();
            $table->string('position')->nullable();
            $table->string('website')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_state')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_zip_code', 20)->nullable();
            $table->text('custom_field1')->nullable();
            $table->text('custom_field2')->nullable();
            $table->text('custom_field3')->nullable();
            $table->text('custom_field4')->nullable();
            $table->text('custom_field5')->nullable();
            $table->text('custom_field6')->nullable();
            $table->text('custom_field7')->nullable();
            $table->text('custom_field8')->nullable();
            $table->text('custom_field9')->nullable();
            $table->text('custom_field10')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->index('business_id');
            $table->index(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('customer_groups');
    }
};

