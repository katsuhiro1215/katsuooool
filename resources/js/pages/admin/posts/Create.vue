<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PostForm from '@/components/admin/PostForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    title: '',
    slug: '',
    type: 'announcement',
    body_markdown: '',
    published_at: '',
});

function submit() {
    form.post(route('admin.posts.store'));
}
</script>

<template>
    <Head title="投稿を新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">投稿を新規作成</h1>
        </template>

        <PostForm :form="form" submit-label="作成する" @submit="submit" />
    </AdminLayout>
</template>
