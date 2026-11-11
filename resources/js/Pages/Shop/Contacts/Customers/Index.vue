<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'
import ContactActionDropdown from '@/Components/Contacts/ContactActionDropdown.vue'
import ContactActionModals from '@/Components/Contacts/ContactActionModals.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    customers:      { type: Object, required: true },
    totals:         { type: Object, default: () => ({ total_sell_due: 0, total_sell_return_due: 0 }) },
    customerGroups: { type: Array, default: () => [] },
    users:          { type: Array, default: () => [] },
    filters:        { type: Object, default: () => ({}) },
    can:            { type: Object, default: () => ({}) },
})

// ── Filters & Search ──────────────────────────────────────────────────────────
const showFilters         = ref(false)
const search              = ref(props.filters.search ?? '')
const perPage             = ref(Number(props.filters.per_page ?? 25))
const filterGroupId       = ref(props.filters.customer_group_id ?? '')
const filterAssignedTo    = ref(props.filters.assigned_to ?? '')
const filterNoSellSince   = ref(props.filters.no_sell_since ?? '')
const filterSellDue       = ref(Boolean(props.filters.has_sell_due))
const filterSellReturnDue = ref(Boolean(props.filters.has_sell_return_due))
const filterAdvanceBal    = ref(Boolean(props.filters.has_advance_balance))
const filterOpeningBal    = ref(Boolean(props.filters.has_opening_balance))
const filterOnlyTrashed   = ref(Boolean(props.filters.only_trashed))

const buildFilterParams = () => ({
    search:              search.value || undefined,
    per_page:            perPage.value,
    customer_group_id:   filterGroupId.value || undefined,
    assigned_to:         filterAssignedTo.value || undefined,
    no_sell_since:       filterNoSellSince.value || undefined,
    has_sell_due:        filterSellDue.value ? 1 : undefined,
    has_sell_return_due: filterSellReturnDue.value ? 1 : undefined,
    has_advance_balance: filterAdvanceBal.value ? 1 : undefined,
    has_opening_balance: filterOpeningBal.value ? 1 : undefined,
    only_trashed:        filterOnlyTrashed.value ? 1 : undefined,
})

const applyFilters = (replace = false) => {
    router.get(route('shop.customers.index'), buildFilterParams(), {
        preserveState: true,
        replace,
    })
}

const resetFilters = () => {
    search.value              = ''
    filterGroupId.value       = ''
    filterAssignedTo.value    = ''
    filterNoSellSince.value   = ''
    filterSellDue.value       = false
    filterSellReturnDue.value = false
    filterAdvanceBal.value    = false
    filterOpeningBal.value    = false
    filterOnlyTrashed.value   = false
    applyFilters(true)
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(true), 300)
})

// ── Columns (matching Image 1) ────────────────────────────────────────────────
const columns = reactive([
    { key: 'contact_id',             label: 'Contact ID',           visible: true },
    { key: 'supplier_business_name', label: 'Business Name',        visible: true },
    { key: 'name',                   label: 'Name',                 visible: true },
    { key: 'email',                  label: 'Email',                visible: true },
    { key: 'tax_number',             label: 'Tax number',           visible: true },
    { key: 'credit_limit',           label: 'Credit Limit',         visible: true },
    { key: 'pay_term',               label: 'Pay term',             visible: true },
    { key: 'opening_balance',        label: 'Opening Balance',      visible: true },
    { key: 'advance_balance',        label: 'Advance Balance',      visible: true },
    { key: 'created_at',             label: 'Added On',             visible: true },
    { key: 'customer_group_name',    label: 'Customer Group',       visible: true },
    { key: 'address',                label: 'Address',              visible: true },
    { key: 'mobile',                 label: 'Mobile',               visible: true },
    { key: 'total_sell_due',         label: 'Total Sale Due',       visible: true },
    { key: 'total_sell_return_due',  label: 'Total Sell Return Due', visible: true },
    { key: 'custom_field1',          label: 'Custom Field 1',       visible: true },
    { key: 'custom_field2',          label: 'Custom Field 2',       visible: true },
    { key: 'custom_field3',          label: 'Custom Field 3',       visible: true },
    { key: 'custom_field4',          label: 'Custom Field 4',       visible: false },
    { key: 'custom_field5',          label: 'Custom Field 5',       visible: false },
    { key: 'custom_field6',          label: 'Custom Field 6',       visible: false },
    { key: 'custom_field7',          label: 'Custom Field 7',       visible: false },
    { key: 'custom_field8',          label: 'Custom Field 8',       visible: false },
    { key: 'custom_field9',          label: 'Custom Field 9',       visible: false },
    { key: 'custom_field10',         label: 'Custom Field 10',      visible: false },
])

