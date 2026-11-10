<script setup>
import { ref, reactive, computed } from "vue";
import { Head, useForm, router, Link } from "@inertiajs/vue3";
import PlatformLayout from "@/Layouts/PlatformLayout.vue";
import DataTableActions from "@/Components/DataTableActions.vue";

defineOptions({ layout: PlatformLayout });

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    businesses: { type: Array, default: () => [] },
    permission_groups: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const activeTab = ref(props.filters.tab ?? "users"); // 'users', 'roles', 'agents'
const search = ref(props.filters.search ?? "");

// Modals
const showUserModal = ref(false);
const showRoleModal = ref(false);
const showAgentModal = ref(false);
const selectedRolePermissions = ref(null);

const userColumns = reactive([
    { key: "username", label: "Username", visible: true },
    { key: "name", label: "Name", visible: true },
    { key: "role", label: "Role / Type", visible: true },
    { key: "business_name", label: "Business / Shop", visible: true },
    { key: "email", label: "Email", visible: true },
    { key: "phone", label: "Phone", visible: true },
]);

const agentColumns = reactive([
    { key: "name", label: "Name", visible: true },
    { key: "email", label: "Email", visible: true },
    { key: "phone", label: "Contact Number", visible: true },
    { key: "address", label: "Address", visible: true },
    {
        key: "commission_rate",
        label: "Sales Commission Percentage (%)",
        visible: true,
    },
]);

// Forms
const userForm = useForm({
    first_name: "",
    last_name: "",
    email: "",
    username: "",
    password: "",
    type: "staff",
    business_id: "",
    role: "",
    phone: "",
    is_active: true,
});

const agentForm = useForm({
    prefix: "",
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    permanent_address: "",
    commission_rate: "",
});

const openAddUser = () => {
    userForm.reset();
    userForm.clearErrors();
    showUserModal.value = true;
};

const submitUser = () => {
    userForm.post(route("platform.users.store"), {
        onSuccess: () => {
            showUserModal.value = false;
            userForm.reset();
        },
    });
};

const openAddAgent = () => {
    agentForm.reset();
    agentForm.clearErrors();
    showAgentModal.value = true;
};

const submitAgent = () => {
    agentForm.post(route("shop.sales-commission-agents.store"), {
        onSuccess: () => {
            showAgentModal.value = false;
            agentForm.reset();
        },
    });
};

const inspectRole = (role) => {
    selectedRolePermissions.value = role;
    showRoleModal.value = true;
};

const deleteUser = (user) => {
    if (confirm(`Are you sure you want to delete ${user.name}?`)) {
        router.delete(route("platform.users.destroy", user.id));
    }
};
</script>

