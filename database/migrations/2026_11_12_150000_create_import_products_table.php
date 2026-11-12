<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_products', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();

            // Exact 37 columns from Import Products specification
            $table->string('product_name');
            $table->string('brand')->nullable();
            $table->string('unit');
            $table->string('category')->nullable();
            $table->string('sub_category')->nullable();
            $table->string('sku')->nullable();
            $table->string('barcode_type', 20)->default('C128');
            $table->boolean('manage_stock')->default(true);
            $table->decimal('alert_quantity', 10, 2)->nullable();
            $table->unsignedInteger('expires_in')->nullable();
            $table->enum('expiry_period_unit', ['days', 'months'])->nullable();
            $table->string('applicable_tax')->nullable();
            $table->enum('selling_price_tax_type', ['inclusive', 'exclusive'])->default('exclusive');
            $table->enum('product_type', ['single', 'variable'])->default('single');
            $table->string('variation_name')->nullable();
            $table->text('variation_values')->nullable();
            $table->text('variation_skus')->nullable();
            $table->text('purchase_price_inc_tax')->nullable();
            $table->text('purchase_price_exc_tax')->nullable();
            $table->decimal('profit_margin', 8, 2)->nullable();
            $table->text('selling_price')->nullable();
            $table->text('opening_stock')->nullable();
            $table->string('opening_stock_location')->nullable();
            $table->string('expiry_date', 30)->nullable();
            $table->boolean('enable_sr_no')->default(false);
            $table->string('weight', 100)->nullable();
            $table->text('rack')->nullable();
            $table->text('row')->nullable();
            $table->text('position')->nullable();
            $table->string('image')->nullable();
            $table->text('product_description')->nullable();
            $table->text('custom_field1')->nullable();
            $table->text('custom_field2')->nullable();
            $table->text('custom_field3')->nullable();
            $table->text('custom_field4')->nullable();
            $table->boolean('not_for_selling')->default(false);
            $table->text('product_locations')->nullable();

            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_products');
    }
};
