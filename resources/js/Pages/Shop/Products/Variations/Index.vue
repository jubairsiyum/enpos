<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import DataTableActions from '@/Components/DataTableActions.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    variations: { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
    can:        { type: Object, default: () => ({}) },
})

const search  = ref(props.filters.search ?? '')
const perPage = ref(Number(props.filters.per_page ?? 25))
const sortBy  = ref(props.filters.sort_by ?? 'id')
const sortDir = ref(props.filters.sort_dir ?? 'asc')

const isModalOpen      = ref(false)
const editingVariation = ref(null)

const columns = reactive([
    { key: 'name',        label: 'Variations', visible: true },
    { key: 'values_text', label: 'Values',     visible: true },
])

const isVisible = (key) => columns.find(c => c.key === key)?.visible !== false

const form = useForm({
    name:   '',
    values: [''],
})

const applyFilters = (replace = false) => {
    router.get(
        route('shop.variations.index'),
        {
            search:   search.value || undefined,
            per_page: perPage.value,
            sort_by:  sortBy.value,
            sort_dir: sortDir.value,
        },
        { preserveState: true, replace }
    )
}

let searchTimeout = null
watch(search, () => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(true), 300)
})

const toggleSort = (col) => {
    if (sortBy.value === col) {
        sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortBy.value = col
        sortDir.value = 'asc'
    }
    applyFilters(true)
}

const openCreateModal = () => {
    editingVariation.value = null
    form.reset()
    form.clearErrors()
    form.name = ''
    form.values = ['']
    isModalOpen.value = true
}

const openEditModal = (variation) => {
    editingVariation.value = variation
    form.clearErrors()
    form.name = variation.name ?? ''
    form.values = variation.values?.length
        ? variation.values.map(v => v.name)
        : ['']
    isModalOpen.value = true
}

const addValueInput = () => {
    form.values.push('')
}

const removeValueInput = (index) => {
    if (form.values.length > 1) {
        form.values.splice(index, 1)
    }
}

const submitForm = () => {
    if (editingVariation.value) {
        form.put(route('shop.variations.update', editingVariation.value.id), {
            onSuccess: () => {
                isModalOpen.value = false
            },
        })
    } else {
        form.post(route('shop.variations.store'), {
            onSuccess: () => {
                isModalOpen.value = false
                form.reset()
                form.values = ['']
            },
        })
    }
}

const deleteVariation = (variation) => {
    if (confirm(`Are you sure you want to delete variation "${variation.name}"?`)) {
        router.delete(route('shop.variations.destroy', variation.id))
    }
}

const valueError = computed(() => {
    if (form.errors.values) {
        return form.errors.values
    }
    const firstItemErrorKey = Object.keys(form.errors).find(k => k.startsWith('values.'))
    return firstItemErrorKey ? form.errors[firstItemErrorKey] : null
})
</script>

