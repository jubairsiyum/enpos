<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    role: { type: Object, required: true },
    permission_layout: { type: Array, required: true },
    permission_groups: { type: Object, default: () => ({}) },
})

const form = useForm({
    name: props.role.name,
    permissions: [...(props.role.permissions ?? [])],
})

const isRowAllSelected = (row) => {
    const checkboxes = row.items.filter(i => i.type === 'checkbox')
    const radios = row.items.filter(i => i.type === 'radio')

    const allCheckboxes = checkboxes.length === 0 || checkboxes.every(c => form.permissions.includes(c.key))
    const allRadios = radios.length === 0 || radios.every(r => r.options.some(opt => form.permissions.includes(opt.key)))

    return allCheckboxes && allRadios
}

const toggleRowSelectAll = (row) => {
    if (isRowAllSelected(row)) {
        // Collect all keys in this row and remove them
        const keysToRemove = []
        row.items.forEach(item => {
            if (item.type === 'checkbox') keysToRemove.push(item.key)
            if (item.type === 'radio') item.options.forEach(o => keysToRemove.push(o.key))
        })
        form.permissions = form.permissions.filter(p => !keysToRemove.includes(p))
    } else {
        // Select all checkboxes and default/first option of each radio group
        row.items.forEach(item => {
            if (item.type === 'checkbox') {
                if (!form.permissions.includes(item.key)) {
                    form.permissions.push(item.key)
                }
            }
            if (item.type === 'radio') {
                const groupKeys = item.options.map(o => o.key)
                // Filter out any existing selection in this radio group
                form.permissions = form.permissions.filter(p => !groupKeys.includes(p))
                // Select the first option (e.g. view all)
                form.permissions.push(item.options[0].key)
            }
        })
    }
}

const selectRadio = (options, selectedKey) => {
    const groupKeys = options.map(o => o.key)
    form.permissions = form.permissions.filter(p => !groupKeys.includes(p))
    if (selectedKey) {
        form.permissions.push(selectedKey)
    }
}

const submit = () => {
    form.put(route('shop.roles.update', props.role.id))
}
</script>

<template>
    <Head :title="`Edit Role - ${role.name}`" />

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Edit Role: {{ role.name }}</h1>
            <Link :href="route('shop.roles.index')" class="text-xs text-indigo-600 hover:underline">
                ← Back to roles
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Role Name Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <div>
                    <label class="block text-xs font-semibold text-gray-800 mb-1">Role Name:*</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        :disabled="role.is_system"
                        placeholder="Role Name"
                        class="w-full max-w-xl border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100"
                    />
                    <p v-if="role.is_system" class="text-xs text-gray-400 mt-1">System roles cannot be renamed.</p>
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>
            </div>

            <!-- Permissions Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
                <div class="border-b-2 border-indigo-600 pb-2">
                    <h2 class="text-sm font-bold text-gray-900">Permissions:</h2>
                </div>

                <div class="divide-y divide-gray-200">
                    <div
                        v-for="row in permission_layout"
                        :key="row.group"
                        class="py-4 flex flex-col md:flex-row gap-4 items-start"
                    >
                        <!-- Column 1: Group Name & Tooltip -->
                        <div class="w-full md:w-52 flex-shrink-0 flex items-center gap-1.5">
                            <span class="text-xs font-bold text-gray-900">{{ row.group }}</span>
                            <InfoTooltip v-if="row.info" :text="row.info" />
                        </div>

                        <!-- Column 2: Select All (if applicable) -->
                        <div class="w-full md:w-36 flex-shrink-0">
                            <label
                                v-if="row.has_select_all"
                                class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-700 cursor-pointer select-none"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isRowAllSelected(row)"
                                    @change="toggleRowSelectAll(row)"
                                    class="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>Select all</span>
                            </label>
                        </div>

                        <!-- Column 3: Items (Radios vs Checkboxes vs Dividers) -->
                        <div class="flex-1 space-y-2 w-full">
                            <template v-for="(item, idx) in row.items" :key="idx">
                                <!-- Horizontal Divider -->
                                <hr v-if="item.type === 'divider'" class="border-gray-200 my-2" />

                                <!-- Radio Group (Circles) -->
                                <div v-else-if="item.type === 'radio'" class="space-y-1.5">
                                    <label
                                        v-for="opt in item.options"
                                        :key="opt.key"
                                        class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer select-none hover:text-gray-900"
                                    >
                                        <input
                                            type="radio"
                                            :name="`${row.group}_${item.name}`"
                                            :value="opt.key"
                                            :checked="form.permissions.includes(opt.key)"
                                            @change="selectRadio(item.options, opt.key)"
                                            class="w-3.5 h-3.5 text-indigo-600 focus:ring-indigo-500 border-gray-300"
                                        />
                                        <span>{{ opt.label }}</span>
                                    </label>
                                </div>

                                <!-- Checkbox (Square) -->
                                <label
                                    v-else-if="item.type === 'checkbox'"
                                    class="inline-flex items-center gap-2 text-xs text-gray-700 cursor-pointer select-none hover:text-gray-900"
                                >
                                    <input
                                        type="checkbox"
                                        :value="item.key"
                                        v-model="form.permissions"
                                        class="w-3.5 h-3.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                    />
                                    <span>{{ item.label }}</span>
                                    <InfoTooltip v-if="item.info" :text="item.info" />
                                </label>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex justify-end gap-3">
                <Link
                    :href="route('shop.roles.index')"
                    class="px-5 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50 transition"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold shadow-sm transition disabled:opacity-50"
                >
                    {{ form.processing ? 'Updating...' : 'Update' }}
                </button>
            </div>
        </form>
    </div>
</template>
