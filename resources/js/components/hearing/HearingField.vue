<script setup lang="ts">
import { computed } from 'vue';
import type { HearingField } from '@/lib/hearing/questions';
import {
    errorMessage,
    isValid,
    MAX_ROWS
    
} from '@/lib/hearing/sheet';
import type {SheetState} from '@/lib/hearing/sheet';

const props = defineProps<{ field: HearingField; state: SheetState }>();
const emit = defineEmits<{
    update: [value: string | string[]];
    'toggle-pending': [];
    'rows-limit': [];
}>();

const id = computed(() => `f-${props.field.id}`);
const value = computed(() => props.state.answers[props.field.id]);
const text = computed(() =>
    typeof value.value === 'string' ? value.value : '',
);
const list = computed(() => (Array.isArray(value.value) ? value.value : []));
const rows = computed(() => (list.value.length ? list.value : ['']));
const pending = computed(() => Boolean(props.state.pending[props.field.id]));
const invalid = computed(() => !isValid(props.state, props.field));
const isGroup = computed(() =>
    ['radio', 'chips', 'repeat'].includes(props.field.type),
);
const swatch = computed(() =>
    /^#[\da-f]{6}$/i.test(text.value) ? text.value : undefined,
);

const control =
    'block w-full rounded-md border-gray-300 text-base shadow-sm focus:border-gray-900 focus:ring-gray-900';

function toggleChip(option: string, checked: boolean) {
    emit(
        'update',
        checked
            ? [...list.value, option]
            : list.value.filter((v) => v !== option),
    );
}

function setRow(index: number, next: string) {
    const copy = [...rows.value];
    copy[index] = next;
    emit('update', copy);
}

function addRow() {
    if (rows.value.length >= MAX_ROWS) {
        emit('rows-limit');

        return;
    }

    emit('update', [...rows.value, '']);
}

function removeRow(index: number) {
    const copy = rows.value.filter((_, i) => i !== index);
    emit('update', copy.length ? copy : ['']);
}
</script>

<template>
    <div
        :id="`field-${field.id}`"
        class="rounded-lg"
        :class="[
            field.wide ? 'sm:col-span-2' : '',
            pending ? 'bg-amber-50 p-3 ring-1 ring-amber-200' : '',
        ]"
    >
        <div class="mb-2 flex items-start justify-between gap-3">
            <component
                :is="isGroup ? 'span' : 'label'"
                :id="`label-${field.id}`"
                :for="isGroup ? undefined : id"
                class="text-base font-semibold text-gray-900"
            >
                {{ field.label }}
                <span
                    v-if="field.core"
                    class="ml-1 text-sm text-rose-600"
                    aria-label="重点項目"
                    >●</span
                >
            </component>
            <button
                type="button"
                :aria-pressed="pending"
                :aria-label="`${field.label}を確認待ち${pending ? 'から解除' : 'にする'}`"
                class="shrink-0 rounded-md border px-2 py-1 text-sm transition"
                :class="
                    pending
                        ? 'border-amber-400 bg-amber-100 text-amber-900'
                        : 'border-gray-300 text-gray-600 hover:bg-gray-50'
                "
                data-pending
                @click="$emit('toggle-pending')"
            >
                {{ pending ? '◷ 確認待ち' : '確認待ちにする' }}
            </button>
        </div>

        <textarea
            v-if="field.type === 'textarea'"
            :id="id"
            :value="text"
            :class="control"
            :placeholder="field.placeholder"
            :aria-invalid="invalid"
            rows="3"
            maxlength="10000"
            @input="
                $emit('update', ($event.target as HTMLTextAreaElement).value)
            "
        />

        <div
            v-else-if="field.type === 'radio' || field.type === 'chips'"
            role="group"
            :aria-labelledby="`label-${field.id}`"
            class="flex flex-wrap gap-2"
        >
            <label
                v-for="option in field.options"
                :key="option"
                class="inline-flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-base has-[:checked]:border-gray-900 has-[:checked]:bg-gray-900 has-[:checked]:text-white"
            >
                <input
                    :type="field.type === 'radio' ? 'radio' : 'checkbox'"
                    :name="field.id"
                    :value="option"
                    :checked="
                        field.type === 'radio'
                            ? text === option
                            : list.includes(option)
                    "
                    class="sr-only"
                    @change="
                        field.type === 'radio'
                            ? $emit('update', option)
                            : toggleChip(
                                  option,
                                  ($event.target as HTMLInputElement).checked,
                              )
                    "
                />
                <span>{{ option }}</span>
            </label>
        </div>

        <select
            v-else-if="field.type === 'select'"
            :id="id"
            :value="text"
            :class="control"
            @change="
                $emit('update', ($event.target as HTMLSelectElement).value)
            "
        >
            <option value="">選択してください</option>
            <option v-for="option in field.options" :key="option">
                {{ option }}
            </option>
        </select>

        <div v-else-if="field.type === 'repeat'" class="space-y-2">
            <div v-for="(row, i) in rows" :key="i" class="flex gap-2">
                <textarea
                    :value="row"
                    :class="control"
                    :placeholder="field.placeholder"
                    :aria-label="`${field.label} ${i + 1}`"
                    :data-row="i"
                    rows="2"
                    maxlength="10000"
                    @input="
                        setRow(i, ($event.target as HTMLTextAreaElement).value)
                    "
                />
                <button
                    type="button"
                    class="shrink-0 rounded-md border border-gray-300 px-3 text-base text-gray-600 hover:bg-gray-50"
                    :aria-label="`${field.label} ${i + 1}を削除`"
                    data-remove
                    @click="removeRow(i)"
                >
                    ×
                </button>
            </div>
            <button
                type="button"
                class="text-base font-medium text-gray-700 underline"
                data-add
                @click="addRow"
            >
                ＋ 入力項目を追加
            </button>
        </div>

        <div v-else-if="field.type === 'color'" class="flex items-center gap-3">
            <span
                class="h-10 w-10 shrink-0 rounded-md border border-gray-300"
                :style="swatch ? { background: swatch } : undefined"
                data-swatch
            />
            <input
                :id="id"
                type="text"
                :value="text"
                :class="control"
                :placeholder="field.placeholder"
                :aria-invalid="invalid"
                :aria-label="`${field.label} 6桁のHEXコード`"
                spellcheck="false"
                maxlength="10000"
                @input="
                    $emit('update', ($event.target as HTMLInputElement).value)
                "
            />
        </div>

        <input
            v-else
            :id="id"
            :type="field.type"
            :value="text"
            :class="control"
            :placeholder="field.placeholder"
            :aria-invalid="invalid"
            maxlength="10000"
            @input="$emit('update', ($event.target as HTMLInputElement).value)"
        />

        <p
            v-if="invalid"
            class="mt-1 text-sm text-rose-700"
            role="alert"
            data-error
        >
            {{ errorMessage(field) }}
        </p>
    </div>
</template>
