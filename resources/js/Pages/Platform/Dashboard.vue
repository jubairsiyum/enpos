<script setup>
import { computed } from 'vue'
import { Head, usePage, Link } from '@inertiajs/vue3'
import PlatformLayout from '@/Layouts/PlatformLayout.vue'

defineOptions({ layout: PlatformLayout })

const page = usePage()

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    recentBusinesses: { type: Array, default: () => [] },
})

const statCards = computed(() => [
    { label: 'Total Shops',          value: props.stats.total_businesses ?? 0,       color: 'bg-blue-500' },
    { label: 'Active Shops',         value: props.stats.active_businesses ?? 0,      color: 'bg-green-500' },
    { label: 'Suspended Shops',      value: props.stats.suspended_businesses ?? 0,   color: 'bg-red-500' },
    { label: 'Active Subscriptions', value: props.stats.active_subscriptions ?? 0,   color: 'bg-indigo-500' },
])
</script>

<template>
    <Head title="Platform Dashboard" />

    <!-- Stat cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
        <div
            v-for="card in statCards"
            :key="card.label"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4"
        >
            <div :class="[card.color, 'w-10 h-10 rounded-lg flex items-center justify-center']">
                <span class="text-white font-bold text-lg">📊</span>
            </div>
            <div>
                <p class="text-2xl font-bold text-gray-900">{{ card.value }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ card.label }}</p>
            </div>
        </div>
    </div>

    <!-- Recent Businesses -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-sm font-semibold text-gray-900">Recent Shops</h2>
            <Link :href="route('platform.businesses.index')" class="text-xs text-primary-600 hover:underline">View all →</Link>
        </div>

        <div v-if="recentBusinesses.length === 0" class="text-sm text-gray-400 py-4 text-center">
            No shops yet.
            <Link :href="route('platform.businesses.create')" class="text-primary-600 hover:underline">Create the first one</Link>.
        </div>

        <table v-else class="w-full text-sm">
            <thead>
                <tr class="text-xs font-medium text-gray-400 uppercase tracking-wide border-b border-gray-100">
                    <th class="text-left pb-2">Shop</th>
                    <th class="text-left pb-2">Plan</th>
                    <th class="text-left pb-2">Status</th>
                    <th class="text-left pb-2">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <tr v-for="biz in recentBusinesses" :key="biz.id" class="hover:bg-gray-50 transition">
                    <td class="py-2.5">
                        <Link :href="route('platform.businesses.show', biz.id)"
                              class="font-medium text-gray-900 hover:text-primary-600">
                            {{ biz.name }}
                        </Link>
                        <p class="text-xs text-gray-400">{{ biz.slug }}</p>
                    </td>
                    <td class="py-2.5 text-gray-600">{{ biz.active_subscription?.plan?.name ?? '—' }}</td>
                    <td class="py-2.5">
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="{
                                'bg-green-100 text-green-700': biz.status === 'active',
                                'bg-red-100 text-red-700': biz.status === 'suspended',
                                'bg-yellow-100 text-yellow-700': biz.status === 'pending',
                            }"
                        >
                            {{ biz.status }}
                        </span>
                    </td>
                    <td class="py-2.5 text-gray-400 text-xs">{{ biz.created_at }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
