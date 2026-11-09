<script setup>
import { ref } from 'vue'

const props = defineProps({
    columns: { type: Array, default: () => [] },
    data: { type: Array, default: () => [] },
    filename: { type: String, default: 'export' },
})

const showVisibilityDropdown = ref(false)

const exportCSV = () => {
    if (!props.data.length) return
    const keys = props.columns.map(c => c.key)
    const headers = props.columns.map(c => `"${c.label}"`).join(',')
    const rows = props.data.map(item =>
        keys.map(k => `"${(item[k] ?? '').toString().replace(/"/g, '""')}"`).join(',')
    )
    const csvContent = 'data:text/csv;charset=utf-8,' + [headers, ...rows].join('\n')
    const encodedUri = encodeURI(csvContent)
    const link = document.createElement('a')
    link.setAttribute('href', encodedUri)
    link.setAttribute('download', `${props.filename}.csv`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

const exportExcel = () => {
    exportCSV()
}

const printTable = () => {
    window.print()
}

const exportPDF = () => {
    window.print()
}

const toggleColumn = (col) => {
    col.visible = !col.visible
}
</script>

<template>
    <div class="flex items-center gap-1.5 text-xs text-gray-700 relative">
        <button
            type="button"
            @click="exportCSV"
            class="px-2.5 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 flex items-center gap-1 transition shadow-sm"
        >
            📄 Export CSV
        </button>
        <button
            type="button"
            @click="exportExcel"
            class="px-2.5 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 flex items-center gap-1 transition shadow-sm"
        >
            📊 Export Excel
        </button>
        <button
            type="button"
            @click="printTable"
            class="px-2.5 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 flex items-center gap-1 transition shadow-sm"
        >
            🖨️ Print
        </button>
        
        <div class="relative inline-block">
            <button
                type="button"
                @click="showVisibilityDropdown = !showVisibilityDropdown"
                class="px-2.5 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 flex items-center gap-1 transition shadow-sm"
            >
                👁️ Column visibility
            </button>
            <div
                v-if="showVisibilityDropdown"
                class="absolute left-0 mt-1 w-44 bg-white border border-gray-200 rounded-md shadow-lg z-30 p-2 space-y-1"
            >
                <label
                    v-for="col in columns"
                    :key="col.key"
                    class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer hover:bg-gray-50 p-1 rounded"
                >
                    <input
                        type="checkbox"
                        :checked="col.visible !== false"
                        @change="toggleColumn(col)"
                        class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>{{ col.label }}</span>
                </label>
            </div>
        </div>

        <button
            type="button"
            @click="exportPDF"
            class="px-2.5 py-1.5 border border-gray-300 rounded bg-white hover:bg-gray-50 flex items-center gap-1 transition shadow-sm"
        >
            📑 Export PDF
        </button>
    </div>
</template>
