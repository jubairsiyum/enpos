<?php

namespace Tests\Feature\Shop;

use App\Models\Brand;
use App\Models\Business;
use App\Models\Category;
use App\Models\ImportProduct;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\User;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportProductTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $ownerB;
    private Unit $unitA;
    private Location $locationA;
    private TaxRate $taxA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $plan = Plan::create([
            'name'          => 'Pro Plan',
            'slug'          => 'pro',
            'price'         => 99,
            'billing_cycle' => 'monthly',
            'is_active'     => true,
            'features'      => ['users' => 10],
        ]);

        $this->businessA = Business::create([
            'name'   => 'Shop A',
            'slug'   => 'shop-a',
            'status' => 'active',
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
            'email'       => 'owner-a@example.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);
        $this->unitA = Unit::create([
            'business_id'   => $this->businessA->id,
            'actual_name'   => 'Pieces',
            'short_name'    => 'Pc(s)',
            'allow_decimal' => 0,
        ]);
        $this->locationA = Location::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Main Store',
            'is_active'   => true,
        ]);
        $this->taxA = TaxRate::create([
            'business_id' => $this->businessA->id,
            'name'        => 'VAT 10%',
            'amount'      => 10.00,
        ]);

        $this->businessB = Business::create([
            'name'   => 'Shop B',
            'slug'   => 'shop-b',
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
            'email'       => 'owner-b@example.test',
            'password'    => 'password',
            'type'        => 'owner',
            'is_active'   => true,
        ]);
    }

    public function test_import_products_page_renders_with_37_columns(): void
    {
        $response = $this->actingAs($this->ownerA)->get('/app/products/import');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Shop/Products/ImportProducts/Index')
            ->has('columns', 37)
        );
    }

    public function test_template_download_returns_binary_xls_with_37_headers(): void
    {
        $response = $this->actingAs($this->ownerA)->get('/api/v1/shop/products/import/template');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.ms-excel');

        $binary = $response->streamedContent();
        $codec = app(ExcelWorkbookCodec::class);
        $this->assertTrue($codec->isBinaryXls($binary));

        $rows = $codec->parseBinaryXls($binary);
        $this->assertCount(1, $rows);
        $this->assertCount(37, $rows[0]);
        $this->assertSame('Product Name', $rows[0][0]);
        $this->assertSame('Product locations', $rows[0][36]);
    }

    public function test_can_import_single_and_variable_products_via_api_csv(): void
    {
        $headers = array_map(fn (array $c): string => $c['name'], ImportProduct::TEMPLATE_COLUMNS);

        $singleRow = [
            'Classic Cotton Tee', // 1 Product Name
            'AcmeWear',           // 2 Brand (auto-created)
            'Pieces',             // 3 Unit
            'Apparel',            // 4 Category (auto-created)
            'T-Shirts',           // 5 Sub category (auto-created)
            'IMP-TEE-001',        // 6 SKU
            'EAN-13',             // 7 Barcode Type
            '1',                  // 8 Manage Stock?
            '5',                  // 9 Alert quantity
            '12',                 // 10 Expires in
            'months',             // 11 Expiry Period Unit
            'VAT 10%',            // 12 Applicable Tax
            'exclusive',          // 13 Selling Price Tax Type
            'single',             // 14 Product Type
            '',                   // 15 Variation Name
            '',                   // 16 Variation Values
            '',                   // 17 Variation SKUs
            '110',                // 18 Purchase Price (Including Tax)
            '100',                // 19 Purchase Price (Excluding Tax)
            '25',                 // 20 Profit Margin %
            '125',                // 21 Selling Price
            '50',                 // 22 Opening Stock
            'Main Store',         // 23 Opening stock location
            '11-25-2027',         // 24 Expiry Date
            '0',                  // 25 Enable sr no
            '200g',               // 26 Weight
            'R1',                 // 27 Rack
            'ROW1',               // 28 Row
            'POS1',               // 29 Position
            '',                   // 30 Image
            'Soft cotton tee',    // 31 Product Description
            'Cotton',             // 32 Custom Field1
            '', '', '',           // 33-35
            '0',                  // 36 Not for selling
            'Main Store',         // 37 Product locations
        ];

        $variableRow = [
            'Running Sneakers',   // 1 Product Name
            'StridePro',          // 2 Brand
            'Pc(s)',              // 3 Unit (by short_name)
            'Footwear',           // 4 Category
            '',                   // 5 Sub category
            '',                   // 6 SKU (auto-generated)
            'C128',               // 7 Barcode Type
            '1',                  // 8 Manage Stock?
            '3',                  // 9 Alert quantity
            '', '', '',           // 10-12
            'inclusive',          // 13 Selling Price Tax Type
            'variable',           // 14 Product Type
            'Size',               // 15 Variation Name
            '40|41|42',           // 16 Variation Values
            'SNK-40|SNK-41|SNK-42', // 17 Variation SKUs
            '50|52|55',           // 18 Purchase Price (Including Tax)
            '50|52|55',           // 19 Purchase Price (Excluding Tax)
            '20',                 // 20 Profit Margin %
            '65|68|72',           // 21 Selling Price
            '10|15|20',           // 22 Opening Stock
            'Main Store',         // 23 Opening stock location
            '', '', '', '', '', '', '', '', '', '', '', '', '0', 'Main Store',
        ];

        $csvLines = [
            $this->toCsvLine($headers),
            $this->toCsvLine($singleRow),
            $this->toCsvLine($variableRow),
        ];

        $file = UploadedFile::fake()->createWithContent('import_products.csv', implode("\n", $csvLines));

        $response = $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/shop/products/import', ['file' => $file]);

        $response->assertCreated()
            ->assertJsonPath('imported_count', 2);

        $this->assertDatabaseHas('products', [
            'business_id' => $this->businessA->id,
            'name'        => 'Classic Cotton Tee',
            'sku'         => 'IMP-TEE-001',
            'barcode_type' => 'EAN13',
            'type'        => 'single',
        ]);

        $this->assertDatabaseHas('brands', [
            'business_id' => $this->businessA->id,
            'name'        => 'AcmeWear',
        ]);

        $parentCat = Category::where('business_id', $this->businessA->id)->where('name', 'Apparel')->first();
        $this->assertNotNull($parentCat);
        $this->assertDatabaseHas('categories', [
            'business_id' => $this->businessA->id,
            'name'        => 'T-Shirts',
            'parent_id'   => $parentCat->id,
        ]);

        $variableProduct = Product::where('business_id', $this->businessA->id)
            ->where('name', 'Running Sneakers')
            ->with(['variations', 'variationLocationDetails'])
            ->first();
        $this->assertNotNull($variableProduct);
        $this->assertCount(3, $variableProduct->variations);
        $this->assertSame(45.0, (float) $variableProduct->variationLocationDetails->sum('qty_available'));

        // Tenant isolation: Business B sees 0 imported products
        $this->actingAs($this->ownerB, 'sanctum')
            ->getJson('/api/v1/shop/products/import')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_import_rejects_unknown_unit_with_row_error(): void
    {
        $headers = array_map(fn (array $c): string => $c['name'], ImportProduct::TEMPLATE_COLUMNS);
        $badRow = array_fill(0, 37, '');
        $badRow[0] = 'Bad Product';
        $badRow[2] = 'NonExistentUnit';
        $badRow[7] = '1';
        $badRow[12] = 'exclusive';
        $badRow[13] = 'single';
        $badRow[18] = '10';

        $csv = $this->toCsvLine($headers) . "\n" . $this->toCsvLine($badRow);
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $this->actingAs($this->ownerA, 'sanctum')
            ->postJson('/api/v1/shop/products/import', ['file' => $file])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    /**
     * @param  array<int, string>  $fields
     */
    private function toCsvLine(array $fields): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $fields);
        rewind($handle);
        $line = rtrim((string) stream_get_contents($handle), "\r\n");
        fclose($handle);

        return $line;
    }
}
