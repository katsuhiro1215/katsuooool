<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CategorySidebar from '@/components/Public/CategorySidebar.vue';
import PublicFooter from '@/components/Public/PublicFooter.vue';
import PublicHeader from '@/components/Public/PublicHeader.vue';
import { extractQuery, resolveActiveCategory } from '@/lib/toolFilter';

const page = usePage();
const user = computed(() => page.props.auth.user);
const toolNav = computed(() => page.props.toolNav);
const activeCategory = computed(() =>
    resolveActiveCategory(page.url, toolNav.value),
);
const query = computed(() => extractQuery(page.url));

const showingSidebar = ref(false);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <PublicHeader :user="user" />

        <div class="mx-auto max-w-7xl px-6 py-6 lg:flex lg:gap-8">
            <button
                type="button"
                class="mb-4 inline-flex w-full items-center justify-between rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 lg:hidden"
                :aria-expanded="showingSidebar"
                @click="showingSidebar = !showingSidebar"
            >
                カテゴリ・検索
                <span aria-hidden="true">{{ showingSidebar ? '−' : '+' }}</span>
            </button>

            <div
                :class="showingSidebar ? 'block' : 'hidden'"
                class="mb-6 lg:mb-0 lg:block"
            >
                <CategorySidebar
                    :tools="toolNav"
                    :active-category="activeCategory"
                    :query="query"
                />
            </div>

            <main class="min-w-0 flex-1">
                <slot />
            </main>
        </div>

        <PublicFooter />
    </div>
</template>
