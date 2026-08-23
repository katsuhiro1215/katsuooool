<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import DailyBarChart from '@/components/admin/DailyBarChart.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface ToolStat {
    slug: string;
    name: string;
    total_executions: number;
    total_files: number;
    last_7_days: number;
    last_30_days: number;
    error_count: number;
}

interface DailyPoint {
    date: string;
    executions: number;
    errors: number;
}

const props = defineProps<{
    stats: ToolStat[];
    dailyStats: Record<string, DailyPoint[]>;
}>();

const selectedSlug = ref(props.stats[0]?.slug ?? '');

const selectedSeries = computed(
    () => props.dailyStats[selectedSlug.value] ?? [],
);
</script>

<template>
    <Head title="Admin ダッシュボード" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">
                Admin ダッシュボード
            </h1>
        </template>

        <div v-if="stats.length === 0" class="text-sm text-gray-500">
            データがありません。
        </div>

        <template v-else>
            <div class="mb-8 rounded-lg border border-gray-200 bg-white p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-sm font-medium text-gray-700">
                        日次推移(直近30日)
                    </h2>
                    <select
                        v-model="selectedSlug"
                        class="rounded-md border border-gray-300 px-2 py-1.5 text-sm"
                    >
                        <option
                            v-for="stat in stats"
                            :key="stat.slug"
                            :value="stat.slug"
                        >
                            {{ stat.name }}
                        </option>
                    </select>
                </div>
                <DailyBarChart :series="selectedSeries" />
            </div>

            <div
                class="overflow-hidden rounded-lg border border-gray-200 bg-white"
            >
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-gray-200 bg-gray-50 text-gray-500"
                    >
                        <tr>
                            <th class="px-4 py-3 font-medium">ツール名</th>
                            <th class="px-4 py-3 font-medium">
                                実行回数(保存期間内)
                            </th>
                            <th class="px-4 py-3 font-medium">
                                ダウンロード回数(概算・保存期間内)
                            </th>
                            <th class="px-4 py-3 font-medium">直近7日</th>
                            <th class="px-4 py-3 font-medium">直近30日</th>
                            <th class="px-4 py-3 font-medium">エラー数</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="stat in stats"
                            :key="stat.slug"
                            class="border-b border-gray-100 last:border-0"
                        >
                            <td class="px-4 py-3 text-gray-900">
                                {{ stat.name }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ stat.total_executions }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ stat.total_files }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ stat.last_7_days }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ stat.last_30_days }}
                            </td>
                            <td
                                class="px-4 py-3"
                                :class="
                                    stat.error_count > 0
                                        ? 'font-medium text-red-600'
                                        : 'text-gray-700'
                                "
                            >
                                {{ stat.error_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AdminLayout>
</template>
