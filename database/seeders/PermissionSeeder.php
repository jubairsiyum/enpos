<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ----------------------------------------------------------------
        // Platform permissions (no team / business_id)
        // ----------------------------------------------------------------
        $platformPerms = [
            // Businesses
            'platform.businesses.view',
            'platform.businesses.create',
            'platform.businesses.edit',
            'platform.businesses.delete',
            'platform.businesses.suspend',
            'platform.businesses.impersonate',
            // Plans
            'platform.plans.view',
            'platform.plans.create',
            'platform.plans.edit',
            'platform.plans.delete',
            // Subscriptions
            'platform.subscriptions.view',
            'platform.subscriptions.edit',
            // Platform users
            'platform.users.view',
            'platform.users.create',
            'platform.users.edit',
            'platform.users.delete',
        ];

        foreach ($platformPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ----------------------------------------------------------------
        // Shop-level permissions (scoped to a business_id team)
        // ----------------------------------------------------------------
        $shopGroups = config('shop_permissions.groups', []);
        $shopPerms = [
            'shop.dashboard.view',
            'shop.pos.access',
        ];

        foreach ($shopGroups as $groupPerms) {
            foreach (array_keys($groupPerms) as $permKey) {
                $shopPerms[] = $permKey;
            }
        }
        $shopPerms = array_unique($shopPerms);

        foreach ($shopPerms as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ----------------------------------------------------------------
        // Platform roles
        // ----------------------------------------------------------------
        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::where('name', 'like', 'platform.%')->pluck('name')->toArray());

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $adminRole->syncPermissions([
            'platform.businesses.view', 'platform.businesses.edit',
            'platform.subscriptions.view',
        ]);

        // ----------------------------------------------------------------
        // Shop roles (note: Spatie team_id = business_id when assigned)
        // ----------------------------------------------------------------
        // These are templates — actual assignment uses setPermissionTeamId
        $ownerRole = Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web']);
        $ownerRole->syncPermissions(Permission::where('name', 'like', 'shop.%')->pluck('name')->toArray());

        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web']);
        $cashierPermissions = array_filter([
            'shop.dashboard.view',
            'shop.pos.view',
            'shop.pos.create',
            'shop.sell.view_all',
            'shop.sell.create',
            'shop.customers.view_all',
            'shop.products.view',
        ], fn ($p) => Permission::where('name', $p)->exists());
        $cashierRole->syncPermissions($cashierPermissions);

        $this->command->info('Permissions and roles seeded.');
    }
}

