<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import SnippetForm from '@/components/admin/SnippetForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    category: 'button',
    title: '',
    html: '',
    css: '',
});

function submit() {
    form.post(route('admin.ui-pattern-snippets.store'));
}
</script>

<template>
    <Head title="パターンを新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">パターンを新規作成</h1>
        </template>

        <SnippetForm :form="form" submit-label="作成する" @submit="submit" />
    </AdminLayout>
</template>
