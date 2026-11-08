<script setup>
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const page = usePage()
const business = page.props.auth?.business
</script>

<template>
    <Head title="Dashboard" />

    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <h2 class="text-lg font-semibold text-gray-900">
            Welcome to {{ business?.name ?? 'your shop' }}
        </h2>
        <p class="mt-1 text-sm text-gray-500">
            Pick a module from the sidebar to get started.
        </p>
    </div>
</template>
