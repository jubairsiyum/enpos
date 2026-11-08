<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cash registers (POS terminals)
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->index(['business_id', 'location_id']);
        });

        // Expense categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_refund')->default(false);
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('parent_id')->references('id')->on('expense_categories')->nullOnDelete();
        });

        // Core transactions table (purchase | sell | expense | stock_adjustment | sell_transfer | purchase_transfer)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->enum('type', [
                'purchase', 'purchase_return',
                'sell', 'sell_return',
                'expense',
                'sell_transfer', 'purchase_transfer',
                'stock_adjustment',
                'opening_stock',
            ]);
            $table->enum('status', ['received', 'pending', 'ordered', 'final', 'draft', 'quotation'])->default('final');
            $table->enum('payment_status', ['paid', 'due', 'partial', 'overdue'])->default('due');
            $table->string('invoice_no')->nullable();
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->unsignedBigInteger('expense_category_id')->nullable();
            $table->unsignedBigInteger('expense_sub_category_id')->nullable();
            $table->unsignedBigInteger('cash_register_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('selling_price_group_id')->nullable();
            $table->date('transaction_date');
            $table->decimal('total_before_tax', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('discount_type_value', 15, 2)->default(0); // amount or %
            $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('change_return', 15, 2)->default(0);
            $table->decimal('shipping_charges', 15, 2)->default(0);
            $table->unsignedBigInteger('shipping_address_id')->nullable();
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('packing_charge', 15, 2)->default(0);
            $table->unsignedBigInteger('transfer_parent_id')->nullable(); // for paired transfers
            $table->boolean('is_suspend')->default(false);
            $table->boolean('is_hold')->default(false);
            $table->boolean('is_quotation')->default(false);
            $table->boolean('is_recurring')->default(false);
            $table->string('recur_interval')->nullable();
            $table->string('recur_interval_type')->nullable();
            $table->unsignedSmallInteger('recur_repetitions')->nullable();
            $table->unsignedBigInteger('recur_parent_id')->nullable();
            $table->text('additional_notes')->nullable();
            $table->text('staff_note')->nullable();
            $table->json('exchange_rate')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations');
            $table->foreign('contact_id')->references('id')->on('contacts')->nullOnDelete();
            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'type', 'status']);
            $table->index(['business_id', 'transaction_date']);
            $table->index('invoice_no');
        });

        // Transaction sell lines
        Schema::create('sell_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('quantity_returned', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->decimal('unit_price_inc_tax', 15, 4)->default(0);
            $table->decimal('unit_price_before_discount', 15, 4)->default(0);
            $table->decimal('line_discount_amount', 15, 4)->default(0);
            $table->enum('line_discount_type', ['fixed', 'percentage'])->default('fixed');
            $table->decimal('item_tax', 15, 4)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->unsignedBigInteger('selling_price_group_id')->nullable();
            $table->unsignedBigInteger('warranty_id')->nullable();
            $table->decimal('sub_total', 15, 4)->default(0);
            $table->unsignedBigInteger('parent_sell_line_id')->nullable(); // for combo
            $table->json('modifiers')->nullable(); // for combo modifiers
            $table->text('lot_no_line_id')->nullable(); // FIFO lot tracking
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('variations');
            $table->index('transaction_id');
        });

        // Transaction purchase lines
        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('variation_id');
            $table->decimal('quantity', 15, 4)->default(1);
            $table->decimal('quantity_adjusted', 15, 4)->default(0);
            $table->decimal('quantity_returned', 15, 4)->default(0);
            $table->decimal('quantity_sold', 15, 4)->default(0);
            $table->decimal('purchase_price', 15, 4)->default(0);
            $table->decimal('purchase_price_inc_tax', 15, 4)->default(0);
            $table->decimal('item_tax', 15, 4)->default(0);
            $table->unsignedBigInteger('tax_id')->nullable();
            $table->decimal('sub_total', 15, 4)->default(0);
            $table->string('lot_number')->nullable();
            $table->date('exp_date')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('variation_id')->references('id')->on('variations');
            $table->index('transaction_id');
        });

        // Transaction payment lines
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('method')->default('cash'); // cash | card | bank_transfer | cheque | ...
            $table->string('card_type')->nullable();
            $table->string('card_number', 20)->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('card_month', 2)->nullable();
            $table->string('card_year', 4)->nullable();
            $table->decimal('card_transaction_number')->nullable();
            $table->string('card_security')->nullable();
            $table->string('cheque_number')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->decimal('transaction_no')->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->decimal('change_return', 15, 2)->default(0);
            $table->boolean('is_return')->default(false);
            $table->unsignedBigInteger('business_id');
            $table->date('paid_on');
            $table->timestamps();

            $table->foreign('transaction_id')->references('id')->on('transactions')->cascadeOnDelete();
            $table->index('transaction_id');
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('sell_lines');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('cash_registers');
    }
};

