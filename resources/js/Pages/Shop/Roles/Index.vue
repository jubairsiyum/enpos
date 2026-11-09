<script setup>
import { ref, reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    roles: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const perPage = ref(props.filters.per_page ?? 25)

const columns = reactive([
    { key: 'name', label: 'Roles', visible: true },
])

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('shop.roles.index'), { search: val, per_page: perPage.value }, { preserveState: true, replace: true })
    }, 300)
})

const changePerPage = () => {
    router.get(route('shop.roles.index'), { search: search.value, per_page: perPage.value }, { preserveState: true })
}

const deleteRole = (role) => {
    if (confirm(`Are you sure you want to delete role "${role.name}"?`)) {
        router.delete(route('shop.roles.destroy', role.id))
    }
}
</script>

<template>
    <Head title="Roles - Manage roles" />

    <div class="space-y-4">
        <!-- Breadcrumb / Title -->
        <div>
            <h1 class="text-xl font-bold text-gray-900 inline-block mr-2">Roles</h1>
            <span class="text-sm text-gray-500">Manage roles</span>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All roles</h2>
                <Link
                    v-if="can.create"
                    :href="route('shop.roles.create')"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
                >
                    <span class="text-base leading-none">+</span> Add
                </Link>
            </div>

            <!-- Controls row -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span>Show</span>
                    <select
                        v-model="perPage"
                        @change="changePerPage"
                        class="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>entries</span>
                </div>

                <!-- Export & Visibility Buttons -->
                <DataTableActions
                    :columns="columns"
                    :data="roles.data"
                    filename="roles_export"
                />

                <!-- Search -->
                <div class="flex items-center">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search ..."
                        class="border border-gray-300 rounded-md px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 w-48 sm:w-64"
                    />
                </div>
            </div>

            <!-- Roles Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/50 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <th class="py-3 px-4">
                                <span class="flex items-center gap-1">Roles <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="roles.data.length === 0">
                            <td colspan="2" class="py-6 text-center text-gray-400">
                                No roles found.
                            </td>
                        </tr>
                        <tr
                            v-for="role in roles.data"
                            :key="role.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td class="py-3 px-4 font-medium text-gray-900 capitalize">
                                {{ role.name }}
                                <span v-if="role.is_system" class="ml-2 px-1.5 py-0.5 text-[10px] bg-gray-100 text-gray-500 rounded">
                                    System
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 justify-center">
                                    <Link
                                        v-if="can.edit"
                                        :href="route('shop.roles.edit', role.id)"
                                        class="px-2.5 py-1 border border-indigo-500 text-indigo-600 hover:bg-indigo-50 rounded text-xs font-medium flex items-center gap-1 transition"
                                    >
                                        ✏️ Edit
                                    </Link>
                                    <button
                                        v-if="can.delete && !role.is_system"
                                        type="button"
                                        @click="deleteRole(role)"
                                        class="px-2.5 py-1 border border-red-400 text-red-500 hover:bg-red-50 rounded text-xs font-medium flex items-center gap-1 transition"
                                    >
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination info & controls -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Showing {{ roles.from ?? 0 }} to {{ roles.to ?? 0 }} of {{ roles.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-if="roles.prev_page_url"
                        :href="roles.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Previous
                    </Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">
                        Previous
                    </span>

                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ roles.current_page }}
                    </span>

                    <Link
                        v-if="roles.next_page_url"
                        :href="roles.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Next
                    </Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">
                        Next
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
