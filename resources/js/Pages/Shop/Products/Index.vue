<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    products:      { type: Object, required: true },
    stockReport:   { type: Array, default: () => [] },
    filterOptions: { type: Object, default: () => ({ categories: [], brands: [], units: [], tax_rates: [], locations: [] }) },
    filters:       { type: Object, default: () => ({}) },
    can:           { type: Object, default: () => ({}) },
})

const page = usePage()
const currencySymbol = computed(() => page.props.auth?.business?.currency_symbol ?? '$')

// ── Tabs & Filters ────────────────────────────────────────────────────────────
const activeTab       = ref('all')
const showFilters     = ref(false)
const search          = ref(props.filters.search ?? '')
const perPage         = ref(Number(props.filters.per_page ?? 25))
const filterType      = ref(props.filters.type ?? '')
const filterCategory  = ref(props.filters.category_id ?? '')
const filterUnit      = ref(props.filters.unit_id ?? '')
const filterTax       = ref(props.filters.tax_id ?? '')
const filterBrand     = ref(props.filters.brand_id ?? '')
const filterLocation  = ref(props.filters.location_id ?? '')
const filterStatus    = ref(props.filters.is_inactive ?? '')
const filterTrashed   = ref(Boolean(props.filters.only_trashed))
const sortBy          = ref(props.filters.sort_by ?? 'id')
const sortDir         = ref(props.filters.sort_dir ?? 'desc')

const buildFilterParams = () => ({
    search:       search.value || undefined,
    per_page:     perPage.value,
    type:         filterType.value || undefined,
    category_id:  filterCategory.value || undefined,
    unit_id:      filterUnit.value || undefined,
    tax_id:       filterTax.value || undefined,
    brand_id:     filterBrand.value || undefined,
    location_id:  filterLocation.value || undefined,
    is_inactive:  filterStatus.value !== '' ? filterStatus.value : undefined,
    only_trashed: filterTrashed.value ? 1 : undefined,
    sort_by:      sortBy.value || undefined,
    sort_dir:     sortDir.value || undefined,
})

const applyFilters = (replace = false) => {
    router.get(route('shop.products.index'), buildFilterParams(), {
        preserveState: true,
        replace,
    })
}

const resetFilters = () => {
    search.value         = ''
    filterType.value     = ''
    filterCategory.value = ''
    filterUnit.value     = ''
    filterTax.value      = ''
    filterBrand.value    = ''
    filterLocation.value = ''
    filterStatus.value   = ''
    filterTrashed.value  = false
    applyFilters(true)
}

const toggleSort = (col) => {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = col
        sortDir.value = 'asc'
    }
    applyFilters(true)
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(true), 300)
})

// ── Columns & Exports ─────────────────────────────────────────────────────────
const columns = reactive([
    { key: 'image_url',             label: 'Product image',       visible: true },
    { key: 'name',                  label: 'Product',             visible: true },
    { key: 'location_names',        label: 'Business Location',   visible: true },
    { key: 'default_purchase_price', label: 'Unit Purchase Price', visible: true },
    { key: 'default_sell_price',    label: 'Selling Price',       visible: true },
    { key: 'current_stock',         label: 'Current stock',       visible: true },
    { key: 'type_label',            label: 'Product Type',        visible: true },
    { key: 'category_display',      label: 'Category',            visible: true },
    { key: 'brand_name',            label: 'Brand',               visible: true },
    { key: 'tax_name',              label: 'Tax',                 visible: true },
    { key: 'sku',                   label: 'SKU',                 visible: true },
])
const isVisible = (key) => columns.find(c => c.key === key)?.visible !== false

const exportRows = computed(() =>
    (props.products?.data ?? []).map(p => ({
        ...p,
        location_names: (p.location_names ?? []).join(', '),
        current_stock: `${Number(p.current_stock ?? 0).toFixed(2)} ${p.unit_short_name ?? ''}`.trim(),
    }))
)

