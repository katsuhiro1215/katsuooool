<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface ColorExampleFormData {
    type: string;
    title: string;
    colors: string[];
    angle: number | null;
}

const props = defineProps<{
    form: InertiaForm<ColorExampleFormData>;
    submitLabel: string;
}>();

const emit = defineEmits<{ submit: [] }>();

const colorsText = computed({
    get: () => props.form.colors.join(', '),
    set: (value: string) => {
        // eslint-disable-next-line vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call.
        props.form.colors = value
            .split(',')
            .map((hex) => hex.trim())
            .filter((hex) => hex.length > 0);
    },
});
</script>

<template>
    <!-- eslint-disable vue/no-mutating-props -- InertiaForm is a shared reactive form object designed to be mutated in place via v-model from the parent's useForm() call. -->
    <form class="space-y-4" @submit.prevent="emit('submit')">
        <div>
            <label class="block text-sm font-medium text-gray-700" for="type"
                >種別</label
            >
            <select
                id="type"
                v-model="form.type"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            >
                <option value="palette">パレット</option>
                <option value="gradient">グラデーション</option>
                <option value="scheme">色の組み合わせ</option>
            </select>
            <p v-if="form.errors.type" class="mt-1 text-xs text-red-600">
                {{ form.errors.type }}
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
            <label class="block text-sm font-medium text-gray-700" for="colors"
                >色(カンマ区切りのhex、例: #3366ff, #ff9933)</label
            >
            <input
                id="colors"
                v-model="colorsText"
                type="text"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm"
            />
            <p
                v-if="form.errors['colors.0'] || form.errors.colors"
                class="mt-1 text-xs text-red-600"
            >
                {{ form.errors['colors.0'] ?? form.errors.colors }}
            </p>
        </div>

        <div v-if="form.type === 'gradient'">
            <label class="block text-sm font-medium text-gray-700" for="angle"
                >角度(0〜360)</label
            >
            <input
                id="angle"
                v-model.number="form.angle"
                type="number"
                min="0"
                max="360"
                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
            />
            <p v-if="form.errors.angle" class="mt-1 text-xs text-red-600">
                {{ form.errors.angle }}
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
