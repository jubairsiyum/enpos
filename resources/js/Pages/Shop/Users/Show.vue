<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    user: { type: Object, required: true },
})
</script>

<template>
    <Head :title="user.name" />

    <div class="max-w-4xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">User Details</h1>
            <Link :href="route('shop.users.index')" class="text-xs text-indigo-600 hover:underline">
                ← Back to users
            </Link>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-gray-100 pb-4">
                <div>
                    <h2 class="text-lg font-bold text-gray-900">{{ user.name }}</h2>
                    <p class="text-xs text-gray-500">Username: {{ user.username ?? '-' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="[user.is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700', 'px-2 py-0.5 rounded text-xs font-semibold']">
                        {{ user.is_active ? 'Active' : 'Inactive' }}
                    </span>
                    <Link
                        :href="route('shop.users.edit', user.id)"
                        class="px-3 py-1 bg-indigo-50 border border-indigo-200 text-indigo-600 hover:bg-indigo-100 rounded text-xs font-medium"
                    >
                        Edit User
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                <div><span class="text-gray-400">Email:</span> <span class="font-medium text-gray-800">{{ user.email }}</span></div>
                <div><span class="text-gray-400">Phone:</span> <span class="font-medium text-gray-800">{{ user.phone ?? '-' }}</span></div>
                <div><span class="text-gray-400">Role:</span> <span class="font-medium text-gray-800 capitalize">{{ user.roles?.[0]?.name ?? user.type }}</span></div>
                <div><span class="text-gray-400">Commission Rate:</span> <span class="font-medium text-gray-800">{{ user.commission_rate ? user.commission_rate + '%' : '0%' }}</span></div>
                <div><span class="text-gray-400">Date of Birth:</span> <span class="font-medium text-gray-800">{{ user.dob ? user.dob.substring(0, 10) : '-' }}</span></div>
                <div><span class="text-gray-400">Blood Group:</span> <span class="font-medium text-gray-800">{{ user.blood_group ?? '-' }}</span></div>
                <div><span class="text-gray-400">Permanent Address:</span> <span class="font-medium text-gray-800">{{ user.permanent_address ?? '-' }}</span></div>
                <div><span class="text-gray-400">Current Address:</span> <span class="font-medium text-gray-800">{{ user.current_address ?? '-' }}</span></div>
            </div>
        </div>
    </div>
</template>
