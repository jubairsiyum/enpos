<script setup>
defineProps({
    text: { type: String, required: true },
})
</script>

<template>
    <div class="relative inline-flex items-center group">
        <span
            tabindex="0"
            class="inline-flex items-center justify-center w-3.5 h-3.5 text-[9px] font-bold text-white bg-[#00c0ef] hover:bg-cyan-600 rounded-full cursor-help select-none transition"
        >
            i
        </span>

        <!-- Tooltip Popover Box matching screenshot -->
        <div
            role="tooltip"
            class="absolute left-1/2 -translate-x-1/2 top-full mt-2 hidden group-hover:block group-focus-within:block z-50 w-64 p-2.5 bg-white border border-gray-300 rounded shadow-md text-[11px] leading-relaxed text-gray-800 font-normal select-text cursor-default pointer-events-auto"
        >
            <!-- Triangle Arrow -->
            <div class="absolute -top-1.5 left-1/2 -translate-x-1/2 w-0 h-0 border-x-[5px] border-x-transparent border-b-[6px] border-b-gray-300"></div>
            <div class="absolute -top-1 left-1/2 -translate-x-1/2 w-0 h-0 border-x-[5px] border-x-transparent border-b-[6px] border-b-white"></div>

            {{ text }}
        </div>
    </div>
</template>
