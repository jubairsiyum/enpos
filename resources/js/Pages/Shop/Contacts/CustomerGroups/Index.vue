<script setup>
import { reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    customerGroups:     { type: Object, required: true },
    sellingPriceGroups: { type: Array, default: () => [] },
    filters:            { type: Object, default: () => ({}) },
    can:                { type: Object, default: () => ({}) },
})

const search  = ref(props.filters.search ?? '')
const perPage = ref(Number(props.filters.per_page ?? 25))

const isModalOpen  = ref(false)
const editingGroup = ref(null)

const columns = reactive([
    { key: 'name',                     label: 'Customer Group Name',        visible: true },
    { key: 'amount',                   label: 'Calculation Percentage (%)', visible: true },
    { key: 'selling_price_group_name', label: 'Selling Price Group',        visible: true },
])

const isVisible = (key) => columns.find(c => c.key === key)?.visible !== false

const form = useForm({
    name:                   '',
    price_calculation_type: 'percentage',
    amount:                 '',
    selling_price_group_id: '',
})

const applyFilters = (replace = false) => {
    router.get(
        route('shop.customer-groups.index'),
        {
            search:   search.value || undefined,
            per_page: perPage.value,
        },
        { preserveState: true, replace }
    )
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(true), 300)
})

const openCreateModal = () => {
    editingGroup.value = null
    form.reset()
    form.clearErrors()
    form.price_calculation_type = 'percentage'
    isModalOpen.value = true
}

const openEditModal = (group) => {
    editingGroup.value = group
    form.clearErrors()
    form.name                   = group.name ?? ''
    form.price_calculation_type = group.price_calculation_type ?? 'percentage'
    form.amount                 = group.amount ?? ''
    form.selling_price_group_id = group.selling_price_group_id ?? ''
    isModalOpen.value = true
}

const submitForm = () => {
    if (editingGroup.value) {
        form.put(route('shop.customer-groups.update', editingGroup.value.id), {
            onSuccess: () => {
                isModalOpen.value = false
            },
        })
    } else {
        form.post(route('shop.customer-groups.store'), {
            onSuccess: () => {
                isModalOpen.value = false
                form.reset()
                form.price_calculation_type = 'percentage'
            },
        })
    }
}

const deleteGroup = (group) => {
    if (confirm(`Are you sure you want to delete customer group "${group.name}"?`)) {
        router.delete(route('shop.customer-groups.destroy', group.id))
    }
}
</script>