const downloadExcel = () => {
    if (!exportRows.value.length) return
    const visibleCols = columns.filter(c => c.visible !== false && c.key !== 'image_url')
    const headers = visibleCols.map(c => `"${c.label}"`).join(',')
    const rows = exportRows.value.map(item =>
        visibleCols.map(c => `"${(item[c.key] ?? '').toString().replace(/"/g, '""')}"`).join(',')
    )
    const csv = 'data:text/csv;charset=utf-8,' + [headers, ...rows].join('\n')
    const link = document.createElement('a')
    link.setAttribute('href', encodeURI(csv))
    link.setAttribute('download', 'products.csv')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

// ── Money & Price Range Formatters ────────────────────────────────────────────
const formatMoney = (val) => `${currencySymbol.value} ${Number(val ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const formatPriceRange = (min, max) => {
    if (Number(min ?? 0) !== Number(max ?? 0)) {
        return `${formatMoney(min)} - ${formatMoney(max)}`
    }
    return formatMoney(min)
}

// ── Row Selection & Bulk Actions ──────────────────────────────────────────────
const selectedIds = ref([])
const allSelected = computed(() => {
    const rows = props.products?.data ?? []
    return rows.length > 0 && rows.every(r => selectedIds.value.includes(r.id))
})
const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = (props.products?.data ?? []).map(r => r.id)
    } else {
        selectedIds.value = []
    }
}

const bulkDelete = () => {
    if (!selectedIds.value.length) return
    if (!confirm(`Delete ${selectedIds.value.length} selected product(s)?`)) return
    router.post(route('shop.products.bulk-delete'), { product_ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = [] },
    })
}

const bulkDeactivate = () => {
    if (!selectedIds.value.length) return
    router.post(route('shop.products.bulk-status'), { product_ids: selectedIds.value, is_inactive: true }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = [] },
    })
}

const bulkWooCommerceSync = () => {
    if (!selectedIds.value.length) return
    router.post(route('shop.products.bulk-woocommerce-sync'), { product_ids: selectedIds.value }, {
        preserveScroll: true,
        onSuccess: () => { selectedIds.value = [] },
    })
}

const bulkLocationModal = reactive({ open: false, action: 'add', location_id: '' })
const openBulkLocationModal = (action) => {
    if (!selectedIds.value.length) return
    bulkLocationModal.action = action
    bulkLocationModal.location_id = props.filterOptions?.locations?.[0]?.id ?? ''
    bulkLocationModal.open = true
}
const submitBulkLocation = () => {
    if (!bulkLocationModal.location_id) return
    router.post(route('shop.products.bulk-location'), {
        product_ids: selectedIds.value,
        location_id: Number(bulkLocationModal.location_id),
        action: bulkLocationModal.action,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            bulkLocationModal.open = false
            selectedIds.value = []
        },
    })
}

// ── Row Actions Dropdown & Modals ─────────────────────────────────────────────
const openActionId = ref(null)
const toggleActionMenu = (id) => {
    openActionId.value = openActionId.value === id ? null : id
}

const deleteProduct = (product) => {
    openActionId.value = null
    if (!confirm(`Delete "${product.name}"?`)) return
    router.delete(route('shop.products.destroy', product.id), { preserveScroll: true })
}

const restoreProduct = (product) => {
    openActionId.value = null
    router.post(route('shop.products.restore', product.id), {}, { preserveScroll: true })
}

const forceDeleteProduct = (product) => {
    openActionId.value = null
    if (!confirm(`Permanently delete "${product.name}"? This cannot be undone.`)) return
    router.delete(route('shop.products.force-delete', product.id), { preserveScroll: true })
}

const duplicateProduct = (product) => {
    openActionId.value = null
    router.post(route('shop.products.duplicate', product.id), {}, { preserveScroll: true })
}

// View Modal
const viewModal = reactive({ open: false, product: null })
const openViewModal = (product) => {
    openActionId.value = null
    viewModal.product = product
    viewModal.open = true
}

// Opening Stock Modal
const openingStockModal = reactive({
    open: false,
    loading: false,
    saving: false,
    productName: '',
    productId: null,
    unit: '',
    rows: [],
})

const openOpeningStockModal = async (productId, productName = '') => {
    openActionId.value = null
    openingStockModal.open = true
    openingStockModal.loading = true
    openingStockModal.productId = productId
    openingStockModal.productName = productName
    try {
        const res = await fetch(route('shop.products.opening-stock', productId), {
            headers: { Accept: 'application/json' },
        })
        const json = await res.json()
        openingStockModal.productName = json.data.product_name
        openingStockModal.unit = json.data.unit
        openingStockModal.rows = json.data.rows
    } finally {
        openingStockModal.loading = false
    }
}

const submitOpeningStock = () => {
    openingStockModal.saving = true
    router.post(route('shop.products.save-opening-stock', openingStockModal.productId), {
        stocks: openingStockModal.rows.map(r => ({
            variation_id: r.variation_id,
            location_id: r.location_id,
            quantity: r.quantity,
            unit_cost_before_tax: r.unit_cost_before_tax,
            lot_number: r.lot_number || null,
            exp_date: r.exp_date || null,
        })),
    }, {
        preserveScroll: true,
        onSuccess: () => {
            openingStockModal.open = false
        },
        onFinish: () => {
            openingStockModal.saving = false
        },
    })
}

// Stock History Modal
const historyModal = reactive({
    open: false,
    loading: false,
    productName: '',
    sku: '',
    unit: '',
    currentStock: 0,
    entries: [],
})
const openHistoryModal = async (product) => {
    openActionId.value = null
    historyModal.open = true
    historyModal.loading = true
    historyModal.productName = product.name
    try {
        const res = await fetch(route('shop.products.stock-history', product.id), {
            headers: { Accept: 'application/json' },
        })
        const json = await res.json()
        historyModal.productName = json.data.product_name
        historyModal.sku = json.data.sku
        historyModal.unit = json.data.unit
        historyModal.currentStock = json.data.current_stock
        historyModal.entries = json.data.entries
    } finally {
        historyModal.loading = false
    }
}

// Labels Modal
const labelsModal = reactive({
    open: false,
    loading: false,
    data: null,
})
const openLabelsModal = async (product) => {
    openActionId.value = null
    labelsModal.open = true
    labelsModal.loading = true
    try {
        const res = await fetch(route('shop.products.labels', product.id), {
            headers: { Accept: 'application/json' },
        })
        const json = await res.json()
        labelsModal.data = json.data
    } finally {
        labelsModal.loading = false
    }
}

onMounted(() => {
    if (props.filters?.opening_stock_product_id) {
        openOpeningStockModal(Number(props.filters.opening_stock_product_id))
    }
})
</script>

<template>
    <Head title="Products" />

    <div class="space-y-4">
        <!-- Flash Banner -->
        <div
            v-if="page.props.flash?.success"
            class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm"
        >
            {{ page.props.flash.success }}
        </div>
        <div
            v-if="page.props.flash?.error"
            class="px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm"
        >
            {{ page.props.flash.error }}
        </div>

        <!-- Header -->
        <div class="flex items-baseline gap-2">
            <h1 class="text-2xl font-bold text-gray-900">Products</h1>
            <span class="text-sm text-gray-500">Manage your products</span>
        </div>

        <!-- Collapsible Filters Panel (matching Image 1) -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <button
                type="button"
                @click="showFilters = !showFilters"
                class="w-full px-4 py-3 flex items-center justify-between text-left hover:bg-gray-50 transition"
            >
                <span class="flex items-center gap-2 text-sm font-semibold text-indigo-700">
                    <span>🔍</span>
                    <span>Filters</span>
                </span>
                <span class="text-xs text-gray-500">{{ showFilters ? '▴' : '▾' }}</span>
            </button>

            <div v-show="showFilters" class="px-4 pb-4 pt-2 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Product Type:</label>
                    <select v-model="filterType" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option value="single">Single</option>
                        <option value="variable">Variable</option>
                        <option value="combo">Combo</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Category:</label>
                    <select v-model="filterCategory" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option v-for="c in filterOptions.categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Unit:</label>
                    <select v-model="filterUnit" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option v-for="u in filterOptions.units" :key="u.id" :value="u.id">{{ u.actual_name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Tax:</label>
                    <select v-model="filterTax" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option v-for="t in filterOptions.tax_rates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Brand:</label>
                    <select v-model="filterBrand" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option v-for="b in filterOptions.brands" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Business Location:</label>
                    <select v-model="filterLocation" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option v-for="l in filterOptions.locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-gray-600 mb-1">Status:</label>
                    <select v-model="filterStatus" @change="applyFilters()" class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded-md bg-white">
                        <option value="">All</option>
                        <option value="0">Active</option>
                        <option value="1">Inactive</option>
                    </select>
                </div>

                <div class="flex items-end justify-between gap-2 pb-1">
                    <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                        <input v-model="filterTrashed" @change="applyFilters()" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <span>Show Trashed</span>
                    </label>
                    <button type="button" @click="resetFilters" class="px-2.5 py-1 text-xs text-indigo-600 hover:bg-indigo-50 rounded">
                        Reset
                    </button>
                </div>
            </div>
        </div>

        <!-- Tabs Card (All Products / Stock Report) -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <!-- Tabs -->
            <div class="flex items-center border-b border-gray-200 px-4 pt-2 gap-1">
                <button
                    type="button"
                    @click="activeTab = 'all'"
                    class="px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5"
                    :class="activeTab === 'all'
                        ? 'border-indigo-600 text-indigo-700'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                >
                    <span>📦</span>
                    <span>All Products</span>
                </button>
                <button
                    type="button"
                    @click="activeTab = 'stock_report'"
                    class="px-4 py-2.5 text-xs font-semibold border-b-2 transition flex items-center gap-1.5"
                    :class="activeTab === 'stock_report'
                        ? 'border-indigo-600 text-indigo-700'
                        : 'border-transparent text-gray-600 hover:text-gray-900'"
                >
                    <span>⏳</span>
                    <span>Stock Report</span>
                </button>
            </div>

            <!-- Tab 1: All Products -->
            <div v-show="activeTab === 'all'" class="p-4 space-y-3">
                <!-- Toolbar matching Image 1 -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-xs text-gray-600">
                        <span>Show</span>
                        <select v-model="perPage" @change="applyFilters()" class="border border-gray-300 rounded px-2 py-1 text-xs bg-white">
                            <option :value="10">10</option>
                            <option :value="25">25</option>
                            <option :value="50">50</option>
                            <option :value="100">100</option>
                        </select>
                        <span>entries</span>
                    </div>

                    <DataTableActions
                        :columns="columns"
                        :data="exportRows"
                        filename="products"
                    />

                    <div class="flex items-center gap-2">
                        <Link
                            v-if="can.create !== false"
                            :href="route('shop.products.create')"
                            class="inline-flex items-center gap-1 px-4 py-2 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm transition"
                        >
                            <span>+ Add</span>
                        </Link>
                        <button
                            type="button"
                            @click="downloadExcel"
                            class="inline-flex items-center gap-1 px-4 py-2 rounded-full bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-semibold shadow-sm transition"
                        >
                            <span>⬇ Download Excel</span>
                        </button>
                    </div>
                </div>

                <!-- Search right-aligned like Image 1 -->
                <div class="flex justify-end">
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search ..."
                        class="w-56 px-3 py-1.5 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>

                <!-- Products DataTable -->
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-700 font-semibold">
                            <tr>
                                <th class="px-3 py-2.5 text-left w-8">
                                    <input type="checkbox" :checked="allSelected" @change="toggleSelectAll" class="rounded border-gray-300 text-indigo-600" />
                                </th>
                                <th v-if="isVisible('image_url')" class="px-3 py-2.5 text-left">Product image</th>
                                <th class="px-3 py-2.5 text-left">Action</th>
                                <th v-if="isVisible('name')" @click="toggleSort('name')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    Product ⇅
                                </th>
                                <th v-if="isVisible('location_names')" class="px-3 py-2.5 text-left">
                                    <span class="inline-flex items-center gap-1">
                                        <span>Business Location</span>
                                        <InfoTooltip text="Locations where this product is available." />
                                    </span>
                                </th>
                                <th v-if="isVisible('default_purchase_price') && can.view_purchase_price !== false" @click="toggleSort('default_purchase_price')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    Unit Purchase Price ⇅
                                </th>
                                <th v-if="isVisible('default_sell_price')" @click="toggleSort('default_sell_price')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    Selling Price ⇅
                                </th>
                                <th v-if="isVisible('current_stock')" @click="toggleSort('current_stock')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    Current stock ⇅
                                </th>
                                <th v-if="isVisible('type_label')" @click="toggleSort('type')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    Product Type ⇅
                                </th>
                                <th v-if="isVisible('category_display')" class="px-3 py-2.5 text-left">Category</th>
                                <th v-if="isVisible('brand_name')" class="px-3 py-2.5 text-left">Brand</th>
                                <th v-if="isVisible('tax_name')" class="px-3 py-2.5 text-left">Tax</th>
                                <th v-if="isVisible('sku')" @click="toggleSort('sku')" class="px-3 py-2.5 text-left cursor-pointer hover:text-indigo-600">
                                    SKU ⇅
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="!products.data?.length">
                                <td colspan="13" class="px-4 py-8 text-center text-gray-500">
                                    No products found. Click <strong>+ Add</strong> to create your first product.
                                </td>
                            </tr>
                            <tr v-for="p in products.data" :key="p.id" class="hover:bg-gray-50">
                                <td class="px-3 py-2.5">
                                    <input v-model="selectedIds" :value="p.id" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                                </td>
                                <td v-if="isVisible('image_url')" class="px-3 py-2">
                                    <div class="w-10 h-10 rounded border border-gray-200 bg-gray-50 overflow-hidden flex items-center justify-center">
                                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover" />
                                        <span v-else class="text-gray-400 text-base">📦</span>
                                    </div>
                                </td>
                                <td class="px-3 py-2 relative">
                                    <button
                                        type="button"
                                        @click="toggleActionMenu(p.id)"
                                        class="px-2.5 py-1 rounded border border-sky-400 text-sky-700 bg-sky-50 hover:bg-sky-100 text-[11px] font-medium inline-flex items-center gap-1"
                                    >
                                        <span>Actions</span>
                                        <span>▾</span>
                                    </button>

                                    <div
                                        v-if="openActionId === p.id"
                                        class="absolute left-3 mt-1 w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-40 py-1 text-xs"
                                    >
                                        <template v-if="!p.deleted_at">
                                            <button type="button" @click="openLabelsModal(p)" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2">
                                                <span>🏷️</span><span>Labels</span>
                                            </button>
                                            <button type="button" @click="openViewModal(p)" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2">
                                                <span>👁️</span><span>View</span>
                                            </button>
                                            <Link
                                                v-if="can.edit !== false"
                                                :href="route('shop.products.edit', p.id)"
                                                class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2"
                                            >
                                                <span>✏️</span><span>Edit</span>
                                            </Link>
                                            <button
                                                v-if="can.delete !== false"
                                                type="button"
                                                @click="deleteProduct(p)"
                                                class="w-full text-left px-3 py-1.5 hover:bg-red-50 text-red-600 flex items-center gap-2"
                                            >
                                                <span>🗑️</span><span>Delete</span>
                                            </button>
                                            <div class="my-1 border-t border-gray-100"></div>
                                            <button
                                                v-if="can.add_opening_stock !== false"
                                                type="button"
                                                @click="openOpeningStockModal(p.id, p.name)"
                                                class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2"
                                            >
                                                <span>🗄️</span><span>Add or edit opening stock</span>
                                            </button>
                                            <button type="button" @click="openHistoryModal(p)" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2">
                                                <span>🕒</span><span>Product stock history</span>
                                            </button>
                                            <button type="button" @click="duplicateProduct(p)" class="w-full text-left px-3 py-1.5 hover:bg-gray-100 flex items-center gap-2">
                                                <span>📄</span><span>Duplicate Product</span>
                                            </button>
                                        </template>
                                        <template v-else>
                                            <button type="button" @click="restoreProduct(p)" class="w-full text-left px-3 py-1.5 hover:bg-emerald-50 text-emerald-700 flex items-center gap-2">
                                                <span>♻️</span><span>Restore</span>
                                            </button>
                                            <button type="button" @click="forceDeleteProduct(p)" class="w-full text-left px-3 py-1.5 hover:bg-red-50 text-red-600 flex items-center gap-2">
                                                <span>❌</span><span>Force Delete</span>
                                            </button>
                                        </template>
                                    </div>
                                </td>
                                <td v-if="isVisible('name')" class="px-3 py-2 font-medium text-gray-900">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ p.name }}</span>
                                        <span v-if="p.is_inactive" class="px-1.5 py-0.5 text-[10px] rounded bg-amber-100 text-amber-800">Inactive</span>
                                    </div>
                                    <span v-if="!p.woocommerce_disable_sync" title="WooCommerce Sync Enabled" class="inline-block text-[10px] text-gray-500 mt-0.5">🌐 Woo</span>
                                </td>
                                <td v-if="isVisible('location_names')" class="px-3 py-2 text-gray-700">
                                    {{ (p.location_names ?? []).join(', ') || '-' }}
                                </td>
                                <td v-if="isVisible('default_purchase_price') && can.view_purchase_price !== false" class="px-3 py-2 whitespace-nowrap text-gray-800">
                                    {{ formatPriceRange(p.min_purchase_price, p.max_purchase_price) }}
                                </td>
                                <td v-if="isVisible('default_sell_price')" class="px-3 py-2 whitespace-nowrap text-gray-800">
                                    {{ formatPriceRange(p.min_sell_price, p.max_sell_price) }}
                                </td>
                                <td v-if="isVisible('current_stock')" class="px-3 py-2 whitespace-nowrap text-gray-800">
                                    {{ Number(p.current_stock ?? 0).toFixed(2) }} {{ p.unit_short_name }}
                                </td>
                                <td v-if="isVisible('type_label')" class="px-3 py-2 text-gray-700">
                                    {{ p.type_label }}
                                </td>
                                <td v-if="isVisible('category_display')" class="px-3 py-2 text-gray-700">
                                    {{ p.category_display ?? '-' }}
                                </td>
                                <td v-if="isVisible('brand_name')" class="px-3 py-2 text-gray-700">
                                    {{ p.brand_name ?? '' }}
                                </td>
                                <td v-if="isVisible('tax_name')" class="px-3 py-2 text-gray-700">
                                    {{ p.tax_name ?? '' }}
                                </td>
                                <td v-if="isVisible('sku')" class="px-3 py-2 font-mono text-gray-700">
                                    {{ p.sku }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Bulk Action Buttons matching Image 2 -->
                <div class="flex flex-wrap items-center gap-2 pt-2">
                    <button
                        type="button"
                        :disabled="!selectedIds.length"
                        @click="bulkDelete"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border border-red-300 text-red-600 hover:bg-red-50 disabled:opacity-40 transition"
                    >
                        Delete Selected
                    </button>
                    <button
                        type="button"
                        :disabled="!selectedIds.length"
                        @click="openBulkLocationModal('add')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border border-cyan-400 text-cyan-700 hover:bg-cyan-50 disabled:opacity-40 transition"
                    >
                        Add to location
                    </button>
                    <button
                        type="button"
                        :disabled="!selectedIds.length"
                        @click="openBulkLocationModal('remove')"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border border-gray-400 text-gray-700 hover:bg-gray-100 disabled:opacity-40 transition"
                    >
                        Remove from location
                    </button>
                    <div class="inline-flex items-center gap-1">
                        <button
                            type="button"
                            :disabled="!selectedIds.length"
                            @click="bulkDeactivate"
                            class="px-3 py-1.5 text-xs font-medium rounded-full border border-amber-400 text-amber-700 hover:bg-amber-50 disabled:opacity-40 transition"
                        >
                            Deactivate Selected
                        </button>
                        <InfoTooltip text="Deactivated products will be hidden from POS/Sell screens." />
                    </div>
                    <button
                        type="button"
                        :disabled="!selectedIds.length"
                        @click="bulkWooCommerceSync"
                        class="px-3 py-1.5 text-xs font-medium rounded-full border border-amber-300 text-amber-600 hover:bg-amber-50 disabled:opacity-40 transition"
                    >
                        WooCommerce Sync
                    </button>
                </div>

                <!-- Pagination Footer matching Image 2 -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 text-xs text-gray-600">
                    <div>
                        Showing {{ products.from ?? 0 }} to {{ products.to ?? 0 }} of {{ products.total ?? 0 }} entries
                    </div>
                    <div class="flex items-center gap-1">
                        <Link
                            v-for="link in products.links"
                            :key="link.label"
                            :href="link.url || '#'"
                            preserve-state
                            v-html="link.label"
                            class="px-2.5 py-1 rounded border text-xs transition"
                            :class="link.active
                                ? 'bg-indigo-600 text-white border-indigo-600 font-semibold'
                                : (link.url ? 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' : 'bg-gray-50 text-gray-400 border-gray-200 pointer-events-none')"
                        />
                    </div>
                </div>
            </div>

            <!-- Tab 2: Stock Report -->
            <div v-show="activeTab === 'stock_report'" class="p-4">
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 text-gray-700 font-semibold">
                            <tr>
                                <th class="px-3 py-2.5 text-left">SKU</th>
                                <th class="px-3 py-2.5 text-left">Product</th>
                                <th class="px-3 py-2.5 text-left">Variation</th>
                                <th class="px-3 py-2.5 text-left">Category</th>
                                <th class="px-3 py-2.5 text-left">Location</th>
                                <th class="px-3 py-2.5 text-right">Unit Selling Price</th>
                                <th class="px-3 py-2.5 text-right">Current stock</th>
                                <th class="px-3 py-2.5 text-right">Stock Value (Purchase)</th>
                                <th class="px-3 py-2.5 text-right">Stock Value (Sale)</th>
                                <th class="px-3 py-2.5 text-right">Potential Profit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-if="!stockReport.length">
                                <td colspan="10" class="px-4 py-8 text-center text-gray-500">
                                    No stock records available yet.
                                </td>
                            </tr>
                            <tr v-for="(r, idx) in stockReport" :key="idx" class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono">{{ r.sku }}</td>
                                <td class="px-3 py-2 font-medium text-gray-900">{{ r.product_name }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ r.variation_name || '-' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ r.category_name }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ r.location_name }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(r.unit_selling_price) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ Number(r.current_stock).toFixed(2) }} {{ r.unit_short_name }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(r.stock_value_by_purchase) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(r.stock_value_by_sale) }}</td>
                                <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ formatMoney(r.potential_profit) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- View Product Modal -->
        <div v-if="viewModal.open && viewModal.product" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-base font-bold text-gray-900">{{ viewModal.product.name }}</h3>
                    <button type="button" @click="viewModal.open = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                    <div><span class="text-gray-500">SKU:</span> <strong>{{ viewModal.product.sku }}</strong></div>
                    <div><span class="text-gray-500">Type:</span> <strong>{{ viewModal.product.type_label }}</strong></div>
                    <div><span class="text-gray-500">Unit:</span> <strong>{{ viewModal.product.unit_name }}</strong></div>
                    <div><span class="text-gray-500">Category:</span> <strong>{{ viewModal.product.category_display ?? '-' }}</strong></div>
                    <div><span class="text-gray-500">Brand:</span> <strong>{{ viewModal.product.brand_name ?? '-' }}</strong></div>
                    <div><span class="text-gray-500">Tax:</span> <strong>{{ viewModal.product.tax_name ?? 'None' }}</strong></div>
                    <div><span class="text-gray-500">Locations:</span> <strong>{{ (viewModal.product.location_names ?? []).join(', ') || '-' }}</strong></div>
                    <div><span class="text-gray-500">Alert Qty:</span> <strong>{{ viewModal.product.alert_quantity ?? '-' }}</strong></div>
                    <div><span class="text-gray-500">Current Stock:</span> <strong>{{ Number(viewModal.product.current_stock ?? 0).toFixed(2) }} {{ viewModal.product.unit_short_name }}</strong></div>
                </div>
                <div v-if="viewModal.product.variations?.length" class="border rounded-lg overflow-hidden">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 text-gray-700 font-semibold">
                            <tr>
                                <th class="px-3 py-2 text-left">Variation</th>
                                <th class="px-3 py-2 text-left">SKU</th>
                                <th class="px-3 py-2 text-right">Purchase Exc. Tax</th>
                                <th class="px-3 py-2 text-right">Sell Exc. Tax</th>
                                <th class="px-3 py-2 text-right">Sell Inc. Tax</th>
                                <th class="px-3 py-2 text-right">Stock</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="v in viewModal.product.variations" :key="v.id">
                                <td class="px-3 py-2">{{ v.name === 'DUMMY' ? 'Default' : v.name }}</td>
                                <td class="px-3 py-2 font-mono">{{ v.sku }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(v.default_purchase_price) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(v.default_sell_price) }}</td>
                                <td class="px-3 py-2 text-right">{{ formatMoney(v.sell_price_inc_tax) }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ Number(v.stock ?? 0).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end">
                    <button type="button" @click="viewModal.open = false" class="px-4 py-1.5 text-xs font-medium border rounded-md">Close</button>
                </div>
            </div>
        </div>

        <!-- Opening Stock Modal -->
        <div v-if="openingStockModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Add or edit opening stock — {{ openingStockModal.productName }}</h3>
                    <button type="button" @click="openingStockModal.open = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div v-if="openingStockModal.loading" class="py-8 text-center text-xs text-gray-500">Loading stock rows...</div>
                <div v-else class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 font-semibold">
                            <tr>
                                <th class="px-3 py-2 text-left">Location</th>
                                <th class="px-3 py-2 text-left">Variation</th>
                                <th class="px-3 py-2 text-left">Quantity ({{ openingStockModal.unit }})</th>
                                <th class="px-3 py-2 text-left">Unit Cost (Before Tax)</th>
                                <th class="px-3 py-2 text-left">Lot Number</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="(row, idx) in openingStockModal.rows" :key="idx">
                                <td class="px-3 py-2">{{ row.location_name }}</td>
                                <td class="px-3 py-2">{{ row.variation_name }}</td>
                                <td class="px-3 py-2">
                                    <input v-model="row.quantity" type="number" step="0.01" min="0" class="w-24 px-2 py-1 border rounded" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.unit_cost_before_tax" type="number" step="0.01" min="0" class="w-28 px-2 py-1 border rounded" />
                                </td>
                                <td class="px-3 py-2">
                                    <input v-model="row.lot_number" type="text" class="w-28 px-2 py-1 border rounded" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="openingStockModal.open = false" class="px-3 py-1.5 text-xs border rounded-md">Close</button>
                    <button type="button" :disabled="openingStockModal.saving" @click="submitOpeningStock" class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-md">
                        Save Opening Stock
                    </button>
                </div>
            </div>
        </div>

        <!-- Stock History Modal -->
        <div v-if="historyModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full p-5 space-y-4 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Product stock history — {{ historyModal.productName }} ({{ historyModal.sku }})</h3>
                    <button type="button" @click="historyModal.open = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="text-xs text-gray-700">
                    Current Stock: <strong>{{ Number(historyModal.currentStock).toFixed(2) }} {{ historyModal.unit }}</strong>
                </div>
                <div v-if="historyModal.loading" class="py-6 text-center text-xs text-gray-500">Loading history...</div>
                <div v-else class="overflow-x-auto border rounded-lg">
                    <table class="min-w-full text-xs">
                        <thead class="bg-gray-50 font-semibold">
                            <tr>
                                <th class="px-3 py-2 text-left">Date</th>
                                <th class="px-3 py-2 text-left">Type</th>
                                <th class="px-3 py-2 text-left">Reference</th>
                                <th class="px-3 py-2 text-left">Location</th>
                                <th class="px-3 py-2 text-right">Qty Change</th>
                                <th class="px-3 py-2 text-right">New Quantity</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-if="!historyModal.entries.length">
                                <td colspan="6" class="px-3 py-6 text-center text-gray-500">No stock history found.</td>
                            </tr>
                            <tr v-for="(entry, idx) in historyModal.entries" :key="idx">
                                <td class="px-3 py-2">{{ entry.date }}</td>
                                <td class="px-3 py-2">{{ entry.type_label }}</td>
                                <td class="px-3 py-2 font-mono">{{ entry.reference_no }}</td>
                                <td class="px-3 py-2">{{ entry.location_name }}</td>
                                <td class="px-3 py-2 text-right font-semibold" :class="entry.quantity_change >= 0 ? 'text-emerald-600' : 'text-red-600'">
                                    {{ entry.quantity_change >= 0 ? '+' : '' }}{{ Number(entry.quantity_change).toFixed(2) }}
                                </td>
                                <td class="px-3 py-2 text-right font-semibold">{{ Number(entry.new_quantity).toFixed(2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Labels Modal -->
        <div v-if="labelsModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Print Labels</h3>
                    <button type="button" @click="labelsModal.open = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div v-if="labelsModal.loading" class="py-6 text-center text-xs text-gray-500">Loading labels...</div>
                <div v-else-if="labelsModal.data" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div
                        v-for="v in labelsModal.data.variations"
                        :key="v.variation_id"
                        class="border border-dashed border-gray-400 rounded p-3 text-center space-y-1"
                    >
                        <div class="text-xs font-bold text-gray-900">{{ labelsModal.data.product_name }}</div>
                        <div v-if="v.variation_name !== 'Default'" class="text-[11px] text-gray-600">{{ v.variation_name }}</div>
                        <div class="text-sm font-mono tracking-widest py-1 bg-gray-100 rounded">|||| {{ v.sku }} ||||</div>
                        <div class="text-xs font-semibold text-indigo-700">{{ formatMoney(v.sell_price_inc_tax) }}</div>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="labelsModal.open = false" class="px-3 py-1.5 text-xs border rounded-md">Close</button>
                </div>
            </div>
        </div>

        <!-- Bulk Add/Remove Location Modal -->
        <div v-if="bulkLocationModal.open" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">
                        {{ bulkLocationModal.action === 'remove' ? 'Remove from location' : 'Add to location' }}
                    </h3>
                    <button type="button" @click="bulkLocationModal.open = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Business Location:</label>
                    <select v-model="bulkLocationModal.location_id" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md">
                        <option v-for="l in filterOptions.locations" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" @click="bulkLocationModal.open = false" class="px-3 py-1.5 text-xs border rounded-md">Close</button>
                    <button type="button" @click="submitBulkLocation" class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-md">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
