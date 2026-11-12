<?php

namespace Tests\Feature\Shop;

use App\Models\BarcodeSetting;
use App\Models\Business;
use App\Models\Plan;
use App\Models\Product;
use App\Models\SellingPriceGroup;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Models\Variation;
use App\Models\VariationGroupPrice;
use App\Models\VariationTemplate;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductToolsTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $ownerB;
    private Product $productA;
    private Variation $variationA;
    private SellingPriceGroup $wholesaleGroupA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $plan = Plan::create([
            'name'          => 'Enterprise Plan',
            'slug'          => 'enterprise-tools-test',
            'price'         => 99,
            'billing_cycle' => 'monthly',
            'is_active'     => true,
            'features'      => ['users' => 20],
        ]);

        $this->businessA = Business::create([
            'name'                   => 'Awesome Shop',
            'slug'                   => 'awesome-shop-tools',
            'status'                 => 'active',
            'currency_symbol'        => '$',
            'default_profit_percent' => 25,
        ]);
        Subscription::create([
            'business_id'   => $this->businessA->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'starts_at'     => now(),
            'ends_at'       => now()->addYear(),
        ]);
        $this->ownerA = User::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Owner A',
            'email'       => 'owner-tools-a@enpos.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);

        $this->businessB = Business::create([
            'name'            => 'Other Shop',
            'slug'            => 'other-shop-tools',
            'status'          => 'active',
            'currency_symbol' => '$',
        ]);
        Subscription::create([
            'business_id'   => $this->businessB->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'starts_at'     => now(),
            'ends_at'       => now()->addYear(),
        ]);
        $this->ownerB = User::create([
            'business_id' => $this->businessB->id,
            'name'        => 'Owner B',
            'email'       => 'owner-tools-b@enpos.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);

        $unitA = Unit::create([
            'business_id'   => $this->businessA->id,
            'actual_name'   => 'Pieces',
            'short_name'    => 'Pc',
            'allow_decimal' => false,
        ]);
        $taxA = TaxRate::create([
            'business_id' => $this->businessA->id,
            'name'        => 'VAT@10%',
            'amount'      => 10.00,
        ]);
        $this->wholesaleGroupA = SellingPriceGroup::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Wholesale',
            'is_active'   => true,
        ]);

        $this->productA = Product::create([
            'business_id'            => $this->businessA->id,
            'created_by'             => $this->ownerA->id,
            'name'                   => 'Classic T-Shirt',
            'type'                   => 'single',
            'sku'                    => 'AS0001',
            'unit_id'                => $unitA->id,
            'tax_id'                 => $taxA->id,
            'tax_type'               => 'exclusive',
            'default_purchase_price' => 100.00,
            'default_sell_price'     => 125.00,
            'profit_percent'         => 25.00,
            'barcode_type'           => 'C128',
            'enable_stock'           => true,
        ]);

        $this->variationA = Variation::create([
            'product_id'             => $this->productA->id,
            'name'                   => 'DUMMY',
            'sku'                    => 'AS0001',
            'default_purchase_price' => 100.00,
            'dpp_inc_tax'            => 110.00,
            'profit_percent'         => 25.00,
            'default_sell_price'     => 125.00,
            'sell_price_inc_tax'     => 137.50,
        ]);
    }

    public function test_update_price_export_and_import_roundtrip_updates_prices_and_groups(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        $indexResp = $this->getJson('/api/v1/shop/products/update-price');
        $indexResp->assertOk()
            ->assertJsonPath('data.expected_headers.0', 'Product')
            ->assertJsonPath('data.expected_headers.5', 'Wholesale');

        $exportResp = $this->get('/api/v1/shop/products/update-price/export');
        $exportResp->assertOk();
        $xlsBytes = $exportResp->streamedContent();

        $codec = app(ExcelWorkbookCodec::class);
        $this->assertTrue($codec->isBinaryXls($xlsBytes));
        $parsed = $codec->parseBinaryXls($xlsBytes);
        $this->assertSame('Classic T-Shirt', $parsed[1][0]);

        $updatedXls = $codec->buildXls([
            $parsed[0],
            ['Classic T-Shirt', 'AS0001', 'DUMMY', 'AS0001', '220.00', '198.00'],
        ], 'ProductPrices');

        $upload = UploadedFile::fake()->createWithContent('updated_prices.xls', $updatedXls);

        $importResp = $this->postJson('/api/v1/shop/products/update-price/import', [
            'file' => $upload,
        ]);

        $importResp->assertOk()
            ->assertJsonPath('updated_count', 1);

        $this->variationA->refresh();
        $this->productA->refresh();

        $this->assertEquals(220.0, (float) $this->variationA->sell_price_inc_tax);
        $this->assertEquals(200.0, (float) $this->variationA->default_sell_price);
        $this->assertEquals(200.0, (float) $this->productA->default_sell_price);

        $groupPrice = VariationGroupPrice::where('variation_id', $this->variationA->id)
            ->where('price_group_id', $this->wholesaleGroupA->id)
            ->first();
        $this->assertNotNull($groupPrice);
        $this->assertEquals(198.0, (float) $groupPrice->price_inc_tax);
    }

    public function test_update_price_import_rejects_modified_headers_or_sku(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        $badCsv = "Wrong Header,SKU,Variation,Variation SKU,Selling Price (Inc. Tax),Wholesale\n"
            . "Classic T-Shirt,AS0001,DUMMY,AS0001,150,140\n";
        $file = UploadedFile::fake()->createWithContent('bad_headers.csv', $badCsv);

        $this->postJson('/api/v1/shop/products/update-price/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $badSkuCsv = "Product,SKU,Variation,Variation SKU,Selling Price (Inc. Tax),Wholesale\n"
            . "Classic T-Shirt,WRONG-SKU,DUMMY,WRONG-SKU,150,140\n";
        $file2 = UploadedFile::fake()->createWithContent('bad_sku.csv', $badSkuCsv);

        $this->postJson('/api/v1/shop/products/update-price/import', ['file' => $file2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_print_labels_endpoints_search_preview_and_pdf(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        VariationGroupPrice::create([
            'variation_id'   => $this->variationA->id,
            'price_group_id' => $this->wholesaleGroupA->id,
            'price_inc_tax'  => 110.00,
        ]);

        $indexResp = $this->getJson('/api/v1/shop/products/print-labels');
        $indexResp->assertOk();
        $this->assertCount(6, $indexResp->json('data.barcode_settings'));

        $searchResp = $this->getJson('/api/v1/shop/products/print-labels/search?search=Classic');
        $searchResp->assertOk()
            ->assertJsonPath('data.0.variation_id', $this->variationA->id);

        $settingId = BarcodeSetting::where('business_id', $this->businessA->id)->firstOrFail()->id;

        $previewResp = $this->postJson('/api/v1/shop/products/print-labels/preview', [
            'barcode_setting_id' => $settingId,
            'items'              => [
                [
                    'product_id'             => $this->productA->id,
                    'variation_id'           => $this->variationA->id,
                    'quantity'               => 3,
                    'packing_date'           => '2026-11-12',
                    'selling_price_group_id' => $this->wholesaleGroupA->id,
                ],
            ],
            'display'            => [
                'show_product_name'  => true,
                'product_name_size'  => 15,
                'show_variation'     => true,
                'variation_size'     => 17,
                'show_price'         => true,
                'price_size'         => 17,
                'price_type'         => 'exclusive',
                'show_business_name' => true,
                'business_name_size' => 20,
                'show_packing_date'  => true,
                'packing_date_size'  => 12,
            ],
        ]);

        $previewResp->assertOk()
            ->assertJsonPath('data.total_labels', 3)
            ->assertJsonPath('data.labels.0.formatted_price', '$100.00')
            ->assertJsonPath('data.labels.0.business_name', 'Awesome Shop');

        $pdfResp = $this->post('/api/v1/shop/products/print-labels/preview-pdf', [
            'barcode_setting_id' => $settingId,
            'items'              => [
                [
                    'variation_id' => $this->variationA->id,
                    'quantity'     => 2,
                ],
            ],
        ], ['Accept' => 'application/json']);

        $pdfResp->assertOk();
        $pdfContent = $pdfResp->streamedContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdfContent);
    }

    public function test_variations_crud_validation_and_in_use_guard(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        $createResp = $this->postJson('/api/v1/shop/variations', [
            'name'   => 'Size (Tshirts)',
            'values' => ['S', 'M', 'L', 'XL'],
        ]);

        $createResp->assertCreated()
            ->assertJsonPath('data.name', 'Size (Tshirts)')
            ->assertJsonPath('data.values_text', 'S, M, L, XL')
            ->assertJsonPath('data.can_delete', true);

        $templateId = (int) $createResp->json('data.id');

        // Duplicate name rejected
        $this->postJson('/api/v1/shop/variations', [
            'name'   => 'size (tshirts)',
            'values' => ['XXL'],
        ])->assertStatus(422)->assertJsonValidationErrors(['name']);

        // List & search
        $this->getJson('/api/v1/shop/variations?search=XL')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Update values
        $this->putJson("/api/v1/shop/variations/{$templateId}", [
            'name'   => 'Apparel Size',
            'values' => ['S', 'M', 'L', 'XL', 'XXL'],
        ])->assertOk()
            ->assertJsonPath('data.values_text', 'S, M, L, XL, XXL');

        // Tenant isolation: Business B cannot see Business A variation
        $this->actingAs($this->ownerB, 'sanctum');
        $this->getJson("/api/v1/shop/variations/{$templateId}")->assertNotFound();

        // Guard deletion when in use by a product variation
        $this->actingAs($this->ownerA, 'sanctum');
        $template = VariationTemplate::withoutGlobalScope('business')->findOrFail($templateId);
        $firstValueId = $template->values()->firstOrFail()->id;

        $this->variationA->update([
            'variation_template_id' => $templateId,
            'variation_value_id'    => $firstValueId,
        ]);

        $this->deleteJson("/api/v1/shop/variations/{$templateId}")
            ->assertStatus(422);

        // Detach and delete cleanly
        $this->variationA->update([
            'variation_template_id' => null,
            'variation_value_id'    => null,
        ]);

        $this->deleteJson("/api/v1/shop/variations/{$templateId}")
            ->assertOk();
    }

    public function test_barcode_settings_crud_and_variations_export(): void
    {
        $this->actingAs($this->ownerA, 'sanctum');

        $createSetting = $this->postJson('/api/v1/shop/products/print-labels/barcode-settings', [
            'name'                => 'Custom Roll 50x25',
            'barcode_type'        => 'C128',
            'sticker_size'        => '50mm x 25mm',
            'sticker_width'       => 50,
            'sticker_height'      => 25,
            'stickers_in_one_row' => 1,
            'is_default'          => true,
        ]);

        $createSetting->assertCreated()
            ->assertJsonPath('data.name', 'Custom Roll 50x25')
            ->assertJsonPath('data.is_default', true);

        $settingId = (int) $createSetting->json('data.id');

        $this->putJson("/api/v1/shop/products/print-labels/barcode-settings/{$settingId}", [
            'name' => 'Custom Roll 50x30',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Custom Roll 50x30');

        $this->deleteJson("/api/v1/shop/products/print-labels/barcode-settings/{$settingId}")
            ->assertOk();

        $this->postJson('/api/v1/shop/variations', [
            'name'   => 'Color',
            'values' => ['Black', 'Blue'],
        ])->assertCreated();

        $exportCsv = $this->get('/api/v1/shop/variations/export?format=csv');
        $exportCsv->assertOk();
        $this->assertStringContainsString('Black, Blue', $exportCsv->streamedContent());
    }

    public function test_inertia_web_pages_render_for_update_price_print_labels_and_variations(): void
    {
        $this->actingAs($this->ownerA);

        $this->get('/app/products/update-price')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shop/Products/UpdatePrice/Index'));

        $this->get('/app/products/print-labels')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shop/Products/PrintLabels/Index'));

        $this->get('/app/variations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Shop/Products/Variations/Index'));
    }
}
