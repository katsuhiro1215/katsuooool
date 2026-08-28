<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import ColorExampleForm from '@/components/admin/ColorExampleForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

const form = useForm({
    type: 'palette',
    title: '',
    colors: [] as string[],
    angle: null as number | null,
});

function submit() {
    form.post(route('admin.color-examples.store'));
}
</script>

<template>
    <Head title="カラー例を新規作成 - Admin" />

    <AdminLayout>
        <template #header>
            <h1 class="text-lg font-bold text-gray-900">カラー例を新規作成</h1>
        </template>

        <ColorExampleForm
            :form="form"
            submit-label="作成する"
            @submit="submit"
        />
    </AdminLayout>
</template>
