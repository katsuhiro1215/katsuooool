<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import PremiumBanner from '@/components/PremiumBanner.vue';
import ToolCard from '@/components/tools/ToolCard.vue';
import WebsiteStarterBanner from '@/components/WebsiteStarterBanner.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { CATEGORY_TABS, filterTools } from '@/lib/toolFilter';
import type { Tool } from '@/lib/toolFilter';

const props = defineProps<{ tools: Tool[] }>();

const query = ref('');
const activeCategory = ref('all');

const filteredTools = computed(() =>
    filterTools(props.tools, activeCategory.value, query.value),
);
</script>

<template>
    <Head title="katsuooool - Web制作ツール集" />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-6 py-10">
            <h1 class="text-2xl font-bold text-gray-900">katsuooool</h1>

            <input
                v-model="query"
                type="search"
                placeholder="ツールを検索..."
                class="mt-4 w-full max-w-md rounded-md border border-gray-300 px-4 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
            />

            <div class="-mx-6 mt-4 overflow-x-auto px-6">
                <div class="flex gap-2">
                    <button
                        v-for="tab in CATEGORY_TABS"
                        :key="tab.key"
                        type="button"
                        class="shrink-0 rounded-full border px-4 py-1.5 text-sm font-medium transition"
                        :class="
                            activeCategory === tab.key
                                ? 'border-gray-900 bg-gray-900 text-white'
                                : 'border-gray-300 bg-white text-gray-600 hover:border-gray-400'
                        "
                        @click="activeCategory = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <div
                v-if="filteredTools.length === 0"
                class="mt-10 text-sm text-gray-500"
            >
                該当するツールが見つかりませんでした。
            </div>

            <div
                v-else
                class="mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
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
