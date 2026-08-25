<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { onBeforeUnmount, ref, watch } from 'vue';

import { CATEGORY_OPTIONS } from '@/lib/uiPatternCategory';
import { buildPatternDocument } from '@/lib/uiPatternSnippet';

interface SnippetFormData {
    category: string;
    title: string;
    html: string;
    css: string;
}

const props = defineProps<{
    form: InertiaForm<SnippetFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

// srcdoc is debounced (rather than a plain computed) because the iframe
// fully reloads its document every time srcdoc changes; recomputing on
// every keystroke would cause visible flicker while typing.
const PREVIEW_DEBOUNCE_MS = 300;

const previewSrcdoc = ref(
    buildPatternDocument(props.form.html, props.form.css),
);

let debounceTimer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => [props.form.html, props.form.css] as const,
    ([html, css]) => {
        if (debounceTimer !== undefined) {
            clearTimeout(debounceTimer);
        }

        debounceTimer = setTimeout(() => {
            previewSrcdoc.value = buildPatternDocument(html, css);
        }, PREVIEW_DEBOUNCE_MS);
    },
);

onBeforeUnmount(() => {
    if (debounceTimer !== undefined) {
        clearTimeout(debounceTimer);
    }
});
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call. -->
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div>
            <label
                class="block text-sm font-medium text-gray-700"
                for="category"
                >種別</label
            >
            <select
                id="category"
                v-model="form.category"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option
                    v-for="option in CATEGORY_OPTIONS"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <p v-if="form.errors.category" class="mt-1 text-xs text-red-600">
                {{ form.errors.category }}
            </p>
        </div>

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
            <label class="block text-sm font-medium text-gray-700" for="html"
                >HTML</label
            >
            <textarea
                id="html"
                v-model="form.html"
                rows="8"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p v-if="form.errors.html" class="mt-1 text-xs text-red-600">
                {{ form.errors.html }}
            </p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700" for="css"
                >CSS</label
            >
            <textarea
                id="css"
                v-model="form.css"
                rows="10"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p v-if="form.errors.css" class="mt-1 text-xs text-red-600">
                {{ form.errors.css }}
            </p>
        </div>

        <div>
            <span class="block text-sm font-medium text-gray-700"
                >プレビュー</span
            >
            <div
                class="mt-1 flex h-40 items-center justify-center rounded-md border border-gray-300 bg-gray-50"
            >
                <iframe
                    :srcdoc="previewSrcdoc"
                    sandbox=""
                    class="h-full w-full"
                    title="プレビュー"
                />
            </div>
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
