<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import SnippetForm from '@/components/admin/SnippetForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface SnippetDetail {
    id: number;
    category: string;
    title: string;
    html: string;
    css: string;
    is_premium: boolean;
}

const props = defineProps<{ snippet: SnippetDetail }>();

const form = useForm({
    category: props.snippet.category,
    title: props.snippet.title,
    html: props.snippet.html,
    css: props.snippet.css,
    is_premium: props.snippet.is_premium,
});

function submit() {
    form.put(route('admin.ui-pattern-snippets.update', props.snippet.id));
}

function destroy() {
    if (confirm('このパターンを削除しますか?')) {
        router.delete(
            route('admin.ui-pattern-snippets.destroy', props.snippet.id),
        );
    }
}
</script>

<template>
    <Head title="パターンを編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">パターンを編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <SnippetForm :form="form" submit-label="更新する" @submit="submit" />
    </AdminLayout>
</template>
