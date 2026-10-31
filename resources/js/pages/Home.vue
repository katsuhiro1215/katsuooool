<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import PremiumBanner from '@/components/PremiumBanner.vue';
import HomeHero from '@/components/Public/HomeHero.vue';
import ToolCard from '@/components/tools/ToolCard.vue';
import WebsiteStarterBanner from '@/components/WebsiteStarterBanner.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import {
    CATEGORY_TABS,
    extractQuery,
    filterTools,
    normalizeCategory,
    parsePageUrl,
} from '@/lib/toolFilter';
import type { Tool } from '@/lib/toolFilter';

const props = defineProps<{ tools: Tool[] }>();

const page = usePage();

const activeCategory = computed(() =>
    normalizeCategory(parsePageUrl(page.url).params.get('category')),
);
const query = computed(() => extractQuery(page.url));

const isLanding = computed(
    () => activeCategory.value === 'all' && query.value === '',
);

const categoryCount = computed(
    () => CATEGORY_TABS.filter((tab) => tab.key !== 'all').length,
);

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
        <template v-if="isLanding" #hero>
            <HomeHero :tool-count="tools.length" :category-count="categoryCount" />
        </template>

        <div class="px-6 py-6 lg:pl-8">
            <h1 class="text-2xl font-bold tracking-tight text-ink-900">
                {{ heading }}
                <span class="ml-2 text-sm font-normal text-ink-900/40"
                    >{{ filteredTools.length }}件</span
                >
            </h1>
            <p v-if="query !== ''" class="mt-1 text-sm text-ink-900/50">
                「{{ query }}」の検索結果
            </p>

            <div
                v-if="filteredTools.length === 0"
                class="mt-10 text-sm text-ink-900/50"
            >
                該当するツールが見つかりませんでした。
            </div>

            <div
                v-else
                class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4"
            >
                <ToolCard
                    v-for="tool in filteredTools"
                    :key="tool.slug"
                    :tool="tool"
                />
            </div>

            <PremiumBanner />
            <WebsiteStarterBanner />
        </div>
    </PublicLayout>
</template>