const isVisible = (key) => columns.find(c => c.key === key)?.visible !== false

const preDueColspan = computed(() => {
    const keysBeforeDue = [
        'contact_id',
        'supplier_business_name',
        'name',
        'email',
        'tax_number',
        'credit_limit',
        'pay_term',
        'opening_balance',
        'advance_balance',
        'created_at',
        'customer_group_name',
        'address',
        'mobile',
    ]
    return 1 + keysBeforeDue.filter(isVisible).length
})

const trailingEmptyCols = computed(() => {
    const trailingKeys = [
        'custom_field1',
        'custom_field2',
        'custom_field3',
        'custom_field4',
        'custom_field5',
        'custom_field6',
        'custom_field7',
        'custom_field8',
        'custom_field9',
        'custom_field10',
    ]
    return trailingKeys.filter(isVisible).length
})

// ── Action Dropdown & Modals ──────────────────────────────────────────────────
const openActionMenuId = ref(null)
const payingCustomer   = ref(null)
const detailCustomer   = ref(null)
const detailInitialTab = ref('overview')

const toggleActionMenu = (id) => {
    openActionMenuId.value = openActionMenuId.value === id ? null : id
}

const closeActionMenuOnOutsideClick = () => {
    openActionMenuId.value = null
}

onMounted(() => window.addEventListener('click', closeActionMenuOnOutsideClick))
onBeforeUnmount(() => window.removeEventListener('click', closeActionMenuOnOutsideClick))

const openPayModal = (customer) => {
    openActionMenuId.value = null
    payingCustomer.value = customer
}

const openDetailModal = (customer, tab = 'overview') => {
    openActionMenuId.value = null
    detailInitialTab.value = tab
    detailCustomer.value = customer
}

const toggleCustomerStatus = (customer) => {
    openActionMenuId.value = null
    const verb = customer.contact_status === 'inactive' ? 'activate' : 'deactivate'
    if (confirm(`Are you sure you want to ${verb} "${customer.name}"?`)) {
        router.post(route('shop.contacts.toggle-status', customer.id), {}, { preserveScroll: true })
    }
}

// ── Add / Edit Modal State ────────────────────────────────────────────────────
const isModalOpen        = ref(false)
const editingCustomer    = ref(null)
const showMoreInfo       = ref(false)
const showContactPersons = ref(false)
const openPersonPanels   = ref([true, false, false])

const emptyPerson = () => ({
    id:                          null,
    prefix:                      '',
    first_name:                  '',
    last_name:                   '',
    email:                       '',
    mobile:                      '',
    alternate_number:            '',
    family_number:               '',
    department:                  '',
    designation:                 '',
    sales_commission_percentage: '',
    allow_login:                 false,
})

const form = useForm({
    type:                   'customer',
    entity_type:            'individual',
    contact_id:             '',
    customer_group_id:      '',
    supplier_business_name: '',
    prefix:                 '',
    first_name:             '',
    middle_name:            '',
    last_name:              '',
    dob:                    '',
    mobile:                 '',
    alternate_number:       '',
    landline:               '',
    email:                  '',
    assigned_to:            [],
    tax_number:             '',
    opening_balance:        0,
    pay_term_number:        '',
    pay_term_type:          '',
    credit_limit:           '',
    address_line_1:         '',
    address_line_2:         '',
    city:                   '',
    state:                  '',
    country:                '',
    zip_code:               '',
    landmark:               '',
    street_name:            '',
    building_number:        '',
    additional_number:      '',
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
    shipping_address:       '',
    contact_persons:        [emptyPerson(), emptyPerson(), emptyPerson()],
})

const openCreateModal = () => {
    editingCustomer.value    = null
    showMoreInfo.value       = false
    showContactPersons.value = false
    openPersonPanels.value   = [true, false, false]
    form.reset()
    form.clearErrors()
    form.type            = 'customer'
    form.entity_type     = 'individual'
    form.opening_balance = 0
    form.contact_persons = [emptyPerson(), emptyPerson(), emptyPerson()]
    isModalOpen.value    = true
}

