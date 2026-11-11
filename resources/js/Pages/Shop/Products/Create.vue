<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    product: { type: Object, default: null },
    formOptions: { type: Object, required: true },
    can: { type: Object, default: () => ({}) },
})

const page = usePage()
const isEditing = computed(() => Boolean(props.product?.id))

const defaultProfit = Number(props.formOptions?.default_profit_percent ?? 25)
const firstVariation = props.product?.variations?.[0] ?? null

const form = useForm({
    name: props.product?.name ?? '',
    sku: props.product?.sku ?? '',
    barcode_type: props.product?.barcode_type ?? 'C128',
    unit_id: props.product?.unit_id ?? (props.formOptions?.units?.[0]?.id ?? ''),
    sub_unit_id: props.product?.sub_unit_id ?? '',
    brand_id: props.product?.brand_id ?? '',
    category_id: props.product?.category_id ?? '',
    sub_category_id: props.product?.sub_category_id ?? '',
    warranty_id: props.product?.warranty_id ?? '',
    location_ids: props.product?.location_ids?.length
        ? [...props.product.location_ids]
        : (props.formOptions?.locations?.map(l => l.id) ?? []),
    enable_stock: props.product ? Boolean(props.product.enable_stock) : true,
    alert_quantity: props.product?.alert_quantity ?? '',
    description: props.product?.description ?? '',
    image: null,
    product_brochure: null,
    enable_sr_no: props.product ? Boolean(props.product.enable_sr_no) : false,
    not_for_selling: props.product ? Boolean(props.product.not_for_selling) : false,
    weight: props.product?.weight ?? '',
    preparation_time_in_minutes: props.product?.preparation_time_in_minutes ?? '',
    woocommerce_disable_sync: props.product ? Boolean(props.product.woocommerce_disable_sync) : false,
    tax_id: props.product?.tax_id ?? '',
    tax_type: props.product?.tax_type ?? 'exclusive',
    type: props.product?.type ?? 'single',
    default_purchase_price: props.product?.default_purchase_price ?? '',
    dpp_inc_tax: props.product?.dpp_inc_tax ?? '',
    profit_percent: props.product?.profit_percent ?? defaultProfit,
    default_sell_price: props.product?.default_sell_price ?? '',
    sell_price_inc_tax: props.product?.sell_price_inc_tax ?? '',
    variation_image: null,
    variations: props.product?.type === 'variable' && props.product?.variations?.length
        ? props.product.variations.map(v => ({
            id: v.id,
            name: v.name,
            sku: v.sku,
            default_purchase_price: v.default_purchase_price,
            dpp_inc_tax: v.dpp_inc_tax,
            profit_percent: v.profit_percent,
            default_sell_price: v.default_sell_price,
            sell_price_inc_tax: v.sell_price_inc_tax,
            image: null,
        }))
        : [
            {
                id: null,
                name: 'Default',
                sku: '',
                default_purchase_price: '',
                dpp_inc_tax: '',
                profit_percent: defaultProfit,
                default_sell_price: '',
                sell_price_inc_tax: '',
                image: null,
            },
        ],
    combo_components: firstVariation?.combo_components?.length
        ? firstVariation.combo_components.map(c => ({
            component_variation_id: c.component_variation_id,
            qty: c.qty,
        }))
        : [],
    save_action: 'save',
})

// ── Image preview ─────────────────────────────────────────────────────────────
const imagePreview = ref(props.product?.image_url ?? null)
const handleImageChange = (e) => {
    const file = e.target.files?.[0] ?? null
    form.image = file
    if (file) {
        imagePreview.value = URL.createObjectURL(file)
    }
}

const handleBrochureChange = (e) => {
    form.product_brochure = e.target.files?.[0] ?? null
}

const handleSingleVariationImageChange = (e) => {
    form.variation_image = e.target.files?.[0] ?? null
}

// ── Cascading Sub-categories ──────────────────────────────────────────────────
const filteredSubCategories = computed(() => {
    if (!form.category_id) return []
    return (props.formOptions?.sub_categories ?? []).filter(
        sub => Number(sub.parent_id) === Number(form.category_id)
    )
})

watch(() => form.category_id, () => {
    const stillValid = filteredSubCategories.value.some(
        sub => Number(sub.id) === Number(form.sub_category_id)
    )
    if (!stillValid) {
        form.sub_category_id = ''
    }
})

