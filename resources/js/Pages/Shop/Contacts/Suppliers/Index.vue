<script setup>
import { ref, reactive, watch } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    contacts: { type: Object, required: true },
    filters:  { type: Object, default: () => ({}) },
    can:      { type: Object, default: () => ({}) },
})

const search  = ref(props.filters.search ?? '')
const perPage = ref(props.filters.per_page ?? 25)

const isModalOpen    = ref(false)
const editingContact = ref(null)

// ── columns for DataTable export / visibility toggle ─────────────────────────
const columns = reactive([
    { key: 'contact_id',             label: 'Contact ID',                 visible: true },
    { key: 'supplier_business_name', label: 'Business Name',              visible: true },
    { key: 'name',                   label: 'Name',                       visible: true },
    { key: 'email',                  label: 'Email',                      visible: true },
    { key: 'tax_number',             label: 'Tax Number',                 visible: true },
    { key: 'opening_balance',        label: 'Opening Balance',            visible: true },
    { key: 'advance_balance',        label: 'Advance Balance',            visible: true },
    { key: 'created_at',             label: 'Added On',                   visible: true },
    { key: 'address',                label: 'Address',                    visible: true },
    { key: 'mobile',                 label: 'Mobile',                     visible: true },
    { key: 'total_purchase_due',     label: 'Total Purchase Due',         visible: true },
    { key: 'total_purchase_return_due', label: 'Total Purchase Return Due', visible: true },
    { key: 'custom_field1',          label: 'Custom Field 1',             visible: false },
    { key: 'custom_field2',          label: 'Custom Field 2',             visible: false },
    { key: 'custom_field3',          label: 'Custom Field 3',             visible: false },
    { key: 'custom_field4',          label: 'Custom Field 4',             visible: false },
])

// ── form ─────────────────────────────────────────────────────────────────────
const form = useForm({
    type:                   'supplier',
    contact_type_individual: 'individual',  // individual | business (UI only)
    supplier_business_name: '',
    prefix:                 '',
    first_name:             '',
    last_name:              '',
    email:                  '',
    mobile:                 '',
    landline:               '',
    alternate_number:       '',
    tax_number:             '',
    opening_balance:        '',
    pay_term:               '',
    address_line_1:         '',
    address_line_2:         '',
    city:                   '',
    state:                  '',
    country:                '',
    zip_code:               '',
    shipping_address:       '',
    custom_field1:          '',
    custom_field2:          '',
    custom_field3:          '',
    custom_field4:          '',
    custom_field5:          '',
    custom_field6:          '',
    custom_field7:          '',
    custom_field8:          '',
    custom_field9:          '',
    custom_field10:         '',
    // contact persons
    contact_persons: [],
})

// ── search with debounce ──────────────────────────────────────────────────────
let searchTimeout = null
watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => {
        router.get(
            route('shop.contacts.index'),
            { type: 'supplier', search: val, per_page: perPage.value },
            { preserveState: true, replace: true }
        )
    }, 300)
})

const changePerPage = () => {
    router.get(
        route('shop.contacts.index'),
        { type: 'supplier', search: search.value, per_page: perPage.value },
        { preserveState: true }
    )
}

// ── modal helpers ─────────────────────────────────────────────────────────────
const openCreateModal = () => {
    editingContact.value = null
    form.reset()
    form.clearErrors()
    form.type = 'supplier'
    form.contact_type_individual = 'individual'
    isModalOpen.value = true
}

const openEditModal = (contact) => {
    editingContact.value = contact
    form.clearErrors()
    form.type                   = contact.type ?? 'supplier'
    form.contact_type_individual = 'individual'
    form.supplier_business_name = contact.supplier_business_name ?? ''
    form.prefix                 = ''
    form.first_name             = contact.name?.split(' ')[0] ?? ''
    form.last_name              = contact.name?.split(' ').slice(1).join(' ') ?? ''
    form.email                  = contact.email ?? ''
    form.mobile                 = contact.mobile ?? ''
    form.landline               = ''
    form.alternate_number       = ''
    form.tax_number             = contact.tax_number ?? ''
    form.opening_balance        = contact.opening_balance ?? ''
    form.pay_term               = ''
    form.address_line_1         = ''
    form.address_line_2         = ''
    form.city                   = ''
    form.state                  = ''
    form.country                = ''
    form.zip_code               = ''
    form.shipping_address       = ''
    form.custom_field1          = contact.custom_field1 ?? ''
    form.custom_field2          = contact.custom_field2 ?? ''
    form.custom_field3          = contact.custom_field3 ?? ''
    form.custom_field4          = contact.custom_field4 ?? ''
    form.custom_field5          = ''
    form.custom_field6          = ''
    form.custom_field7          = ''
    form.custom_field8          = ''
    form.custom_field9          = ''
    form.custom_field10         = ''
    isModalOpen.value = true
}

