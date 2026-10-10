<script setup lang="ts">
withDefaults(defineProps<{ narrow?: boolean }>(), { narrow: false });
</script>

<template>
    <div :class="['mx-auto px-6 py-10', narrow ? 'max-w-3xl' : '']">
        <slot />
    </div>
</template>
