<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('email');
            $table->string('prefix')->nullable()->after('username');
            $table->string('first_name')->nullable()->after('prefix');
            $table->string('last_name')->nullable()->after('first_name');
            $table->boolean('is_cmmsn_agent')->default(false)->after('max_sales_discount');
            $table->json('access_locations')->nullable()->after('is_cmmsn_agent');

            // More Information
            $table->date('dob')->nullable();
            $table->string('gender', 20)->nullable();
            $table->string('marital_status', 30)->nullable();
            $table->string('blood_group', 10)->nullable();
            $table->string('alt_number', 30)->nullable();
            $table->string('family_number', 30)->nullable();
            $table->string('fb_link')->nullable();
            $table->string('twitter_link')->nullable();
            $table->string('social_media_1')->nullable();
            $table->string('social_media_2')->nullable();
            $table->string('custom_field_1')->nullable();
            $table->string('custom_field_2')->nullable();
            $table->string('custom_field_3')->nullable();
            $table->string('custom_field_4')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('id_proof_name')->nullable();
            $table->string('id_proof_number')->nullable();
            $table->text('permanent_address')->nullable();
            $table->text('current_address')->nullable();

            // Bank details & HRM/payroll metadata
            $table->json('bank_details')->nullable();
            $table->json('hrm_details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username',
                'prefix',
                'first_name',
                'last_name',
                'is_cmmsn_agent',
                'access_locations',
                'dob',
                'gender',
                'marital_status',
                'blood_group',
                'alt_number',
                'family_number',
                'fb_link',
                'twitter_link',
                'social_media_1',
                'social_media_2',
                'custom_field_1',
                'custom_field_2',
                'custom_field_3',
                'custom_field_4',
                'guardian_name',
                'id_proof_name',
                'id_proof_number',
                'permanent_address',
                'current_address',
                'bank_details',
                'hrm_details',
            ]);
        });
    }
};
