<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('enable_service_staff_pin')->default(false)->after('is_active');
            $table->string('staff_pin', 30)->nullable()->after('enable_service_staff_pin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['enable_service_staff_pin', 'staff_pin']);
        });
    }
};
