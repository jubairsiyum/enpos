<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

defineProps({
    columns: { type: Array, required: true },
})

const fileInput = ref(null)

const form = useForm({
    file: null,
})

const onFileChange = (event) => {
    form.file = event.target.files?.[0] ?? null
}

const submitImport = () => {
    form.post(route('shop.products.import.store'), {
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
    <Head title="Products - Import Products" />

    <div class="space-y-4">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Import Products</h1>
        </div>

        <!-- Flash messages -->
        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>

        <!-- Upload & Template Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
            <form @submit.prevent="submitImport" class="flex flex-wrap items-end gap-6">
                <div class="min-w-[260px]">
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
                        class="px-5 py-2 bg-[#4f2bed] hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Submitting...' : 'Submit' }}
                    </button>
                </div>
            </form>

            <div>
                <a
                    :href="route('shop.products.import.template')"
                    download="import_products_template.xls"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-lg shadow-sm transition"
                >
                    <span>⬇</span>
                    <span>Download template file</span>
                </a>
            </div>
        </div>

        <!-- Instructions Card -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
            <h2 class="text-sm font-semibold text-gray-800">Instructions</h2>

            <div class="text-xs text-gray-700 space-y-0.5">
                <p class="font-semibold">Carefully follow the instructions before importing the file.</p>
                <p>The columns of the file should be in the following order.</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/70 font-semibold text-gray-800">
                            <th class="py-2.5 px-3 w-40">Column Number</th>
                            <th class="py-2.5 px-3 w-96">Column Name</th>
                            <th class="py-2.5 px-3">Instruction</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr
                            v-for="col in columns"
                            :key="col.number"
                            class="odd:bg-gray-50/50 even:bg-white"
                        >
                            <td class="py-2.5 px-3 align-top">{{ col.number }}</td>
                            <td class="py-2.5 px-3 align-top">
                                <span class="font-medium text-gray-900">{{ col.name }}</span>
                                <span class="block sm:inline text-[11px] text-gray-500 sm:ml-1">{{ col.requirement }}</span>
                            </td>
                            <td class="py-2.5 px-3 align-top whitespace-pre-line font-medium text-gray-800">
                                {{ col.instruction }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