// ── Business Locations multi-select chip helper ───────────────────────────────
const selectedLocations = computed(() =>
    (props.formOptions?.locations ?? []).filter(l => form.location_ids.includes(l.id))
)
const availableLocationsToAdd = computed(() =>
    (props.formOptions?.locations ?? []).filter(l => !form.location_ids.includes(l.id))
)
const locationPicker = ref('')
const addLocation = () => {
    if (!locationPicker.value) return
    const id = Number(locationPicker.value)
    if (!form.location_ids.includes(id)) {
        form.location_ids.push(id)
    }
    locationPicker.value = ''
}
const removeLocation = (id) => {
    form.location_ids = form.location_ids.filter(lId => lId !== id)
}

// ── Tax & Pricing Auto-calculation ────────────────────────────────────────────
const activeTaxPercent = computed(() => {
    if (!form.tax_id) return 0
    const tax = (props.formOptions?.tax_rates ?? []).find(t => Number(t.id) === Number(form.tax_id))
    return tax ? Number(tax.amount) : 0
})

const round2 = (val) => Math.round((Number(val) + Number.EPSILON) * 100) / 100

const syncSingleFromExcPurchase = () => {
    const pExc = Number(form.default_purchase_price || 0)
    const mult = 1 + activeTaxPercent.value / 100
    form.dpp_inc_tax = round2(pExc * mult)
    const margin = Number(form.profit_percent || 0)
    const sExc = round2(pExc * (1 + margin / 100))
    form.default_sell_price = sExc
    form.sell_price_inc_tax = round2(sExc * mult)
}

const syncSingleFromIncPurchase = () => {
    const pInc = Number(form.dpp_inc_tax || 0)
    const mult = 1 + activeTaxPercent.value / 100
    const pExc = mult > 0 ? round2(pInc / mult) : pInc
    form.default_purchase_price = pExc
    const margin = Number(form.profit_percent || 0)
    const sExc = round2(pExc * (1 + margin / 100))
    form.default_sell_price = sExc
    form.sell_price_inc_tax = round2(sExc * mult)
}

const syncSingleFromMargin = () => {
    const pExc = Number(form.default_purchase_price || 0)
    const margin = Number(form.profit_percent || 0)
    const mult = 1 + activeTaxPercent.value / 100
    const sExc = round2(pExc * (1 + margin / 100))
    form.default_sell_price = sExc
    form.sell_price_inc_tax = round2(sExc * mult)
}

const syncSingleFromSellExc = () => {
    const sExc = Number(form.default_sell_price || 0)
    const pExc = Number(form.default_purchase_price || 0)
    const mult = 1 + activeTaxPercent.value / 100
    form.sell_price_inc_tax = round2(sExc * mult)
    if (pExc > 0) {
        form.profit_percent = round2(((sExc - pExc) / pExc) * 100)
    }
}

watch(() => form.tax_id, () => {
    if (form.type === 'single' && form.default_purchase_price !== '') {
        syncSingleFromExcPurchase()
    }
})

// ── Variable Product Helpers ──────────────────────────────────────────────────
const addVariationRow = () => {
    form.variations.push({
        id: null,
        name: '',
        sku: '',
        default_purchase_price: '',
        dpp_inc_tax: '',
        profit_percent: defaultProfit,
        default_sell_price: '',
        sell_price_inc_tax: '',
        image: null,
    })
}

const removeVariationRow = (idx) => {
    if (form.variations.length <= 1) return
    form.variations.splice(idx, 1)
}

const syncVariationRow = (row) => {
    const pExc = Number(row.default_purchase_price || 0)
    const mult = 1 + activeTaxPercent.value / 100
    row.dpp_inc_tax = round2(pExc * mult)
    const margin = Number(row.profit_percent || 0)
    const sExc = round2(pExc * (1 + margin / 100))
    row.default_sell_price = sExc
    row.sell_price_inc_tax = round2(sExc * mult)
}

// ── Combo Product Helpers ─────────────────────────────────────────────────────
const comboPickerId = ref('')
const addComboComponent = () => {
    if (!comboPickerId.value) return
    const variationId = Number(comboPickerId.value)
    if (!form.combo_components.some(c => c.component_variation_id === variationId)) {
        form.combo_components.push({ component_variation_id: variationId, qty: 1 })
        recalcComboPurchaseTotal()
    }
    comboPickerId.value = ''
}

