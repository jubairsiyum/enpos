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
        $shopPerms = [
            // Dashboard
            'shop.dashboard.view',
            // Users
            'shop.users.view', 'shop.users.create', 'shop.users.edit', 'shop.users.delete',
            // Contacts
            'shop.contacts.view', 'shop.contacts.create', 'shop.contacts.edit', 'shop.contacts.delete',
            // Products
            'shop.products.view', 'shop.products.create', 'shop.products.edit', 'shop.products.delete',
            // Purchases
            'shop.purchases.view', 'shop.purchases.create', 'shop.purchases.edit', 'shop.purchases.delete',
            // Sell
            'shop.sell.view', 'shop.sell.create', 'shop.sell.edit', 'shop.sell.delete',
            // POS
            'shop.pos.access',
            // Stock
            'shop.stock_transfers.view', 'shop.stock_transfers.create',
            'shop.stock_adjustment.view', 'shop.stock_adjustment.create',
            // Expenses
            'shop.expenses.view', 'shop.expenses.create', 'shop.expenses.edit', 'shop.expenses.delete',
            // Accounts
            'shop.accounts.view', 'shop.accounts.create',
            // Reports
            'shop.reports.view',
            // Settings
            'shop.settings.view', 'shop.settings.edit',
        ];

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
        $cashierRole->syncPermissions([
            'shop.dashboard.view',
            'shop.contacts.view', 'shop.contacts.create',
            'shop.products.view',
            'shop.sell.view', 'shop.sell.create',
            'shop.pos.access',
        ]);

        $this->command->info('Permissions and roles seeded.');
    }
}