<template>
    <Head title="User Management — SuperAdmin View" />

    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-gray-900">
                    User Management Hub
                </h1>
                <p class="text-xs text-gray-500">
                    SuperAdmin centralized control for Users, Roles, and
                    Commission Agents
                </p>
            </div>

            <!-- Direct Link to Shop POS view -->
            <div class="flex items-center gap-2">
                <Link
                    :href="route('shop.users.index')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-indigo-50 border border-indigo-200 text-indigo-700 hover:bg-indigo-100 rounded-lg text-xs font-semibold shadow-sm transition"
                >
                    <span>👁️</span> Switch to Shop POS View
                </Link>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div
            class="border-b border-gray-200 bg-white rounded-t-xl px-4 pt-2 shadow-sm"
        >
            <nav class="flex space-x-6 text-xs font-medium">
                <button
                    type="button"
                    @click="activeTab = 'users'"
                    :class="[
                        activeTab === 'users'
                            ? 'border-indigo-600 text-indigo-600 font-bold border-b-2'
                            : 'text-gray-500 hover:text-gray-700',
                        'pb-3 px-2 flex items-center gap-2',
                    ]"
                >
                    <span>👥</span>
                    <span>Users</span>
                    <span
                        class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]"
                        >{{ users.total ?? users.data?.length ?? 0 }}</span
                    >
                </button>

                <button
                    type="button"
                    @click="activeTab = 'roles'"
                    :class="[
                        activeTab === 'roles'
                            ? 'border-indigo-600 text-indigo-600 font-bold border-b-2'
                            : 'text-gray-500 hover:text-gray-700',
                        'pb-3 px-2 flex items-center gap-2',
                    ]"
                >
                    <span>🛡️</span>
                    <span>Roles & Permissions</span>
                    <span
                        class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]"
                        >{{ roles.length }}</span
                    >
                </button>

                <button
                    type="button"
                    @click="activeTab = 'agents'"
                    :class="[
                        activeTab === 'agents'
                            ? 'border-indigo-600 text-indigo-600 font-bold border-b-2'
                            : 'text-gray-500 hover:text-gray-700',
                        'pb-3 px-2 flex items-center gap-2',
                    ]"
                >
                    <span>💼</span>
                    <span>Sales Commission Agents</span>
                    <span
                        class="bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full text-[10px]"
                        >{{ agents.length }}</span
                    >
                </button>
            </nav>
        </div>

        <!-- TAB 1: USERS -->
        <div
            v-show="activeTab === 'users'"
            class="bg-white rounded-b-xl shadow-sm border border-gray-100 p-6 space-y-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h2 class="text-sm font-bold text-gray-800">
                    All System Users
                </h2>
                <div class="flex items-center gap-3">
                    <DataTableActions
                        :columns="userColumns"
                        :data="users.data"
                        filename="users_export"
                    />
                    <button
                        type="button"
                        @click="openAddUser"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm"
                    >
                        + Add User
                    </button>
                </div>
            </div>

            <!-- Users Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-y border-gray-200 bg-gray-50/70 text-gray-700 uppercase font-semibold"
                        >
                            <th class="py-3 px-4">Username</th>
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Role / Type</th>
                            <th class="py-3 px-4">Shop / Workspace</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td class="py-3 px-4 font-medium text-gray-900">
                                {{ user.username }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-gray-800">
                                {{ user.name }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-700 capitalize"
                                >
                                    {{ user.role }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ user.business_name }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ user.email }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    :class="[
                                        user.is_active
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-red-100 text-red-700',
                                        'px-2 py-0.5 rounded text-[10px] font-bold',
                                    ]"
                                >
                                    {{ user.is_active ? "Active" : "Inactive" }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button
                                    type="button"
                                    @click="deleteUser(user)"
                                    class="text-red-600 hover:text-red-800 text-xs font-medium"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: ROLES & PERMISSIONS -->
        <div
            v-show="activeTab === 'roles'"
            class="bg-white rounded-b-xl shadow-sm border border-gray-100 p-6 space-y-4"
        >
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-bold text-gray-800">
                        Roles & Access Control
                    </h2>
                    <p class="text-xs text-gray-500">
                        SuperAdmin, Admin, ShopOwners, Cashier, and Custom
                        business roles
                    </p>
                </div>
                <Link
                    :href="route('shop.roles.create')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm"
                >
                    + Add Role (Full Permissions Form)
                </Link>
            </div>

            <!-- Roles Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-y border-gray-200 bg-gray-50/70 text-gray-700 uppercase font-semibold"
                        >
                            <th class="py-3 px-4">Role Name</th>
                            <th class="py-3 px-4">Role Type</th>
                            <th class="py-3 px-4">Permissions Assigned</th>
                            <th class="py-3 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="role in roles"
                            :key="role.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td
                                class="py-3 px-4 font-bold text-gray-900 capitalize"
                            >
                                {{ role.name }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    :class="[
                                        role.is_system
                                            ? 'bg-amber-100 text-amber-800'
                                            : 'bg-blue-100 text-blue-800',
                                        'px-2 py-0.5 rounded text-[10px] font-bold',
                                    ]"
                                >
                                    {{
                                        role.is_system
                                            ? "System Predefined"
                                            : "Custom Shop Role"
                                    }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-indigo-600">
                                {{ role.permissions_count }} permissions
                            </td>
                            <td class="py-3 px-4 text-center">
                                <button
                                    type="button"
                                    @click="inspectRole(role)"
                                    class="px-2.5 py-1 border border-indigo-400 text-indigo-600 hover:bg-indigo-50 rounded text-xs font-medium transition"
                                >
                                    👁️ View Permissions
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TAB 3: SALES COMMISSION AGENTS -->
        <div
            v-show="activeTab === 'agents'"
            class="bg-white rounded-b-xl shadow-sm border border-gray-100 p-6 space-y-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-sm font-bold text-gray-800">
                        Sales Commission Agents
                    </h2>
                    <p class="text-xs text-gray-500">
                        Track all sales commission representatives across the
                        system
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <DataTableActions
                        :columns="agentColumns"
                        :data="agents"
                        filename="commission_agents_export"
                    />
                    <button
                        type="button"
                        @click="openAddAgent"
                        class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-lg shadow-sm"
                    >
                        + Add Agent
                    </button>
                </div>
            </div>

            <!-- Agents Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr
                            class="border-y border-gray-200 bg-gray-50/70 text-gray-700 uppercase font-semibold"
                        >
                            <th class="py-3 px-4">Name</th>
                            <th class="py-3 px-4">Email</th>
                            <th class="py-3 px-4">Contact Number</th>
                            <th class="py-3 px-4">Address</th>
                            <th class="py-3 px-4">Sales Commission %</th>
                            <th class="py-3 px-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-if="agents.length === 0">
                            <td
                                colspan="6"
                                class="py-6 text-center text-gray-400"
                            >
                                No commission agents found.
                            </td>
                        </tr>
                        <tr
                            v-for="agent in agents"
                            :key="agent.id"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td class="py-3 px-4 font-bold text-gray-900">
                                {{ agent.name }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{
                                    agent.email?.includes("@agent.local")
                                        ? "-"
                                        : agent.email
                                }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ agent.phone }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ agent.address }}
                            </td>
                            <td class="py-3 px-4 font-bold text-indigo-700">
                                {{ agent.commission_rate }}
                            </td>
                            <td class="py-3 px-4">
                                <span
                                    class="px-2 py-0.5 rounded text-[10px] font-bold bg-green-100 text-green-700"
                                    >Active</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- MODAL: Role Permissions Viewer -->
        <div
            v-if="showRoleModal"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-2xl max-w-4xl w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto"
            >
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-base font-bold text-gray-900">
                        Permissions for:
                        <span class="text-indigo-600 uppercase">{{
                            selectedRolePermissions?.name
                        }}</span>
                    </h3>
                    <button
                        type="button"
                        @click="showRoleModal = false"
                        class="text-gray-400 hover:text-gray-600 text-lg"
                    >
                        ✕
                    </button>
                </div>

                <div class="divide-y divide-gray-100 text-xs">
                    <div
                        v-for="(perms, groupName) in permission_groups"
                        :key="groupName"
                        class="py-3"
                    >
                        <h4 class="font-bold text-gray-800 mb-2">
                            {{ groupName }}
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                            <div
                                v-for="(label, permKey) in perms"
                                :key="permKey"
                                class="flex items-center gap-1.5"
                            >
                                <span
                                    :class="[
                                        selectedRolePermissions?.permissions?.includes(
                                            permKey,
                                        )
                                            ? 'text-green-600'
                                            : 'text-gray-300',
                                    ]"
                                >
                                    {{
                                        selectedRolePermissions?.permissions?.includes(
                                            permKey,
                                        )
                                            ? "✅"
                                            : "⚪"
                                    }}
                                </span>
                                <span
                                    :class="[
                                        selectedRolePermissions?.permissions?.includes(
                                            permKey,
                                        )
                                            ? 'font-medium text-gray-800'
                                            : 'text-gray-400',
                                    ]"
                                >
                                    {{ label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-3 border-t">
                    <button
                        type="button"
                        @click="showRoleModal = false"
                        class="px-4 py-1.5 bg-gray-800 text-white rounded text-xs"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

        <!-- MODAL: Add User -->
        <div
            v-if="showUserModal"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-6 space-y-4"
            >
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold text-gray-900">
                        Add User (SuperAdmin)
                    </h3>
                    <button
                        type="button"
                        @click="showUserModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitUser" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-1 font-medium"
                                >First Name:*</label
                            >
                            <input
                                v-model="userForm.first_name"
                                type="text"
                                required
                                class="w-full border rounded px-2.5 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block mb-1 font-medium"
                                >Last Name:</label
                            >
                            <input
                                v-model="userForm.last_name"
                                type="text"
                                class="w-full border rounded px-2.5 py-1.5"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-1 font-medium"
                                >Email:*</label
                            >
                            <input
                                v-model="userForm.email"
                                type="email"
                                required
                                class="w-full border rounded px-2.5 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block mb-1 font-medium"
                                >Username:</label
                            >
                            <input
                                v-model="userForm.username"
                                type="text"
                                class="w-full border rounded px-2.5 py-1.5"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1 font-medium">Password:*</label>
                        <input
                            v-model="userForm.password"
                            type="password"
                            required
                            class="w-full border rounded px-2.5 py-1.5"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block mb-1 font-medium"
                                >User Type:*</label
                            >
                            <select
                                v-model="userForm.type"
                                class="w-full border rounded px-2.5 py-1.5"
                            >
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Platform Admin</option>
                                <option value="owner">Shop Owner</option>
                                <option value="staff">Shop Staff</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 font-medium"
                                >Assign Business/Shop:</label
                            >
                            <select
                                v-model="userForm.business_id"
                                class="w-full border rounded px-2.5 py-1.5"
                            >
                                <option value="">None (Platform Level)</option>
                                <option
                                    v-for="b in businesses"
                                    :key="b.id"
                                    :value="b.id"
                                >
                                    {{ b.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button
                            type="button"
                            @click="showUserModal = false"
                            class="px-3 py-1.5 border rounded text-gray-600"
                        >
                            Close
                        </button>
                        <button
                            type="submit"
                            :disabled="userForm.processing"
                            class="px-4 py-1.5 bg-indigo-600 text-white rounded font-medium"
                        >
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: Add Sales Commission Agent -->
        <div
            v-if="showAgentModal"
            class="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4"
        >
            <div
                class="bg-white rounded-lg shadow-2xl max-w-lg w-full p-6 space-y-4"
            >
                <div class="flex items-center justify-between border-b pb-3">
                    <h3 class="text-sm font-bold text-gray-900">
                        Add sales commission agent
                    </h3>
                    <button
                        type="button"
                        @click="showAgentModal = false"
                        class="text-gray-400 hover:text-gray-600"
                    >
                        ✕
                    </button>
                </div>

                <form @submit.prevent="submitAgent" class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-2">
                        <div>
                            <label class="block mb-1">Prefix:</label>
                            <input
                                v-model="agentForm.prefix"
                                type="text"
                                placeholder="Mr / M"
                                class="w-full border rounded px-2 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block mb-1">First Name:*</label>
                            <input
                                v-model="agentForm.first_name"
                                type="text"
                                required
                                placeholder="First Name"
                                class="w-full border rounded px-2 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block mb-1">Last Name:</label>
                            <input
                                v-model="agentForm.last_name"
                                type="text"
                                placeholder="Last Name"
                                class="w-full border rounded px-2 py-1.5"
                            />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block mb-1">Email:</label>
                            <input
                                v-model="agentForm.email"
                                type="email"
                                placeholder="Email"
                                class="w-full border rounded px-2 py-1.5"
                            />
                        </div>
                        <div>
                            <label class="block mb-1">Contact Number:</label>
                            <input
                                v-model="agentForm.phone"
                                type="text"
                                placeholder="Contact Number"
                                class="w-full border rounded px-2 py-1.5"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block mb-1">Address:</label>
                        <textarea
                            v-model="agentForm.permanent_address"
                            rows="2"
                            placeholder="Address"
                            class="w-full border rounded px-2 py-1.5"
                        ></textarea>
                    </div>
                    <div>
                        <label class="block mb-1"
                            >Sales Commission Percentage (%):</label
                        >
                        <input
                            v-model="agentForm.commission_rate"
                            type="number"
                            step="0.01"
                            placeholder="Sales Commission Percentage (%)"
                            class="w-full border rounded px-2 py-1.5"
                        />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t">
                        <button
                            type="submit"
                            :disabled="agentForm.processing"
                            class="px-5 py-1.5 bg-indigo-600 text-white rounded font-medium"
                        >
                            Save
                        </button>
                        <button
                            type="button"
                            @click="showAgentModal = false"
                            class="px-4 py-1.5 bg-slate-800 text-white rounded font-medium"
                        >
                            Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
