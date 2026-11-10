<script setup>
import { ref, reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    roles: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const perPage = ref(props.filters.per_page ?? 25)

const columns = reactive([
    { key: 'username', label: 'Username', visible: true },
    { key: 'name', label: 'Name', visible: true },
    { key: 'role', label: 'Role', visible: true },
    { key: 'email', label: 'Email', visible: true },
])

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('shop.users.index'), { search: val, per_page: perPage.value }, { preserveState: true, replace: true })
    }, 300)
})

const changePerPage = () => {
    router.get(route('shop.users.index'), { search: search.value, per_page: perPage.value }, { preserveState: true })
}

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete user "${user.name}"?`)) {
        router.delete(route('shop.users.destroy', user.id))
    }
}
</script>

<template>
    <Head title="Users - Manage users" />

    <div class="space-y-4">
        <!-- Breadcrumb / Title -->
        <div>
            <h1 class="text-xl font-bold text-gray-900 inline-block mr-2">Users</h1>
            <span class="text-sm text-gray-500">Manage users</span>
        </div>

        <!-- Flash Alert -->
        <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-4 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <span class="text-green-600 font-bold text-sm">✓</span>
                <span class="font-medium">{{ $page.props.flash.success }}</span>
            </div>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All users</h2>
                <Link
                    v-if="can.create"
                    :href="route('shop.users.create')"
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
                    :data="users.data"
                    filename="users_export"
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

            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/50 text-xs font-semibold text-gray-700 uppercase tracking-wider">
                            <th v-if="columns.find(c => c.key === 'username')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Username <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'name')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Name <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'role')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Role <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'email')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Email <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="py-6 text-center text-gray-400">
                                No users found.
                            </td>
                        </tr>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td v-if="columns.find(c => c.key === 'username')?.visible !== false" class="py-3 px-4 font-medium text-gray-900">
                                {{ user.username }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'name')?.visible !== false" class="py-3 px-4">
                                {{ user.name }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'role')?.visible !== false" class="py-3 px-4 capitalize">
                                {{ user.role }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'email')?.visible !== false" class="py-3 px-4">
                                {{ user.email }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 justify-center">
                                    <Link
                                        v-if="can.edit"
                                        :href="route('shop.users.edit', user.id)"
                                        class="px-2.5 py-1 border border-indigo-500 text-indigo-600 hover:bg-indigo-50 rounded text-xs font-medium flex items-center gap-1 transition"
                                    >
                                        ✏️ Edit
                                    </Link>
                                    <Link
                                        :href="route('shop.users.show', user.id)"
                                        class="px-2.5 py-1 border border-sky-500 text-sky-600 hover:bg-sky-50 rounded text-xs font-medium flex items-center gap-1 transition"
                                    >
                                        👁️ View
                                    </Link>
                                    <button
                                        v-if="can.delete"
                                        type="button"
                                        @click="deleteUser(user)"
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
                    Showing {{ users.from ?? 0 }} to {{ users.to ?? 0 }} of {{ users.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-if="users.prev_page_url"
                        :href="users.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Previous
                    </Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">
                        Previous
                    </span>

                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ users.current_page }}
                    </span>

                    <Link
                        v-if="users.next_page_url"
                        :href="users.next_page_url"
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
