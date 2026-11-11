<?php

namespace Tests\Feature\Shop;

use App\Models\Business;
use App\Models\Contact;
use App\Models\CustomerGroup;
use App\Models\Plan;
use App\Models\SellingPriceGroup;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private Business $businessA;
    private Business $businessB;
    private User $ownerA;
    private User $staffA;
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

        $this->staffA = User::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Staff A',
            'email'       => 'staff-a@example.test',
            'password'    => 'password',
            'type'        => 'staff',
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

    public function test_customer_can_be_created_with_more_info_assigned_users_and_contact_persons(): void
    {
        $group = CustomerGroup::create([
            'business_id' => $this->businessA->id,
            'name'        => 'VIP Tier',
            'amount'      => 10,
        ]);

        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/customers', [
                'type'              => 'customer',
                'entity_type'       => 'individual',
                'customer_group_id' => $group->id,
                'prefix'            => 'Mr',
                'first_name'        => 'Harry',
                'last_name'         => 'Potter',
                'mobile'            => '(378) 400-1234',
                'alternate_number'  => '(378) 400-0001',
                'landline'          => '0123456',
                'email'             => 'harry@example.test',
                'assigned_to'       => [$this->staffA->id],
                'tax_number'        => 'TAX-101',
                'opening_balance'   => 250.50,
                'pay_term_number'   => 15,
                'pay_term_type'     => 'days',
                'credit_limit'      => null,
                'address_line_1'    => 'Linking Street',
                'city'              => 'Phoenix',
                'state'             => 'Arizona',
                'country'           => 'USA',
                'zip_code'          => '85001',
                'landmark'          => 'Near Central Mall',
                'street_name'       => 'Linking Street',
                'building_number'   => '42B',
                'additional_number' => '102',
                'custom_field1'     => 'CF1',
                'custom_field10'    => 'CF10',
                'shipping_address'  => 'Phoenix Shipping Hub',
                'contact_persons'   => [
                    [
                        'prefix'                      => 'Mr',
                        'first_name'                  => 'Ron',
                        'last_name'                   => 'Weasley',
                        'email'                       => 'ron@example.test',
                        'mobile'                      => '(378) 400-5678',
                        'family_number'               => '(378) 400-9999',
                        'department'                  => 'Purchasing',
                        'designation'                 => 'Manager',
                        'sales_commission_percentage' => 2.5,
                        'allow_login'                 => true,
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mr Harry Potter')
            ->assertJsonPath('data.contact_id', 'CO0001')
            ->assertJsonPath('data.customer_group_name', 'VIP Tier')
            ->assertJsonPath('data.pay_term', '15 Days')
            ->assertJsonPath('data.credit_limit', null)
            ->assertJsonPath('data.assigned_to.0', $this->staffA->id)
            ->assertJsonPath('data.contact_persons.0.first_name', 'Ron')
            ->assertJsonPath('data.contact_persons.0.allow_login', true);

        $this->assertDatabaseHas('contacts', [
            'business_id'       => $this->businessA->id,
            'contact_id'        => 'CO0001',
            'landmark'          => 'Near Central Mall',
            'building_number'   => '42B',
            'additional_number' => '102',
        ]);

        $this->assertDatabaseHas('contact_persons', [
            'business_id' => $this->businessA->id,
            'first_name'  => 'Ron',
            'department'  => 'Purchasing',
        ]);

        $this->assertDatabaseHas('contact_user', [
            'user_id' => $this->staffA->id,
        ]);
    }

    public function test_customers_can_be_listed_and_filtered(): void
    {
        $group = CustomerGroup::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Wholesale',
            'amount'      => 5,
        ]);

        $c1 = Contact::create([
            'business_id'       => $this->businessA->id,
            'type'              => 'customer',
            'contact_id'        => 'CO0005',
            'name'              => 'Walk-In Customer',
            'mobile'            => '(378) 400-1234',
            'total_sell'        => 500,
            'total_sell_paid'   => 200,
            'customer_group_id' => $group->id,
        ]);
        $c1->assignedUsers()->sync([$this->staffA->id]);

        Contact::create([
            'business_id' => $this->businessA->id,
            'type'        => 'supplier',
            'contact_id'  => 'CO0006',
            'name'        => 'Supplier Only',
            'mobile'      => '111222333',
        ]);

        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/customers?has_sell_due=1&assigned_to=' . $this->staffA->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Walk-In Customer')
            ->assertJsonPath('totals.total_sell_due', 300);
    }

    public function test_customer_can_be_updated_soft_deleted_restored_and_force_deleted(): void
    {
        $customer = Contact::create([
            'business_id' => $this->businessA->id,
            'type'        => 'customer',
            'contact_id'  => 'CO0010',
            'name'        => 'Alice',
            'mobile'      => '555-0100',
        ]);

        $updateResponse = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->putJson("/api/v1/shop/customers/{$customer->id}", [
                'first_name'   => 'Alice',
                'last_name'    => 'Wonder',
                'credit_limit' => 1500,
                'contact_persons' => [
                    ['first_name' => 'Bob', 'mobile' => '555-0101'],
                ],
            ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.name', 'Alice Wonder')
            ->assertJsonPath('data.credit_limit', 1500)
            ->assertJsonCount(1, 'data.contact_persons');

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/customers/{$customer->id}")
            ->assertOk();

        $this->assertSoftDeleted('contacts', ['id' => $customer->id]);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson("/api/v1/shop/customers/{$customer->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $customer->id);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/customers/{$customer->id}/force-delete")
            ->assertOk();

        $this->assertDatabaseMissing('contacts', ['id' => $customer->id]);
    }

    public function test_business_isolation_prevents_accessing_other_business_customer(): void
    {
        $customerB = Contact::create([
            'business_id' => $this->businessB->id,
            'type'        => 'customer',
            'name'        => 'Shop B Customer',
            'mobile'      => '999-9999',
        ]);

        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/customers/{$customerB->id}")
            ->assertNotFound();
    }

    public function test_customer_groups_crud_endpoints_work(): void
    {
        $spg = SellingPriceGroup::create([
            'business_id' => $this->businessA->id,
            'name'        => 'Wholesale Tier',
            'is_active'   => true,
        ]);

        // 1. Create Percentage type group (negative discount supported)
        $create = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/customer-groups', [
                'name'                   => 'Gold Members',
                'price_calculation_type' => 'percentage',
                'amount'                 => -15,
                'is_default'             => true,
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Gold Members')
            ->assertJsonPath('data.price_calculation_type', 'percentage')
            ->assertJsonPath('data.amount', '-15.00')
            ->assertJsonPath('data.selling_price_group_id', null);

        $groupId = $create->json('data.id');

        // 2. Create Selling Price Group type
        $createSpg = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/customer-groups', [
                'name'                   => 'Wholesale Customers',
                'price_calculation_type' => 'selling_price_group',
                'selling_price_group_id' => $spg->id,
            ]);

        $createSpg->assertCreated()
            ->assertJsonPath('data.price_calculation_type', 'selling_price_group')
            ->assertJsonPath('data.amount', null)
            ->assertJsonPath('data.selling_price_group_id', $spg->id)
            ->assertJsonPath('data.selling_price_group_name', 'Wholesale Tier');

        // 3. List
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/customer-groups')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        // 4. Switch percentage group to selling_price_group on update
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->putJson("/api/v1/shop/customer-groups/{$groupId}", [
                'price_calculation_type' => 'selling_price_group',
                'selling_price_group_id' => $spg->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.price_calculation_type', 'selling_price_group')
            ->assertJsonPath('data.amount', null)
            ->assertJsonPath('data.selling_price_group_id', $spg->id);

        // 5. Delete
        $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->deleteJson("/api/v1/shop/customer-groups/{$groupId}")
            ->assertOk();
    }

    public function test_customers_and_customer_groups_web_pages_render(): void
    {
        $this->actingAs($this->ownerA)
            ->get('/app/contacts/customers')
            ->assertOk();

        $this->actingAs($this->ownerA)
            ->get('/app/contacts/customer-groups')
            ->assertOk();
    }
}
