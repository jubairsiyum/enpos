<script setup>
import { reactive, ref, watch } from 'vue'
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''

const props = defineProps({
    barcode_settings:     { type: Array, default: () => [] },
    selling_price_groups: { type: Array, default: () => [] },
    default_display:      { type: Object, default: () => ({}) },
    recent_jobs:          { type: Array, default: () => [] },
})

const searchQuery = ref('')
const searchResults = ref([])
const isSearching = ref(false)
const selectedItems = ref([])

const display = reactive({
    show_product_name:  props.default_display.show_product_name ?? true,
    product_name_size:  props.default_display.product_name_size ?? 15,
    show_variation:     props.default_display.show_variation ?? true,
    variation_size:     props.default_display.variation_size ?? 17,
    show_price:         props.default_display.show_price ?? true,
    price_size:         props.default_display.price_size ?? 17,
    price_type:         props.default_display.price_type ?? 'inclusive',
    show_business_name: props.default_display.show_business_name ?? true,
    business_name_size: props.default_display.business_name_size ?? 20,
    show_packing_date:  props.default_display.show_packing_date ?? true,
    packing_date_size:  props.default_display.packing_date_size ?? 12,
})

const defaultSetting = props.barcode_settings.find(s => s.is_default) ?? props.barcode_settings[0]
const barcodeSettingId = ref(defaultSetting?.id ?? '')

const previewResult = ref(null)
const errorMessage = ref('')
const isLoadingPreview = ref(false)
const isLoadingPdf = ref(false)

let searchTimer = null
watch(searchQuery, (val) => {
    clearTimeout(searchTimer)
    const term = val.trim()
    if (!term) {
        searchResults.value = []
        return
    }
    searchTimer = setTimeout(async () => {
        isSearching.value = true
        try {
            const url = `${route('shop.products.print-labels.search')}?search=${encodeURIComponent(term)}`
            const res = await fetch(url, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
            const data = await res.json()
            searchResults.value = res.ok ? (data.data ?? []) : []
        } catch {
            searchResults.value = []
        } finally {
            isSearching.value = false
        }
    }, 250)
})

const addProductRow = (item) => {
    const existing = selectedItems.value.find(r => r.variation_id === item.variation_id)
    if (existing) {
        existing.quantity += 1
    } else {
        selectedItems.value.push({
            product_id:             item.product_id,
            variation_id:           item.variation_id,
            full_name:              item.full_name,
            sku:                    item.variation_sku || item.product_sku,
            quantity:               1,
            packing_date:           '',
            selling_price_group_id: '',
        })
    }
    searchQuery.value = ''
    searchResults.value = []
    errorMessage.value = ''
}

const removeRow = (index) => {
    selectedItems.value.splice(index, 1)
}

const buildPayload = () => ({
    barcode_setting_id: barcodeSettingId.value || null,
    items: selectedItems.value.map(row => ({
        product_id:             row.product_id,
        variation_id:           row.variation_id,
        quantity:               Number(row.quantity) || 1,
        packing_date:           row.packing_date || null,
        selling_price_group_id: row.selling_price_group_id ? Number(row.selling_price_group_id) : null,
    })),
    display: { ...display },
})

const generatePreview = async () => {
    errorMessage.value = ''
    if (!selectedItems.value.length) {
        errorMessage.value = 'Please add at least one product to print labels.'
        return
    }
    isLoadingPreview.value = true
    try {
        const res = await fetch(route('shop.products.print-labels.preview'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(buildPayload()),
        })
        const data = await res.json()
        if (!res.ok) {
            errorMessage.value = data.message || 'Unable to generate label preview.'
            return
        }
        previewResult.value = data.data
    } catch {
        errorMessage.value = 'Unable to generate label preview.'
    } finally {
        isLoadingPreview.value = false
    }
}

const downloadPreviewPdf = async () => {
    errorMessage.value = ''
    if (!selectedItems.value.length) {
        errorMessage.value = 'Please add at least one product to print labels.'
        return
    }
    isLoadingPdf.value = true
    try {
        const res = await fetch(route('shop.products.print-labels.preview-pdf'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/pdf, application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify(buildPayload()),
        })
        if (!res.ok) {
            const err = await res.json().catch(() => ({}))
            errorMessage.value = err.message || 'Unable to generate PDF labels.'
            return
        }
        const blob = await res.blob()
        const blobUrl = window.URL.createObjectURL(blob)
        const link = document.createElement('a')
        link.href = blobUrl
        link.setAttribute('download', 'labels_preview.pdf')
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)
        window.URL.revokeObjectURL(blobUrl)
    } catch {
        errorMessage.value = 'Unable to generate PDF labels.'
    } finally {
        isLoadingPdf.value = false
    }
}

