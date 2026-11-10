<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import InfoTooltip from '@/Components/InfoTooltip.vue'

defineOptions({ layout: AppLayout })

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    locations: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    designations: { type: Array, default: () => [] },
    contacts: { type: Array, default: () => [] },
})

const form = useForm({
    prefix: props.user.prefix ?? '',
    first_name: props.user.first_name ?? props.user.name?.split(' ')[0] ?? '',
    last_name: props.user.last_name ?? props.user.name?.split(' ').slice(1).join(' ') ?? '',
    email: props.user.email ?? '',
    is_active: Boolean(props.user.is_active),
    enable_service_staff_pin: Boolean(props.user.enable_service_staff_pin),
    staff_pin: props.user.staff_pin ?? '',
    allow_login: Boolean(props.user.allow_login),
    username: props.user.username ?? '',
    password: '',
    password_confirmation: '',
    role: props.user.role ?? props.user.roles?.[0]?.name ?? (props.user.type === 'owner' ? 'owner' : (props.user.type === 'super_admin' ? 'super_admin' : (props.user.type === 'admin' ? 'admin' : ''))),
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

    // HRM Details & Payroll
    hrm_details: props.user.hrm_details ?? {
        department: '',
        designation: '',
        primary_work_location: '',
        basic_salary: '',
        pay_cycle: 'Per Month',
        pay_components: '',
    },
})

const submit = () => {
    form.put(route('shop.users.update', props.user.id))
}
</script>