const openEditModal = (customer) => {
    openActionMenuId.value   = null
    editingCustomer.value    = customer
    showMoreInfo.value       = true
    showContactPersons.value = (customer.contact_persons?.length ?? 0) > 0
    form.clearErrors()

    form.type                   = customer.type ?? 'customer'
    form.entity_type            = customer.entity_type ?? 'individual'
    form.contact_id             = customer.contact_id ?? ''
    form.customer_group_id      = customer.customer_group_id ?? ''
    form.supplier_business_name = customer.supplier_business_name ?? ''
    form.prefix                 = customer.prefix ?? ''
    form.first_name             = customer.first_name ?? customer.name?.split(' ')[0] ?? ''
    form.middle_name            = customer.middle_name ?? ''
    form.last_name              = customer.last_name ?? ''
    form.dob                    = customer.dob ?? ''
    form.mobile                 = customer.mobile ?? ''
    form.alternate_number       = customer.alternate_number ?? ''
    form.landline               = customer.landline ?? ''
    form.email                  = customer.email ?? ''
    form.assigned_to            = [...(customer.assigned_to ?? [])]
    form.tax_number             = customer.tax_number ?? ''
    form.opening_balance        = customer.opening_balance ?? 0
    form.pay_term_number        = customer.pay_term_number ?? ''
    form.pay_term_type          = customer.pay_term_type ?? ''
    form.credit_limit           = customer.credit_limit ?? ''
    form.address_line_1         = customer.address_line_1 ?? ''
    form.address_line_2         = customer.address_line_2 ?? ''
    form.city                   = customer.city ?? ''
    form.state                  = customer.state ?? ''
    form.country                = customer.country ?? ''
    form.zip_code               = customer.zip_code ?? ''
    form.landmark               = customer.landmark ?? ''
    form.street_name            = customer.street_name ?? ''
    form.building_number        = customer.building_number ?? ''
    form.additional_number      = customer.additional_number ?? ''
    form.shipping_address       = customer.shipping_address ?? ''

    for (let i = 1; i <= 10; i++) {
        form[`custom_field${i}`] = customer[`custom_field${i}`] ?? ''
    }

    const existingPersons = (customer.contact_persons ?? []).map(p => ({
        id:                          p.id ?? null,
        prefix:                      p.prefix ?? '',
        first_name:                  p.first_name ?? '',
        last_name:                   p.last_name ?? '',
        email:                       p.email ?? '',
        mobile:                      p.mobile ?? '',
        alternate_number:            p.alternate_number ?? '',
        family_number:               p.family_number ?? '',
        department:                  p.department ?? '',
        designation:                 p.designation ?? '',
        sales_commission_percentage: p.sales_commission_percentage ?? '',
        allow_login:                 Boolean(p.allow_login),
    }))

    while (existingPersons.length < 3) {
        existingPersons.push(emptyPerson())
    }
    form.contact_persons = existingPersons
    openPersonPanels.value = existingPersons.map((_, idx) => idx === 0 || Boolean(existingPersons[idx].first_name))
    isModalOpen.value = true
}

const toggleAssignedUser = (userId) => {
    const idx = form.assigned_to.indexOf(userId)
    if (idx === -1) {
        form.assigned_to.push(userId)
    } else {
        form.assigned_to.splice(idx, 1)
    }
}

const submitForm = () => {
    if (editingCustomer.value) {
        form.put(route('shop.customers.update', editingCustomer.value.id), {
            onSuccess: () => {
                isModalOpen.value = false
            },
        })
    } else {
        form.post(route('shop.customers.store'), {
            onSuccess: () => {
                isModalOpen.value = false
                form.reset()
            },
        })
    }
}

const deleteCustomer = (customer) => {
    openActionMenuId.value = null
    if (confirm(`Are you sure you want to delete customer "${customer.name}"?`)) {
        router.delete(route('shop.customers.destroy', customer.id))
    }
}

const restoreCustomer = (customer) => {
    openActionMenuId.value = null
    router.post(route('shop.customers.restore', customer.id))
}

const forceDeleteCustomer = (customer) => {
    openActionMenuId.value = null
    if (confirm(`Permanently delete customer "${customer.name}"? This cannot be undone.`)) {
        router.delete(route('shop.customers.force-delete', customer.id))
    }
}

// ── Formatters ────────────────────────────────────────────────────────────────
const currency = (val) => {
    const n = parseFloat(val ?? 0)
    return '$ ' + (Number.isNaN(n) ? '0.00' : n.toFixed(2))
}

