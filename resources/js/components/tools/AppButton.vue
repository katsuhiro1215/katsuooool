<script setup lang="ts">
withDefaults(
    defineProps<{
        as?: 'button' | 'a' | 'label';
        loading?: boolean;
    }>(),
    {
        as: 'button',
        loading: false,
    },
);
</script>

<template>
    <component
        :is="as"
        class="inline-flex cursor-pointer items-center justify-center gap-2 rounded-md bg-ink-900 px-6 py-3 text-center text-base font-medium text-white transition hover:bg-ink-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold-400 disabled:cursor-not-allowed disabled:bg-ink-900/30"
    >
        <svg
            v-if="loading"
            class="h-4 w-4 shrink-0 animate-spin"
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
        <slot />
    </component>
</template>