<template>
    <Head title="Edit user" />

    <div class="max-w-7xl mx-auto space-y-6 pb-12">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-gray-900">Edit user</h1>
            <Link :href="route('shop.users.index')" class="text-xs text-indigo-600 hover:underline">
                ← Back to users
            </Link>
        </div>

        <form @submit.prevent="submit" class="space-y-6">
            <!-- Section 1: Basic User Info Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Prefix:</label>
                        <input
                            v-model="form.prefix"
                            type="text"
                            placeholder="Mr / Mrs / Miss"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">First Name:*</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            required
                            placeholder="First Name"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                        <p v-if="form.errors.first_name" class="text-xs text-red-500 mt-0.5">{{ form.errors.first_name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Last Name:</label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            placeholder="Last Name"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <div class="md:col-span-4">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Email:*</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            placeholder="Email"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                        <p v-if="form.errors.email" class="text-xs text-red-500 mt-0.5">{{ form.errors.email }}</p>
                    </div>

                    <div class="md:col-span-2 flex items-center md:pt-4">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Is active ?</span>
                            <InfoTooltip text="Activate or deactivate user login and system access." />
                        </label>
                    </div>

                    <div class="md:col-span-3 flex items-center md:pt-4">
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input
                                v-model="form.enable_service_staff_pin"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Enable service staff pin</span>
                            <InfoTooltip text="Enable quick PIN authentication for POS service staff." />
                        </label>
                    </div>

                    <!-- Staff pin input only visible when Enable service staff pin is checked -->
                    <div v-if="form.enable_service_staff_pin" class="md:col-span-3">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Staff Pin:</label>
                        <input
                            v-model="form.staff_pin"
                            type="text"
                            autocomplete="off"
                            placeholder="Staff Pin"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>
                </div>
            </div>

            <!-- Section 2: Roles and Permissions Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Roles and Permissions</h2>

                <div>
                    <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                        <input
                            v-model="form.allow_login"
                            type="checkbox"
                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span>Allow login</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Username:</label>
                        <input
                            v-model="form.username"
                            type="text"
                            placeholder="Username"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                        <p class="text-[11px] text-gray-400 mt-1">Leave blank to auto generate username</p>
                        <p v-if="form.errors.username" class="text-xs text-red-500 mt-0.5">{{ form.errors.username }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Password:</label>
                        <input
                            v-model="form.password"
                            type="password"
                            placeholder="Leave blank to keep unchanged"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                        <p v-if="form.errors.password" class="text-xs text-red-500 mt-0.5">{{ form.errors.password }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Confirm Password:</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            placeholder="Confirm Password"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>
                </div>

                <div class="space-y-4 pt-1">
                    <div>
                        <div class="flex items-center gap-1 mb-1">
                            <label class="text-xs font-semibold text-gray-700">Role:*</label>
                            <InfoTooltip text="Assign role to specify functional permissions for this user." />
                        </div>
                        <select
                            v-model="form.role"
                            required
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            :class="form.role ? 'text-gray-700' : 'text-gray-400'"
                        >
                            <option value="" disabled selected hidden>Select Role</option>
                            <option value="super_admin" class="text-gray-700">Superadmin</option>
                            <option value="admin" class="text-gray-700">Admin</option>
                            <option value="owner" class="text-gray-700">Shop Owners</option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-1">
                            <span class="text-xs font-semibold text-gray-700">Access locations</span>
                            <InfoTooltip text="Locations to which this user will have operational access." />
                        </div>

                        <div class="space-y-2">
                            <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                <input
                                    v-model="form.access_all_locations"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span>All Locations</span>
                                <InfoTooltip text="Grants permission to operate across all present and future locations." />
                            </label>

                            <div class="space-y-1 pl-4" v-if="!form.access_all_locations">
                                <label v-for="loc in locations" :key="loc.id" class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        :value="loc.id"
                                        v-model="form.access_locations"
                                        class="rounded border-gray-300 text-indigo-600"
                                    />
                                    <span>{{ loc.name }}</span>
                                </label>
                            </div>
                            <div v-else class="pl-4">
                                <label v-for="loc in locations" :key="loc.id" class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked
                                        disabled
                                        class="rounded border-gray-300 text-indigo-600 opacity-60"
                                    />
                                    <span>{{ loc.name }}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section 3: Sales Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Sales</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center gap-1 mb-1">
                            <label class="text-xs font-semibold text-gray-700">Sales Commission Percentage (%):</label>
                            <InfoTooltip text="Commission percentage earned by this user on completed sales transactions." />
                        </div>
                        <input
                            v-model="form.commission_rate"
                            type="number"
                            step="0.01"
                            placeholder="Sales Commission Percentage (%)"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>

                    <div>
                        <div class="flex items-center gap-1 mb-1">
                            <label class="text-xs font-semibold text-gray-700">Max sales discount percent:</label>
                            <InfoTooltip text="Maximum discount percentage this user is authorized to give on sales." />
                        </div>
                        <input
                            v-model="form.max_sales_discount"
                            type="number"
                            step="0.01"
                            placeholder="Max sales discount percent"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                    <div>
                        <label class="flex items-center gap-2 text-xs font-medium text-gray-700 cursor-pointer">
                            <input
                                v-model="form.allow_selected_contacts"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span>Allow Selected Contacts</span>
                            <InfoTooltip text="Restrict POS and sell operations to selected contacts only." />
                        </label>
                    </div>

                    <!-- Select Contacts input field only visible when Allow Selected Contacts is checked -->
                    <div v-if="form.allow_selected_contacts">
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Select Contacts:</label>
                        <select
                            v-model="form.selected_contacts"
                            multiple
                            class="w-full border border-gray-300 rounded px-3 py-1 text-xs focus:ring-indigo-500 focus:border-indigo-500 h-9"
                        >
                            <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 4: More Information Card (Always Open) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">More Information</h2>

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

            <!-- Section 5: Bank Details Card (Always Open) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Bank Details</h2>

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

            <!-- Section 6: HRM Details Card (Image 2) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">HRM Details</h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Department:</label>
                        <select
                            v-model="form.hrm_details.department"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 text-gray-700"
                        >
                            <option value="">Please Select</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Designation:</label>
                        <select
                            v-model="form.hrm_details.designation"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 text-gray-700"
                        >
                            <option value="">Please Select</option>
                            <option v-for="des in designations" :key="des.id" :value="des.name">{{ des.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Section 7: Payroll Card (Image 2) -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-4">
                <h2 class="text-sm font-bold text-gray-900 border-b border-gray-100 pb-2">Payroll</h2>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Primary work location:</label>
                        <select
                            v-model="form.hrm_details.primary_work_location"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 text-gray-700"
                        >
                            <option value="">Please Select</option>
                            <option v-for="loc in locations" :key="loc.id" :value="loc.id">{{ loc.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Basic salary:</label>
                        <div class="flex">
                            <input
                                v-model="form.hrm_details.basic_salary"
                                type="number"
                                step="0.01"
                                placeholder="Basic salary"
                                class="w-full border border-gray-300 rounded-l px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                            />
                            <select
                                v-model="form.hrm_details.pay_cycle"
                                class="border border-l-0 border-gray-300 rounded-r px-2.5 py-1.5 text-xs bg-gray-50 focus:ring-indigo-500 focus:border-indigo-500 text-gray-700 whitespace-nowrap min-w-[105px]"
                            >
                                <option value="Per Month">Per Month</option>
                                <option value="Per Week">Per Week</option>
                                <option value="Per Day">Per Day</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1">Pay Components:</label>
                        <input
                            v-model="form.hrm_details.pay_components"
                            type="text"
                            placeholder="Pay Components"
                            class="w-full border border-gray-300 rounded px-3 py-1.5 text-xs focus:ring-indigo-500 focus:border-indigo-500 placeholder-gray-400"
                        />
                    </div>
                </div>
            </div>

            <!-- Save Action Button matching Image 2 centered layout -->
            <div class="flex justify-center pt-2">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-8 py-2.5 bg-[#4f2bed] hover:bg-[#4323d4] text-white text-sm font-semibold rounded-md shadow transition disabled:opacity-50"
                >
                    Save
                </button>
            </div>
        </form>
    </div>
</template>
