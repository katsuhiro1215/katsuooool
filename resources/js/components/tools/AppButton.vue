<script setup lang="ts">
withDefaults(
    defineProps<{
        as?: 'button' | 'a' | 'label';
    }>(),
    {
        as: 'button',
    },
);
</script>

<template>
    <component
        :is="as"
        class="cursor-pointer rounded-md bg-gray-900 px-6 py-3 text-center text-base font-medium text-white transition hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
    >
        <slot />
    </component>
</template>
