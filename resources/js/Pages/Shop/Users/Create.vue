<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    roles: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
})

const form = useForm({
    prefix: '',
    first_name: '',
    last_name: '',
    email: '',
    is_active: true,
    allow_login: true,
    username: '',
    password: '',
    password_confirmation: '',
    role: '',
    access_all_locations: true,
    access_locations: [],
    commission_rate: '',
    max_sales_discount: '',
    allow_selected_contacts: false,
    selected_contacts: [],

    // More Information
    dob: '',
    gender: '',
    marital_status: '',
    blood_group: '',
    phone: '',
    alt_number: '',
    family_number: '',
    fb_link: '',
    twitter_link: '',
    social_media_1: '',
    social_media_2: '',
    custom_field_1: '',
    custom_field_2: '',
    custom_field_3: '',
    custom_field_4: '',
    guardian_name: '',
    id_proof_name: '',
    id_proof_number: '',
    permanent_address: '',
    current_address: '',

    // Bank Details
    bank_details: {
        account_holder_name: '',
        account_number: '',
        bank_name: '',
        bank_code: '',
        branch: '',
        tax_payer_id: '',
    },

    // HRM & Payroll
    hrm_details: {
        department: '',
        designation: '',
        primary_work_location: '',
        basic_salary: '',
        pay_cycle: '',
    },
})

const submit = () => {
    form.post(route('shop.users.store'))
}
</script>

<template>
    <Head title="Add user" />

    <div class="max-w-7xl mx-auto space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Add user</h1>
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
                        <p class="text-[10px] text-gray-400 mt-0.5">Leave blank to auto generate username</p>
                        <p v-if="form.errors.username" class="text-xs text-red-500 mt-0.5">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Password:*</label>
                        <input
                            v-model="form.password"
                            type="password"
                            required
                            placeholder="Password"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                        <p v-if="form.errors.password" class="text-xs text-red-500 mt-0.5">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Confirm Password:*</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            required
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

            <!-- Sales Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Sales</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Sales Commission Percentage (%):</label>
                        <input
                            v-model="form.commission_rate"
                            type="number"
                            step="0.01"
                            placeholder="Sales Commission Percentage (%)"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Max sales discount percent:</label>
                        <input
                            v-model="form.max_sales_discount"
                            type="number"
                            step="0.01"
                            placeholder="Max sales discount percent"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                        />
                    </div>
                </div>

                <div>
                    <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                        <input
                            v-model="form.allow_selected_contacts"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        Allow Selected Contacts
                    </label>
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

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Facebook Link:</label>
                        <input v-model="form.fb_link" type="text" placeholder="Facebook Link" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Twitter Link:</label>
                        <input v-model="form.twitter_link" type="text" placeholder="Twitter Link" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Social Media 1:</label>
                        <input v-model="form.social_media_1" type="text" placeholder="Social Media 1" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Social Media 2:</label>
                        <input v-model="form.social_media_2" type="text" placeholder="Social Media 2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Custom Field 1:</label>
                        <input v-model="form.custom_field_1" type="text" placeholder="Custom Field 1" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Custom Field 2:</label>
                        <input v-model="form.custom_field_2" type="text" placeholder="Custom Field 2" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Custom Field 3:</label>
                        <input v-model="form.custom_field_3" type="text" placeholder="Custom Field 3" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Custom Field 4:</label>
                        <input v-model="form.custom_field_4" type="text" placeholder="Custom Field 4" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Guardian Name:</label>
                        <input v-model="form.guardian_name" type="text" placeholder="Guardian Name" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">ID Proof Name:</label>
                        <input v-model="form.id_proof_name" type="text" placeholder="ID Proof Name" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">ID proof number:</label>
                        <input v-model="form.id_proof_number" type="text" placeholder="ID proof number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
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

            <!-- Bank Details Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">Bank Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Account Holder's Name:</label>
                        <input v-model="form.bank_details.account_holder_name" type="text" placeholder="Account Holder's Name" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Account Number:</label>
                        <input v-model="form.bank_details.account_number" type="text" placeholder="Account Number" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bank Name:</label>
                        <input v-model="form.bank_details.bank_name" type="text" placeholder="Bank Name" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Bank Identifier Code:</label>
                        <input v-model="form.bank_details.bank_code" type="text" placeholder="Bank Identifier Code" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Branch:</label>
                        <input v-model="form.bank_details.branch" type="text" placeholder="Branch" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Tax Payer ID:</label>
                        <input v-model="form.bank_details.tax_payer_id" type="text" placeholder="Tax Payer ID" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>
            </div>

            <!-- HRM & Payroll Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-semibold text-gray-900 border-b border-gray-100 pb-2">HRM Details & Payroll</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Department:</label>
                        <input v-model="form.hrm_details.department" type="text" placeholder="Department" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Designation:</label>
                        <input v-model="form.hrm_details.designation" type="text" placeholder="Designation" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Primary work location:</label>
                        <select v-model="form.hrm_details.primary_work_location" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs">
                            <option value="">Please Select</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Basic salary:</label>
                        <input v-model="form.hrm_details.basic_salary" type="number" step="0.01" placeholder="Basic salary" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Pay cycle:</label>
                        <select v-model="form.hrm_details.pay_cycle" class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs">
                            <option value="">Please Select</option>
                            <option value="monthly">Monthly</option>
                            <option value="weekly">Weekly</option>
                            <option value="daily">Daily</option>
                        </select>
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
                    Save
                </button>
            </div>
        </form>
    </div>
</template>