const removeComboComponent = (idx) => {
    form.combo_components.splice(idx, 1)
    recalcComboPurchaseTotal()
}

const findComboMeta = (variationId) =>
    (props.formOptions?.combo_variations ?? []).find(v => Number(v.id) === Number(variationId))

const recalcComboPurchaseTotal = () => {
    const total = form.combo_components.reduce((sum, item) => {
        const meta = findComboMeta(item.component_variation_id)
        return sum + (Number(meta?.default_purchase_price ?? 0) * Number(item.qty || 0))
    }, 0)
    form.default_purchase_price = round2(total)
    syncSingleFromExcPurchase()
}

// ── Quick Add Unit / Brand Modals ─────────────────────────────────────────────
const showQuickUnitModal = ref(false)
const quickUnit = reactive({ actual_name: '', short_name: '', allow_decimal: false })
const quickUnitSaving = ref(false)

const submitQuickUnit = () => {
    quickUnitSaving.value = true
    router.post(route('shop.products.quick-units'), quickUnit, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showQuickUnitModal.value = false
            quickUnit.actual_name = ''
            quickUnit.short_name = ''
            quickUnit.allow_decimal = false
            router.reload({ only: ['formOptions'] })
        },
        onFinish: () => {
            quickUnitSaving.value = false
        },
    })
}

const showQuickBrandModal = ref(false)
const quickBrand = reactive({ name: '', description: '' })
const quickBrandSaving = ref(false)

const submitQuickBrand = () => {
    quickBrandSaving.value = true
    router.post(route('shop.products.quick-brands'), quickBrand, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showQuickBrandModal.value = false
            quickBrand.name = ''
            quickBrand.description = ''
            router.reload({ only: ['formOptions'] })
        },
        onFinish: () => {
            quickBrandSaving.value = false
        },
    })
}

// ── Submit Form ───────────────────────────────────────────────────────────────
const submitForm = (saveAction = 'save') => {
    form.save_action = saveAction

    const payloadTransform = (data) => {
        const cleaned = { ...data }
        if (cleaned.type !== 'variable') {
            delete cleaned.variations
        } else {
            cleaned.variations = cleaned.variations.map(v => ({ ...v }))
        }
        if (cleaned.type !== 'combo') {
            delete cleaned.combo_components
        }
        return cleaned
    }

    if (isEditing.value) {
        form.transform((data) => ({ ...payloadTransform(data), _method: 'PUT' }))
            .post(route('shop.products.update', props.product.id), {
                forceFormData: true,
                preserveScroll: true,
            })
        return
    }

    form.transform(payloadTransform)
        .post(route('shop.products.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                if (saveAction === 'save_and_add_another') {
                    form.reset('name', 'sku', 'alert_quantity', 'description', 'image', 'product_brochure', 'weight', 'preparation_time_in_minutes', 'default_purchase_price', 'dpp_inc_tax', 'default_sell_price', 'sell_price_inc_tax', 'variation_image')
                    imagePreview.value = null
                }
            },
        })
}
</script>

