<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import ToolCard from '@/components/tools/ToolCard.vue';

interface Tool {
    slug: string;
    name: string;
    category: string;
    description: string;
    href: string;
}

const props = defineProps<{ tools: Tool[] }>();

const query = ref('');

const filteredTools = computed(() => {
    const keyword = query.value.trim().toLowerCase();

    if (keyword === '') {
        return props.tools;
    }

    return props.tools.filter(
        (tool) =>
            tool.name.toLowerCase().includes(keyword) ||
            tool.description.toLowerCase().includes(keyword),
    );
});
</script>

<template>
    <Head title="katsuooool - Web制作ツール集" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-6 py-10">
                <h1 class="text-2xl font-bold text-gray-900">katsuooool</h1>
                <p class="mt-2 text-sm text-gray-600">
                    Web制作に役立つ実用ツール集
                </p>

                <input
                    v-model="query"
                    type="search"
                    placeholder="ツールを検索..."
                    class="mt-6 w-full max-w-md rounded-md border border-gray-300 px-4 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
                />
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <div
                v-if="filteredTools.length === 0"
                class="text-sm text-gray-500"
            >
                該当するツールが見つかりませんでした。
            </div>

            <div
                v-else
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <ToolCard
                    v-for="tool in filteredTools"
                    :key="tool.slug"
                    :tool="tool"
                />
            </div>
        </main>
    </div>
</template>
