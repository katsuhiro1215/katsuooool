<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';

interface PostFormData {
    title: string;
    slug: string;
    type: string;
    body_markdown: string;
    published_at: string;
}

defineProps<{
    form: InertiaForm<PostFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const TYPE_OPTIONS = [
    { value: 'update', label: 'アップデート' },
    { value: 'fix', label: '不具合修正' },
    { value: 'announcement', label: 'お知らせ' },
];
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call. -->
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div>
            <label class="block text-sm font-medium text-gray-700" for="title"
                >タイトル</label
            >
            <input
                id="title"
                v-model="form.title"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">
                {{ form.errors.title }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="slug"
                >スラッグ(URL用、英数字とハイフンのみ)</label
            >
            <input
                id="slug"
                v-model="form.slug"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.slug" class="mt-1 text-xs text-red-600">
                {{ form.errors.slug }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="type"
                >種別</label
            >
            <select
                id="type"
                v-model="form.type"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option
                    v-for="option in TYPE_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">
                {{ form.errors.type }}
            </p>
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="body_markdown"
                >本文(Markdown)</label
            >
            <textarea
                id="body_markdown"
                v-model="form.body_markdown"
                rows="12"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p
                v-if="form.errors.body_markdown"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors.body_markdown }}
            </p>
        </div>

        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="published_at"
                >公開日時(空欄なら下書き)</label
            >
            <input
                id="published_at"
                v-model="form.published_at"
                type="datetime-local"
                class="mt-1 w-full max-w-xs rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p
                v-if="form.errors.published_at"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors.published_at }}
            </p>
        </div>

        <button
            type="submit"
            class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:cursor-not-allowed disabled:bg-gray-300"
            :disabled="form.processing"
        >
            {{ submitLabel }}
        </button>
    </form>
</template>
