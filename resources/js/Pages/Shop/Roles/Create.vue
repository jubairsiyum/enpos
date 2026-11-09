<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    permission_groups: { type: Object, required: true },
})

const form = useForm({
    name: '',
    permissions: [],
})

const isGroupAllSelected = (groupPerms) => {
    const keys = Object.keys(groupPerms)
    return keys.every(key => form.permissions.includes(key))
}

const toggleGroup = (groupPerms) => {
    const keys = Object.keys(groupPerms)
    if (isGroupAllSelected(groupPerms)) {
        form.permissions = form.permissions.filter(p => !keys.includes(p))
    } else {
        const toAdd = keys.filter(k => !form.permissions.includes(k))
        form.permissions.push(...toAdd)
    }
}

const submit = () => {
    form.post(route('shop.roles.store'))
}
</script>

<template>
    <Head title="Add Role" />

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Add Role</h1>
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
                        placeholder="Role Name"
                        class="w-full max-w-xl border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                    />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>
            </div>

            <!-- Permissions Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-6">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Permissions:</h2>

                <div class="divide-y divide-gray-100">
                    <div
                        v-for="(perms, groupName) in permission_groups"
                        :key="groupName"
                        class="py-4 grid grid-cols-1 md:grid-cols-12 gap-4 items-start"
                    >
                        <!-- Group Name -->
                        <div class="md:col-span-3">
                            <span class="text-xs font-bold text-gray-800">{{ groupName }}</span>
                        </div>

                        <!-- Select All Checkbox -->
                        <div class="md:col-span-2">
                            <label class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 cursor-pointer">
                                <input
                                    type="checkbox"
                                    :checked="isGroupAllSelected(perms)"
                                    @change="toggleGroup(perms)"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                Select all
                            </label>
                        </div>

                        <!-- Individual Permissions -->
                        <div class="md:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <label
                                v-for="(label, permKey) in perms"
                                :key="permKey"
                                class="inline-flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer hover:text-gray-900"
                            >
                                <input
                                    type="checkbox"
                                    :value="permKey"
                                    v-model="form.permissions"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>{{ label }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3">
                <Link :href="route('shop.roles.index')" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition disabled:opacity-50"
                >
                    Save
                </button>
            </div>
        </form>
    </div>
</template>
