<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id');
            $table->string('product_name');
            $table->string('sku');
            $table->string('variation_name');
            $table->string('variation_sku')->nullable();
            $table->decimal('old_sell_price', 15, 4)->default(0);
            $table->decimal('new_sell_price', 15, 4)->default(0);
            $table->decimal('old_sell_price_inc_tax', 15, 4)->default(0);
            $table->decimal('new_sell_price_inc_tax', 15, 4)->default(0);
            $table->json('group_prices_snapshot')->nullable();
            $table->string('source', 20)->default('import');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('variation_id')->references('id')->on('variations')->cascadeOnDelete();
            $table->index(['business_id', 'product_id']);
        });

        Schema::create('print_label_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('barcode_setting_id')->nullable();
            $table->json('items');
            $table->json('display_options');
            $table->unsignedInteger('total_labels')->default(0);
            $table->string('output_format', 20)->default('preview');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('barcode_setting_id')->references('id')->on('barcode_settings')->nullOnDelete();
            $table->index(['business_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_label_jobs');
        Schema::dropIfExists('product_price_updates');
    }
};
