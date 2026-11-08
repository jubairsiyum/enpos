<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------------------------------------------
        // 1. Plans
        // ----------------------------------------------------------------
        $starter = Plan::firstOrCreate(['slug' => 'starter'], [
            'name'          => 'Starter',
            'description'   => 'Perfect for small shops.',
            'price'         => 9.99,
            'billing_cycle' => 'monthly',
            'trial_days'    => 14,
            'max_locations' => 1,
            'max_users'     => 3,
            'max_products'  => 500,
            'modules'       => [],
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        $growth = Plan::firstOrCreate(['slug' => 'growth'], [
            'name'          => 'Growth',
            'description'   => 'Multi-location, more team members.',
            'price'         => 29.99,
            'billing_cycle' => 'monthly',
            'trial_days'    => 14,
            'max_locations' => 5,
            'max_users'     => 15,
            'max_products'  => 5000,
            'modules'       => ['hrm', 'essentials', 'cheque'],
            'is_active'     => true,
            'sort_order'    => 2,
        ]);

        $enterprise = Plan::firstOrCreate(['slug' => 'enterprise'], [
            'name'          => 'Enterprise',
            'description'   => 'All modules, unlimited usage.',
            'price'         => 79.99,
            'billing_cycle' => 'monthly',
            'trial_days'    => 14,
            'max_locations' => 999,
            'max_users'     => 999,
            'max_products'  => 999999,
            'modules'       => ['hrm', 'essentials', 'cheque', 'woocommerce'],
            'is_active'     => true,
            'is_featured'   => true,
            'sort_order'    => 3,
        ]);

        // ----------------------------------------------------------------
        // 2. Super Admin
        // ----------------------------------------------------------------
        $superAdmin = User::firstOrCreate(['email' => 'superadmin@enpos.test'], [
            'name'        => 'Super Admin',
            'password'    => Hash::make('password'),
            'type'        => 'super_admin',
            'business_id' => null,
            'is_active'   => true,
        ]);

        // ----------------------------------------------------------------
        // 3. Demo shop + owner (for development convenience)
        // ----------------------------------------------------------------
        $demoBusiness = Business::withoutGlobalScope('business')
            ->firstOrCreate(['slug' => 'demo'], [
                'name'            => 'Demo Shop',
                'email'           => 'demo@enpos.test',
                'currency_symbol' => '$',
                'status'          => 'active',
                'enabled_modules' => ['hrm', 'essentials', 'cheque'],
            ]);

        $demoOwner = User::firstOrCreate(['email' => 'owner@demo.test'], [
            'name'        => 'Demo Owner',
            'password'    => Hash::make('password'),
            'type'        => 'owner',
            'business_id' => $demoBusiness->id,
            'is_active'   => true,
        ]);

        if (is_null($demoBusiness->owner_id)) {
            $demoBusiness->update(['owner_id' => $demoOwner->id]);
        }

        // Active subscription for demo shop
        Subscription::firstOrCreate(
            ['business_id' => $demoBusiness->id, 'status' => 'active'],
            [
                'plan_id'       => $enterprise->id,
                'billing_cycle' => 'monthly',
                'amount'        => $enterprise->price,
                'starts_at'     => now(),
                'ends_at'       => now()->addYear(),
            ]
        );

        $this->command->info('Platform seeded. Super Admin: superadmin@enpos.test / password');
        $this->command->info('Demo shop owner: owner@demo.test / password');
    }
}

