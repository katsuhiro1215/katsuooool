<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import ColorExampleForm from '@/components/admin/ColorExampleForm.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';

interface ColorExampleDetail {
    id: number;
    type: string;
    title: string;
    colors: string[];
    angle: number | null;
}

const props = defineProps<{ example: ColorExampleDetail }>();

const form = useForm({
    type: props.example.type,
    title: props.example.title,
    colors: props.example.colors,
    angle: props.example.angle,
});

function submit() {
    form.put(route('admin.color-examples.update', props.example.id));
}

function destroy() {
    if (confirm('このカラー例を削除しますか?')) {
        router.delete(route('admin.color-examples.destroy', props.example.id));
    }
}
</script>

<template>
    <Head title="カラー例を編集 - Admin" />

    <AdminLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-bold text-gray-900">カラー例を編集</h1>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 text-sm text-red-600 hover:bg-red-50"
                    @click="destroy"
                >
                    削除する
                </button>
            </div>
        </template>

        <ColorExampleForm
            :form="form"
            submit-label="更新する"
            @submit="submit"
        />
    </AdminLayout>
</template>
