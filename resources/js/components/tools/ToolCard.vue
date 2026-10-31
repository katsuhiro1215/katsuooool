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
        class="group block rounded-xl border border-ink-900/10 bg-white p-5 transition hover:-translate-y-0.5 hover:border-gold-400/60 hover:shadow-lg hover:shadow-ink-900/5"
    >
        <span
            class="inline-block rounded-full bg-gold-50 px-2 py-0.5 text-xs font-medium uppercase tracking-wide text-gold-600 group-hover:bg-gold-100"
            >{{ tool.category }}</span
        >
        <h3 class="mt-2 text-lg font-semibold text-ink-900">
            {{ tool.name }}
        </h3>
        <p class="mt-1 text-sm text-ink-900/60">{{ tool.description }}</p>
    </a>
</template>
