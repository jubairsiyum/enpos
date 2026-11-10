<?php

namespace Tests\Feature\Shop;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserManagementTest extends TestCase
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

    public function test_user_index_can_be_retrieved(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson('/api/v1/shop/users');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_user_can_be_created(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/users', [
                'prefix'                => 'Mr',
                'first_name'            => 'John',
                'last_name'             => 'Doe',
                'email'                 => 'john@example.test',
                'username'              => 'johndoe',
                'password'              => 'password123',
                'password_confirmation' => 'password123',
                'is_active'             => true,
                'allow_login'           => true,
                'commission_rate'       => 5.5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'john@example.test')
            ->assertJsonPath('data.username', 'johndoe');

        $this->assertDatabaseHas('users', [
            'email'       => 'john@example.test',
            'business_id' => $this->businessA->id,
        ]);
    }

    public function test_user_can_be_created_with_service_staff_pin_and_hrm_details(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/users', [
                'prefix'                   => 'Mr',
                'first_name'               => 'Alex',
                'last_name'                => 'Turner',
                'email'                    => 'alex.turner@example.test',
                'username'                 => 'alexturner',
                'password'                 => 'password123',
                'password_confirmation'    => 'password123',
                'is_active'                => true,
                'enable_service_staff_pin' => true,
                'staff_pin'                => '4321',
                'allow_login'              => true,
                'hrm_details'              => [
                    'department'            => 'Sales & Marketing',
                    'designation'           => 'Sales Lead',
                    'primary_work_location' => 'Main Store',
                    'basic_salary'          => 4500.00,
                    'pay_cycle'             => 'Per Month',
                    'pay_components'        => 'Standard Bonus',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'alex.turner@example.test')
            ->assertJsonPath('data.enable_service_staff_pin', true)
            ->assertJsonPath('data.staff_pin', '4321');

        $this->assertDatabaseHas('users', [
            'email'                    => 'alex.turner@example.test',
            'enable_service_staff_pin' => true,
            'staff_pin'                => '4321',
            'business_id'              => $this->businessA->id,
        ]);

        $user = User::where('email', 'alex.turner@example.test')->first();
        $this->assertEquals('Sales & Marketing', $user->hrm_details['department']);
        $this->assertEquals('Per Month', $user->hrm_details['pay_cycle']);
    }

    public function test_user_can_be_assigned_partitioned_roles_superadmin_admin_and_owner(): void
    {
        $rolesToTest = [
            'super_admin' => 'super_admin',
            'admin'       => 'admin',
            'owner'       => 'owner',
        ];

        foreach ($rolesToTest as $roleKey => $expectedType) {
            $email = "user-{$roleKey}@example.test";
            $response = $this->actingAs($this->ownerA)
                ->withHeader('X-Business', (string) $this->businessA->id)
                ->postJson('/api/v1/shop/users', [
                    'prefix'                => 'Mr',
                    'first_name'            => 'Role',
                    'last_name'             => $roleKey,
                    'email'                 => $email,
                    'username'              => "user_{$roleKey}",
                    'password'              => 'password123',
                    'password_confirmation' => 'password123',
                    'role'                  => $roleKey,
                    'is_active'             => true,
                    'allow_login'           => true,
                ]);

            $response->assertCreated();

            $user = User::where('email', $email)->first();
            $this->assertNotNull($user);
            $this->assertEquals($expectedType, $user->type);

            setPermissionsTeamId($this->businessA->id);
            $this->assertTrue($user->hasRole($roleKey));
        }
    }

    public function test_web_user_create_form_stores_to_database_and_redirects_back_with_flash_success(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->from('/app/users/create')
            ->post('/app/users', [
                'prefix'                => 'Ms',
                'first_name'            => 'Sarah',
                'last_name'             => 'Connor',
                'email'                 => 'sarah.connor@example.test',
                'username'              => 'sarahconnor',
                'password'              => 'terminator123',
                'password_confirmation' => 'terminator123',
                'is_active'             => true,
                'allow_login'           => true,
            ]);

        $response->assertRedirect('/app/users/create')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email'       => 'sarah.connor@example.test',
            'business_id' => $this->businessA->id,
            'name'        => 'Ms Sarah Connor',
        ]);
    }

    public function test_user_creation_validates_required_fields(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/users', [
                'first_name' => '',
                'email'      => 'not-an-email',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'email', 'password']);
    }

    public function test_shop_cannot_view_another_shops_user(): void
    {
        $userB = User::create([
            'business_id' => $this->businessB->id,
            'name'        => 'Staff B',
            'email'       => 'staff-b@example.test',
            'password'    => 'password',
            'type'        => 'staff',
            'is_active'   => true,
        ]);

        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->getJson("/api/v1/shop/users/{$userB->id}");

        $response->assertNotFound();
    }

    public function test_role_can_be_created_and_assigned(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/roles', [
                'name'        => 'Store Manager',
                'permissions' => [],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Store Manager');

        $this->assertDatabaseHas('roles', [
            'name'        => 'Store Manager',
            'business_id' => $this->businessA->id,
        ]);
    }

    public function test_role_with_radio_and_checkbox_permissions_persists_correctly(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/roles', [
                'name'        => 'Cashier With Limited View',
                'permissions' => [
                    'shop.suppliers.view_own',
                    'shop.customers.view_all',
                    'shop.customers.view_no_sell_1m',
                    'shop.customers.create',
                    'shop.pos.view',
                    'shop.pos.create',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Cashier With Limited View');

        $role = Role::where('name', 'Cashier With Limited View')
            ->where('business_id', $this->businessA->id)
            ->first();

        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('shop.suppliers.view_own'));
        $this->assertTrue($role->hasPermissionTo('shop.customers.view_all'));
        $this->assertTrue($role->hasPermissionTo('shop.pos.create'));
    }

    public function test_sales_commission_agent_crud(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withHeader('X-Business', (string) $this->businessA->id)
            ->postJson('/api/v1/shop/sales-commission-agents', [
                'first_name'        => 'Agent',
                'last_name'         => 'Smith',
                'email'             => 'agent.smith@example.test',
                'phone'             => '123456789',
                'commission_rate'   => 10.0,
                'permanent_address' => 'Dhaka, Bangladesh',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.email', 'agent.smith@example.test')
            ->assertJsonPath('data.is_cmmsn_agent', true);

        $this->assertDatabaseHas('users', [
            'email'          => 'agent.smith@example.test',
            'is_cmmsn_agent' => true,
            'business_id'    => $this->businessA->id,
        ]);
    }
}
