<?php

namespace Tests\Feature\Shop;

use App\Models\Business;
use App\Models\Contact;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContactActionsTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $ownerB;
    private Contact $customerA;
    private Contact $supplierA;
    private Contact $customerB;

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

        $this->customerA = Contact::create([
            'business_id'     => $this->businessA->id,
            'type'            => 'customer',
            'contact_id'      => 'CO0001',
            'name'            => 'Harry',
            'mobile'          => '01711111111',
            'opening_balance' => 100.00,
            'total_sell'      => 500.00,
            'total_sell_paid' => 200.00,
        ]);

        $this->supplierA = Contact::create([
            'business_id'         => $this->businessA->id,
            'type'                => 'supplier',
            'contact_id'          => 'SUP001',
            'name'                => 'Acme Supplies',
            'mobile'              => '01722222222',
            'total_purchase'      => 400.00,
            'total_purchase_paid' => 100.00,
        ]);

        $this->customerB = Contact::create([
            'business_id' => $this->businessB->id,
            'type'        => 'customer',
            'contact_id'  => 'CO0001',
            'name'        => 'Shop B Customer',
            'mobile'      => '01733333333',
        ]);
    }

    public function test_pay_reduces_due_and_adds_excess_to_advance_balance(): void
    {
        // Customer due = 500 - 200 = 300; paying 350 clears 300 due and adds 50 to balance
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->customerA->id}/pay", [
                'amount'  => 350,
                'method'  => 'cash',
                'paid_on' => '2026-11-11',
                'note'    => 'Cleared due + advance',
            ])
            ->assertCreated()
            ->assertJsonPath('data.due_applied', '300.00')
            ->assertJsonPath('data.advance_added', '50.00');

        $this->customerA->refresh();
        $this->assertEquals(500.00, (float) $this->customerA->total_sell_paid);
        $this->assertEquals(50.00, (float) $this->customerA->balance);

        // Supplier due = 400 - 100 = 300; paying 120 applies 120
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->supplierA->id}/pay", [
                'amount'  => 120,
                'method'  => 'bank_transfer',
                'paid_on' => '2026-11-11',
            ])
            ->assertCreated()
            ->assertJsonPath('data.due_applied', '120.00');

        $this->supplierA->refresh();
        $this->assertEquals(220.00, (float) $this->supplierA->total_purchase_paid);
    }

    public function test_toggle_status_deactivates_and_reactivates_contact(): void
    {
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->customerA->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('data.contact_status', 'inactive');

        $this->assertDatabaseHas('contacts', [
            'id'             => $this->customerA->id,
            'contact_status' => 'inactive',
        ]);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->customerA->id}/toggle-status")
            ->assertOk()
            ->assertJsonPath('data.contact_status', 'active');
    }

    public function test_ledger_and_sales_endpoints_return_contact_activity(): void
    {
        $location = Location::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Main Shop',
            'location_id' => 'LOC-1',
            'is_active'   => true,
        ]);

        Transaction::create([
            'business_id'      => $this->businessA->id,
            'type'             => 'sell',
            'status'           => 'final',
            'payment_status'   => 'due',
            'invoice_no'       => 'INV-1001',
            'location_id'      => $location->id,
            'contact_id'       => $this->customerA->id,
            'created_by'       => $this->ownerA->id,
            'transaction_date' => '2026-11-11',
            'total_amount'     => 250.00,
        ]);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->customerA->id}/pay", [
                'amount'  => 100,
                'method'  => 'cash',
                'paid_on' => '2026-11-11',
            ])
            ->assertCreated();

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/contacts/{$this->customerA->id}/ledger")
            ->assertOk()
            ->assertJsonPath('data.summary.opening_balance', 100)
            ->assertJsonCount(3, 'data.entries');

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/contacts/{$this->customerA->id}/sales")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_no', 'INV-1001')
            ->assertJsonPath('data.0.total', 250);
    }

    public function test_documents_and_notes_crud_with_file_upload(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->create('agreement.pdf', 50, 'application/pdf');

        $createRes = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->supplierA->id}/documents", [
                'heading'     => 'Supplier Contract',
                'description' => 'Signed agreement',
                'is_private'  => false,
                'file'        => $file,
            ])
            ->assertCreated();

        $docId = $createRes->json('data.id');

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/contacts/{$this->supplierA->id}/documents")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.heading', 'Supplier Contract');

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/contacts/{$this->supplierA->id}/documents/{$docId}")
            ->assertOk();

        $this->assertDatabaseCount('contact_documents', 0);
    }

    public function test_contact_actions_enforce_business_isolation(): void
    {
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/contacts/{$this->customerB->id}/toggle-status")
            ->assertNotFound();

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/contacts/{$this->customerB->id}/ledger")
            ->assertNotFound();
    }
}
