<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->enum('contact_status', ['active', 'inactive'])
                ->default('active')
                ->after('entity_type');
            $table->index(['business_id', 'contact_status']);
        });

        Schema::create('contact_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('payment_ref_no', 50)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('due_applied', 15, 2)->default(0);
            $table->decimal('advance_added', 15, 2)->default(0);
            $table->string('method', 50)->default('cash');
            $table->date('paid_on');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'contact_id']);
        });

        Schema::create('contact_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('heading');
            $table->text('description')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_name')->nullable();
            $table->boolean('is_private')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['business_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_documents');
        Schema::dropIfExists('contact_payments');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['business_id', 'contact_status']);
            $table->dropColumn('contact_status');
        });
    }
};
