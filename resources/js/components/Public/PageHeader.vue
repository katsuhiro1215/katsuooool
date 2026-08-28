<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps({
    title: {
        type: String,
        required: true,
    },
    description: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <div class="border-b border-gray-200 bg-white">
        <div class="mx-auto max-w-7xl px-6 py-10">
            <Link href="/" class="text-sm text-gray-500 hover:text-gray-700"
                >← トップに戻る</Link
            >
            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ title }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ description }}
            </p>
        </div>
    </div>
</template>
