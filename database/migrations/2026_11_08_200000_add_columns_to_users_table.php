<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('type')->default('staff')->after('id'); // super_admin|admin|owner|staff
            $table->unsignedBigInteger('business_id')->nullable()->after('type');
            $table->boolean('is_active')->default(true)->after('email_verified_at');
            $table->string('phone', 20)->nullable()->after('is_active');
            $table->string('avatar')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('theme')->default('default');
            $table->boolean('dark_mode')->default(false);
            $table->enum('commission_type', ['fixed', 'percentage'])->nullable();
            $table->decimal('commission_rate', 10, 4)->nullable();
            $table->decimal('max_sales_discount', 5, 2)->nullable();
            $table->boolean('allow_login')->default(true);
            $table->json('selected_contacts')->nullable();
            $table->unsignedBigInteger('crm_contact_id')->nullable();
            $table->softDeletes();

            $table->index('business_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'type','business_id','is_active','phone','avatar','language',
                'theme','dark_mode','commission_type','commission_rate',
                'max_sales_discount','allow_login','selected_contacts',
                'crm_contact_id','deleted_at',
            ]);
        });
    }
};

