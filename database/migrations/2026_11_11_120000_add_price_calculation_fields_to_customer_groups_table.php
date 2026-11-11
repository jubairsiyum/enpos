<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->enum('price_calculation_type', ['percentage', 'selling_price_group'])
                ->default('percentage')
                ->after('name');
            $table->decimal('amount', 5, 2)->nullable()->default(0)->change();
            $table->unsignedBigInteger('selling_price_group_id')->nullable()->after('amount');

            $table->foreign('selling_price_group_id')
                ->references('id')
                ->on('selling_price_groups')
                ->nullOnDelete();
            $table->index(['business_id', 'price_calculation_type']);
        });
    }

    public function down(): void
    {
        Schema::table('customer_groups', function (Blueprint $table) {
            $table->dropForeign(['selling_price_group_id']);
            $table->dropIndex(['business_id', 'price_calculation_type']);
            $table->dropColumn(['price_calculation_type', 'selling_price_group_id']);
            $table->decimal('amount', 5, 2)->default(0)->change();
        });
    }
};