const submitForm = () => {
    const payload = { ...form.data() }
    // Remove UI-only key
    delete payload.contact_type_individual
    delete payload.contact_persons

    if (editingContact.value) {
        form.put(route('shop.contacts.update', editingContact.value.id), {
            onSuccess: () => { isModalOpen.value = false },
        })
    } else {
        form.post(route('shop.contacts.store'), {
            onSuccess: () => {
                isModalOpen.value = false
                form.reset()
            },
        })
    }
}

const deleteContact = (contact) => {
    if (confirm(`Are you sure you want to delete contact "${contact.name}"?`)) {
        router.delete(route('shop.contacts.destroy', contact.id))
    }
}

// ── visible columns helper ────────────────────────────────────────────────────
const isVisible = (key) => columns.find(c => c.key === key)?.visible !== false

// ── currency formatter ────────────────────────────────────────────────────────
const currency = (val) => {
    const n = parseFloat(val ?? 0)
    return '$ ' + n.toFixed(2)
}

// ── totals ────────────────────────────────────────────────────────────────────
const totalPurchaseDue = () => props.contacts.data.reduce((s, c) => s + parseFloat(c.total_purchase_due ?? 0), 0)
const totalPurchaseReturnDue = () => props.contacts.data.reduce((s, c) => s + parseFloat(c.total_purchase_return_due ?? 0), 0)
</script>

