<script setup>
import {
    BanknotesIcon,
    EyeIcon,
    PencilSquareIcon,
    TrashIcon,
    PowerIcon,
    DocumentTextIcon,
    ArrowUpCircleIcon,
    PaperClipIcon,
    ChevronDownIcon,
    ArrowPathIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'

defineProps({
    contact: { type: Object, required: true },
    isOpen: { type: Boolean, default: false },
    canEdit: { type: Boolean, default: true },
    canDelete: { type: Boolean, default: true },
})

const emit = defineEmits([
    'toggle',
    'pay',
    'view',
    'edit',
    'delete',
    'toggle-status',
    'ledger',
    'sales',
    'documents',
    'restore',
    'force-delete',
])
</script>

<template>
    <div class="relative inline-block text-left">
        <button
            type="button"
            @click.stop="emit('toggle')"
            class="inline-flex items-center gap-1 px-3 py-1 border border-sky-400 text-sky-600 hover:bg-sky-50 rounded-full text-[11px] font-medium transition bg-white"
        >
            <span>Actions</span>
            <ChevronDownIcon class="w-3 h-3" />
        </button>

        <div
            v-if="isOpen"
            class="absolute left-0 mt-1 w-44 bg-white border border-gray-200 rounded-lg shadow-lg z-40 py-1.5 text-left"
        >
            <template v-if="!contact.deleted_at">
                <button
                    type="button"
                    @click="emit('pay', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <BanknotesIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Pay</span>
                </button>
                <button
                    type="button"
                    @click="emit('view', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <EyeIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>View</span>
                </button>
                <button
                    v-if="canEdit"
                    type="button"
                    @click="emit('edit', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <PencilSquareIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Edit</span>
                </button>
                <button
                    v-if="canDelete"
                    type="button"
                    @click="emit('delete', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-red-50 hover:text-red-600 flex items-center gap-2.5"
                >
                    <TrashIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Delete</span>
                </button>
                <button
                    v-if="canEdit"
                    type="button"
                    @click="emit('toggle-status', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <PowerIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>{{ contact.contact_status === 'inactive' ? 'Activate' : 'Deactivate' }}</span>
                </button>

                <div class="my-1 border-t border-gray-100"></div>

                <button
                    type="button"
                    @click="emit('ledger', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <DocumentTextIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Ledger</span>
                </button>
                <button
                    type="button"
                    @click="emit('sales', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <ArrowUpCircleIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Sales</span>
                </button>
                <button
                    type="button"
                    @click="emit('documents', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <PaperClipIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>Documents &amp; Note</span>
                </button>
            </template>

            <template v-else>
                <button
                    type="button"
                    @click="emit('view', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 flex items-center gap-2.5"
                >
                    <EyeIcon class="w-4 h-4 text-gray-500 shrink-0" />
                    <span>View</span>
                </button>
                <button
                    type="button"
                    @click="emit('restore', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-emerald-600 hover:bg-emerald-50 flex items-center gap-2.5"
                >
                    <ArrowPathIcon class="w-4 h-4 shrink-0" />
                    <span>Restore</span>
                </button>
                <button
                    type="button"
                    @click="emit('force-delete', contact)"
                    class="w-full px-3.5 py-1.5 text-xs text-red-600 hover:bg-red-50 flex items-center gap-2.5"
                >
                    <XMarkIcon class="w-4 h-4 shrink-0" />
                    <span>Force Delete</span>
                </button>
            </template>
        </div>
    </div>
</template>
