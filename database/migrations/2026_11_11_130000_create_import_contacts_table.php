<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('imported_by')->nullable();
            $table->unsignedBigInteger('contact_record_id')->nullable();

            // Exact 27 columns from Import Contacts specification
            $table->unsignedTinyInteger('contact_type'); // 1 = Customer, 2 = Supplier, 3 = Both
            $table->string('prefix', 20)->nullable();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('contact_id', 50)->nullable();
            $table->string('tax_number', 100)->nullable();
            $table->decimal('opening_balance', 15, 2)->nullable();
            $table->unsignedSmallInteger('pay_term')->nullable();
            $table->enum('pay_term_period', ['days', 'months'])->nullable();
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 20);
            $table->string('alternate_number', 20)->nullable();
            $table->string('landline', 20)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->text('address_line_1')->nullable();
            $table->text('address_line_2')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->date('dob')->nullable();
            $table->text('custom_field1')->nullable();
            $table->text('custom_field2')->nullable();
            $table->text('custom_field3')->nullable();
            $table->text('custom_field4')->nullable();

            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('imported_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('contact_record_id')->references('id')->on('contacts')->nullOnDelete();
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_contacts');
    }
};
