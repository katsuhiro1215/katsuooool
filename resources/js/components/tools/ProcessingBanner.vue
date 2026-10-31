<script setup lang="ts">
defineProps<{
    message: string;
}>();
</script>

<template>
    <div
        class="mt-4 flex items-center gap-3 rounded-md border border-gold-400/30 bg-gold-50 p-4 text-sm text-ink-900/70"
    >
        <svg
            class="h-5 w-5 shrink-0 animate-spin text-gold-600"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
        </svg>
        <span>{{ message }}</span>
    </div>
</template>
