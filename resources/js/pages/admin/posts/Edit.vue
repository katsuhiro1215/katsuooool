<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import PostForm from '@/components/admin/PostForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface PostDetail {
    id: number;
    title: string;
    slug: string;
    type: string;
    body_markdown: string;
    published_at: string | null;
}

const props = defineProps<{ post: PostDetail }>();

const form = useForm({
    title: props.post.title,
    slug: props.post.slug,
    type: props.post.type,
    body_markdown: props.post.body_markdown,
    published_at: props.post.published_at ?? '',
});

function submit() {
    form.put(route('admin.posts.update', props.post.id));
}

function destroy() {
    if (confirm('この投稿を削除しますか?')) {
        router.delete(route('admin.posts.destroy', props.post.id));
    }
}
</script>

<template>
    <Head title="投稿を編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">投稿を編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <PostForm :form="form" submit-label="更新する" @submit="submit" />
    </AdminLayout>
</template>
