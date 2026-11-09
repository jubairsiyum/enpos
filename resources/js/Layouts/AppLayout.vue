<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const business = computed(() => page.props.auth?.business)

const modules = computed(() => page.props.enabledModules ?? [])

const openSubmenus = ref({
    user_management: true,
})

const toggleSubmenu = (module) => {
    openSubmenus.value[module] = !openSubmenus.value[module]
}

const allNavLinks = [
    { label: 'Home', module: 'home', icon: '🏠', href: 'shop.dashboard' },
    {
        label: 'User Management',
        module: 'user_management',
        icon: '👥',
        children: [
            { label: 'Users', href: 'shop.users.index' },
            { label: 'Roles', href: 'shop.roles.index' },
            { label: 'Sales Commission Agent', href: 'shop.sales-commission-agents.index' },
        ],
    },
    { label: 'Contacts',              module: 'contacts',               icon: '📇', href: 'shop.contacts.index' },
    { label: 'Products',              module: 'products',               icon: '📦', href: 'shop.products.index' },
    { label: 'Purchases',             module: 'purchases',              icon: '🛒', href: 'shop.purchases.index' },
    { label: 'Sell',                  module: 'sell',                   icon: '💰', href: 'shop.sells.index' },
    { label: 'POS',                   module: 'pos',                    icon: '🖥️', href: 'shop.pos' },
    { label: 'Stock Transfers',       module: 'stock_transfers',        icon: '🔄', href: 'shop.stock-transfers.index' },
    { label: 'Stock Adjustment',      module: 'stock_adjustment',       icon: '⚖️', href: 'shop.stock-adjustments.index' },
    { label: 'Expenses',              module: 'expenses',               icon: '💸', href: 'shop.expenses.index' },
    { label: 'Payment Accounts',      module: 'payment_accounts',       icon: '🏦', href: 'shop.accounts.index' },
    { label: 'Reports',               module: 'reports',                icon: '📊', href: 'shop.reports.profit-loss' },
    { label: 'Notifications',         module: 'notification_templates', icon: '🔔', href: 'shop.notifications.index' },
    { label: 'Settings',              module: 'settings',               icon: '⚙️', href: 'shop.settings.index' },
    { label: 'HRM',                   module: 'hrm',                    icon: '🏢', href: 'shop.hrm.employees.index' },
    { label: 'Essentials',            module: 'essentials',             icon: '⭐', href: 'shop.essentials.todos.index' },
    { label: 'Cheque',                module: 'cheque',                 icon: '🗒️', href: 'shop.cheques.index' },
    { label: 'Woocommerce',           module: 'woocommerce',            icon: '🛍️', href: 'shop.woocommerce.settings' },
]

// Core modules always show; optional only if enabled
const coreModules = [
    'home','user_management','contacts','products','purchases','sell','pos',
    'stock_transfers','stock_adjustment','expenses','payment_accounts',
    'reports','notification_templates','settings',
]

const navLinks = computed(() =>
    allNavLinks.filter(l =>
        coreModules.includes(l.module) || modules.value.includes(l.module)
    )
)
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside class="w-60 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-y-auto">
            <!-- Brand -->
            <div class="h-14 flex items-center px-5 border-b border-gray-100">
                <span class="font-bold text-primary-700 text-lg">{{ business?.name ?? 'enPOS' }}</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 py-3 px-2 space-y-0.5">
                <template v-for="link in navLinks" :key="link.module">
                    <!-- Item with submenu -->
                    <div v-if="link.children">
                        <button
                            type="button"
                            @click="toggleSubmenu(link.module)"
                            class="w-full flex items-center justify-between px-3 py-1.5 rounded-lg text-sm text-gray-700
                                   hover:bg-primary-50 hover:text-primary-700 transition"
                        >
                            <span class="flex items-center gap-2.5">
                                <span>{{ link.icon }}</span>
                                <span class="font-medium">{{ link.label }}</span>
                            </span>
                            <span class="text-xs transition-transform duration-200" :class="{ 'rotate-90': openSubmenus[link.module] }">
                                ▶
                            </span>
                        </button>

                        <div v-show="openSubmenus[link.module]" class="pl-8 pr-2 py-1 space-y-0.5">
                            <Link
                                v-for="child in link.children"
                                :key="child.href"
                                :href="route(child.href)"
                                class="block px-2.5 py-1.5 rounded-md text-xs text-gray-600 hover:text-primary-700 hover:bg-gray-100 transition"
                            >
                                {{ child.label }}
                            </Link>
                        </div>
                    </div>

                    <!-- Single link -->
                    <Link
                        v-else
                        :href="route(link.href)"
                        class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-sm text-gray-600
                               hover:bg-primary-50 hover:text-primary-700 transition"
                    >
                        <span>{{ link.icon }}</span>
                        <span>{{ link.label }}</span>
                    </Link>
                </template>
            </nav>

            <!-- User -->
            <div class="p-3 border-t border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium text-gray-800 truncate">{{ user?.name }}</p>
                        <p class="text-xs text-gray-400 capitalize">{{ user?.type }}</p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button"
                          class="text-gray-400 hover:text-gray-700 text-xs transition">
                        ⏻
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Content -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-14 bg-white border-b border-gray-200 flex items-center px-6">
                <slot name="header">
                    <h1 class="text-base font-semibold text-gray-900">Dashboard</h1>
                </slot>
            </header>
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

