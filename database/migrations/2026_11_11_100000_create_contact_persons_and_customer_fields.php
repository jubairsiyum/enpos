<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('contact_id', 50)->nullable()->after('type');
            $table->enum('entity_type', ['individual', 'business'])->default('individual')->after('contact_id');
            $table->unsignedBigInteger('created_by')->nullable()->after('business_id');
            $table->decimal('credit_limit', 15, 2)->nullable()->default(null)->change();

            $table->index(['business_id', 'contact_id']);
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('contact_persons', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('contact_id');
            $table->string('prefix', 20)->nullable();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile', 30)->nullable();
            $table->string('alternate_number', 30)->nullable();
            $table->string('family_number', 30)->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->decimal('sales_commission_percentage', 5, 2)->nullable();
            $table->boolean('allow_login')->default(false);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'contact_id']);
        });

        Schema::create('contact_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['contact_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_user');
        Schema::dropIfExists('contact_persons');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropIndex(['business_id', 'contact_id']);
            $table->dropColumn(['contact_id', 'entity_type', 'created_by']);
            $table->decimal('credit_limit', 15, 2)->default(0)->change();
        });
    }
};