const printPreviewArea = () => {
    window.print()
}
</script>

<template>
    <Head title="Products - Print Labels" />

    <div class="space-y-4">
        <div class="flex items-center gap-2">
            <h1 class="text-xl font-bold text-gray-900">Print Labels</h1>
            <InfoTooltip text="Add products -> Choose informations to show in Labels -> Select Barcode Setting -> Preview Labels -> Print" />
        </div>

        <div
            v-if="errorMessage"
            class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg p-3.5 shadow-sm"
        >
            {{ errorMessage }}
        </div>

        <!-- Product Search & Table Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <div class="max-w-3xl mx-auto relative">
                <div class="flex items-center border border-gray-300 rounded overflow-hidden focus-within:ring-1 focus-within:ring-indigo-500">
                    <span class="px-3 py-2 bg-gray-50 border-r border-gray-300 text-gray-500 text-xs">🔍</span>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Enter products name to print labels"
                        class="w-full px-3 py-2 text-xs text-gray-700 focus:outline-none"
                    />
                </div>

                <div
                    v-if="searchResults.length || isSearching"
                    class="absolute left-0 right-0 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-64 overflow-y-auto"
                >
                    <div v-if="isSearching" class="px-3 py-2 text-xs text-gray-500">Searching...</div>
                    <button
                        v-for="item in searchResults"
                        :key="item.variation_id"
                        type="button"
                        @click="addProductRow(item)"
                        class="w-full text-left px-3 py-2 text-xs hover:bg-indigo-50 flex items-center justify-between border-b border-gray-100 last:border-0"
                    >
                        <span class="font-medium text-gray-800">{{ item.full_name }}</span>
                        <span class="text-[11px] text-gray-500">SKU: {{ item.variation_sku || item.product_sku }}</span>
                    </button>
                </div>
            </div>

            <div class="max-w-4xl mx-auto overflow-x-auto">
                <table class="w-full text-left text-xs border border-gray-200">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-gray-800 font-semibold">
                            <th class="py-2.5 px-3 border-r border-gray-200">Products</th>
                            <th class="py-2.5 px-3 border-r border-gray-200 w-36">No. of labels</th>
                            <th class="py-2.5 px-3 border-r border-gray-200 w-44">Packing Date</th>
                            <th class="py-2.5 px-3 w-48">Selling Price Group</th>
                            <th class="py-2.5 px-3 w-12"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr v-if="!selectedItems.length">
                            <td colspan="5" class="py-4 px-3 text-center text-gray-400">
                                Search and add products above to print labels.
                            </td>
                        </tr>
                        <tr v-for="(row, idx) in selectedItems" :key="row.variation_id">
                            <td class="py-2 px-3 border-r border-gray-200">
                                <div class="font-medium text-gray-800">{{ row.full_name }}</div>
                                <div class="text-[11px] text-gray-500">{{ row.sku }}</div>
                            </td>
                            <td class="py-2 px-3 border-r border-gray-200">
                                <input
                                    v-model.number="row.quantity"
                                    type="number"
                                    min="1"
                                    max="5000"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-xs"
                                />
                            </td>
                            <td class="py-2 px-3 border-r border-gray-200">
                                <input
                                    v-model="row.packing_date"
                                    type="date"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-xs"
                                />
                            </td>
                            <td class="py-2 px-3">
                                <select
                                    v-model="row.selling_price_group_id"
                                    class="w-full border border-gray-300 rounded px-2 py-1 text-xs"
                                >
                                    <option value="">Default Selling Price</option>
                                    <option v-for="g in selling_price_groups" :key="g.id" :value="g.id">
                                        {{ g.name }}
                                    </option>
                                </select>
                            </td>
                            <td class="py-2 px-3 text-center">
                                <button
                                    type="button"
                                    @click="removeRow(idx)"
                                    class="text-red-500 hover:text-red-700 font-bold"
                                >
                                    ×
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Information to show in Labels & Barcode Setting Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <h2 class="text-sm font-medium text-gray-700">Information to show in Labels</h2>

            <div class="grid grid-cols-1 md:grid-cols-4 border border-gray-100 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                <div class="p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                        <input v-model="display.show_product_name" type="checkbox" class="rounded text-indigo-600" />
                        <span>Product Name</span>
                    </label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-semibold text-gray-700">Size</span>
                        <input v-model.number="display.product_name_size" type="number" min="6" max="72" class="w-full px-2.5 py-1.5 text-xs focus:outline-none" />
                    </div>
                </div>

                <div class="p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                        <input v-model="display.show_variation" type="checkbox" class="rounded text-indigo-600" />
                        <span>Product Variation (recommended)</span>
                    </label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-semibold text-gray-700">Size</span>
                        <input v-model.number="display.variation_size" type="number" min="6" max="72" class="w-full px-2.5 py-1.5 text-xs focus:outline-none" />
                    </div>
                </div>

                <div class="p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                        <input v-model="display.show_price" type="checkbox" class="rounded text-indigo-600" />
                        <span>Product Price</span>
                    </label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-semibold text-gray-700">Size</span>
                        <input v-model.number="display.price_size" type="number" min="6" max="72" class="w-full px-2.5 py-1.5 text-xs focus:outline-none" />
                    </div>
                </div>

                <div class="p-3 space-y-2">
                    <label class="block text-xs font-semibold text-gray-800">Show Price:</label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-bold text-gray-700">i</span>
                        <select v-model="display.price_type" class="w-full px-2.5 py-1.5 text-xs focus:outline-none">
                            <option value="inclusive">Inc. tax</option>
                            <option value="exclusive">Exc. tax</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 border border-gray-100 divide-y md:divide-y-0 md:divide-x divide-gray-100">
                <div class="p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                        <input v-model="display.show_business_name" type="checkbox" class="rounded text-indigo-600" />
                        <span>Business name</span>
                    </label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-semibold text-gray-700">Size</span>
                        <input v-model.number="display.business_name_size" type="number" min="6" max="72" class="w-full px-2.5 py-1.5 text-xs focus:outline-none" />
                    </div>
                </div>

                <div class="p-3 space-y-2">
                    <label class="flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                        <input v-model="display.show_packing_date" type="checkbox" class="rounded text-indigo-600" />
                        <span>Print packing date</span>
                    </label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-1.5 bg-gray-50 border-r border-gray-300 text-xs font-semibold text-gray-700">Size</span>
                        <input v-model.number="display.packing_date_size" type="number" min="6" max="72" class="w-full px-2.5 py-1.5 text-xs focus:outline-none" />
                    </div>
                </div>
            </div>

            <hr class="border-gray-200" />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-end">
                <div>
                    <label class="block text-xs font-semibold text-gray-800 mb-1.5">Barcode setting:</label>
                    <div class="flex border border-gray-300 rounded overflow-hidden">
                        <span class="px-2.5 py-2 bg-gray-50 border-r border-gray-300 text-xs text-gray-700">⚙</span>
                        <select v-model="barcodeSettingId" class="w-full px-2.5 py-2 text-xs focus:outline-none">
                            <option v-for="setting in barcode_settings" :key="setting.id" :value="setting.id">
                                {{ setting.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="isLoadingPreview"
                        @click="generatePreview"
                        class="px-6 py-2.5 bg-[#4f2bed] hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
                    >
                        {{ isLoadingPreview ? 'Loading...' : 'Preview' }}
                    </button>
                    <button
                        type="button"
                        :disabled="isLoadingPdf"
                        @click="downloadPreviewPdf"
                        class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
                    >
                        {{ isLoadingPdf ? 'Generating...' : 'Preview PDF' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Live Preview Area -->
        <div
            v-if="previewResult"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4"
        >
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-800">
                    Labels Preview ({{ previewResult.total_labels }} labels)
                </h2>
                <button
                    type="button"
                    @click="printPreviewArea"
                    class="px-4 py-1.5 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold rounded-lg transition"
                >
                    Print
                </button>
            </div>

            <div v-html="previewResult.html"></div>
        </div>
    </div>
</template>
