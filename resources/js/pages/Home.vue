<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import PremiumBanner from '@/components/PremiumBanner.vue';
import ToolCard from '@/components/tools/ToolCard.vue';
import WebsiteStarterBanner from '@/components/WebsiteStarterBanner.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import {
    CATEGORY_TABS,
    extractQuery,
    filterTools,
    normalizeCategory,
} from '@/lib/toolFilter';
import type { Tool } from '@/lib/toolFilter';

const props = defineProps<{ tools: Tool[] }>();

const page = usePage();

const activeCategory = computed(() =>
    normalizeCategory(
        new URLSearchParams(page.url.split('?')[1] ?? '').get('category'),
    ),
);
const query = computed(() => extractQuery(page.url));

const heading = computed(
    () =>
        CATEGORY_TABS.find((tab) => tab.key === activeCategory.value)?.label ??
        'すべて',
);

const filteredTools = computed(() =>
    filterTools(props.tools, activeCategory.value, query.value),
);
</script>

<template>
    <Head title="Web制作ツール集 - katsuooool" />

    <PublicLayout>
        <h1 class="text-2xl font-bold text-gray-900">
            {{ heading }}
            <span class="ml-2 text-sm font-normal text-gray-500"
                >{{ filteredTools.length }}件</span
            >
        </h1>
        <p v-if="query !== ''" class="mt-1 text-sm text-gray-500">
            「{{ query }}」の検索結果
        </p>

        <div
            v-if="filteredTools.length === 0"
            class="mt-10 text-sm text-gray-500"
        >
            該当するツールが見つかりませんでした。
        </div>

        <div
            v-else
            class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <ToolCard
                v-for="tool in filteredTools"
                :key="tool.slug"
                :tool="tool"
            />
        </div>

        <PremiumBanner />
        <WebsiteStarterBanner />
    </PublicLayout>
</template>
