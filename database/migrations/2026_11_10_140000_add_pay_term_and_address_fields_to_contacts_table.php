<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Pay term: number of days / months
            $table->unsignedSmallInteger('pay_term_number')->nullable()->after('opening_balance');
            $table->enum('pay_term_type', ['days', 'months'])->nullable()->after('pay_term_number');

            // Extra address fields shown in the add-contact form
            $table->string('landmark')->nullable()->after('zip_code');
            $table->string('street_name')->nullable()->after('landmark');
            $table->string('building_number')->nullable()->after('street_name');
            $table->string('additional_number')->nullable()->after('building_number');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'pay_term_number',
                'pay_term_type',
                'landmark',
                'street_name',
                'building_number',
                'additional_number',
            ]);
        });
    }
};
