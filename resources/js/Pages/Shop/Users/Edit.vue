<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const form = useForm({
    prefix: props.user.prefix ?? '',
    first_name: props.user.first_name ?? props.user.name?.split(' ')[0] ?? '',
    last_name: props.user.last_name ?? props.user.name?.split(' ').slice(1).join(' ') ?? '',
    email: props.user.email ?? '',
    is_active: Boolean(props.user.is_active),
    allow_login: Boolean(props.user.allow_login),
    username: props.user.username ?? '',
    password: '',
    password_confirmation: '',
    role: props.user.role ?? '',
    access_all_locations: !props.user.access_locations || props.user.access_locations.includes('all'),
    access_locations: props.user.access_locations ?? [],
    commission_rate: props.user.commission_rate ?? '',
    max_sales_discount: props.user.max_sales_discount ?? '',
    allow_selected_contacts: Boolean(props.user.selected_contacts?.length),
    selected_contacts: props.user.selected_contacts ?? [],

    // More Information
    dob: props.user.dob ? props.user.dob.substring(0, 10) : '',
    gender: props.user.gender ?? '',
    marital_status: props.user.marital_status ?? '',
    blood_group: props.user.blood_group ?? '',
    phone: props.user.phone ?? '',
    alt_number: props.user.alt_number ?? '',
    family_number: props.user.family_number ?? '',
    fb_link: props.user.fb_link ?? '',
    twitter_link: props.user.twitter_link ?? '',
    social_media_1: props.user.social_media_1 ?? '',
    social_media_2: props.user.social_media_2 ?? '',
    custom_field_1: props.user.custom_field_1 ?? '',
    custom_field_2: props.user.custom_field_2 ?? '',
    custom_field_3: props.user.custom_field_3 ?? '',
    custom_field_4: props.user.custom_field_4 ?? '',
    guardian_name: props.user.guardian_name ?? '',
    id_proof_name: props.user.id_proof_name ?? '',
    id_proof_number: props.user.id_proof_number ?? '',
    permanent_address: props.user.permanent_address ?? '',
    current_address: props.user.current_address ?? '',

    // Bank Details
    bank_details: props.user.bank_details ?? {
        account_holder_name: '',
        account_number: '',
        bank_name: '',
        bank_code: '',
        branch: '',
        tax_payer_id: '',
    },

    // HRM & Payroll
    hrm_details: props.user.hrm_details ?? {
        department: '',
        designation: '',
        primary_work_location: '',
        basic_salary: '',
        pay_cycle: '',
    },
})

const submit = () => {
    form.put(route('shop.users.update', props.user.id))
}
</script>

<template>
    <Head title="Edit user" />

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Edit user</h1>
            <Link :href="route('shop.users.index')" class="text-xs text-indigo-600 hover:underline">
                ← Back to users
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Basic User Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Prefix:</label>
                        <input
                            v-model="form.prefix"
                            type="text"
                            placeholder="Mr / Mrs / Miss"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">First Name:*</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            required
                            placeholder="First Name"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-0.5">{{ form.errors.first_name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Last Name:</label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            placeholder="Last Name"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Email:*</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="Email"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.email" class="text-xs text-red-500 mt-0.5">{{ form.errors.email }}</p>
                    </div>

                    <div class="flex items-center gap-6 md:pt-5">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Is active?
                        </label>

                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input
                                v-model="form.allow_login"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            Allow login
                        </label>
                    </div>
                </div>
            </div>

            <!-- Roles and Permissions Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Roles and Permissions</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Username:</label>
                        <input
                            v-model="form.username"
                            type="text"
                            placeholder="Username"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.username" class="text-xs text-red-500 mt-0.5">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password:</label>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="Leave blank to keep password"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.password" class="text-xs text-red-500 mt-0.5">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Confirm Password:</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Confirm Password"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Role:*</label>
                        <select
                            v-model="form.role"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="">Select Role</option>
                            <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Access locations:</label>
                        <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer mb-2">
                            <input
                                v-model="form.access_all_locations"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            All Locations
                        </label>
                        <div v-if="!form.access_all_locations" class="grid grid-cols-2 gap-2 p-2 border border-gray-200 rounded max-h-32 overflow-y-auto">
                            <label v-for="loc in locations" :key="loc.id" class="flex items-center gap-1.5 text-xs text-gray-600">
                                <input
                                    type="checkbox"
                                    :value="loc.id"
                                    v-model="form.access_locations"
                                    class="rounded border-gray-300 text-indigo-600"
                                />
                                {{ loc.name }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- More Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">More Information</h2>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Date of Birth:</label>
                        <input v-model="form.dob" type="date" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Gender:</label>
                        <select v-model="form.gender" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs">
                            <option value="">Please Select</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Marital Status:</label>
                        <select v-model="form.marital_status" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs">
                            <option value="">Please Select</option>
                            <option value="married">Married</option>
                            <option value="unmarried">Unmarried</option>
                            <option value="divorced">Divorced</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Blood Group:</label>
                        <input v-model="form.blood_group" type="text" placeholder="Blood Group" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Mobile Number:</label>
                        <input v-model="form.phone" type="text" placeholder="Mobile Number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Alternate contact number:</label>
                        <input v-model="form.alt_number" type="text" placeholder="Alternate contact number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Family contact number:</label>
                        <input v-model="form.family_number" type="text" placeholder="Family contact number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Permanent Address:</label>
                        <textarea v-model="form.permanent_address" rows="2" placeholder="Permanent Address" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Current Address:</label>
                        <textarea v-model="form.current_address" rows="2" placeholder="Current Address" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs"></textarea>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="flex items-center justify-end gap-3">
                <Link :href="route('shop.users.index')" class="px-4 py-2 border border-gray-300 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-50">
                    Cancel
                </Link>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-medium rounded-lg shadow-sm transition disabled:opacity-50"
                >
                    Update
                </button>
            </div>
        </form>
    </div>
</template>