<template>
    <Head title="Contacts - Customer Groups" />

    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900 inline-block mr-2">Customer Groups</h1>
        </div>

        <!-- Flash -->
        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All Customer Groups</h2>
                <button
                    v-if="can.create"
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-full shadow-sm transition"
                >
                    <span class="text-base leading-none">+</span> Add
                </button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-2 text-xs text-gray-600">
                    <span>Show</span>
                    <select
                        v-model="perPage"
                        @change="applyFilters()"
                        class="border border-gray-300 rounded px-2 py-1 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                    >
                        <option :value="10">10</option>
                        <option :value="25">25</option>
                        <option :value="50">50</option>
                        <option :value="100">100</option>
                    </select>
                    <span>entries</span>
                </div>

                <DataTableActions
                    :columns="columns"
                    :data="customerGroups.data"
                    filename="customer_groups_export"
                />

                <div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search ..."
                        class="border border-gray-300 rounded-md px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 w-48 sm:w-64"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/60 text-[11px] font-semibold text-gray-700 tracking-wide">
                            <th v-if="isVisible('name')" class="py-3 px-4">Customer Group Name</th>
                            <th v-if="isVisible('amount')" class="py-3 px-4">Calculation Percentage (%)</th>
                            <th v-if="isVisible('selling_price_group_name')" class="py-3 px-4">Selling Price Group</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="customerGroups.data.length === 0">
                            <td colspan="4" class="py-8 text-center text-gray-400">No data available in table</td>
                        </tr>
                        <tr
                            v-for="group in customerGroups.data"
                            :key="group.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td v-if="isVisible('name')" class="py-3 px-4 font-medium text-gray-900">{{ group.name }}</td>
                            <td v-if="isVisible('amount')" class="py-3 px-4">
                                {{ group.price_calculation_type === 'percentage' && group.amount !== null ? `${group.amount}%` : '-' }}
                            </td>
                            <td v-if="isVisible('selling_price_group_name')" class="py-3 px-4">
                                {{ group.price_calculation_type === 'selling_price_group' ? (group.selling_price_group_name || '-') : '-' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex items-center gap-1.5 justify-center">
                                    <button
                                        v-if="can.edit"
                                        type="button"
                                        @click="openEditModal(group)"
                                        class="px-2.5 py-1 border border-indigo-500 text-indigo-600 hover:bg-indigo-50 rounded text-[11px] font-medium transition"
                                    >
                                        ✏️ Edit
                                    </button>
                                    <button
                                        v-if="can.delete"
                                        type="button"
                                        @click="deleteGroup(group)"
                                        class="px-2.5 py-1 border border-red-400 text-red-500 hover:bg-red-50 rounded text-[11px] font-medium transition"
                                    >
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Showing {{ customerGroups.from ?? 0 }} to {{ customerGroups.to ?? 0 }} of {{ customerGroups.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-if="customerGroups.prev_page_url"
                        :href="customerGroups.prev_page_url"
                        preserve-state
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Previous</Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Previous</span>

                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ customerGroups.current_page ?? 1 }}
                    </span>

                    <Link
                        v-if="customerGroups.next_page_url"
                        :href="customerGroups.next_page_url"
                        preserve-state
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Next</Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Next</span>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT CUSTOMER GROUP MODAL (matching screenshot) -->
    <Teleport to="body">
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-16 px-4"
            @click.self="isModalOpen = false"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ editingGroup ? 'Edit Customer Group' : 'Add Customer Group' }}
                    </h3>
                    <button
                        type="button"
                        @click="isModalOpen = false"
                        class="text-gray-400 hover:text-gray-600 transition text-lg leading-none"
                    >✕</button>
                </div>

                <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-800 mb-1.5">
                            Customer Group Name:*
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Customer Group Name"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-[10px] mt-1">{{ form.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-800 mb-1.5">
                            Price calculation type:
                        </label>
                        <select
                            v-model="form.price_calculation_type"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="percentage">Percentage</option>
                            <option value="selling_price_group">Selling Price Group</option>
                        </select>
                        <p v-if="form.errors.price_calculation_type" class="text-red-500 text-[10px] mt-1">
                            {{ form.errors.price_calculation_type }}
                        </p>
                    </div>

                    <div v-if="form.price_calculation_type === 'percentage'">
                        <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-800 mb-1.5">
                            <span>Calculation Percentage (%):</span>
                            <InfoTooltip text="Selling price = Selling price Set For the product + Calculation percentage. You can specify percentage as positive to increase and negative to decrease selling price." />
                        </label>
                        <input
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="-100"
                            max="999.99"
                            placeholder="Calculation Percentage (%)"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.amount" class="text-red-500 text-[10px] mt-1">{{ form.errors.amount }}</p>
                    </div>

                    <div v-else>
                        <label class="block text-xs font-semibold text-gray-800 mb-1.5">
                            Selling Price Group:
                        </label>
                        <select
                            v-model="form.selling_price_group_id"
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Please Select</option>
                            <option v-for="spg in sellingPriceGroups" :key="spg.id" :value="spg.id">
                                {{ spg.name }}
                            </option>
                        </select>
                        <p v-if="form.errors.selling_price_group_id" class="text-red-500 text-[10px] mt-1">
                            {{ form.errors.selling_price_group_id }}
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-[#4f2bed] hover:bg-indigo-700 text-white rounded-lg font-medium text-xs shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </button>
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 bg-[#1f2937] hover:bg-slate-900 text-white rounded-lg font-medium text-xs transition"
                        >
                            Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>
</template>