<template>
    <Head title="Products - Variations" />

    <div class="space-y-4">
        <div class="flex items-baseline gap-2">
            <h1 class="text-xl font-bold text-gray-900">Variations</h1>
            <span class="text-xs text-gray-500">Manage product variations</span>
        </div>

        <div
            v-if="$page.props.flash?.success"
            class="bg-green-50 border border-green-200 text-green-800 text-xs rounded-lg p-3.5 flex items-center gap-2 shadow-sm"
        >
            <span class="text-green-600 font-bold text-sm">✓</span>
            <span class="font-medium">{{ $page.props.flash.success }}</span>
        </div>

        <div
            v-if="$page.props.flash?.error"
            class="bg-red-50 border border-red-200 text-red-700 text-xs rounded-lg p-3.5 shadow-sm"
        >
            {{ $page.props.flash.error }}
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-4">
                <h2 class="text-base font-semibold text-gray-800">All variations</h2>
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
                    :data="variations.data ?? []"
                    filename="variations"
                />

                <div>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Search ..."
                        class="border border-gray-300 rounded-md px-3 py-1.5 text-xs w-48 focus:ring-indigo-500 focus:border-indigo-500"
                    />
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-y border-gray-200 bg-gray-50/70 font-semibold text-gray-800">
                            <th
                                v-if="isVisible('name')"
                                @click="toggleSort('name')"
                                class="py-2.5 px-3 cursor-pointer select-none"
                            >
                                Variations
                            </th>
                            <th v-if="isVisible('values_text')" class="py-2.5 px-3">
                                Values
                            </th>
                            <th class="py-2.5 px-3 w-48">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700">
                        <tr v-if="!variations.data?.length">
                            <td colspan="3" class="py-6 text-center text-gray-400">
                                No variations found.
                            </td>
                        </tr>
                        <tr
                            v-for="row in variations.data"
                            :key="row.id"
                            class="hover:bg-gray-50/60"
                        >
                            <td v-if="isVisible('name')" class="py-2.5 px-3 font-medium text-gray-900">
                                {{ row.name }}
                            </td>
                            <td v-if="isVisible('values_text')" class="py-2.5 px-3">
                                {{ row.values_text }}
                            </td>
                            <td class="py-2.5 px-3">
                                <div class="flex items-center gap-2">
                                    <button
                                        v-if="can.edit"
                                        type="button"
                                        @click="openEditModal(row)"
                                        class="px-2.5 py-1 border border-indigo-300 text-indigo-700 hover:bg-indigo-50 rounded text-[11px] font-medium transition"
                                    >
                                        ✎ Edit
                                    </button>
                                    <button
                                        v-if="can.delete && row.can_delete"
                                        type="button"
                                        @click="deleteVariation(row)"
                                        class="px-2.5 py-1 border border-red-300 text-red-600 hover:bg-red-50 rounded text-[11px] font-medium transition"
                                    >
                                        🗑 Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 text-xs text-gray-600">
                <div>
                    Showing {{ variations.from ?? 0 }} to {{ variations.to ?? 0 }} of {{ variations.total ?? 0 }} entries
                </div>
                <div class="flex items-center gap-1">
                    <template v-for="(link, i) in variations.links" :key="i">
                        <Link
                            v-if="link.url"
                            :href="link.url"
                            preserve-state
                            class="px-2.5 py-1 border rounded text-xs transition"
                            :class="link.active ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                            v-html="link.label"
                        />
                        <span
                            v-else
                            class="px-2.5 py-1 border border-gray-200 rounded text-xs text-gray-400"
                            v-html="link.label"
                        />
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Add / Edit Variation Modal (4th image) -->
    <div
        v-if="isModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
    >
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                <h3 class="text-base font-semibold text-gray-800">
                    {{ editingVariation ? 'Edit Variation' : 'Add Variation' }}
                </h3>
                <button
                    type="button"
                    @click="isModalOpen = false"
                    class="text-gray-400 hover:text-gray-600 text-lg leading-none"
                >
                    ×
                </button>
            </div>

            <form @submit.prevent="submitForm" class="p-5 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-center">
                    <label class="text-xs font-semibold text-gray-800">
                        Variation Name:*
                    </label>
                    <div class="sm:col-span-2">
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Variation Name"
                            required
                            class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.name" class="text-red-500 text-[11px] mt-1">
                            {{ form.errors.name }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-start">
                    <label class="text-xs font-semibold text-gray-800 pt-2">
                        Add variation values:*
                    </label>
                    <div class="sm:col-span-2 space-y-2">
                        <div
                            v-for="(val, idx) in form.values"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="form.values[idx]"
                                type="text"
                                placeholder="Variation value"
                                required
                                class="flex-1 border border-gray-300 rounded px-3 py-2 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <button
                                v-if="idx === 0"
                                type="button"
                                @click="addValueInput"
                                class="w-8 h-8 flex items-center justify-center bg-[#4f2bed] hover:bg-indigo-700 text-white text-sm font-bold rounded shadow-sm transition"
                                title="Add value"
                            >
                                +
                            </button>
                            <button
                                v-else
                                type="button"
                                @click="removeValueInput(idx)"
                                class="w-8 h-8 flex items-center justify-center bg-red-500 hover:bg-red-600 text-white text-sm font-bold rounded shadow-sm transition"
                                title="Remove value"
                            >
                                −
                            </button>
                        </div>
                        <p v-if="valueError" class="text-red-500 text-[11px]">
                            {{ valueError }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-gray-100">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-5 py-2 bg-[#4f2bed] hover:bg-indigo-700 text-white text-xs font-semibold rounded shadow-sm transition disabled:opacity-50"
                    >
                        {{ form.processing ? 'Saving...' : 'Save' }}
                    </button>
                    <button
                        type="button"
                        @click="isModalOpen = false"
                        class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white text-xs font-semibold rounded shadow-sm transition"
                    >
                        Close
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
