<script setup>
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)
const business = computed(() => page.props.auth?.business)

const openSubmenus = ref({
    user_management: ! page.url?.startsWith('/app/contacts') && ! page.url?.startsWith('/app/products') && ! page.url?.startsWith('/app/variations'),
    contact: page.url?.startsWith('/app/contacts') ?? false,
    products: (page.url?.startsWith('/app/products') || page.url?.startsWith('/app/variations')) ?? false,
})

const toggleSubmenu = (module) => {
    openSubmenus.value[module] = !openSubmenus.value[module]
}

const userManagementLinks = [
    { label: 'Users', href: 'shop.users.index', pattern: '/app/users', icon: '👤' },
    { label: 'Roles', href: 'shop.roles.index', pattern: '/app/roles', icon: '🛡️' },
    { label: 'Sales Commission Agent', href: 'shop.sales-commission-agents.index', pattern: '/app/sales-commission-agents', icon: '💼' },
]

const contactLinks = [
    { label: 'Suppliers', href: 'shop.contacts.index', params: { type: 'supplier' }, pattern: '/app/contacts', exactExclude: ['/app/contacts/customers', '/app/contacts/customer-groups', '/app/contacts/import'], icon: '🏭' },
    { label: 'Customers', href: 'shop.customers.index', params: {}, pattern: '/app/contacts/customers', icon: '🧑‍🤝‍🧑' },
    { label: 'Customer Groups', href: 'shop.customer-groups.index', params: {}, pattern: '/app/contacts/customer-groups', icon: '🏷️' },
    { label: 'Import Contacts', href: 'shop.import-contacts.index', params: {}, pattern: '/app/contacts/import', icon: '📥' },
]

const productLinks = [
    { label: 'List Products', href: 'shop.products.index', params: {}, pattern: '/app/products', exactExclude: ['/app/products/create', '/app/products/update-price', '/app/products/print-labels'], icon: '📦' },
    { label: 'Add Product', href: 'shop.products.create', params: {}, pattern: '/app/products/create', icon: '➕' },
    { label: 'Update Price', href: 'shop.products.update-price.index', params: {}, pattern: '/app/products/update-price', icon: '💲' },
    { label: 'Print Labels', href: 'shop.products.print-labels.index', params: {}, pattern: '/app/products/print-labels', icon: '🏷️' },
    { label: 'Variations', href: 'shop.variations.index', params: {}, pattern: '/app/variations', icon: '🧬' },
]

const isChildActive = (child) => {
    const pattern = typeof child === 'string' ? child : child.pattern
    const excludes = typeof child === 'object' ? child.exactExclude : null
    if (Array.isArray(excludes) && excludes.some(prefix => page.url?.startsWith(prefix))) {
        return false
    }
    if (typeof excludes === 'string' && page.url?.startsWith(excludes)) {
        return false
    }
    return page.url?.startsWith(pattern)
}
</script>

