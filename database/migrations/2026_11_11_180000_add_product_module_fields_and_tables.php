<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('business_id');
            $table->boolean('is_inactive')->default(false)->after('not_for_selling');
            $table->boolean('enable_sr_no')->default(false)->after('is_inactive');
            $table->string('weight', 100)->nullable()->after('enable_sr_no');
            $table->unsignedInteger('preparation_time_in_minutes')->nullable()->after('weight');
            $table->boolean('woocommerce_disable_sync')->default(false)->after('preparation_time_in_minutes');
            $table->string('product_brochure')->nullable()->after('image');

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'is_inactive']);
            $table->index(['business_id', 'sku']);
        });

        Schema::table('variations', function (Blueprint $table) {
            $table->string('image')->nullable()->after('variation_template_id');
        });

        Schema::create('product_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('location_id');
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('variation_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        Schema::create('variation_value_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('variation_template_id');
            $table->string('name');
            $table->timestamps();

            $table->foreign('variation_template_id')->references('id')->on('variation_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variation_value_templates');
        Schema::dropIfExists('variation_templates');
        Schema::dropIfExists('product_locations');

        Schema::table('variations', function (Blueprint $table) {
            $table->dropColumn('image');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['business_id', 'is_inactive']);
            $table->dropIndex(['business_id', 'sku']);
            $table->dropColumn([
                'created_by',
                'is_inactive',
                'enable_sr_no',
                'weight',
                'preparation_time_in_minutes',
                'woocommerce_disable_sync',
                'product_brochure',
            ]);
        });
    }
};
