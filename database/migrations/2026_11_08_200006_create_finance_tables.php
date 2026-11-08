<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts / payment accounts
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->string('account_type')->default('asset'); // asset | liability | equity | revenue | expense
            $table->string('note')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index('business_id');
        });

        // Account ledger
        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('transaction_payment_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->enum('type', ['debit', 'credit']);
            $table->string('sub_type')->nullable(); // purchase | sell | expense | fund_transfer | opening_balance
            $table->date('operation_date');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            $table->index(['business_id', 'account_id']);
            $table->index('operation_date');
        });

        // Notification templates (email/SMS/WhatsApp)
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('type'); // new_sale | payment_received | low_stock | etc.
            $table->string('template_for'); // email | sms | whatsapp
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->boolean('auto_send')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->unique(['business_id', 'type', 'template_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('accounts');
    }
};

