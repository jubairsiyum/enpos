<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Business settings (key/value store per shop)
        Schema::create('business_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('key');
            $table->longText('value')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'key']);
            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Locations (branches / outlets per shop)
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('location_id')->nullable(); // human-readable code e.g. LOC-001
            $table->string('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code', 20)->nullable();
            $table->unsignedBigInteger('invoice_scheme_id')->nullable();
            $table->unsignedBigInteger('invoice_layout_id')->nullable();
            $table->unsignedBigInteger('selling_price_group_id')->nullable();
            $table->boolean('print_receipt_on_invoice')->default(false);
            $table->enum('receipt_printer_type', ['browser', 'printer'])->default('browser');
            $table->unsignedBigInteger('printer_id')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('alternate_number', 20)->nullable();
            $table->string('email')->nullable();
            $table->json('featured_products')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('default_payment_accounts')->nullable();
            $table->string('custom_field1')->nullable();
            $table->string('custom_field2')->nullable();
            $table->string('custom_field3')->nullable();
            $table->string('custom_field4')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
            $table->index('business_id');
        });

        // Invoice schemes (invoice numbering)
        Schema::create('invoice_schemes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->enum('scheme_type', ['sequential', 'date_based'])->default('sequential');
            $table->string('prefix')->nullable();
            $table->unsignedInteger('start_number')->default(1);
            $table->unsignedInteger('total_digits')->default(5);
            $table->string('date_format')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Invoice layouts (print templates)
        Schema::create('invoice_layouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('logo')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('fields')->nullable(); // which fields to show/hide
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Printers
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->enum('type', ['browser', 'thermal'])->default('browser');
            $table->string('connection_type')->nullable(); // usb | network | cloud
            $table->string('ip_address', 45)->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->string('path')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Barcode settings
        Schema::create('barcode_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('name');
            $table->string('barcode_type')->default('C128'); // C128|C39|QRCODE|EAN13 etc.
            $table->string('sticker_size')->nullable();
            $table->decimal('sticker_height', 5, 2)->nullable();
            $table->decimal('sticker_width', 5, 2)->nullable();
            $table->decimal('border_top', 5, 2)->nullable();
            $table->decimal('border_bottom', 5, 2)->nullable();
            $table->decimal('border_left', 5, 2)->nullable();
            $table->decimal('border_right', 5, 2)->nullable();
            $table->unsignedSmallInteger('stickers_in_one_row')->default(1);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });

        // Business themes (per-shop branding)
        Schema::create('business_themes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique();
            $table->string('primary_color', 20)->default('#4F46E5');
            $table->string('secondary_color', 20)->default('#7C3AED');
            $table->string('font_family')->default('Inter');
            $table->string('pos_theme')->default('default');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->foreign('business_id')->references('id')->on('businesses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_themes');
        Schema::dropIfExists('barcode_settings');
        Schema::dropIfExists('printers');
        Schema::dropIfExists('invoice_layouts');
        Schema::dropIfExists('invoice_schemes');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('business_settings');
    }
};

