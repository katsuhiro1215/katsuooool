<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        title: string;
        description: string;
        narrow?: boolean;
        backHref?: string;
        backLabel?: string;
    }>(),
    {
        narrow: false,
        backHref: '/',
        backLabel: '← トップに戻る',
    },
);
</script>

<template>
    <div class="border-b border-gray-200 bg-white">
        <div
            :class="['mx-auto px-6 py-10', narrow ? 'max-w-3xl' : '']"
        >
            <Link
                :href="backHref"
                class="text-sm text-gray-500 hover:text-gray-700"
                >{{ backLabel }}</Link
            >
            <h1 class="mt-2 text-2xl font-bold text-gray-900">{{ title }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ description }}
            </p>
        </div>
    </div>
</template>
