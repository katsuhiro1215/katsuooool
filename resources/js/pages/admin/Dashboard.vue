<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

interface ToolStat {
    slug: string;
    name: string;
    total_executions: number;
    total_files: number;
    last_7_days: number;
    last_30_days: number;
}

defineProps<{ stats: ToolStat[] }>();

function logout() {
    router.post('/admin/logout');
}
</script>

<template>
    <Head title="Admin ダッシュボード" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-6">
                <h1 class="text-lg font-bold text-gray-900">Admin ダッシュボード</h1>
                <button type="button" class="text-sm text-gray-500 hover:text-gray-700" @click="logout">ログアウト</button>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-6 py-10">
            <div v-if="stats.length === 0" class="text-sm text-gray-500">データがありません。</div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">ツール名</th>
                            <th class="px-4 py-3 font-medium">実行回数</th>
                            <th class="px-4 py-3 font-medium">ダウンロード回数(概算)</th>
                            <th class="px-4 py-3 font-medium">直近7日</th>
                            <th class="px-4 py-3 font-medium">直近30日</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="stat in stats" :key="stat.slug" class="border-b border-gray-100 last:border-0">
                            <td class="px-4 py-3 text-gray-900">{{ stat.name }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.total_executions }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.total_files }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.last_7_days }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ stat.last_30_days }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</template>