const formatCreditLimit = (val) => {
    if (val === null || val === undefined || val === '') {
        return 'No Limit'
    }
    return currency(val)
}

const pageSellDueTotal = computed(() =>
    props.customers.data.reduce((sum, c) => sum + parseFloat(c.total_sell_due ?? 0), 0)
)
const pageSellReturnDueTotal = computed(() =>
    props.customers.data.reduce((sum, c) => sum + parseFloat(c.total_sell_return_due ?? 0), 0)
)
</script>

<template>
    <Head title="Contacts - Customers" />

    <div class="space-y-4">
        <!-- Page Title -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold text-gray-900 inline-block mr-2">Customers</h1>
                <span class="text-sm text-gray-500">Manage your Customers</span>
            </div>
        </div>

        <!-- Flash messages -->
        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>
        <div
            v-if="$page.props.flash?.error"
            class="bg-red-50 border border-red-200 text-red-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-red-600 font-bold text-sm">!</span>
            <span class="font-medium">{{ $page.props.flash.error }}</span>
        </div>

        <!-- Collapsible Filters Card (Image 1 top) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <button
                type="button"
                @click="showFilters = !showFilters"
                class="w-full flex items-center justify-between px-5 py-3 text-left hover:bg-gray-50/70 rounded-xl transition"
            >
                <span class="flex items-center gap-2.5 text-sm font-medium text-indigo-900">
                    <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 text-xs">
                        ⏧
                    </span>
                    <span>Filters</span>
                </span>
                <span class="text-gray-400 text-xs">{{ showFilters ? '▴' : '▾' }}</span>
            </button>

            <div v-show="showFilters" class="px-5 pb-5 pt-2 border-t border-gray-100 space-y-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs text-gray-700">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="filterSellDue" type="checkbox" @change="applyFilters()" class="rounded border-gray-300 text-indigo-600" />
                        <span>Sell Due</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="filterSellReturnDue" type="checkbox" @change="applyFilters()" class="rounded border-gray-300 text-indigo-600" />
                        <span>Sell Return</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="filterAdvanceBal" type="checkbox" @change="applyFilters()" class="rounded border-gray-300 text-indigo-600" />
                        <span>Advance Balance</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="filterOpeningBal" type="checkbox" @change="applyFilters()" class="rounded border-gray-300 text-indigo-600" />
                        <span>Opening Balance</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Has no sell from:</label>
                        <select v-model="filterNoSellSince" @change="applyFilters()" class="w-full border border-gray-300 rounded px-2.5 py-1.5">
                            <option value="">Please Select</option>
                            <option value="1_month">One month</option>
                            <option value="3_months">Three months</option>
                            <option value="6_months">Six months</option>
                            <option value="1_year">One year</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Customer Group:</label>
                        <select v-model="filterGroupId" @change="applyFilters()" class="w-full border border-gray-300 rounded px-2.5 py-1.5">
                            <option value="">All</option>
                            <option v-for="group in customerGroups" :key="group.id" :value="group.id">
                                {{ group.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-medium text-gray-700 mb-1">Assigned to:</label>
                        <select v-model="filterAssignedTo" @change="applyFilters()" class="w-full border border-gray-300 rounded px-2.5 py-1.5">
                            <option value="">All</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">
                                {{ u.name }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end justify-between gap-2">
                        <label class="flex items-center gap-2 cursor-pointer text-gray-700 pb-1.5">
                            <input v-model="filterOnlyTrashed" type="checkbox" @change="applyFilters()" class="rounded border-gray-300 text-indigo-600" />
                            <span>Show Trashed</span>
                        </label>
                        <button
                            type="button"
                            @click="resetFilters"
                            class="px-3 py-1.5 border border-gray-300 rounded text-gray-600 hover:bg-gray-50 transition"
                        >
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Customers Table Card (Image 1) -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <!-- Header -->
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All your Customers</h2>
                <button
                    v-if="can.create"
                    type="button"
                    @click="openCreateModal"
                    class="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium rounded-full shadow-sm transition"
                >
                    <span class="text-base leading-none">+</span> Add
                </button>
            </div>

            <!-- Controls row -->
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
                    :data="customers.data"
                    filename="customers_export"
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

            <!-- Table -->
            <div :class="openActionMenuId ? 'overflow-x-auto pb-64' : 'overflow-x-auto pb-8'">
                <table class="w-full text-left text-xs border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/60 text-[11px] font-semibold text-gray-700 tracking-wide">
                            <th class="py-3 px-3 text-center">Action</th>
                            <th v-if="isVisible('contact_id')"             class="py-3 px-3">Contact ID</th>
                            <th v-if="isVisible('supplier_business_name')" class="py-3 px-3">Business Name</th>
                            <th v-if="isVisible('name')"                   class="py-3 px-3">Name</th>
                            <th v-if="isVisible('email')"                  class="py-3 px-3">Email</th>
                            <th v-if="isVisible('tax_number')"             class="py-3 px-3">Tax number</th>
                            <th v-if="isVisible('credit_limit')"           class="py-3 px-3">Credit Limit</th>
                            <th v-if="isVisible('pay_term')"               class="py-3 px-3">Pay term</th>
                            <th v-if="isVisible('opening_balance')"        class="py-3 px-3">Opening Balance</th>
                            <th v-if="isVisible('advance_balance')"        class="py-3 px-3">Advance Balance</th>
                            <th v-if="isVisible('created_at')"             class="py-3 px-3">Added On</th>
                            <th v-if="isVisible('customer_group_name')"    class="py-3 px-3">Customer Group</th>
                            <th v-if="isVisible('address')"                class="py-3 px-3">Address</th>
                            <th v-if="isVisible('mobile')"                 class="py-3 px-3">Mobile</th>
                            <th v-if="isVisible('total_sell_due')"         class="py-3 px-3">Total Sale Due</th>
                            <th v-if="isVisible('total_sell_return_due')"  class="py-3 px-3">Total Sell Return Due</th>
                            <th v-for="n in 10" :key="`th-cf-${n}`" v-show="isVisible(`custom_field${n}`)" class="py-3 px-3">
                                Custom Field {{ n }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-xs text-gray-700">
                        <tr v-if="customers.data.length === 0">
                            <td colspan="28" class="py-8 text-center text-gray-400">No customers found.</td>
                        </tr>
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <!-- Actions Dropdown -->
                            <td class="py-3 px-3 text-center">
                                <ContactActionDropdown
                                    :contact="customer"
                                    :is-open="openActionMenuId === customer.id"
                                    :can-edit="Boolean(can.edit)"
                                    :can-delete="Boolean(can.delete)"
                                    @toggle="toggleActionMenu(customer.id)"
                                    @pay="openPayModal"
                                    @view="(c) => openDetailModal(c, 'overview')"
                                    @edit="openEditModal"
                                    @delete="deleteCustomer"
                                    @toggle-status="toggleCustomerStatus"
                                    @ledger="(c) => openDetailModal(c, 'ledger')"
                                    @sales="(c) => openDetailModal(c, 'sales')"
                                    @documents="(c) => openDetailModal(c, 'documents')"
                                    @restore="restoreCustomer"
                                    @force-delete="forceDeleteCustomer"
                                />
                            </td>

                            <td v-if="isVisible('contact_id')"             class="py-3 px-3 font-medium text-gray-900">{{ customer.contact_id }}</td>
                            <td v-if="isVisible('supplier_business_name')" class="py-3 px-3">{{ customer.supplier_business_name ?? '' }}</td>
                            <td v-if="isVisible('name')"                   class="py-3 px-3">{{ customer.name }}</td>
                            <td v-if="isVisible('email')"                  class="py-3 px-3">{{ customer.email ?? '' }}</td>
                            <td v-if="isVisible('tax_number')"             class="py-3 px-3">{{ customer.tax_number ?? '' }}</td>
                            <td v-if="isVisible('credit_limit')"           class="py-3 px-3">{{ formatCreditLimit(customer.credit_limit) }}</td>
                            <td v-if="isVisible('pay_term')"               class="py-3 px-3">{{ customer.pay_term || '' }}</td>
                            <td v-if="isVisible('opening_balance')"        class="py-3 px-3">{{ currency(customer.opening_balance) }}</td>
                            <td v-if="isVisible('advance_balance')"        class="py-3 px-3">{{ currency(customer.advance_balance) }}</td>
                            <td v-if="isVisible('created_at')"             class="py-3 px-3">{{ customer.created_at }}</td>
                            <td v-if="isVisible('customer_group_name')"    class="py-3 px-3">{{ customer.customer_group_name ?? '' }}</td>
                            <td v-if="isVisible('address')"                class="py-3 px-3 max-w-[200px] whitespace-normal">{{ customer.address || '' }}</td>
                            <td v-if="isVisible('mobile')"                 class="py-3 px-3">{{ customer.mobile ?? '' }}</td>
                            <td v-if="isVisible('total_sell_due')"         class="py-3 px-3">{{ currency(customer.total_sell_due) }}</td>
                            <td v-if="isVisible('total_sell_return_due')"  class="py-3 px-3">{{ currency(customer.total_sell_return_due) }}</td>
                            <td v-for="n in 10" :key="`td-cf-${n}`" v-show="isVisible(`custom_field${n}`)" class="py-3 px-3">
                                {{ customer[`custom_field${n}`] ?? '' }}
                            </td>
                        </tr>
                    </tbody>

                    <!-- Totals Row (Image 1) -->
                    <tfoot v-if="customers.data.length > 0">
                        <tr class="border-t-2 border-gray-300 font-semibold text-gray-800 bg-gray-200/70 text-xs">
                            <td :colspan="preDueColspan" class="py-3 px-4 text-center">Total:</td>
                            <td v-if="isVisible('total_sell_due')"        class="py-3 px-3">{{ currency(totals?.total_sell_due ?? pageSellDueTotal) }}</td>
                            <td v-if="isVisible('total_sell_return_due')" class="py-3 px-3">{{ currency(totals?.total_sell_return_due ?? pageSellReturnDueTotal) }}</td>
                            <td v-for="n in trailingEmptyCols" :key="`tf-empty-${n}`" class="py-3 px-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Pagination -->
            <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-gray-100 text-xs text-gray-500">
                <div>
                    Showing {{ customers.from ?? 0 }} to {{ customers.to ?? 0 }} of {{ customers.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <Link
                        v-if="customers.prev_page_url"
                        :href="customers.prev_page_url"
                        preserve-state
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Previous</Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Previous</span>

                    <span class="px-3 py-1 bg-indigo-600 text-white rounded font-medium">
                        {{ customers.current_page ?? 1 }}
                    </span>

                    <Link
                        v-if="customers.next_page_url"
                        :href="customers.next_page_url"
                        preserve-state
                        class="px-3 py-1 border border-gray-300 rounded hover:bg-gray-50 transition"
                    >Next</Link>
                    <span v-else class="px-3 py-1 border border-gray-200 text-gray-300 rounded">Next</span>
                </div>
            </div>
        </div>
    </div>

    <!-- =====================================================================
         ADD / EDIT CUSTOMER MODAL (Images 2, 3, 4)
         ===================================================================== -->
    <Teleport to="body">
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-8 px-4"
            @click.self="isModalOpen = false"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">
                        {{ editingCustomer ? 'Edit Contact' : 'Add a new contact' }}
                    </h3>
                    <button
                        type="button"
                        @click="isModalOpen = false"
                        class="text-gray-400 hover:text-gray-600 transition text-lg leading-none"
                    >✕</button>
                </div>

                <!-- Form -->
                <form @submit.prevent="submitForm" class="px-6 py-5 space-y-5">

                    <!-- Row 1: Contact type + Individual/Business + Contact ID (Image 2) -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Contact type:*</label>
                            <select
                                v-model="form.type"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="customer">Customers</option>
                                <option value="supplier">Suppliers</option>
                                <option value="both">Both</option>
                            </select>
                            <p v-if="form.errors.type" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.type }}</p>
                        </div>

                        <div class="flex items-center gap-5 pt-6">
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700">
                                <input v-model="form.entity_type" type="radio" value="individual" class="text-indigo-600" />
                                <span>Individual</span>
                            </label>
                            <label class="flex items-center gap-1.5 cursor-pointer text-xs text-gray-700">
                                <input v-model="form.entity_type" type="radio" value="business" class="text-indigo-600" />
                                <span>Business</span>
                            </label>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Contact ID:</label>
                            <input
                                v-model="form.contact_id"
                                type="text"
                                placeholder="Contact ID"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p class="text-[11px] text-gray-500 mt-1">Leave empty to autogenerate</p>
                            <p v-if="form.errors.contact_id" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.contact_id }}</p>
                        </div>
                    </div>

                    <!-- Individual / Business Identity Row -->
                    <div v-if="form.entity_type === 'business'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Business Name:</label>
                            <input
                                v-model="form.supplier_business_name"
                                type="text"
                                placeholder="Business Name"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Group:</label>
                            <select
                                v-model="form.customer_group_id"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">None</option>
                                <option v-for="group in customerGroups" :key="group.id" :value="group.id">
                                    {{ group.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Customer Group:</label>
                                <select
                                    v-model="form.customer_group_id"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    <option value="">None</option>
                                    <option v-for="group in customerGroups" :key="group.id" :value="group.id">
                                        {{ group.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Prefix:</label>
                                <input
                                    v-model="form.prefix"
                                    type="text"
                                    placeholder="Mr / Mrs / Miss"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">First Name:</label>
                                <input
                                    v-model="form.first_name"
                                    type="text"
                                    placeholder="First Name"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Middle name:</label>
                                <input
                                    v-model="form.middle_name"
                                    type="text"
                                    placeholder="Middle name"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Last Name:</label>
                                <input
                                    v-model="form.last_name"
                                    type="text"
                                    placeholder="Last Name"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Date of birth:</label>
                                <input
                                    v-model="form.dob"
                                    type="date"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Mobile + Alternate + Landline + Email (Image 2) -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Mobile:*</label>
                            <input
                                v-model="form.mobile"
                                type="text"
                                required
                                placeholder="Mobile"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.mobile" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.mobile }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Alternate contact number:</label>
                            <input
                                v-model="form.alternate_number"
                                type="text"
                                placeholder="Alternate contact number"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Landline:</label>
                            <input
                                v-model="form.landline"
                                type="text"
                                placeholder="Landline"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Email:</label>
                            <input
                                v-model="form.email"
                                type="email"
                                placeholder="Email"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="form.errors.email" class="text-red-500 text-[10px] mt-0.5">{{ form.errors.email }}</p>
                        </div>
                    </div>

                    <!-- Row 4: Assigned to (Image 2) -->
                    <div class="max-w-md">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Assigned to:</label>
                        <div class="border border-gray-300 rounded p-2 max-h-28 overflow-y-auto space-y-1 bg-white">
                            <p v-if="users.length === 0" class="text-[11px] text-gray-400">No users available.</p>
                            <label
                                v-for="u in users"
                                :key="u.id"
                                class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:bg-gray-50 px-1.5 py-0.5 rounded"
                            >
                                <input
                                    type="checkbox"
                                    :checked="form.assigned_to.includes(u.id)"
                                    @change="toggleAssignedUser(u.id)"
                                    class="rounded border-gray-300 text-indigo-600"
                                />
                                <span>{{ u.name }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Toggle Button: More Informations (Image 2 & 3) -->
                    <div class="text-center pt-1">
                        <button
                            type="button"
                            @click="showMoreInfo = !showMoreInfo"
                            class="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                        >
                            <span>More Informations</span>
                            <span>{{ showMoreInfo ? '▴' : '▾' }}</span>
                        </button>
                    </div>

                    <!-- More Informations Expanded Section (Image 3) -->
                    <div v-show="showMoreInfo" class="border-t border-gray-200 pt-5 space-y-5">
                        <!-- Tax + Opening Balance + Pay term + Credit Limit -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Tax number:</label>
                                <input
                                    v-model="form.tax_number"
                                    type="text"
                                    placeholder="Tax number"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Opening Balance:</label>
                                <input
                                    v-model="form.opening_balance"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Pay term:</label>
                                <div class="flex gap-2">
                                    <input
                                        v-model="form.pay_term_number"
                                        type="number"
                                        min="1"
                                        placeholder="Pay term"
                                        class="w-1/2 border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    />
                                    <select
                                        v-model="form.pay_term_type"
                                        class="w-1/2 border border-gray-300 rounded px-2 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                    >
                                        <option value="">Please Select</option>
                                        <option value="days">Days</option>
                                        <option value="months">Months</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Credit Limit:</label>
                                <input
                                    v-model="form.credit_limit"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder=""
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                                <p class="text-[11px] text-gray-500 mt-1">Keep blank for no limit</p>
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Address Block (Image 3) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Address line 1:</label>
                                <input
                                    v-model="form.address_line_1"
                                    type="text"
                                    placeholder="Address line 1"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Address line 2:</label>
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
                                <label class="block text-xs font-semibold text-gray-700 mb-1">City:</label>
                                <input v-model="form.city" type="text" placeholder="City" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">State:</label>
                                <input v-model="form.state" type="text" placeholder="State" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Country:</label>
                                <input v-model="form.country" type="text" placeholder="Country" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Zip Code:</label>
                                <input v-model="form.zip_code" type="text" placeholder="Zip/Postal Code" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Landmark:</label>
                                <input v-model="form.landmark" type="text" placeholder="Landmark" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Street name:</label>
                                <input v-model="form.street_name" type="text" placeholder="Street name" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Building number:</label>
                                <input v-model="form.building_number" type="text" placeholder="Building number" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Additional number/Secondary Number:</label>
                                <input v-model="form.additional_number" type="text" placeholder="Additional number" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Custom Fields 1..10 (Image 3: 4 cols) -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div v-for="n in 10" :key="`cf-input-${n}`">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Custom Field {{ n }}:</label>
                                <input
                                    v-model="form[`custom_field${n}`]"
                                    type="text"
                                    :placeholder="`Custom Field ${n}`"
                                    class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <hr class="border-gray-200" />

                        <!-- Shipping Address (Image 3 & 4) -->
                        <div class="max-w-lg mx-auto">
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Shipping Address</label>
                            <input
                                v-model="form.shipping_address"
                                type="text"
                                placeholder="Search address"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <!-- Toggle Button: Add Contact Persons (Image 2 & 4) -->
                    <div class="text-center">
                        <button
                            type="button"
                            @click="showContactPersons = !showContactPersons"
                            class="inline-flex items-center gap-1.5 px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                        >
                            <span>Add Contact Persons</span>
                            <span>{{ showContactPersons ? '▴' : '▾' }}</span>
                        </button>
                    </div>

                    <!-- Contact Persons Expanded Section (Image 4) -->
                    <div v-show="showContactPersons" class="border-t border-gray-200 pt-5 space-y-4">
                        <div
                            v-for="(person, idx) in form.contact_persons"
                            :key="`cp-${idx}`"
                            class="border-b border-gray-200 pb-4 last:border-b-0"
                        >
                            <button
                                type="button"
                                @click="openPersonPanels[idx] = !openPersonPanels[idx]"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition mb-3"
                            >
                                <span>Add contact person {{ idx + 1 }}</span>
                                <span>{{ openPersonPanels[idx] ? '▴' : '▾' }}</span>
                            </button>

                            <div v-show="openPersonPanels[idx]" class="space-y-3 pt-1">
                                <div class="grid grid-cols-1 sm:grid-cols-5 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Prefix:</label>
                                        <input
                                            v-model="person.prefix"
                                            type="text"
                                            placeholder="Mr / Mrs / Miss"
                                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs"
                                        />
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">First Name:*</label>
                                        <input
                                            v-model="person.first_name"
                                            type="text"
                                            placeholder="First Name"
                                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs"
                                        />
                                        <p v-if="form.errors[`contact_persons.${idx}.first_name`]" class="text-red-500 text-[10px] mt-0.5">
                                            {{ form.errors[`contact_persons.${idx}.first_name`] }}
                                        </p>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Last Name:</label>
                                        <input
                                            v-model="person.last_name"
                                            type="text"
                                            placeholder="Last Name"
                                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs"
                                        />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email:</label>
                                        <input v-model="person.email" type="email" placeholder="Email" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Mobile Number:</label>
                                        <input v-model="person.mobile" type="text" placeholder="Mobile Number" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Alternate contact number:</label>
                                        <input v-model="person.alternate_number" type="text" placeholder="Alternate contact number" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Family contact number:</label>
                                        <input v-model="person.family_number" type="text" placeholder="Family contact number" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Department:</label>
                                        <input v-model="person.department" type="text" placeholder="Department" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Designation:</label>
                                        <input v-model="person.designation" type="text" placeholder="Designation" class="w-full border border-gray-300 rounded px-3 py-2 text-xs" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-center">
                                    <div>
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Sales Commission Percentage (%):</label>
                                        <input
                                            v-model="person.sales_commission_percentage"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            placeholder="Sales Commission Percentage (%)"
                                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs"
                                        />
                                    </div>
                                    <div class="pt-4">
                                        <label class="inline-flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                            <input v-model="person.allow_login" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                            <span>Allow login</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Modal Footer -->
                    <div class="flex items-center justify-end gap-2.5 pt-4 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium text-xs shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Saving...' : 'Save' }}
                        </button>
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded-lg font-medium text-xs transition"
                        >
                            Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <ContactActionModals
        :pay-contact="payingCustomer"
        :detail-contact="detailCustomer"
        :initial-tab="detailInitialTab"
        @close-pay="payingCustomer = null"
        @close-detail="detailCustomer = null"
    />
</template>
