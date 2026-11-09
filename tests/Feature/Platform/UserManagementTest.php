<?php

namespace Tests\Feature\Platform;

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

    private User $superAdmin;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::create([
            'business_id' => null,
            'name'        => 'Super Admin',
            'email'       => 'superadmin@enpos.test',
            'password'    => bcrypt('password'),
            'type'        => 'super_admin',
            'is_active'   => true,
        ]);

        $plan = Plan::create([
            'name'          => 'Pro Plan',
            'slug'          => 'pro',
            'price'         => 99,
            'billing_cycle' => 'monthly',
            'is_active'     => true,
        ]);

        $this->business = Business::create([
            'name'   => 'Main Store',
            'slug'   => 'main-store',
            'status' => 'active',
        ]);

        Subscription::create([
            'business_id'   => $this->business->id,
            'plan_id'       => $plan->id,
            'status'        => 'active',
            'billing_cycle' => 'monthly',
            'starts_at'     => now(),
            'ends_at'       => now()->addYear(),
        ]);
    }

    public function test_super_admin_can_access_platform_users_hub(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('platform.users.index'));

        $response->assertOk();
    }

    public function test_super_admin_can_access_shop_users_as_well(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('shop.users.index'));

        $response->assertOk();
    }

    public function test_super_admin_can_access_shop_roles(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('shop.roles.index'));

        $response->assertOk();
    }

    public function test_super_admin_can_access_sales_commission_agents(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('shop.sales-commission-agents.index'));

        $response->assertOk();
    }
}
