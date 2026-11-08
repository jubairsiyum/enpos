<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const user = computed(() => page.props.auth?.user)

const navLinks = [
    { label: 'Dashboard',   href: 'platform.dashboard', icon: '🏠' },
    { label: 'Businesses',  href: 'platform.businesses.index', icon: '🏢' },
    { label: 'Plans',       href: 'platform.plans.index', icon: '📋' },
    { label: 'Subscriptions', href: 'platform.subscriptions.index', icon: '💳' },
    { label: 'Users',       href: 'platform.users.index', icon: '👥' },
]

const sidebarOpen = ref(false)
</script>

<template>
    <div class="min-h-screen bg-gray-100 flex">
        <!-- Sidebar -->
        <aside class="w-64 flex-shrink-0 bg-primary-900 text-white flex flex-col">
            <!-- Brand -->
            <div class="h-16 flex items-center px-6 border-b border-primary-800">
                <span class="text-xl font-bold tracking-tight">enPOS</span>
                <span class="ml-2 text-xs bg-primary-700 text-primary-200 rounded px-1.5 py-0.5">Platform</span>
            </div>

            <!-- Nav -->
            <nav class="flex-1 py-4 px-3 space-y-1">
                <Link
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="route(link.href)"
                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-primary-200
                           hover:bg-primary-800 hover:text-white transition"
                    :class="{ 'bg-primary-800 text-white': $page.url.startsWith('/platform/' + link.label.toLowerCase()) }"
                >
                    <span class="text-base">{{ link.icon }}</span>
                    {{ link.label }}
                </Link>
            </nav>

            <!-- User info -->
            <div class="p-4 border-t border-primary-800">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-primary-700 flex items-center justify-center text-xs font-semibold">
                        {{ user?.name?.charAt(0)?.toUpperCase() }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-white truncate">{{ user?.name }}</p>
                        <p class="text-xs text-primary-300 capitalize">{{ user?.type?.replace('_', ' ') }}</p>
                    </div>
                    <Link :href="route('logout')" method="post" as="button"
                          class="text-primary-400 hover:text-white transition text-xs">
                        Logout
                    </Link>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Top bar -->
            <header class="h-16 bg-white shadow-sm flex items-center px-6">
                <slot name="header">
                    <h1 class="text-lg font-semibold text-gray-900">Platform Admin</h1>
                </slot>
            </header>

            <!-- Page content -->
            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

