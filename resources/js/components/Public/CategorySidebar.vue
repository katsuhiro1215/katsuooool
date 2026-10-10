<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { CATEGORY_TABS, countByCategory } from '@/lib/toolFilter';
import type { ToolNavItem } from '@/lib/toolFilter';

const props = defineProps<{
    tools: ToolNavItem[];
    activeCategory: string | null;
    query: string;
}>();

const keyword = ref(props.query);

watch(
    () => props.query,
    (value) => {
        keyword.value = value;
    },
);

const counts = computed(() => countByCategory(props.tools));

function hrefFor(key: string): string {
    return key === 'all' ? '/' : `/?category=${key}`;
}

function submit(): void {
    const q = keyword.value.trim();

    router.get('/', q === '' ? {} : { q });
}
</script>

<template>
    <aside class="w-full lg:w-60 lg:shrink-0">
        <form class="mb-4" @submit.prevent="submit">
            <input
                v-model="keyword"
                type="search"
                placeholder="ツールを検索..."
                aria-label="ツールを検索"
                class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-400 focus:outline-none focus:ring-0"
            />
        </form>

        <nav aria-label="カテゴリ">
            <ul class="space-y-1">
                <li v-for="tab in CATEGORY_TABS" :key="tab.key">
                    <Link
                        :href="hrefFor(tab.key)"
                        :aria-current="
                            activeCategory === tab.key ? 'page' : undefined
                        "
                        class="flex items-center justify-between rounded-md px-3 py-2 text-sm font-medium transition"
                        :class="
                            activeCategory === tab.key
                                ? 'bg-gray-900 text-white'
                                : 'text-gray-600 hover:bg-gray-100'
                        "
                    >
                        <span>{{ tab.label }}</span>
                        <span
                            class="text-xs"
                            :class="
                                activeCategory === tab.key
                                    ? 'text-gray-300'
                                    : 'text-gray-400'
                            "
                            >{{ counts[tab.key] ?? 0 }}</span
                        >
                    </Link>
                </li>
            </ul>
        </nav>
    </aside>
</template>
