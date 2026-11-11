<?php

namespace Tests\Feature\Shop;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Product;
use App\Models\PurchaseLine;
use App\Models\SellLine;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $ownerB;
    private Unit $unitA;
    private Unit $unitB;
    private Brand $brandA;
    private Category $categoryA;
    private Category $subCategoryA;
    private TaxRate $taxA;
    private Location $locationA1;
    private Location $locationA2;
    private Location $locationB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $plan = Plan::create([
            'name'          => 'Enterprise Plan',
            'slug'          => 'enterprise-test',
            'price'         => 99,
            'billing_cycle' => 'monthly',
            'is_active'     => true,
            'features'      => ['users' => 20],
        ]);

        $this->businessA = Business::create([
            'name'                   => 'Awesome Shop',
            'slug'                   => 'awesome-shop',
            'status'                 => 'active',
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
            'email'       => 'owner-a@enpos.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);

        $this->unitA = Unit::create([
            'business_id'   => $this->businessA->id,
            'actual_name'   => 'Pieces',
            'short_name'    => 'Pieces',
            'allow_decimal' => false,
        ]);
        $this->brandA = Brand::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Apple',
        ]);
        $this->categoryA = Category::create([
            'business_id'   => $this->businessA->id,
            'name'          => 'Electronics',
            'category_type' => 'product',
        ]);
        $this->subCategoryA = Category::create([
            'business_id'   => $this->businessA->id,
            'name'          => 'Computers',
            'parent_id'     => $this->categoryA->id,
            'category_type' => 'product',
        ]);
        $this->taxA = TaxRate::create([
            'business_id' => $this->businessA->id,
            'name'        => 'VAT@10%',
            'amount'      => 10.00,
        ]);
        $this->locationA1 = Location::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Awesome Shop',
            'location_id' => 'LOC-1',
            'is_active'   => true,
        ]);
        $this->locationA2 = Location::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Downtown Branch',
            'location_id' => 'LOC-2',
            'is_active'   => true,
        ]);

        $this->businessB = Business::create([
            'name'   => 'Other Shop',
            'slug'   => 'other-shop',
            'status' => 'active',
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
            'email'       => 'owner-b@enpos.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);
        $this->unitB = Unit::create([
            'business_id'   => $this->businessB->id,
            'actual_name'   => 'Boxes',
            'short_name'    => 'Box',
            'allow_decimal' => false,
        ]);
        $this->locationB = Location::create([
            'business_id' => $this->businessB->id,
            'name'        => 'Other Location',
            'location_id' => 'LOC-B1',
            'is_active'   => true,
        ]);
    }

    public function test_add_product_form_options_endpoint_returns_tenant_scoped_metadata(): void
    {
        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/products/add-options');

        $response->assertOk()
            ->assertJsonPath('data.units.0.actual_name', 'Pieces')
            ->assertJsonPath('data.brands.0.name', 'Apple')
            ->assertJsonPath('data.categories.0.name', 'Electronics')
            ->assertJsonPath('data.sub_categories.0.name', 'Computers');

        $unitNames = collect($response->json('data.units'))->pluck('actual_name')->all();
        $this->assertNotContains('Boxes', $unitNames);
    }

    public function test_single_product_can_be_created_with_auto_sku_tax_calculation_and_media(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->post('/api/v1/shop/products/add', [
                'name'                        => 'MacBook Air M3',
                'barcode_type'                => 'C128',
                'unit_id'                     => $this->unitA->id,
                'brand_id'                    => $this->brandA->id,
                'category_id'                 => $this->categoryA->id,
                'sub_category_id'             => $this->subCategoryA->id,
                'location_ids'                => [$this->locationA1->id],
                'enable_stock'                => true,
                'alert_quantity'              => 5,
                'enable_sr_no'                => true,
                'weight'                      => '1.24kg',
                'preparation_time_in_minutes' => 10,
                'woocommerce_disable_sync'    => false,
                'tax_id'                      => $this->taxA->id,
                'tax_type'                    => 'exclusive',
                'type'                        => 'single',
                'default_purchase_price'      => 1000,
                'profit_percent'              => 25,
                'image'                       => UploadedFile::fake()->create('macbook.jpg', 120, 'image/jpeg'),
                'product_brochure'            => UploadedFile::fake()->create('spec.pdf', 120, 'application/pdf'),
            ], ['Accept' => 'application/json']);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'MacBook Air M3')
            ->assertJsonPath('data.sku', 'AS0001')
            ->assertJsonPath('data.category_display', 'Electronics -- Computers')
            ->assertJsonPath('data.dpp_inc_tax', 1100)
            ->assertJsonPath('data.default_sell_price', 1250)
            ->assertJsonPath('data.sell_price_inc_tax', 1375);

        $productId = $response->json('data.id');
        $product = Product::withoutGlobalScope('business')->findOrFail($productId);

        Storage::disk('public')->assertExists($product->image);
        Storage::disk('public')->assertExists($product->product_brochure);
        $this->assertDatabaseHas('product_locations', [
            'product_id'  => $productId,
            'location_id' => $this->locationA1->id,
        ]);
    }

    public function test_variable_and_combo_products_can_be_created_and_listed_with_price_ranges(): void
    {
        $varResponse = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'         => 'Puma Sneaker',
                'sku'          => 'AS0004',
                'unit_id'      => $this->unitA->id,
                'type'         => 'variable',
                'location_ids' => [$this->locationA1->id],
                'variations'   => [
                    [
                        'name'                   => 'Size 41',
                        'default_purchase_price' => 100,
                        'profit_percent'         => 20,
                    ],
                    [
                        'name'                   => 'Size 42',
                        'default_purchase_price' => 150,
                        'profit_percent'         => 20,
                    ],
                ],
            ]);

        $varResponse->assertCreated()
            ->assertJsonPath('data.min_purchase_price', 100)
            ->assertJsonPath('data.max_purchase_price', 150)
            ->assertJsonPath('data.min_sell_price', 120)
            ->assertJsonPath('data.max_sell_price', 180);

        $componentVariationId = $varResponse->json('data.variations.0.id');

        $comboResponse = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'                   => 'Sneaker Bundle',
                'unit_id'                => $this->unitA->id,
                'type'                   => 'combo',
                'default_purchase_price' => 200,
                'profit_percent'         => 25,
                'combo_components'       => [
                    [
                        'component_variation_id' => $componentVariationId,
                        'qty'                    => 2,
                    ],
                ],
            ]);

        $comboResponse->assertCreated()
            ->assertJsonPath('data.type', 'combo')
            ->assertJsonPath('data.variations.0.combo_components.0.component_variation_id', $componentVariationId);
    }

    public function test_opening_stock_updates_variation_location_details_stock_history_and_stock_report(): void
    {
        $createRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'                   => 'Organic Egg',
                'sku'                    => 'AS0065',
                'unit_id'                => $this->unitA->id,
                'location_ids'           => [$this->locationA1->id],
                'default_purchase_price' => 10,
                'default_sell_price'     => 12.5,
            ]);

        $createRes->assertCreated();
        $productId = $createRes->json('data.id');
        $variationId = $createRes->json('data.variations.0.id');

        $saveStock = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/products/{$productId}/opening-stock", [
                'transaction_date' => '2026-11-11',
                'stocks'           => [
                    [
                        'variation_id'         => $variationId,
                        'location_id'          => $this->locationA1->id,
                        'quantity'             => 50,
                        'unit_cost_before_tax' => 10,
                    ],
                ],
            ]);

        $saveStock->assertOk()
            ->assertJsonPath('data.total_stock', 50);

        $historyRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/products/{$productId}/stock-history");

        $historyRes->assertOk()
            ->assertJsonPath('data.current_stock', 50)
            ->assertJsonPath('data.entries.0.type', 'opening_stock')
            ->assertJsonPath('data.entries.0.quantity_change', 50);

        $reportRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/products/stock-report');

        $reportRes->assertOk()
            ->assertJsonPath('data.0.stock_value_by_purchase', 500)
            ->assertJsonPath('data.0.stock_value_by_sale', 625)
            ->assertJsonPath('data.0.potential_profit', 125);
    }

    public function test_product_duplicate_bulk_actions_soft_delete_restore_and_force_delete(): void
    {
        $res = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'                   => 'Banana',
                'sku'                    => 'AS0063',
                'unit_id'                => $this->unitA->id,
                'location_ids'           => [$this->locationA1->id],
                'default_purchase_price' => 10,
                'default_sell_price'     => 12.5,
            ]);

        $productId = $res->json('data.id');

        $dupRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/products/{$productId}/duplicate");

        $dupRes->assertCreated()
            ->assertJsonPath('data.name', 'Banana (Copy)');
        $copyId = $dupRes->json('data.id');

        // Bulk add to location 2
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/bulk-location', [
                'product_ids' => [$productId, $copyId],
                'location_id' => $this->locationA2->id,
                'action'      => 'add',
            ])
            ->assertOk();

        $this->assertDatabaseHas('product_locations', [
            'product_id'  => $productId,
            'location_id' => $this->locationA2->id,
        ]);

        // Bulk deactivate
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/bulk-status', [
                'product_ids' => [$productId],
                'is_inactive' => true,
            ])
            ->assertOk();

        $this->assertDatabaseHas('products', [
            'id'          => $productId,
            'is_inactive' => 1,
        ]);

        // Soft delete, restore, force delete
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/products/{$copyId}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $copyId]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/products/{$copyId}/restore")
            ->assertOk();

        $this->assertDatabaseHas('products', ['id' => $copyId, 'deleted_at' => null]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/products/{$copyId}/force-delete")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $copyId]);
    }

    public function test_quick_unit_and_brand_and_cross_tenant_isolation(): void
    {
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/quick-units', [
                'actual_name'   => 'Packets',
                'short_name'    => 'Packets',
                'allow_decimal' => false,
            ])
            ->assertCreated();

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/quick-brands', [
                'name' => 'Acer',
            ])
            ->assertCreated();

        // Cross-tenant foreign key rejection (Business A cannot use Business B's unit or location)
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'         => 'Illegal Product',
                'unit_id'      => $this->unitB->id,
                'location_ids' => [$this->locationB->id],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_id', 'location_ids.0']);

        $productA = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'    => 'Tenant A Exclusive',
                'unit_id' => $this->unitA->id,
            ])
            ->assertCreated()
            ->json('data.id');

        // Business B cannot read, update, or delete Business A's product
        $this->actingAs($this->ownerB, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessB->id)
            ->getJson("/api/v1/shop/products/{$productA}")
            ->assertNotFound();

        $this->actingAs($this->ownerB, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessB->id)
            ->putJson("/api/v1/shop/products/{$productA}", ['name' => 'Hijacked'])
            ->assertNotFound();

        $this->actingAs($this->ownerB, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessB->id)
            ->deleteJson("/api/v1/shop/products/{$productA}")
            ->assertNotFound();
    }

    public function test_inertia_list_products_and_add_product_pages_render_correctly(): void
    {
        $this->actingAs($this->ownerA)
            ->get('/app/products')
            ->assertOk();

        $this->actingAs($this->ownerA)
            ->get('/app/products/create')
            ->assertOk();
    }

    public function test_force_delete_cleans_up_opening_stock_and_blocks_real_transactions(): void
    {
        $createRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'                   => 'Opening Stock Delete Test',
                'unit_id'                => $this->unitA->id,
                'location_ids'           => [$this->locationA1->id],
                'default_purchase_price' => 100,
                'profit_percent'         => 25,
            ])
            ->assertCreated();

        $productId = (int) $createRes->json('data.id');
        $variationId = (int) $createRes->json('data.variations.0.id');

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/products/{$productId}/opening-stock", [
                'stocks' => [[
                    'variation_id'         => $variationId,
                    'location_id'          => $this->locationA1->id,
                    'quantity'             => 15,
                    'unit_cost_before_tax' => 100,
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('purchase_lines', ['product_id' => $productId]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/products/{$productId}/force-delete")
            ->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertDatabaseMissing('purchase_lines', ['product_id' => $productId]);
        $this->assertDatabaseMissing('transactions', [
            'business_id' => $this->businessA->id,
            'type'        => 'opening_stock',
            'invoice_no'  => "OS-{$productId}-{$this->locationA1->id}",
        ]);

        // Product with a real sale line cannot be permanently deleted (returns 422 cleanly)
        $soldRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'    => 'Sold Product Test',
                'unit_id' => $this->unitA->id,
            ])
            ->assertCreated();

        $soldProductId = (int) $soldRes->json('data.id');
        $soldVariationId = (int) $soldRes->json('data.variations.0.id');

        $sellTx = Transaction::create([
            'business_id'      => $this->businessA->id,
            'location_id'      => $this->locationA1->id,
            'type'             => 'sell',
            'status'           => 'final',
            'payment_status'   => 'paid',
            'invoice_no'       => 'INV-FORCE-1',
            'created_by'       => $this->ownerA->id,
            'transaction_date' => now()->toDateString(),
            'total_amount'     => 100,
        ]);

        SellLine::create([
            'transaction_id'     => $sellTx->id,
            'product_id'         => $soldProductId,
            'variation_id'       => $soldVariationId,
            'quantity'           => 1,
            'unit_price'         => 100,
            'unit_price_inc_tax' => 100,
            'sub_total'          => 100,
        ]);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/products/{$soldProductId}/force-delete")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product']);

        $this->assertDatabaseHas('products', ['id' => $soldProductId]);
    }

    public function test_same_type_product_resubmission_increments_stock_and_blocks_redundancy(): void
    {
        // 1. Initial single product creation with quantity = 5
        $firstRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/add', [
                'name'                   => 'MacBook Air M3',
                'sku'                    => 'MBA-M3-01',
                'unit_id'                => $this->unitA->id,
                'location_ids'           => [$this->locationA1->id],
                'type'                   => 'single',
                'default_purchase_price' => 1000,
                'profit_percent'         => 25,
                'quantity'               => 5,
            ])
            ->assertCreated();

        $productId = (int) $firstRes->json('data.id');
        $this->assertSame(5.0, (float) $firstRes->json('data.current_stock'));

        // 2. Resubmitting the same single product (with same type) does NOT create a new row; it increases stock
        $secondRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/add', [
                'name'                   => '  macbook air m3 ',
                'unit_id'                => $this->unitA->id,
                'location_ids'           => [$this->locationA1->id],
                'type'                   => 'single',
                'default_purchase_price' => 1000,
                'quantity'               => 3,
            ])
            ->assertOk()
            ->assertJsonPath('stock_incremented', true)
            ->assertJsonPath('data.id', $productId);

        $this->assertSame(8.0, (float) $secondRes->json('data.current_stock'));
        $this->assertSame(1, Product::where('business_id', $this->businessA->id)->count());

        // 3. Resubmitting without explicit quantity increments by 1 by default
        $thirdRes = $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/add', [
                'name'    => 'MacBook Air M3',
                'unit_id' => $this->unitA->id,
                'type'    => 'single',
            ])
            ->assertOk();

        $this->assertSame(9.0, (float) $thirdRes->json('data.current_stock'));
        $this->assertSame(1, Product::where('business_id', $this->businessA->id)->count());

        // 4. Resubmitting the same name with a DIFFERENT type is rejected cleanly with 422
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'       => 'MacBook Air M3',
                'unit_id'    => $this->unitA->id,
                'type'       => 'variable',
                'variations' => [
                    ['name' => '8GB', 'default_purchase_price' => 1000],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        // 5. Using the same SKU with a DIFFERENT product name is rejected cleanly with 422
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'    => 'Different Laptop',
                'sku'     => 'MBA-M3-01',
                'unit_id' => $this->unitA->id,
                'type'    => 'single',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sku']);

        // 6. Quick unit & quick brand block duplicates; sub_unit != unit validation works
        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/quick-units', [
                'actual_name' => $this->unitA->actual_name,
                'short_name'  => $this->unitA->short_name,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['actual_name']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products/quick-brands', [
                'name' => strtolower($this->brandA->name),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->actingAs($this->ownerA, 'sanctum')
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/products', [
                'name'        => 'Invalid Sub Unit Product',
                'unit_id'     => $this->unitA->id,
                'sub_unit_id' => $this->unitA->id,
                'variations'  => [
                    ['name' => 'Red', 'default_purchase_price' => 10],
                    ['name' => 'red', 'default_purchase_price' => 12],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['sub_unit_id', 'variations.1.name']);
    }
}