<template>
    <Head title="Contacts - Suppliers" />

    <div class="space-y-4">
        <!-- Page title -->
        <div>
            <h1 class="text-xl font-bold text-gray-900 inline-block mr-2">Contacts</h1>
            <span class="text-sm text-gray-500">Manage your suppliers</span>
        </div>

        <!-- Flash -->
        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-4 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>

        <!-- Main card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <!-- Header: title + Add button -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All your Suppliers</h2>
                <button
                    v-if="can.create"
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
                >
                    <span class="text-base leading-none">+</span> Add
                </button>
            </div>

            <!-- Controls row -->
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <!-- Per page -->
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

                <!-- Export & visibility -->
                <DataTableActions
                    :columns="columns"
                    :data="contacts.data"
                    filename="suppliers_export"
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

            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/50 text-[11px] font-semibold text-gray-700 uppercase tracking-wider">
                            <th class="py-3 px-3 text-center">Action</th>
                            <th v-if="isVisible('contact_id')"             class="py-3 px-3">Contact ID</th>
                            <th v-if="isVisible('supplier_business_name')" class="py-3 px-3">Business Name</th>
                            <th v-if="isVisible('name')"                   class="py-3 px-3">Name</th>
                            <th v-if="isVisible('email')"                  class="py-3 px-3">Email</th>
                            <th v-if="isVisible('tax_number')"             class="py-3 px-3">Tax Number</th>
                            <th v-if="isVisible('opening_balance')"        class="py-3 px-3">Opening Balance</th>
                            <th v-if="isVisible('advance_balance')"        class="py-3 px-3">Advance Balance</th>
                            <th v-if="isVisible('created_at')"             class="py-3 px-3">Added On</th>
                            <th v-if="isVisible('address')"                class="py-3 px-3">Address</th>
                            <th v-if="isVisible('mobile')"                 class="py-3 px-3">Mobile</th>
                            <th v-if="isVisible('total_purchase_due')"     class="py-3 px-3">Total Purchase Due</th>
                            <th v-if="isVisible('total_purchase_return_due')" class="py-3 px-3">Total Purchase Return Due</th>
                            <th v-if="isVisible('custom_field1')"          class="py-3 px-3">Custom Field 1</th>
                            <th v-if="isVisible('custom_field2')"          class="py-3 px-3">Custom Field 2</th>
                            <th v-if="isVisible('custom_field3')"          class="py-3 px-3">Custom Field 3</th>
                            <th v-if="isVisible('custom_field4')"          class="py-3 px-3">Custom Field 4</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="contacts.data.length === 0">
                            <td colspan="20" class="py-8 text-center text-gray-400">No suppliers found.</td>
                        </tr>
                        <tr
                            v-for="contact in contacts.data"
                            :key="contact.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <!-- Action -->
                            <td class="py-3 px-3 text-center">
                                <div class="inline-flex items-center gap-1 justify-center">
                                    <button
                                        v-if="can.edit"
                                        type="button"
                                        @click="openEditModal(contact)"
                                        class="px-2.5 py-1 border border-indigo-500 text-indigo-600 hover:bg-indigo-50 rounded text-[11px] font-medium transition"
                                    >
                                        ✏️ Edit
                                    </button>
                                    <button
                                        v-if="can.delete"
                                        type="button"
                                        @click="deleteContact(contact)"
                                        class="px-2.5 py-1 border border-red-400 text-red-500 hover:bg-red-50 rounded text-[11px] font-medium transition"
                                    >
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                            <td v-if="isVisible('contact_id')"             class="py-3 px-3 font-medium text-gray-900">{{ contact.contact_id }}</td>
                            <td v-if="isVisible('supplier_business_name')" class="py-3 px-3">{{ contact.supplier_business_name ?? '-' }}</td>
                            <td v-if="isVisible('name')"                   class="py-3 px-3">{{ contact.name }}</td>
                            <td v-if="isVisible('email')"                  class="py-3 px-3">{{ contact.email ?? '-' }}</td>
                            <td v-if="isVisible('tax_number')"             class="py-3 px-3">{{ contact.tax_number ?? '-' }}</td>
                            <td v-if="isVisible('opening_balance')"        class="py-3 px-3">{{ currency(contact.opening_balance) }}</td>
                            <td v-if="isVisible('advance_balance')"        class="py-3 px-3">{{ currency(contact.advance_balance) }}</td>
                            <td v-if="isVisible('created_at')"             class="py-3 px-3">{{ contact.created_at }}</td>
                            <td v-if="isVisible('address')"                class="py-3 px-3">{{ contact.address || '-' }}</td>
                            <td v-if="isVisible('mobile')"                 class="py-3 px-3">{{ contact.mobile ?? '-' }}</td>
                            <td v-if="isVisible('total_purchase_due')"     class="py-3 px-3">{{ currency(contact.total_purchase_due) }}</td>
                            <td v-if="isVisible('total_purchase_return_due')" class="py-3 px-3">{{ currency(contact.total_purchase_return_due) }}</td>
                            <td v-if="isVisible('custom_field1')"          class="py-3 px-3">{{ contact.custom_field1 ?? '' }}</td>
                            <td v-if="isVisible('custom_field2')"          class="py-3 px-3">{{ contact.custom_field2 ?? '' }}</td>
                            <td v-if="isVisible('custom_field3')"          class="py-3 px-3">{{ contact.custom_field3 ?? '' }}</td>
                            <td v-if="isVisible('custom_field4')"          class="py-3 px-3">{{ contact.custom_field4 ?? '' }}</td>
                        </tr>
                    </tbody>
                    <!-- Totals row -->
                    <tfoot v-if="contacts.data.length > 0">
                        <tr class="border-t-2 border-gray-300 font-semibold text-gray-800 bg-gray-50/50 text-xs">
                            <td class="py-3 px-3" colspan="11">Total:</td>
                            <td v-if="isVisible('total_purchase_due')"        class="py-3 px-3">{{ currency(totalPurchaseDue()) }}</td>
                            <td v-if="isVisible('total_purchase_return_due')" class="py-3 px-3">{{ currency(totalPurchaseReturnDue()) }}</td>
                            <td v-if="isVisible('custom_field1')" class="py-3 px-3"></td>
                            <td v-if="isVisible('custom_field2')" class="py-3 px-3"></td>
                            <td v-if="isVisible('custom_field3')" class="py-3 px-3"></td>
                            <td v-if="isVisible('custom_field4')" class="py-3 px-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Showing {{ contacts.from ?? 0 }} to {{ contacts.to ?? 0 }} of {{ contacts.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <a
                        v-if="contacts.prev_page_url"
                        :href="contacts.prev_page_url"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Previous</a>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Previous</span>

                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ contacts.current_page }}
                    </span>

                    <a
                        v-if="contacts.next_page_url"
                        :href="contacts.next_page_url"
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Next</a>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Next</span>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         ADD / EDIT CONTACT MODAL
         ===================================================================== -->
    <Teleport to="body">
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-8 px-4"
            @click.self="isModalOpen = false"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-3xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-sm font-semibold text-gray-800">
                        {{ editingContact ? 'Edit Contact' : 'Add a new contact' }}
                    </h3>
                    <button
                        type="button"
                        @click="isModalOpen = false"
                        class="text-gray-400 hover:text-gray-600 transition text-lg leading-none"
                    >✕</button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">

                    <!-- Row 1: Contact type + Individual/Business toggle + Contact ID -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Contact type*</label>
                            <select
                                v-model="form.type"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="supplier">Supplier</option>
                                <option value="customer">Customer</option>
                                <option value="both">Both</option>
                            </select>
                            <p v-if="form.errors.type" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.type }}</p>
                        </div>

                        <!-- Individual / Business radio -->
                        <div class="flex items-end gap-4 pb-1">
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700">
                                <input type="radio" v-model="form.contact_type_individual" value="individual" class="text-indigo-600" />
                                Individual
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700">
                                <input type="radio" v-model="form.contact_type_individual" value="business" class="text-indigo-600" />
                                Business
                            </label>
                        </div>

                        <!-- Contact ID (auto) -->
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Contact ID</label>
                            <input
                                type="text"
                                disabled
                                placeholder="Contact ID"
                                class="w-full border border-gray-200 rounded px-3 py-2 text-xs bg-gray-50 text-gray-400"
                            />
                            <p class="text-[10px] text-gray-400 mt-0.5">Leave empty to auto-generate</p>
                        </div>
                    </div>

                    <!-- Row 2: Mobile + Alternate contact + Landline + Email -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Mobile*</label>
                            <input
                                v-model="form.mobile"
                                type="text"
                                placeholder="Mobile"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Alternate contact no:</label>
                            <input
                                v-model="form.alternate_number"
                                type="text"
                                placeholder="Alternate contact no"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Landline:</label>
                            <input
                                v-model="form.landline"
                                type="text"
                                placeholder="Landline"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-700 mb-1">Email:</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Email"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.email" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <!-- More Information collapsible section -->
                    <details class="border border-gray-200 rounded-lg" open>
                        <summary class="px-4 py-3 text-xs font-semibold text-gray-700 cursor-pointer select-none bg-gray-50 rounded-lg">
                            More Information ▾
                        </summary>
                        <div class="px-4 py-4 space-y-4">
                            <!-- Tax + Opening Balance + Pay term -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Tax number:</label>
                                    <input
                                        v-model="form.tax_number"
                                        type="text"
                                        placeholder="Tax number"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Opening Balance:</label>
                                    <div class="flex">
                                        <span class="inline-flex items-center px-2 border border-r-0 border-gray-300 rounded-l bg-gray-50 text-gray-500 text-xs">$</span>
                                        <input
                                            v-model="form.opening_balance"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            placeholder="0.00"
                                            class="flex-1 border border-gray-300 rounded-r px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Pay term:</label>
                                    <div class="flex gap-2">
                                        <input
                                            v-model="form.pay_term"
                                            type="number"
                                            min="1"
                                            placeholder="Pay term"
                                            class="flex-1 border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                        />
                                        <select class="border border-gray-300 rounded px-2 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500">
                                            <option>Please Select</option>
                                            <option>Days</option>
                                            <option>Months</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Address fields -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Address line 1:</label>
                                    <input
                                        v-model="form.address_line_1"
                                        type="text"
                                        placeholder="Address line 1"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Address line 2:</label>
                                    <input
                                        v-model="form.address_line_2"
                                        type="text"
                                        placeholder="Address line 2"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">City:</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        placeholder="City"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">State:</label>
                                    <input
                                        v-model="form.state"
                                        type="text"
                                        placeholder="State"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Country:</label>
                                    <input
                                        v-model="form.country"
                                        type="text"
                                        placeholder="Country"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Zip Code:</label>
                                    <input
                                        v-model="form.zip_code"
                                        type="text"
                                        placeholder="Zip Code"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <!-- Custom fields (10 fields in 2 rows of 5) -->
                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                                <div v-for="n in 10" :key="n">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Custom Field {{ n }}:</label>
                                    <input
                                        v-model="form[`custom_field${n}`]"
                                        type="text"
                                        :placeholder="`Custom Field ${n}`"
                                        class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                </div>
                            </div>

                            <!-- Shipping Address -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Shipping Address:</label>
                                <input
                                    v-model="form.shipping_address"
                                    type="text"
                                    placeholder="Shipping address"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>
                    </details>

                    <!-- Modal Actions -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded font-medium text-xs shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save' }}
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
    </Teleport>
</template>
