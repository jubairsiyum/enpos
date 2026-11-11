<script setup>
import { ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    payContact: { type: Object, default: null },
    detailContact: { type: Object, default: null },
    initialTab: { type: String, default: 'overview' },
})

const emit = defineEmits(['close-pay', 'close-detail'])

const activeTab = ref('overview')
const loadingTab = ref(false)
const ledgerData = ref({ summary: {}, entries: [] })
const salesRows = ref([])
const documentsList = ref([])
const docFileInput = ref(null)

const todayIso = () => new Date().toISOString().slice(0, 10)

const payForm = useForm({
    amount: '',
    paid_on: todayIso(),
    method: 'cash',
    note: '',
})

const docForm = useForm({
    heading: '',
    description: '',
    is_private: false,
    file: null,
})

const currency = (val) => '$ ' + parseFloat(val ?? 0).toFixed(2)

const contactDueAmount = (contact) => {
    if (!contact) return 0
    return contact.type === 'supplier'
        ? parseFloat(contact.total_purchase_due ?? 0)
        : parseFloat(contact.total_sell_due ?? 0)
}

watch(
    () => props.payContact,
    (contact) => {
        if (!contact) return
        payForm.reset()
        payForm.clearErrors()
        const due = contactDueAmount(contact)
        payForm.amount = due > 0 ? due.toFixed(2) : ''
        payForm.paid_on = todayIso()
        payForm.method = 'cash'
    }
)

watch(
    () => [props.detailContact, props.initialTab],
    ([contact, tab]) => {
        if (!contact) return
        activeTab.value = tab || 'overview'
        loadActiveTabData()
    }
)

watch(activeTab, () => {
    if (props.detailContact) {
        loadActiveTabData()
    }
})

const loadActiveTabData = async () => {
    const contact = props.detailContact
    if (!contact || activeTab.value === 'overview') return

    loadingTab.value = true
    try {
        if (activeTab.value === 'ledger') {
            const res = await fetch(route('shop.contacts.ledger', contact.id), {
                headers: { Accept: 'application/json' },
            })
            const json = await res.json()
            ledgerData.value = json.data ?? { summary: {}, entries: [] }
        } else if (activeTab.value === 'sales') {
            const res = await fetch(route('shop.contacts.sales', contact.id), {
                headers: { Accept: 'application/json' },
            })
            const json = await res.json()
            salesRows.value = json.data ?? []
        } else if (activeTab.value === 'documents') {
            const res = await fetch(route('shop.contacts.documents.index', contact.id), {
                headers: { Accept: 'application/json' },
            })
            const json = await res.json()
            documentsList.value = json.data ?? []
        }
    } finally {
        loadingTab.value = false
    }
}

const submitPayment = () => {
    if (!props.payContact) return
    payForm.post(route('shop.contacts.pay', props.payContact.id), {
        preserveScroll: true,
        onSuccess: () => {
            payForm.reset()
            emit('close-pay')
        },
    })
}

const onDocFileChange = (event) => {
    docForm.file = event.target.files?.[0] ?? null
}

const submitDocument = () => {
    if (!props.detailContact) return
    docForm.post(route('shop.contacts.documents.store', props.detailContact.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            docForm.reset()
            if (docFileInput.value) {
                docFileInput.value.value = ''
            }
            loadActiveTabData()
        },
    })
}

const deleteDocument = (docId) => {
    if (!props.detailContact) return
    if (!confirm('Delete this document/note?')) return

    router.delete(route('shop.contacts.documents.destroy', [props.detailContact.id, docId]), {
        preserveScroll: true,
        onSuccess: () => loadActiveTabData(),
    })
}
</script>