<template>
    <Head :title="isEditing ? 'Edit product' : 'Add new product'" />

    <div class="space-y-5 max-w-7xl mx-auto">
        <!-- Flash Messages -->
        <div
            v-if="page.props.flash?.success"
            class="px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between"
        >
            <span>{{ page.props.flash.success }}</span>
        </div>

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">
                {{ isEditing ? 'Edit product' : 'Add new product' }}
            </h1>
            <Link
                :href="route('shop.products.index')"
                class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 transition"
            >
                ← Back to List Products
            </Link>
        </div>

        <form @submit.prevent="submitForm('save')" class="space-y-5">
            <!-- Card 1: Basic Information -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-5">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- Product Name -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Product Name:<span class="text-red-500">*</span>
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Product Name"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-[11px] text-red-600">{{ form.errors.name }}</p>
                    </div>

                    <!-- SKU -->
                    <div>
                        <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                            <span>SKU:</span>
                            <InfoTooltip text="Unique product ID or stock keeping unit. Leave blank to automatically generate SKU." />
                        </label>
                        <input
                            v-model="form.sku"
                            type="text"
                            placeholder="SKU"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.sku" class="mt-1 text-[11px] text-red-600">{{ form.errors.sku }}</p>
                    </div>

                    <!-- Barcode Type -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Barcode Type:<span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.barcode_type"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option v-for="b in formOptions.barcode_types" :key="b.value" :value="b.value">
                                {{ b.label }}
                            </option>
                        </select>
                        <p v-if="form.errors.barcode_type" class="mt-1 text-[11px] text-red-600">{{ form.errors.barcode_type }}</p>
                    </div>

                    <!-- Unit + Quick Add -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Unit:<span class="text-red-500">*</span>
                        </label>
                        <div class="flex items-stretch">
                            <select
                                v-model="form.unit_id"
                                required
                                class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-l-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Please Select</option>
                                <option v-for="u in formOptions.units" :key="u.id" :value="u.id">
                                    {{ u.actual_name }} ({{ u.short_name }})
                                </option>
                            </select>
                            <button
                                type="button"
                                @click="showQuickUnitModal = true"
                                title="Add Unit"
                                class="px-2.5 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 hover:bg-indigo-50 text-indigo-600 font-bold text-sm transition"
                            >
                                +
                            </button>
                        </div>
                        <p v-if="form.errors.unit_id" class="mt-1 text-[11px] text-red-600">{{ form.errors.unit_id }}</p>
                    </div>

                    <!-- Brand + Quick Add -->
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Brand:</label>
                        <div class="flex items-stretch">
                            <select
                                v-model="form.brand_id"
                                class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-l-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Please Select</option>
                                <option v-for="b in formOptions.brands" :key="b.id" :value="b.id">
                                    {{ b.name }}
                                </option>
                            </select>
                            <button
                                type="button"
                                @click="showQuickBrandModal = true"
                                title="Add Brand"
                                class="px-2.5 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 hover:bg-indigo-50 text-indigo-600 font-bold text-sm transition"
                            >
                                +
                            </button>
                        </div>
                    </div>

                    <!-- Category & Sub category -->
                    <div class="space-y-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Category:</label>
                            <select
                                v-model="form.category_id"
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Please Select</option>
                                <option v-for="c in formOptions.categories" :key="c.id" :value="c.id">
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Sub category:</label>
                            <select
                                v-model="form.sub_category_id"
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="">Please Select</option>
                                <option v-for="sc in filteredSubCategories" :key="sc.id" :value="sc.id">
                                    {{ sc.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Business Locations (chip multi-select) -->
                    <div>
                        <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                            <span>Business Locations:</span>
                            <InfoTooltip text="Locations where product will be available." />
                        </label>
                        <div class="min-h-[36px] p-1.5 border border-gray-300 rounded-md bg-white flex flex-wrap items-center gap-1.5">
                            <span
                                v-for="loc in selectedLocations"
                                :key="loc.id"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-sky-600 text-white text-[11px] font-medium"
                            >
                                <button type="button" @click="removeLocation(loc.id)" class="hover:text-sky-200">×</button>
                                <span>{{ loc.name }}</span>
                            </span>
                            <select
                                v-if="availableLocationsToAdd.length"
                                v-model="locationPicker"
                                @change="addLocation"
                                class="text-xs border-0 focus:ring-0 py-0.5 pl-1 pr-6 text-gray-600 bg-transparent"
                            >
                                <option value="">+ Add location</option>
                                <option v-for="loc in availableLocationsToAdd" :key="loc.id" :value="loc.id">
                                    {{ loc.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Manage Stock -->
                    <div class="flex flex-col justify-center pt-3">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-800 cursor-pointer">
                            <input
                                v-model="form.enable_stock"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Manage Stock?</span>
                            <InfoTooltip text="Enable or disable stock management for this product." />
                        </label>
                        <p class="text-[11px] text-gray-500 italic mt-0.5 pl-5">
                            Enable stock management at product level
                        </p>
                    </div>

                    <!-- Alert quantity -->
                    <div v-if="form.enable_stock">
                        <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                            <span>Alert quantity:</span>
                            <InfoTooltip text="Get low stock alerts when quantity reaches this threshold." />
                        </label>
                        <input
                            v-model="form.alert_quantity"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Alert quantity"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>

                <!-- Description, Brochure & Product Image -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-gray-100">
                    <div class="md:col-span-2 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Product Description:</label>
                            <textarea
                                v-model="form.description"
                                rows="6"
                                placeholder="Enter detailed product description..."
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                            ></textarea>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Product brochure:</label>
                            <input
                                type="file"
                                accept=".pdf,.csv,.zip,.doc,.docx,.jpeg,.jpg,.png"
                                @change="handleBrochureChange"
                                class="block text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border file:border-gray-300 file:text-xs file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100"
                            />
                            <p class="text-[10px] text-gray-500 mt-1">
                                Max File size: 5MB<br />
                                Allowed File: .pdf, .csv, .zip, .doc, .docx, .jpeg, .jpg, .png
                            </p>
                            <p v-if="form.errors.product_brochure" class="text-[11px] text-red-600">{{ form.errors.product_brochure }}</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Product image:</label>
                        <div class="space-y-2">
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleImageChange"
                                class="block w-full text-xs text-gray-600 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-700"
                            />
                            <p class="text-[10px] text-gray-500">
                                Max File size: 5MB<br />
                                Aspect ratio should be 1:1
                            </p>
                            <div v-if="imagePreview" class="mt-2 w-28 h-28 rounded-lg border border-gray-200 overflow-hidden bg-gray-50 flex items-center justify-center">
                                <img :src="imagePreview" alt="Preview" class="w-full h-full object-cover" />
                            </div>
                            <p v-if="form.errors.image" class="text-[11px] text-red-600">{{ form.errors.image }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Serial, Selling & Logistics -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                        <input
                            v-model="form.enable_sr_no"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Enable Product description, IMEI or Serial Number</span>
                        <InfoTooltip text="Allow capturing serial number or IMEI per sale item." />
                    </label>

                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                        <input
                            v-model="form.not_for_selling"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Not for selling</span>
                        <InfoTooltip text="If checked, product will not be displayed on POS/Sell screen." />
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Weight:</label>
                        <input
                            v-model="form.weight"
                            type="text"
                            placeholder="Weight"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Service staff timer/Preparation time (in minutes):
                        </label>
                        <input
                            v-model="form.preparation_time_in_minutes"
                            type="number"
                            min="0"
                            placeholder="Service staff timer/Preparation time (in minutes)"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                            <input
                                v-model="form.woocommerce_disable_sync"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Disable Woocommerce Sync</span>
                            <InfoTooltip text="If checked, this product will not sync with WooCommerce." />
                        </label>
                    </div>
                </div>
            </div>

            <!-- Card 3: Tax & Pricing -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Applicable Tax:</label>
                        <select
                            v-model="form.tax_id"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">None</option>
                            <option v-for="t in formOptions.tax_rates" :key="t.id" :value="t.id">
                                {{ t.name }} ({{ t.amount }}%)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">
                            Selling Price Tax Type:<span class="text-red-500">*</span>
                        </label>
                        <select
                            v-model="form.tax_type"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="exclusive">Exclusive</option>
                            <option value="inclusive">Inclusive</option>
                        </select>
                    </div>

                    <div>
                        <label class="flex items-center gap-1 text-xs font-semibold text-gray-700 mb-1">
                            <span>Product Type:<span class="text-red-500">*</span></span>
                            <InfoTooltip text="Single: standard item. Variable: sizes/colors. Combo: bundle of existing products." />
                        </label>
                        <select
                            v-model="form.type"
                            class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md bg-white focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="single">Single</option>
                            <option value="variable">Variable</option>
                            <option value="combo">Combo</option>
                        </select>
                    </div>
                </div>

                <!-- Single Pricing Matrix (matching Image 3 green bar) -->
                <div v-if="form.type === 'single'" class="border border-gray-200 rounded-lg overflow-hidden">
                    <div class="grid grid-cols-12 bg-[#2dce89] text-white text-xs font-semibold">
                        <div class="col-span-5 px-3 py-2 border-r border-emerald-400">Default Purchase Price</div>
                        <div class="col-span-2 px-3 py-2 border-r border-emerald-400 flex items-center gap-1">
                            <span>x Margin(%)</span>
                            <InfoTooltip text="Default profit margin percentage." />
                        </div>
                        <div class="col-span-3 px-3 py-2 border-r border-emerald-400">Default Selling Price</div>
                        <div class="col-span-2 px-3 py-2">Product image</div>
                    </div>

                    <div class="grid grid-cols-12 gap-3 p-3 items-start bg-white">
                        <div class="col-span-5 grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">
                                    Exc. tax:<span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.default_purchase_price"
                                    @input="syncSingleFromExcPurchase"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Exc. tax"
                                    class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-700 mb-1">
                                    Inc. tax:<span class="text-red-500">*</span>
                                </label>
                                <input
                                    v-model="form.dpp_inc_tax"
                                    @input="syncSingleFromIncPurchase"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Inc. tax"
                                    class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">&nbsp;</label>
                            <input
                                v-model="form.profit_percent"
                                @input="syncSingleFromMargin"
                                type="number"
                                step="0.01"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <div class="col-span-3">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">
                                Exc. Tax:<span class="text-red-500">*</span>
                            </label>
                            <input
                                v-model="form.default_sell_price"
                                @input="syncSingleFromSellExc"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Exc. Tax"
                                class="w-full px-2.5 py-1.5 text-xs border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500"
                            />
                        </div>

                        <div class="col-span-2">
                            <label class="block text-[11px] font-semibold text-gray-700 mb-1">Product image:</label>
                            <input
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                @change="handleSingleVariationImageChange"
                                class="block w-full text-[11px] text-gray-600"
                            />
                            <p class="text-[10px] text-gray-400 mt-0.5">Max 5MB · 1:1</p>
                        </div>
                    </div>
                </div>

                <!-- Variable Product Variations Matrix -->
                <div v-else-if="form.type === 'variable'" class="space-y-3">
                    <div class="border border-gray-200 rounded-lg overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-[#2dce89] text-white">
                                <tr>
                                    <th class="px-3 py-2 text-left">Variation Name*</th>
                                    <th class="px-3 py-2 text-left">SKU</th>
                                    <th class="px-3 py-2 text-left">Purchase Exc. Tax*</th>
                                    <th class="px-3 py-2 text-left">Purchase Inc. Tax</th>
                                    <th class="px-3 py-2 text-left">Margin(%)</th>
                                    <th class="px-3 py-2 text-left">Sell Exc. Tax*</th>
                                    <th class="px-3 py-2 text-left">Image</th>
                                    <th class="px-3 py-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-for="(v, idx) in form.variations" :key="idx">
                                    <td class="p-2">
                                        <input
                                            v-model="v.name"
                                            type="text"
                                            required
                                            placeholder="e.g. Red / Large"
                                            class="w-full px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model="v.sku"
                                            type="text"
                                            placeholder="Auto"
                                            class="w-full px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model="v.default_purchase_price"
                                            @input="syncVariationRow(v)"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            required
                                            class="w-24 px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model="v.dpp_inc_tax"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 px-2 py-1 border border-gray-300 rounded bg-gray-50"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model="v.profit_percent"
                                            @input="syncVariationRow(v)"
                                            type="number"
                                            step="0.01"
                                            class="w-20 px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            v-model="v.default_sell_price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="p-2">
                                        <input
                                            type="file"
                                            accept="image/*"
                                            @change="e => v.image = e.target.files?.[0] ?? null"
                                            class="text-[11px] w-36"
                                        />
                                    </td>
                                    <td class="p-2 text-center">
                                        <button
                                            type="button"
                                            @click="removeVariationRow(idx)"
                                            class="px-2 py-1 text-red-600 hover:bg-red-50 rounded"
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <button
                        type="button"
                        @click="addVariationRow"
                        class="px-3 py-1.5 text-xs font-medium rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition"
                    >
                        + Add Variation Row
                    </button>
                </div>

                <!-- Combo Product Components -->
                <div v-else class="space-y-3">
                    <div class="flex items-center gap-2">
                        <select
                            v-model="comboPickerId"
                            class="flex-1 px-3 py-2 text-xs border border-gray-300 rounded-md bg-white"
                        >
                            <option value="">Select component product to add...</option>
                            <option v-for="cv in formOptions.combo_variations" :key="cv.id" :value="cv.id">
                                {{ cv.label }} — Purchase: {{ cv.default_purchase_price }}
                            </option>
                        </select>
                        <button
                            type="button"
                            @click="addComboComponent"
                            class="px-3 py-2 text-xs font-medium rounded-md bg-indigo-600 text-white hover:bg-indigo-700"
                        >
                            + Add Component
                        </button>
                    </div>

                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full text-xs">
                            <thead class="bg-[#2dce89] text-white">
                                <tr>
                                    <th class="px-3 py-2 text-left">Component</th>
                                    <th class="px-3 py-2 text-left">Quantity</th>
                                    <th class="px-3 py-2 text-left">Unit Purchase Price</th>
                                    <th class="px-3 py-2 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <tr v-if="!form.combo_components.length">
                                    <td colspan="4" class="px-3 py-4 text-center text-gray-500">
                                        No combo components added yet.
                                    </td>
                                </tr>
                                <tr v-for="(comp, idx) in form.combo_components" :key="comp.component_variation_id">
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        {{ findComboMeta(comp.component_variation_id)?.label ?? comp.component_variation_id }}
                                    </td>
                                    <td class="px-3 py-2">
                                        <input
                                            v-model="comp.qty"
                                            @input="recalcComboPurchaseTotal"
                                            type="number"
                                            step="0.01"
                                            min="0.01"
                                            class="w-24 px-2 py-1 border border-gray-300 rounded"
                                        />
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">
                                        {{ findComboMeta(comp.component_variation_id)?.default_purchase_price ?? 0 }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <button
                                            type="button"
                                            @click="removeComboComponent(idx)"
                                            class="px-2 py-1 text-red-600 hover:bg-red-50 rounded"
                                        >
                                            ✕
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Net Purchase Price (Exc. tax):</label>
                            <input
                                v-model="form.default_purchase_price"
                                @input="syncSingleFromExcPurchase"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">x Margin(%):</label>
                            <input
                                v-model="form.profit_percent"
                                @input="syncSingleFromMargin"
                                type="number"
                                step="0.01"
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1">Default Selling Price (Exc. Tax):</label>
                            <input
                                v-model="form.default_sell_price"
                                @input="syncSingleFromSellExc"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons (centered, matching Image 3) -->
            <div class="flex flex-wrap items-center justify-center gap-3 pb-6">
                <template v-if="!isEditing">
                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="submitForm('save_and_opening_stock')"
                        class="px-5 py-2.5 rounded-md bg-[#5b4d96] hover:bg-[#4c3f82] text-white text-xs font-semibold shadow-sm disabled:opacity-50 transition"
                    >
                        Save &amp; Add Opening Stock
                    </button>
                    <button
                        type="button"
                        :disabled="form.processing"
                        @click="submitForm('save_and_add_another')"
                        class="px-5 py-2.5 rounded-md bg-[#d61f69] hover:bg-[#b81858] text-white text-xs font-semibold shadow-sm disabled:opacity-50 transition"
                    >
                        Save And Add Another
                    </button>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 rounded-md bg-[#4f46e5] hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm disabled:opacity-50 transition"
                    >
                        Save
                    </button>
                </template>
                <template v-else>
                    <Link
                        :href="route('shop.products.index')"
                        class="px-5 py-2.5 rounded-md border border-gray-300 bg-white text-gray-700 text-xs font-semibold hover:bg-gray-50 transition"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 rounded-md bg-[#4f46e5] hover:bg-indigo-700 text-white text-xs font-semibold shadow-sm disabled:opacity-50 transition"
                    >
                        Update Product
                    </button>
                </template>
            </div>
        </form>

        <!-- Quick Add Unit Modal -->
        <div v-if="showQuickUnitModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Add Unit</h3>
                    <button type="button" @click="showQuickUnitModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Name:*</label>
                        <input v-model="quickUnit.actual_name" type="text" placeholder="e.g. Pieces" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Short name:*</label>
                        <input v-model="quickUnit.short_name" type="text" placeholder="e.g. Pc(s)" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs text-gray-700">
                        <input v-model="quickUnit.allow_decimal" type="checkbox" class="rounded border-gray-300 text-indigo-600" />
                        <span>Allow decimal</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showQuickUnitModal = false" class="px-3 py-1.5 text-xs border rounded-md">Close</button>
                    <button type="button" :disabled="quickUnitSaving" @click="submitQuickUnit" class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-md">Save</button>
                </div>
            </div>
        </div>

        <!-- Quick Add Brand Modal -->
        <div v-if="showQuickBrandModal" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between border-b pb-2">
                    <h3 class="text-sm font-bold text-gray-900">Add Brand</h3>
                    <button type="button" @click="showQuickBrandModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Brand Name:*</label>
                        <input v-model="quickBrand.name" type="text" placeholder="e.g. Apple" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md" />
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Short description:</label>
                        <input v-model="quickBrand.description" type="text" class="w-full px-3 py-2 text-xs border border-gray-300 rounded-md" />
                    </div>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showQuickBrandModal = false" class="px-3 py-1.5 text-xs border rounded-md">Close</button>
                    <button type="button" :disabled="quickBrandSaving" @click="submitQuickBrand" class="px-4 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-md">Save</button>
                </div>
            </div>
        </div>
    </div>
</template>