<template>
    <div class="min-h-screen bg-gray-50 flex">
        <!-- Sidebar -->
        <aside class="w-64 flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-y-auto">
            <!-- Brand -->
            <div class="h-16 flex items-center justify-between px-5 border-b border-gray-100 bg-indigo-900 text-white">
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-xl tracking-tight text-white">enPOS</span>
                    <span class="text-[10px] font-semibold bg-indigo-700 text-indigo-200 rounded px-1.5 py-0.5">Admin</span>
                </div>
            </div>

            <!-- Nav -->
            <nav class="flex-1 py-4 px-3 space-y-2">
                <!-- User Management Section (Primary) -->
                <div>
                    <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-indigo-50 text-indigo-900 font-semibold text-sm">
                        <span class="flex items-center gap-2.5">
                            <span class="text-base">👥</span>
                            <span>User Management</span>
                        </span>
                        <span class="text-xs text-indigo-600 font-bold">Active</span>
                    </div>

                    <!-- Sub-pages -->
                    <div class="mt-2 pl-3 pr-1 space-y-1">
                        <Link
                            v-for="child in userManagementLinks"
                            :key="child.href"
                            :href="route(child.href)"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition"
                            :class="isChildActive(child)
                                ? 'bg-indigo-600 text-white font-medium shadow-sm'
                                : 'text-gray-700 hover:bg-gray-100 hover:text-indigo-600'"
                        >
                            <span>{{ child.icon }}</span>
                            <span>{{ child.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Contact Section -->
                <div>
                    <button
                        type="button"
                        @click="toggleSubmenu('contact')"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-indigo-900 font-semibold text-sm hover:bg-indigo-50 transition"
                    >
                        <span class="flex items-center gap-2.5">
                            <span class="text-base">📋</span>
                            <span>Contact</span>
                        </span>
                        <span class="text-xs text-gray-500">{{ openSubmenus.contact ? '▾' : '▸' }}</span>
                    </button>

                    <div v-show="openSubmenus.contact" class="mt-2 pl-3 pr-1 space-y-1">
                        <Link
                            v-for="child in contactLinks"
                            :key="child.href"
                            :href="route(child.href, child.params ?? {})"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition"
                            :class="isChildActive(child)
                                ? 'bg-indigo-600 text-white font-medium shadow-sm'
                                : 'text-gray-700 hover:bg-gray-100 hover:text-indigo-600'"
                        >
                            <span>{{ child.icon }}</span>
                            <span>{{ child.label }}</span>
                        </Link>
                    </div>
                </div>

                <!-- Products Section -->
                <div>
                    <button
                        type="button"
                        @click="toggleSubmenu('products')"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-indigo-900 font-semibold text-sm hover:bg-indigo-50 transition"
                    >
                        <span class="flex items-center gap-2.5">
                            <span class="text-base">📦</span>
                            <span>Products</span>
                        </span>
                        <span class="text-xs text-gray-500">{{ openSubmenus.products ? '▾' : '▸' }}</span>
                    </button>

                    <div v-show="openSubmenus.products" class="mt-2 pl-3 pr-1 space-y-1">
                        <Link
                            v-for="child in productLinks"
                            :key="child.href"
                            :href="route(child.href, child.params ?? {})"
                            class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition"
                            :class="isChildActive(child)
                                ? 'bg-indigo-600 text-white font-medium shadow-sm'
                                : 'text-gray-700 hover:bg-gray-100 hover:text-indigo-600'"
                        >
                            <span>{{ child.icon }}</span>
                            <span>{{ child.label }}</span>
                        </Link>
                    </div>
                </div>
            </nav>

            <!-- User Footer -->
            <div class="p-3 border-t border-gray-100 bg-gray-50">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-full bg-indigo-600 text-white flex items-center justify-center text-sm font-bold shadow-sm">
                        {{ user?.name?.charAt(0)?.toUpperCase() ?? 'U' }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-900 truncate">{{ user?.name }}</p>
                        <span class="inline-block text-[10px] font-medium px-1.5 py-0.2 rounded bg-indigo-100 text-indigo-800 capitalize">
                            {{ user?.type ?? 'SuperAdmin' }}
                        </span>
                    </div>
                    <Link
                        :href="route('logout')"
                        method="post"
                        as="button"
                        title="Logout"
                        class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-white rounded transition"
                    >
                        ⏻
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top Header with Quick Sub-Navigation Pills -->
            <header class="h-16 bg-white border-b border-gray-200 flex items-center justify-between px-6">
                <!-- Quick Navigation Pills for instant testing -->
                <div class="flex items-center gap-2">
                    <Link
                        v-for="child in userManagementLinks"
                        :key="child.href"
                        :href="route(child.href)"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium transition"
                        :class="isChildActive(child.pattern)
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                    >
                        <span>{{ child.icon }}</span>
                        <span>{{ child.label }}</span>
                    </Link>
                </div>

                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">
                        Store: <strong class="text-gray-700">{{ business?.name ?? 'Main Business' }}</strong>
                    </span>
                </div>
            </header>

            <main class="flex-1 p-6 bg-gray-50 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>

