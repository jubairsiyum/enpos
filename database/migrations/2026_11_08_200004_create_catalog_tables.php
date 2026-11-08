<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Units of measure
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('actual_name');
            $table->string('short_name', 20);
            $table->unsignedTinyInteger('allow_decimal')->default(0);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Categories
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('short_code')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('category_type')->default('product'); // product | expense
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['business_id', 'category_type']);
        });

        // Brands
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Warranties
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->unsignedSmallInteger('duration');
            $table->enum('duration_type', ['days', 'months', 'years']);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Selling price groups
        Schema::create('selling_price_groups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Tax rates
        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->decimal('amount', 5, 2)->default(0);
            $table->boolean('is_tax_group')->default(false);
            $table->json('sub_taxes')->nullable(); // for grouped taxes
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Products
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('type')->default('single'); // single | variable | combo
            $table->string('sku')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            $table->unsignedBigInteger('sub_unit_id')->nullable();
            $table->unsignedSmallInteger('sub_unit_multiplier')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('sub_category_id')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->enum('tax_type', ['inclusive', 'exclusive'])->default('exclusive');
            $table->decimal('default_purchase_price', 15, 4)->default(0);
            $table->decimal('default_sell_price', 15, 4)->default(0);
            $table->decimal('profit_percent', 5, 2)->default(0);
            $table->decimal('alert_quantity', 10, 2)->nullable();
            $table->string('barcode_type', 20)->default('C128');
            $table->boolean('enable_stock')->default(true);
            $table->boolean('not_for_selling')->default(false);
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->unsignedBigInteger('warranty_id')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'type']);
        });

        // Product variations (colour, size, etc.)
        Schema::create('variations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->string('name'); // Default for single, or variant name
            $table->string('sku')->nullable();
            $table->decimal('default_purchase_price', 15, 4)->default(0);
            $table->decimal('dpp_inc_tax', 15, 4)->default(0);
            $table->decimal('profit_percent', 5, 2)->default(0);
            $table->decimal('default_sell_price', 15, 4)->default(0);
            $table->decimal('sell_price_inc_tax', 15, 4)->default(0);
            $table->unsignedBigInteger('variation_value_id')->nullable();
            $table->unsignedBigInteger('variation_template_id')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->index('product_id');
        });

        // Per-location stock for each variation
        Schema::create('variation_location_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('product_id');
            $table->decimal('qty_available', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->unique(['variation_id', 'location_id']);
        });

        // Per-selling-price-group prices per variation
        Schema::create('variation_group_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');
            $table->unsignedBigInteger('price_group_id');
            $table->decimal('price_inc_tax', 15, 4)->default(0);
            $table->timestamps();

            $table->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
            $table->foreign('price_group_id')->references('id')->on('selling_price_groups')->cascadeOnDelete();
            $table->unique(['variation_id', 'price_group_id']);
        });

        // Combo product components
        Schema::create('combo_product_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_id');  // the combo
            $table->unsignedBigInteger('component_variation_id');
            $table->decimal('qty', 10, 4)->default(1);
            $table->timestamps();

            $table->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_product_components');
        Schema::dropIfExists('variation_group_prices');
        Schema::dropIfExists('variation_location_details');
        Schema::dropIfExists('variations');
        Schema::dropIfExists('products');
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('selling_price_groups');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('units');
    }
};