<template>
    <!-- PAY MODAL -->
    <Teleport to="body">
        <div
            v-if="payContact"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-8 px-4"
            @click.self="emit('close-pay')"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800">
                        Add Payment — {{ payContact.name }} ({{ payContact.contact_id }})
                    </h3>
                    <button type="button" @click="emit('close-pay')" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                </div>

                <form @submit.prevent="submitPayment" class="p-6 space-y-4 text-xs">
                    <div class="grid grid-cols-3 gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                        <div>
                            <div class="text-gray-500">Opening Balance</div>
                            <div class="font-semibold text-gray-800">{{ currency(payContact.opening_balance) }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Advance Balance</div>
                            <div class="font-semibold text-gray-800">{{ currency(payContact.advance_balance) }}</div>
                        </div>
                        <div>
                            <div class="text-gray-500">Total Due</div>
                            <div class="font-semibold text-indigo-700">{{ currency(contactDueAmount(payContact)) }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Amount:*</label>
                            <input
                                v-model="payForm.amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                required
                                placeholder="0.00"
                                class="w-full px-3 py-2 border border-gray-300 rounded text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="payForm.errors.amount" class="text-red-500 text-[11px] mt-1">{{ payForm.errors.amount }}</p>
                        </div>

                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Paid on:*</label>
                            <input
                                v-model="payForm.paid_on"
                                type="date"
                                required
                                class="w-full px-3 py-2 border border-gray-300 rounded text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            />
                            <p v-if="payForm.errors.paid_on" class="text-red-500 text-[11px] mt-1">{{ payForm.errors.paid_on }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block font-semibold text-gray-700 mb-1">Payment Method:*</label>
                            <select
                                v-model="payForm.method"
                                class="w-full px-3 py-2 border border-gray-300 rounded text-xs bg-white focus:ring-indigo-500 focus:border-indigo-500"
                            >
                                <option value="cash">Cash</option>
                                <option value="card">Card</option>
                                <option value="cheque">Cheque</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="other">Other</option>
                            </select>
                            <p v-if="payForm.errors.method" class="text-red-500 text-[11px] mt-1">{{ payForm.errors.method }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block font-semibold text-gray-700 mb-1">Payment Note:</label>
                            <textarea
                                v-model="payForm.note"
                                rows="3"
                                placeholder="Optional note..."
                                class="w-full px-3 py-2 border border-gray-300 rounded text-xs focus:ring-indigo-500 focus:border-indigo-500"
                            ></textarea>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-3 border-t border-gray-100">
                        <button
                            type="submit"
                            :disabled="payForm.processing"
                            class="px-5 py-2 bg-[#4f2bed] hover:bg-indigo-700 text-white rounded text-xs font-medium disabled:opacity-50"
                        >
                            {{ payForm.processing ? 'Saving...' : 'Save' }}
                        </button>
                        <button
                            type="button"
                            @click="emit('close-pay')"
                            class="px-5 py-2 bg-slate-800 hover:bg-slate-900 text-white rounded text-xs font-medium"
                        >
                            Close
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </Teleport>

    <!-- TABBED CONTACT DETAILS MODAL (VIEW / LEDGER / SALES / DOCUMENTS & NOTE) -->
    <Teleport to="body">
        <div
            v-if="detailContact"
            class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 overflow-y-auto py-8 px-4"
            @click.self="emit('close-detail')"
        >
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-2.5">
                        <h3 class="text-base font-semibold text-gray-800">
                            {{ detailContact.name }} ({{ detailContact.contact_id }})
                        </h3>
                        <span
                            :class="[
                                'px-2 py-0.5 rounded-full text-[11px] font-medium',
                                detailContact.contact_status === 'inactive'
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-emerald-100 text-emerald-700',
                            ]"
                        >
                            {{ detailContact.contact_status === 'inactive' ? 'Inactive' : 'Active' }}
                        </span>
                    </div>
                    <button type="button" @click="emit('close-detail')" class="text-gray-400 hover:text-gray-600 text-lg">✕</button>
                </div>

                <!-- Tabs -->
                <div class="flex flex-wrap gap-1 px-6 pt-3 border-b border-gray-200 bg-gray-50/70 text-xs font-medium">
                    <button
                        v-for="tab in [
                            { key: 'overview', label: 'View' },
                            { key: 'ledger', label: 'Ledger' },
                            { key: 'sales', label: 'Sales' },
                            { key: 'documents', label: 'Documents & Note' },
                        ]"
                        :key="tab.key"
                        type="button"
                        @click="activeTab = tab.key"
                        :class="[
                            'px-4 py-2 border-b-2 transition',
                            activeTab === tab.key
                                ? 'border-indigo-600 text-indigo-700 bg-white rounded-t'
                                : 'border-transparent text-gray-600 hover:text-gray-800',
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="p-6 space-y-4 text-xs text-gray-700 max-h-[70vh] overflow-y-auto">
                    <!-- OVERVIEW TAB -->
                    <div v-if="activeTab === 'overview'" class="space-y-4">
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <div><span class="font-semibold">Contact Type:</span> {{ detailContact.type }} ({{ detailContact.entity_type || 'individual' }})</div>
                            <div><span class="font-semibold">Business Name:</span> {{ detailContact.supplier_business_name || '-' }}</div>
                            <div v-if="detailContact.customer_group_name"><span class="font-semibold">Customer Group:</span> {{ detailContact.customer_group_name }}</div>
                            <div><span class="font-semibold">Mobile:</span> {{ detailContact.mobile || '-' }}</div>
                            <div><span class="font-semibold">Email:</span> {{ detailContact.email || '-' }}</div>
                            <div><span class="font-semibold">Tax Number:</span> {{ detailContact.tax_number || '-' }}</div>
                            <div><span class="font-semibold">Pay Term:</span> {{ detailContact.pay_term || '-' }}</div>
                            <div><span class="font-semibold">Opening Balance:</span> {{ currency(detailContact.opening_balance) }}</div>
                            <div><span class="font-semibold">Advance Balance:</span> {{ currency(detailContact.advance_balance) }}</div>
                            <div><span class="font-semibold">Total Due:</span> {{ currency(contactDueAmount(detailContact)) }}</div>
                        </div>

                        <div>
                            <span class="font-semibold">Address:</span>
                            <p class="mt-0.5 text-gray-600">{{ detailContact.address || '-' }}</p>
                        </div>

                        <div v-if="detailContact.assigned_users?.length">
                            <span class="font-semibold">Assigned To:</span>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <span
                                    v-for="u in detailContact.assigned_users"
                                    :key="u.id"
                                    class="px-2 py-0.5 bg-indigo-50 text-indigo-700 rounded-full text-[11px]"
                                >
                                    {{ u.name }}
                                </span>
                            </div>
                        </div>

                        <div v-if="detailContact.contact_persons?.length">
                            <span class="font-semibold">Contact Persons:</span>
                            <div class="mt-2 space-y-2">
                                <div
                                    v-for="p in detailContact.contact_persons"
                                    :key="p.id"
                                    class="p-2.5 bg-gray-50 rounded border border-gray-200"
                                >
                                    <div class="font-semibold text-gray-800">
                                        {{ [p.prefix, p.first_name, p.last_name].filter(Boolean).join(' ') }}
                                        <span v-if="p.designation" class="text-gray-500 font-normal">({{ p.designation }})</span>
                                    </div>
                                    <div class="text-gray-600 mt-0.5">
                                        Mobile: {{ p.mobile || '-' }} | Email: {{ p.email || '-' }} | Dept: {{ p.department || '-' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- LEDGER TAB -->
                    <div v-else-if="activeTab === 'ledger'" class="space-y-4">
                        <div v-if="loadingTab" class="py-8 text-center text-gray-400">Loading ledger...</div>
                        <template v-else>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-3 bg-gray-50 p-3 rounded-lg border border-gray-200">
                                <div>
                                    <div class="text-gray-500">Opening Balance</div>
                                    <div class="font-semibold">{{ currency(ledgerData.summary?.opening_balance) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Total Invoice</div>
                                    <div class="font-semibold">{{ currency(ledgerData.summary?.total_invoice) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Total Paid</div>
                                    <div class="font-semibold text-emerald-700">{{ currency(ledgerData.summary?.total_paid) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Balance Due</div>
                                    <div class="font-semibold text-red-600">{{ currency(ledgerData.summary?.balance_due) }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Advance Balance</div>
                                    <div class="font-semibold text-indigo-700">{{ currency(ledgerData.summary?.advance_balance) }}</div>
                                </div>
                            </div>

                            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="bg-gray-50 border-b border-gray-200 font-semibold text-gray-600">
                                            <th class="py-2 px-3">Date</th>
                                            <th class="py-2 px-3">Reference No</th>
                                            <th class="py-2 px-3">Type</th>
                                            <th class="py-2 px-3 text-right">Debit</th>
                                            <th class="py-2 px-3 text-right">Credit</th>
                                            <th class="py-2 px-3 text-right">Balance</th>
                                            <th class="py-2 px-3">Payment Method</th>
                                            <th class="py-2 px-3">Note</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-if="!ledgerData.entries?.length">
                                            <td colspan="8" class="py-6 text-center text-gray-400">No ledger entries found.</td>
                                        </tr>
                                        <tr v-for="(entry, idx) in ledgerData.entries" :key="idx">
                                            <td class="py-2 px-3">{{ entry.date }}</td>
                                            <td class="py-2 px-3">{{ entry.reference_no }}</td>
                                            <td class="py-2 px-3">{{ entry.type }}</td>
                                            <td class="py-2 px-3 text-right">{{ currency(entry.debit) }}</td>
                                            <td class="py-2 px-3 text-right">{{ currency(entry.credit) }}</td>
                                            <td class="py-2 px-3 text-right font-medium">{{ currency(entry.balance) }}</td>
                                            <td class="py-2 px-3">{{ entry.payment_method }}</td>
                                            <td class="py-2 px-3">{{ entry.note || '-' }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </template>
                    </div>

                    <!-- SALES TAB -->
                    <div v-else-if="activeTab === 'sales'" class="space-y-4">
                        <div v-if="loadingTab" class="py-8 text-center text-gray-400">Loading transactions...</div>
                        <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 font-semibold text-gray-600">
                                        <th class="py-2 px-3">Date</th>
                                        <th class="py-2 px-3">Invoice / Ref No</th>
                                        <th class="py-2 px-3">Type</th>
                                        <th class="py-2 px-3">Payment Status</th>
                                        <th class="py-2 px-3 text-right">Total Amount</th>
                                        <th class="py-2 px-3 text-right">Total Paid</th>
                                        <th class="py-2 px-3 text-right">Due</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-if="!salesRows.length">
                                        <td colspan="7" class="py-6 text-center text-gray-400">No transactions found for this contact.</td>
                                    </tr>
                                    <tr v-for="tx in salesRows" :key="tx.id">
                                        <td class="py-2 px-3">{{ tx.date }}</td>
                                        <td class="py-2 px-3 font-medium">{{ tx.invoice_no }}</td>
                                        <td class="py-2 px-3">{{ tx.type }}</td>
                                        <td class="py-2 px-3">{{ tx.payment_status }}</td>
                                        <td class="py-2 px-3 text-right">{{ currency(tx.total) }}</td>
                                        <td class="py-2 px-3 text-right">{{ currency(tx.paid) }}</td>
                                        <td class="py-2 px-3 text-right font-medium text-red-600">{{ currency(tx.due) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- DOCUMENTS & NOTE TAB -->
                    <div v-else-if="activeTab === 'documents'" class="space-y-4">
                        <form @submit.prevent="submitDocument" class="p-4 bg-gray-50 border border-gray-200 rounded-lg space-y-3">
                            <div class="font-semibold text-gray-800">Add Document &amp; Note</div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div>
                                    <label class="block font-medium text-gray-700 mb-1">Heading:*</label>
                                    <input
                                        v-model="docForm.heading"
                                        type="text"
                                        required
                                        placeholder="Heading"
                                        class="w-full px-3 py-1.5 border border-gray-300 rounded text-xs"
                                    />
                                    <p v-if="docForm.errors.heading" class="text-red-500 text-[11px] mt-1">{{ docForm.errors.heading }}</p>
                                </div>
                                <div>
                                    <label class="block font-medium text-gray-700 mb-1">Document (optional):</label>
                                    <input
                                        ref="docFileInput"
                                        type="file"
                                        @change="onDocFileChange"
                                        class="block w-full text-xs text-gray-700 file:mr-2 file:py-1 file:px-2.5 file:rounded file:border file:border-gray-300 file:bg-white file:text-xs"
                                    />
                                </div>
                            </div>

                            <div>
                                <label class="block font-medium text-gray-700 mb-1">Description:</label>
                                <textarea
                                    v-model="docForm.description"
                                    rows="2"
                                    placeholder="Note or description..."
                                    class="w-full px-3 py-1.5 border border-gray-300 rounded text-xs"
                                ></textarea>
                            </div>

                            <div class="flex items-center justify-between">
                                <label class="inline-flex items-center gap-2 text-gray-700">
                                    <input v-model="docForm.is_private" type="checkbox" class="rounded text-indigo-600" />
                                    <span>Is Private</span>
                                </label>
                                <button
                                    type="submit"
                                    :disabled="docForm.processing"
                                    class="px-4 py-1.5 bg-[#4f2bed] hover:bg-indigo-700 text-white rounded text-xs font-medium disabled:opacity-50"
                                >
                                    {{ docForm.processing ? 'Saving...' : 'Save' }}
                                </button>
                            </div>
                        </form>

                        <div v-if="loadingTab" class="py-6 text-center text-gray-400">Loading documents...</div>
                        <div v-else class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 font-semibold text-gray-600">
                                        <th class="py-2 px-3">Heading</th>
                                        <th class="py-2 px-3">Description</th>
                                        <th class="py-2 px-3">Added By</th>
                                        <th class="py-2 px-3">Added On</th>
                                        <th class="py-2 px-3 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-if="!documentsList.length">
                                        <td colspan="5" class="py-6 text-center text-gray-400">No documents or notes added yet.</td>
                                    </tr>
                                    <tr v-for="doc in documentsList" :key="doc.id">
                                        <td class="py-2 px-3 font-medium">
                                            {{ doc.heading }}
                                            <span v-if="doc.is_private" class="ml-1 px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded text-[10px]">Private</span>
                                        </td>
                                        <td class="py-2 px-3">{{ doc.description || '-' }}</td>
                                        <td class="py-2 px-3">{{ doc.created_by || '-' }}</td>
                                        <td class="py-2 px-3">{{ doc.created_at }}</td>
                                        <td class="py-2 px-3 text-right space-x-2">
                                            <a
                                                v-if="doc.has_file"
                                                :href="route('shop.contacts.documents.download', [detailContact.id, doc.id])"
                                                class="text-indigo-600 hover:underline font-medium"
                                            >
                                                Download
                                            </a>
                                            <button
                                                type="button"
                                                @click="deleteDocument(doc.id)"
                                                class="text-red-600 hover:underline font-medium"
                                            >
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end px-6 py-3 border-t border-gray-100">
                    <button
                        type="button"
                        @click="emit('close-detail')"
                        class="px-4 py-1.5 bg-slate-800 text-white rounded text-xs font-medium"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
