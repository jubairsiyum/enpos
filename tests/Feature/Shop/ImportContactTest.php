<?php

namespace Tests\Feature\Shop;

use App\Models\Business;
use App\Models\ImportContact;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Support\ExcelWorkbookCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportContactTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $ownerB;

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

    public function test_template_downloads_in_xls_format_with_exact_27_columns(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->get('/api/v1/shop/import-contacts/template');

        $response->assertOk();
        $this->assertSame('application/vnd.ms-excel', $response->headers->get('content-type'));
        $this->assertStringContainsString('import_contacts_template.xls', (string) $response->headers->get('content-disposition'));

        $body = $response->streamedContent();
        $codec = new ExcelWorkbookCodec();
        $this->assertTrue($codec->isBinaryXls($body));

        $rows = $codec->parseBinaryXls($body);
        $expectedHeaders = array_map(fn (array $col): string => $col['name'], ImportContact::TEMPLATE_COLUMNS);
        $this->assertSame([$expectedHeaders], $rows);
    }

    public function test_contacts_can_be_imported_from_binary_xls_file(): void
    {
        $headers = array_map(fn (array $col): string => $col['name'], ImportContact::TEMPLATE_COLUMNS);

        $customerRow = [
            '1', 'Mr', 'Harry', '', 'Potter', '', '', 'TAX-1', '0', '', '', '5000',
            'harry@example.test', '(378) 400-1234', '', '', 'Phoenix', 'Arizona', 'USA',
            'Linking Street', '', '85001', '1995-07-31', 'CF1', 'CF2', 'CF3', 'CF4',
        ];

        $supplierRow = [
            '2', 'Ms', 'Hermione', '', 'Granger', 'Granger Supplies', 'SUP001', 'TAX-2', '100', '15', 'days', '',
            'hermione@example.test', '(378) 400-5678', '', '', 'London', '', 'UK',
            'Baker Street', '', 'NW1', '', '', '', '', '',
        ];

        $binaryXls = (new ExcelWorkbookCodec())->buildXls([$headers, $customerRow, $supplierRow]);
        $file = UploadedFile::fake()->createWithContent('contacts.xls', $binaryXls);

        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', [
                'file' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonPath('imported_count', 2);

        $this->assertDatabaseHas('contacts', [
            'business_id' => $this->businessA->id,
            'type'        => 'customer',
            'first_name'  => 'Harry',
            'contact_id'  => 'CO0001',
        ]);

        $this->assertDatabaseHas('contacts', [
            'business_id'            => $this->businessA->id,
            'type'                   => 'supplier',
            'supplier_business_name' => 'Granger Supplies',
            'contact_id'             => 'SUP001',
            'pay_term_number'        => 15,
            'pay_term_type'          => 'days',
        ]);

        $this->assertDatabaseCount('import_contacts', 2);
    }

    public function test_contacts_can_be_imported_from_xls_file(): void
    {
        $headers = array_map(fn (array $col): string => $col['name'], ImportContact::TEMPLATE_COLUMNS);

        $customerRow = [
            '1', 'Mr', 'Harry', '', 'Potter', '', '', 'TAX-1', '0', '', '', '5000',
            'harry@example.test', '(378) 400-1234', '', '', 'Phoenix', 'Arizona', 'USA',
            'Linking Street', '', '85001', '1995-07-31', 'CF1', 'CF2', 'CF3', 'CF4',
        ];

        $supplierRow = [
            '2', 'Ms', 'Hermione', '', 'Granger', 'Granger Supplies', 'SUP001', 'TAX-2', '100', '15', 'days', '',
            'hermione@example.test', '(378) 400-5678', '', '', 'London', '', 'UK',
            'Baker Street', '', 'NW1', '', '', '', '', '',
        ];

        $buildRowXml = function (array $cells): string {
            $xml = "   <Row>\n";
            foreach ($cells as $cell) {
                $val = htmlspecialchars($cell, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "    <Cell><Data ss:Type=\"String\">{$val}</Data></Cell>\n";
            }
            $xml .= "   </Row>\n";
            return $xml;
        };

        $xlsContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="ImportContacts">
  <Table>
{$buildRowXml($headers)}
{$buildRowXml($customerRow)}
{$buildRowXml($supplierRow)}
  </Table>
 </Worksheet>
</Workbook>
XML;

        $file = UploadedFile::fake()->createWithContent('contacts.xls', $xlsContent);

        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', [
                'file' => $file,
            ]);

        $response->assertCreated()
            ->assertJsonPath('imported_count', 2);

        $this->assertDatabaseHas('contacts', [
            'business_id' => $this->businessA->id,
            'type'        => 'customer',
            'first_name'  => 'Harry',
            'contact_id'  => 'CO0001',
        ]);

        $this->assertDatabaseHas('contacts', [
            'business_id'            => $this->businessA->id,
            'type'                   => 'supplier',
            'supplier_business_name' => 'Granger Supplies',
            'contact_id'             => 'SUP001',
            'pay_term_number'        => 15,
            'pay_term_type'          => 'days',
        ]);

        $this->assertDatabaseCount('import_contacts', 2);
    }

    public function test_excel_serial_date_and_utf8_bom_csv_import_cleanly(): void
    {
        $headers = "\xEF\xBB\xBF" . implode(',', array_map(fn (array $col): string => '"' . $col['name'] . '"', ImportContact::TEMPLATE_COLUMNS));
        $row = implode(',', [
            '1', 'Mr', 'Ron', '', 'Weasley', '', '', '', '0', '', '', '',
            'ron@example.test', '01711111111', '', '', '', '', '', '', '', '', '34911', '', '', '', '',
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $headers . "\n" . $row);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', ['file' => $file])
            ->assertCreated()
            ->assertJsonPath('imported_count', 1);

        $this->assertDatabaseHas('contacts', [
            'business_id' => $this->businessA->id,
            'first_name'  => 'Ron',
            'dob'         => '1995-07-31',
        ]);
    }

    public function test_supplier_or_both_requires_business_name_pay_term_and_period(): void
    {
        $headers = implode(',', array_map(fn (array $col): string => '"' . $col['name'] . '"', ImportContact::TEMPLATE_COLUMNS));
        $invalidSupplierRow = implode(',', [
            '2', 'Mr', 'John', '', 'Doe', '', '', '', '', '', '', '',
            '', '01700000000', '', '', '', '', '', '', '', '', '', '', '', '', '',
        ]);

        $file = UploadedFile::fake()->createWithContent('contacts.csv', $headers . "\n" . $invalidSupplierRow);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('import_contacts', 0);
    }

    public function test_imported_contacts_history_is_business_isolated(): void
    {
        ImportContact::create([
            'business_id'  => $this->businessB->id,
            'contact_type' => 1,
            'first_name'   => 'Shop B Contact',
            'mobile'       => '999999',
        ]);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/import-contacts')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_import_contacts_web_page_renders(): void
    {
        $this->actingAs($this->ownerA)
            ->get('/app/contacts/import')
            ->assertOk();
    }

    public function test_reimporting_same_mobile_is_blocked(): void
    {
        $headers = implode(',', array_map(fn (array $col): string => '"' . $col['name'] . '"', ImportContact::TEMPLATE_COLUMNS));
        $row = [
            '1', '', 'Ron', '', 'Weasley', '', '', '', '0', '', '', '', 'ron@example.test', '(378) 400-9999',
            '', '', '', '', '', '', '', '', '', '', '', '',
        ];
        $csv = $headers . "\n" . implode(',', array_map(fn (string $v): string => '"' . $v . '"', $row)) . "\n";

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', [
                'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
            ])
            ->assertCreated();

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/import-contacts', [
                'file' => UploadedFile::fake()->createWithContent('contacts_dup.csv', $csv),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }
}
