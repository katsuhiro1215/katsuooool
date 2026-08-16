<script setup lang="ts">
interface Tool {
    slug: string;
    name: string;
    category: string;
    description: string;
    href: string;
}

defineProps<{ tool: Tool }>();
</script>

<template>
    <a
        :href="tool.href"
        class="block rounded-lg border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm"
    >
        <span
            class="text-xs font-medium uppercase tracking-wide text-gray-400"
            >{{ tool.category }}</span
        >
        <h3 class="mt-1 text-lg font-semibold text-gray-900">
            {{ tool.name }}
        </h3>
        <p class="mt-1 text-sm text-gray-600">{{ tool.description }}</p>
    </a>
</template>
