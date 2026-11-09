<script setup>
import { ref, reactive, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    agents: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
})

const search = ref(props.filters.search ?? '')
const perPage = ref(props.filters.per_page ?? 25)
const isModalOpen = ref(false)
const editingAgent = ref(null)

const columns = reactive([
    { key: 'name', label: 'Name', visible: true },
    { key: 'email', label: 'Email', visible: true },
    { key: 'phone', label: 'Contact Number', visible: true },
    { key: 'address', label: 'Address', visible: true },
    { key: 'commission_rate', label: 'Sales Commission Percentage (%)', visible: true },
])

const form = useForm({
    prefix: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    permanent_address: '',
    commission_rate: '',
    is_active: true,
})

let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(route('shop.sales-commission-agents.index'), { search: val, per_page: perPage.value }, { preserveState: true, replace: true })
    }, 300)
})

const changePerPage = () => {
    router.get(route('shop.sales-commission-agents.index'), { search: search.value, per_page: perPage.value }, { preserveState: true })
}

const openCreateModal = () => {
    editingAgent.value = null
    form.reset()
    form.clearErrors()
    isModalOpen.value = true
}

const openEditModal = (agent) => {
    editingAgent.value = agent
    form.clearErrors()
    form.prefix = ''
    form.first_name = agent.name?.split(' ')[0] ?? ''
    form.last_name = agent.name?.split(' ').slice(1).join(' ') ?? ''
    form.email = agent.email?.includes('@agent.local') ? '' : (agent.email ?? '')
    form.phone = agent.phone === '-' ? '' : agent.phone
    form.permanent_address = agent.address === '-' ? '' : agent.address
    form.commission_rate = agent.commission_rate ? agent.commission_rate.replace('%', '') : ''
    form.is_active = Boolean(agent.is_active)
    isModalOpen.value = true
}

const submitForm = () => {
    if (editingAgent.value) {
        form.put(route('shop.sales-commission-agents.update', editingAgent.value.id), {
            onSuccess: () => { isModalOpen.value = false },
        })
    } else {
        form.post(route('shop.sales-commission-agents.store'), {
            onSuccess: () => {
                isModalOpen.value = false
                form.reset()
            },
        })
    }
}

const deleteAgent = (agent) => {
    if (confirm(`Are you sure you want to delete commission agent "${agent.name}"?`)) {
        router.delete(route('shop.sales-commission-agents.destroy', agent.id))
    }
}
</script>

<template>
    <Head title="Sales Commission Agents" />

    <div class="space-y-4">
        <!-- Title -->
        <div>
            <h1 class="text-xl font-bold text-gray-900 inline-block">Sales Commission Agents</h1>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <!-- Controls row matching Image 3 -->
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
                    :data="agents.data"
                    filename="sales_commission_agents_export"
                />

                <!-- Search & Add Button -->
                <div class="flex items-center gap-3">
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search ..."
                        class="border border-gray-300 rounded-md px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 w-48 sm:w-60"
                    />
                    <button
                        v-if="can.create"
                        type="button"
                        @click="openCreateModal"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
                    >
                        <span class="text-base leading-none">+</span> Add
                    </button>
                </div>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/50 text-xs font-semibold text-gray-700 tracking-wider">
                            <th v-if="columns.find(c => c.key === 'name')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Name <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'email')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Email <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'phone')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Contact Number <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'address')?.visible !== false" class="py-3 px-4">
                                <span>Address</span>
                            </th>
                            <th v-if="columns.find(c => c.key === 'commission_rate')?.visible !== false" class="py-3 px-4">
                                <span class="flex items-center gap-1">Sales Commission Percentage (%) <span class="text-gray-400">⇅</span></span>
                            </th>
                            <th class="py-3 px-4 text-center">
                                <span class="flex items-center justify-center gap-1">Action <span class="text-gray-400">⇅</span></span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="agents.data.length === 0">
                            <td colspan="6" class="py-6 text-center text-gray-400">
                                No data available in table
                            </td>
                        </tr>
                        <tr
                            v-for="agent in agents.data"
                            :key="agent.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td v-if="columns.find(c => c.key === 'name')?.visible !== false" class="py-3 px-4 font-medium text-gray-900">
                                {{ agent.name }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'email')?.visible !== false" class="py-3 px-4">
                                {{ agent.email?.includes('@agent.local') ? '-' : agent.email }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'phone')?.visible !== false" class="py-3 px-4">
                                {{ agent.phone }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'address')?.visible !== false" class="py-3 px-4">
                                {{ agent.address }}
                            </td>
                            <td v-if="columns.find(c => c.key === 'commission_rate')?.visible !== false" class="py-3 px-4 font-semibold text-gray-800">
                                {{ agent.commission_rate }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 justify-center">
                                    <button
                                        v-if="can.edit"
                                        type="button"
                                        @click="openEditModal(agent)"
                                        class="px-2.5 py-1 border border-indigo-500 text-indigo-600 hover:bg-indigo-50 rounded text-xs font-medium flex items-center gap-1 transition"
                                    >
                                        ✏️ Edit
                                    </button>
                                    <button
                                        v-if="can.delete"
                                        type="button"
                                        @click="deleteAgent(agent)"
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

            <!-- Pagination info & controls matching Image 3 -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Showing {{ agents.from ?? 0 }} to {{ agents.to ?? 0 }} of {{ agents.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <button
                        v-if="agents.prev_page_url"
                        @click="router.get(agents.prev_page_url)"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Previous
                    </button>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">
                        Previous
                    </span>

                    <span v-if="agents.total > 0" class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ agents.current_page }}
                    </span>

                    <button
                        v-if="agents.next_page_url"
                        @click="router.get(agents.next_page_url)"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >
                        Next
                    </button>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">
                        Next
                    </span>
                </div>
            </div>
        </div>

        <!-- Add/Edit Modal matching Image 4 -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-lg shadow-2xl max-w-xl w-full p-6 space-y-4">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-gray-200 pb-3">
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ editingAgent ? 'Edit sales commission agent' : 'Add sales commission agent' }}
                    </h3>
                    <button type="button" @click="isModalOpen = false" class="text-gray-400 hover:text-gray-600 text-lg leading-none">
                        ✕
                    </button>
                </div>

                <!-- Modal Form -->
                <form @submit.prevent="submitForm" class="space-y-4 text-xs">
                    <!-- Row 1: Prefix, First Name, Last Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Prefix:</label>
                            <input
                                v-model="form.prefix"
                                type="text"
                                placeholder="Mr / M"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">First Name:*</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                required
                                placeholder="First Name"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.first_name" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Last Name:</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                placeholder="Last Name"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Row 2: Email, Contact Number -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Email:</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Email"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.email" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 font-medium mb-1">Contact Number:</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                placeholder="Contact Number"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Row 3: Address -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Address:</label>
                        <textarea
                            v-model="form.permanent_address"
                            rows="3"
                            placeholder="Address"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        ></textarea>
                    </div>

                    <!-- Row 4: Sales Commission Percentage (%) -->
                    <div>
                        <label class="block text-gray-700 font-medium mb-1">Sales Commission Percentage (%):</label>
                        <input
                            v-model="form.commission_rate"
                            type="number"
                            step="0.01"
                            placeholder="Sales Commission Percentage (%)"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.commission_rate" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.commission_rate }}</p>
                    </div>

                    <!-- Modal Actions matching Image 4 (Save purple button, Close dark slate button) -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded font-medium text-xs shadow-sm transition disabled:opacity-50"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded font-medium text-xs transition"
                        >
                            Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
