<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import type { Company } from '@/types';

const props = defineProps<{
    company: Company;
}>();

interface CompanyItem {
    label: string;
    value: string;
}

const ITEMS = computed<CompanyItem[]>(() => [
    {
        label: '事業者名',
        value: props.company.name ?? '【事業者名を入力してください】',
    },
    {
        label: '代表者',
        value:
            props.company.representative ??
            '【運営責任者名を入力してください】',
    },
    {
        label: '所在地',
        value: props.company.address ?? '【所在地を入力してください】',
    },
    {
        label: '連絡先',
        value:
            props.company.contact ??
            '【メールアドレス等の連絡先を入力してください】',
    },
]);
</script>

<template>
    <Head title="会社情報 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader title="会社情報" description="" narrow />

            <PageMain narrow>
                <dl
                    class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white"
                >
                    <div
                        v-for="item in ITEMS"
                        :key="item.label"
                        class="grid grid-cols-1 gap-1 px-4 py-4 sm:grid-cols-3 sm:gap-4"
                    >
                        <dt class="text-sm font-medium text-gray-500">
                            {{ item.label }}
                        </dt>
                        <dd class="text-sm text-gray-900 sm:col-span-2">
                            {{ item.value }}
                        </dd>
                    </div>
                </dl>
            </PageMain>
        </div>
    </PublicLayout>
</template>
