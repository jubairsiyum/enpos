<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    instructions:         { type: Array, default: () => [] },
    expected_headers:     { type: Array, default: () => [] },
    selling_price_groups: { type: Array, default: () => [] },
    history:              { type: Object, default: () => ({ data: [], meta: {} }) },
})

const fileInput = ref(null)

const form = useForm({
    file: null,
})

const onFileChange = (event) => {
    form.file = event.target.files?.[0] ?? null
}

const submitImport = () => {
    form.post(route('shop.products.update-price.import'), {
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            if (fileInput.value) {
                fileInput.value.value = ''
            }
        },
    })
}
</script>

<template>
    <Head title="Products - Update Price" />

    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Update Price</h1>
        </div>

        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
            <h2 class="text-sm font-medium text-gray-700">Import Export Product Price</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
                <div>
                    <a
                        :href="route('shop.products.update-price.export')"
                        download="product_prices.xls"
                        class="inline-flex items-center justify-center px-5 py-2.5 bg-[#4f2bed] hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                    >
                        Export product prices
                    </a>
                </div>

                <form @submit.prevent="submitImport" class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-800 mb-1.5">
                            File To Import:
                        </label>
                        <input
                            ref="fileInput"
                            type="file"
                            accept=".xls,.xlsx,.csv,.txt"
                            @change="onFileChange"
                            class="block w-full text-xs text-gray-700 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border file:border-gray-300 file:text-xs file:font-medium file:bg-gray-50 file:text-gray-700 hover:file:bg-gray-100"
                        />
                        <p v-if="form.errors.file" class="text-red-500 text-[11px] mt-1">
                            {{ form.errors.file }}
                        </p>
                    </div>

                    <div>
                        <button
                            type="submit"
                            :disabled="form.processing || !form.file"
                            class="px-5 py-2.5 bg-[#4f2bed] hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
                        >
                            {{ form.processing ? 'Submitting...' : 'Submit' }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="pt-2 space-y-2">
                <h3 class="text-sm font-medium text-gray-700">Instructions:</h3>
                <ul class="text-xs text-gray-600 space-y-1">
                    <li v-for="(line, index) in instructions" :key="index">
                        {{ line }}
                    </li>
                </ul>
            </div>
        </div>

        <div
            v-if="history?.data?.length"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-3"
        >
            <h2 class="text-sm font-semibold text-gray-800">Recent Price Updates</h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/70 font-semibold text-gray-800">
                            <th class="py-2.5 px-3">Product</th>
                            <th class="py-2.5 px-3">SKU</th>
                            <th class="py-2.5 px-3">Variation</th>
                            <th class="py-2.5 px-3 text-right">Old Price (Inc. Tax)</th>
                            <th class="py-2.5 px-3 text-right">New Price (Inc. Tax)</th>
                            <th class="py-2.5 px-3">Updated By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr v-for="item in history.data" :key="item.id">
                            <td class="py-2.5 px-3 font-medium text-gray-900">{{ item.product_name }}</td>
                            <td class="py-2.5 px-3">{{ item.sku }}</td>
                            <td class="py-2.5 px-3">{{ item.variation_name === 'DUMMY' ? 'Default' : item.variation_name }}</td>
                            <td class="py-2.5 px-3 text-right">{{ Number(item.old_sell_price_inc_tax).toFixed(2) }}</td>
                            <td class="py-2.5 px-3 text-right font-semibold text-indigo-700">{{ Number(item.new_sell_price_inc_tax).toFixed(2) }}</td>
                            <td class="py-2.5 px-3">{{ item.updater?.name ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
