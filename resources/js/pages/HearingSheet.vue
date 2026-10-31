<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue';

import HearingField from '@/components/hearing/HearingField.vue';
import HearingPaper from '@/components/hearing/HearingPaper.vue';
import HearingStepTabs from '@/components/hearing/HearingStepTabs.vue';
import Modal from '@/components/Modal.vue';
import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { HEARING_STEPS } from '@/lib/hearing/questions';
import { SAMPLE_STATE } from '@/lib/hearing/sample';
import {
    allFields,
    emptyState,
    hasAnswer,
    isAnswered,
    MAX_IMPORT_BYTES,
    normalize,
    plainText,
    reviewItems,
    STORAGE_KEY,
    stepIndexOf
    
} from '@/lib/hearing/sheet';
import type {SheetState} from '@/lib/hearing/sheet';

const state = reactive<SheetState>(emptyState());
const hideEmpty = ref(false);
const previewMode = ref<'sheet' | 'text'>('sheet');
const mobileView = ref<'editor' | 'preview'>('editor');
const saveStatus = ref('このブラウザに自動保存');
const toastMessage = ref('');
const importInput = ref<HTMLInputElement | null>(null);
const editorTop = ref<HTMLElement | null>(null);

type Dialog = 'help' | 'review' | 'reset' | 'sample' | 'import' | null;
const dialog = ref<Dialog>(null);
let pendingImport: SheetState | null = null;

let toastTimer: ReturnType<typeof setTimeout> | undefined;
let saveTimer: ReturnType<typeof setTimeout> | undefined;
let trackTimer: ReturnType<typeof setTimeout> | undefined;

function toast(message: string) {
    toastMessage.value = message;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (toastMessage.value = ''), 3500);
}

function replaceState(next: SheetState) {
    Object.assign(state, next);
}

// 保存済みの内容を復元（読めなければ空のまま、JSON保存を案内する）
try {
    const saved = localStorage.getItem(STORAGE_KEY);

    if (saved) {
        replaceState(normalize(JSON.parse(saved)));
    }
} catch {
    saveStatus.value = '保存データを読めません・JSON保存をご利用ください';
}

function save() {
    clearTimeout(saveTimer);
    saveTimer = undefined;
    state.updatedAt = new Date().toISOString();

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        saveStatus.value = '保存しました';
    } catch {
        saveStatus.value = '保存できません・JSON保存をご利用ください';
    }
}

function changed() {
    state.updatedAt = new Date().toISOString();
    saveStatus.value = '保存中…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(save, 250);
}

function track() {
    clearTimeout(trackTimer);
    trackTimer = setTimeout(() => {
        axios.post('/hearing-sheet/track').catch(() => {});
    }, 500);
}

onBeforeUnmount(() => {
    if (saveTimer) {
        save();
    }
});

const counts = computed(() => ({
    answered: allFields.filter((f) => isAnswered(state, f)).length,
    pending: allFields.filter((f) => state.pending[f.id]).length,
}));
const percent = computed(() =>
    Math.round((counts.value.answered / allFields.length) * 100),
);
const step = computed(() => HEARING_STEPS[state.step]);
const review = computed(() => reviewItems(state));
const text = computed(() => plainText(state, hideEmpty.value));

function setAnswer(id: string, value: string | string[]) {
    state.answers[id] = value;
    changed();
}

function togglePending(id: string) {
    state.pending[id] = !state.pending[id];
    changed();
}

async function goStep(index: number, focus = true) {
    state.step = index;
    mobileView.value = 'editor';
    save();

    if (focus) {
        await nextTick();
        editorTop.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function jumpTo(fieldId: string) {
    dialog.value = null;
    await goStep(stepIndexOf(fieldId), false);
    await nextTick();
    const el = document.getElementById(`field-${fieldId}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el?.querySelector<HTMLElement>('input,textarea,select')?.focus({
        preventScroll: true,
    });
}

function next() {
    if (state.step === HEARING_STEPS.length - 1) {
        dialog.value = 'review';
    } else {
        goStep(state.step + 1);
    }
}

const hasContent = computed(
    () =>
        allFields.some((f) => hasAnswer(state, f)) ||
        Object.values(state.pending).some(Boolean),
);

function downloadJson() {
    save();
    const blob = new Blob([JSON.stringify(state, null, 2)], {
        type: 'application/json',
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `hearing-sheet-${new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' })}.json`;
    a.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    toast('再編集用のJSONファイルを書き出しました');
    track();
}

function askReplace(kind: 'sample' | 'import') {
    if (hasContent.value) {
        dialog.value = kind;
    } else {
        applyReplace(kind);
    }
}

function applyReplace(kind: 'sample' | 'import' | 'reset') {
    dialog.value = null;
    clearTimeout(saveTimer);

    if (kind === 'reset') {
        replaceState(emptyState());

        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch {
            // 保存できない環境では、画面の内容だけを消す
        }

        saveStatus.value = 'このブラウザに自動保存';
        toast('入力内容をリセットしました');

        return;
    }

    if (kind === 'sample') {
        replaceState(JSON.parse(JSON.stringify(SAMPLE_STATE)));
        toast('架空の会社のサンプルを表示しました');
    } else if (pendingImport) {
        replaceState(pendingImport);
        pendingImport = null;
        toast('保存したシートを読み込みました');
    }

    save();
}

async function onImportFile(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!file) {
        return;
    }

    try {
        if (file.size > MAX_IMPORT_BYTES) {
            throw new Error('too large');
        }

        pendingImport = normalize(JSON.parse(await file.text()));
        askReplace('import');
    } catch {
        toast(
            '読み込めません。このツールで保存した2MB以下のJSONを選んでください',
        );
    }

    input.value = '';
}

async function copyText() {
    try {
        await navigator.clipboard.writeText(text.value);
        toast('ヒアリング内容をコピーしました');
        track();
    } catch {
        previewMode.value = 'text';
        mobileView.value = 'preview';
        toast('コピーできませんでした。テキスト表示から選択してください');
    }
}

function printSheet() {
    save();
    track();
    window.print();
}

function printFromReview() {
    dialog.value = null;
    nextTick(printSheet);
}

const buttonLight =
    'inline-flex items-center justify-center rounded-md border border-ink-900/20 bg-white px-4 py-2 text-base font-medium text-ink-900/80 transition hover:bg-ink-900/5 disabled:cursor-not-allowed disabled:opacity-40';
const linkButton =
    'rounded-md px-3 py-2 text-base font-medium text-ink-900/70 hover:bg-ink-900/10';
</script>

<template>
    <Head title="ヒアリングシートジェネレーター - katsuooool" />

    <PublicLayout>
        <div class="hearing-screen min-h-screen w-full">
            <PageHeader
                title="ヒアリングシートジェネレーター"
                description="つくる前に、想いをそろえる。事業のことから公開後のことまで、対話しながらWeb制作のヒアリングシートを作成できます。"
            />

            <PageMain>
                <div
                    class="flex flex-wrap items-center justify-between gap-3"
                    data-toolbar
                >
                    <p class="text-base text-ink-900/70" role="status">
                        <span class="font-semibold">{{
                            state.answers.project || '新しいヒアリングシート'
                        }}</span>
                        <span
                            class="ml-2 rounded bg-ink-900/10 px-2 py-0.5 text-sm text-ink-900/60"
                            >下書き</span
                        >
                        <span class="ml-3 text-sm text-ink-900/40">{{
                            saveStatus
                        }}</span>
                    </p>
                    <div class="flex flex-wrap gap-1">
                        <button
                            type="button"
                            :class="linkButton"
                            data-action="sample"
                            @click="askReplace('sample')"
                        >
                            サンプルを見る
                        </button>
                        <button
                            type="button"
                            :class="linkButton"
                            data-action="import"
                            @click="importInput?.click()"
                        >
                            JSON読込
                        </button>
                        <button
                            type="button"
                            :class="linkButton"
                            data-action="backup"
                            @click="downloadJson"
                        >
                            JSON保存
                        </button>
                        <button
                            type="button"
                            :class="linkButton"
                            data-action="reset"
                            @click="dialog = 'reset'"
                        >
                            リセット
                        </button>
                        <button
                            type="button"
                            :class="linkButton"
                            data-action="help"
                            @click="dialog = 'help'"
                        >
                            使い方
                        </button>
                    </div>
                    <input
                        ref="importInput"
                        type="file"
                        accept=".json,application/json"
                        hidden
                        @change="onImportFile"
                    />
                </div>

                <div
                    class="mt-4 rounded-lg border border-ink-900/10 bg-white p-4"
                >
                    <div class="flex items-center justify-between text-base">
                        <span class="font-medium text-ink-900/80"
                            >ヒアリングの進捗</span
                        >
                        <strong data-percent>{{ percent }}%</strong>
                    </div>
                    <progress
                        class="mt-2 h-2 w-full overflow-hidden rounded [&::-moz-progress-bar]:bg-ink-900 [&::-webkit-progress-bar]:bg-ink-900/10 [&::-webkit-progress-value]:bg-ink-900"
                        max="100"
                        :value="percent"
                    >
                        {{ percent }}%
                    </progress>
                    <p class="mt-2 text-sm text-ink-900/60" data-progress-detail>
                        {{ counts.answered }} /
                        {{ allFields.length }} 項目回答済み · 確認待ち
                        {{ counts.pending }}
                    </p>
                </div>

                <div class="mt-6">
                    <HearingStepTabs
                        :state="state"
                        :current="state.step"
                        @select="goStep"
                    />
                </div>

                <div class="mt-4 flex gap-2 xl:hidden" role="group">
                    <button
                        type="button"
                        :aria-pressed="mobileView === 'editor'"
                        class="flex-1 rounded-md border px-4 py-2 text-base font-medium"
                        :class="
                            mobileView === 'editor'
                                ? 'border-ink-900 bg-ink-900 text-white'
                                : 'border-ink-900/20 bg-white text-ink-900/70'
                        "
                        data-view="editor"
                        @click="mobileView = 'editor'"
                    >
                        ヒアリング入力
                    </button>
                    <button
                        type="button"
                        :aria-pressed="mobileView === 'preview'"
                        class="flex-1 rounded-md border px-4 py-2 text-base font-medium"
                        :class="
                            mobileView === 'preview'
                                ? 'border-ink-900 bg-ink-900 text-white'
                                : 'border-ink-900/20 bg-white text-ink-900/70'
                        "
                        data-view="preview"
                        @click="mobileView = 'preview'"
                    >
                        プレビュー
                    </button>
                </div>

                <div
                    class="mt-6 grid items-start gap-8 xl:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)]"
                >
                    <section
                        ref="editorTop"
                        aria-label="ヒアリング入力"
                        :class="
                            mobileView === 'editor' ? '' : 'hidden xl:block'
                        "
                        class="scroll-mt-4"
                    >
                        <div>
                            <p class="text-sm font-semibold text-ink-900/40">
                                STEP 0{{ state.step + 1 }} / 0{{
                                    HEARING_STEPS.length
                                }}
                            </p>
                            <h2
                                class="mt-1 text-2xl font-bold text-ink-900"
                                data-step-title
                            >
                                {{ step.title }}
                            </h2>
                            <p class="mt-1 text-base text-ink-900/60">
                                {{ step.description }}
                            </p>
                        </div>
                        <p
                            class="mt-4 rounded-md bg-ink-900/10 px-4 py-3 text-sm text-ink-900/70"
                        >
                            わかる項目から入力できます。
                            <span class="text-rose-600">●</span>
                            は押さえておきたい項目です。
                        </p>

                        <form class="mt-4 space-y-6" novalidate @submit.prevent>
                            <section
                                v-for="(group, gi) in step.groups"
                                :key="group.title"
                                class="rounded-lg border border-ink-900/10 bg-white p-5 sm:p-6"
                            >
                                <h3
                                    class="flex items-baseline gap-3 text-lg font-bold text-ink-900"
                                >
                                    <span class="text-sm text-ink-900/35">{{
                                        String(gi + 1).padStart(2, '0')
                                    }}</span>
                                    {{ group.title }}
                                </h3>
                                <p class="mt-1 text-base text-ink-900/60">
                                    {{ group.description }}
                                </p>
                                <div class="mt-5 grid gap-5 sm:grid-cols-2">
                                    <HearingField
                                        v-for="f in group.fields"
                                        :key="f.id"
                                        :field="f"
                                        :state="state"
                                        @update="setAnswer(f.id, $event)"
                                        @toggle-pending="togglePending(f.id)"
                                        @rows-limit="
                                            toast(
                                                '追加できるのは30項目までです',
                                            )
                                        "
                                    />
                                </div>
                            </section>
                            <p
                                class="rounded-lg border border-ink-900/10 bg-white p-4 text-base text-ink-900/70"
                            >
                                <strong class="mr-2 text-ink-900"
                                    >HEARING TIP</strong
                                >{{ step.tip }}
                            </p>
                        </form>

                        <div
                            class="mt-6 flex items-center justify-between gap-3"
                        >
                            <button
                                type="button"
                                :class="buttonLight"
                                :disabled="state.step === 0"
                                data-prev
                                @click="goStep(state.step - 1)"
                            >
                                ← 前のステップ
                            </button>
                            <span class="text-base text-ink-900/40"
                                >{{ state.step + 1 }} /
                                {{ HEARING_STEPS.length }}</span
                            >
                            <AppButton data-next @click="next">{{
                                state.step === HEARING_STEPS.length - 1
                                    ? '内容を確認する ✓'
                                    : '次へ進む →'
                            }}</AppButton>
                        </div>
                    </section>

                    <aside
                        aria-label="ヒアリングシートのプレビュー"
                        :class="
                            mobileView === 'preview' ? '' : 'hidden xl:block'
                        "
                        class="xl:sticky xl:top-4"
                    >
                        <div class="flex items-center justify-between">
                            <h2 class="text-lg font-bold text-ink-900">
                                シートプレビュー
                            </h2>
                            <button
                                type="button"
                                :class="linkButton"
                                data-action="review"
                                @click="dialog = 'review'"
                            >
                                内容を確認する
                            </button>
                        </div>
                        <div
                            class="mt-3 flex flex-wrap items-center justify-between gap-3"
                        >
                            <div
                                class="inline-flex rounded-md border border-ink-900/20 bg-white p-0.5"
                            >
                                <button
                                    type="button"
                                    :aria-pressed="previewMode === 'sheet'"
                                    class="rounded px-3 py-1.5 text-base"
                                    :class="
                                        previewMode === 'sheet'
                                            ? 'bg-ink-900 text-white'
                                            : 'text-ink-900/70'
                                    "
                                    data-mode="sheet"
                                    @click="previewMode = 'sheet'"
                                >
                                    シート
                                </button>
                                <button
                                    type="button"
                                    :aria-pressed="previewMode === 'text'"
                                    class="rounded px-3 py-1.5 text-base"
                                    :class="
                                        previewMode === 'text'
                                            ? 'bg-ink-900 text-white'
                                            : 'text-ink-900/70'
                                    "
                                    data-mode="text"
                                    @click="previewMode = 'text'"
                                >
                                    テキスト
                                </button>
                            </div>
                            <label
                                class="inline-flex items-center gap-2 text-base text-ink-900/70"
                            >
                                <input
                                    v-model="hideEmpty"
                                    type="checkbox"
                                    class="rounded border-ink-900/20"
                                    data-hide-empty
                                />
                                未回答を隠す
                            </label>
                        </div>

                        <div
                            class="mt-3 max-h-[70vh] overflow-y-auto rounded-lg bg-ink-900/10 p-3"
                        >
                            <HearingPaper
                                v-show="previewMode === 'sheet'"
                                :state="state"
                                :hide-empty="hideEmpty"
                            />
                            <pre
                                v-show="previewMode === 'text'"
                                class="whitespace-pre-wrap break-words rounded border border-ink-900/20 bg-white p-4 text-sm leading-relaxed text-ink-900/80"
                                data-text-preview
                                >{{ text }}</pre>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                :class="buttonLight"
                                data-action="copy"
                                @click="copyText"
                            >
                                テキストコピー
                            </button>
                            <AppButton
                                class="!px-4 !py-2"
                                data-action="print"
                                @click="printSheet"
                                >PDF / 印刷</AppButton
                            >
                        </div>
                        <p class="mt-3 text-sm text-ink-900/60">
                            入力はこのブラウザ内に保存されます。外部送信はしません。
                        </p>
                    </aside>
                </div>
            </PageMain>
        </div>

        <div id="hearing-print-area" aria-hidden="true">
            <HearingPaper :state="state" :hide-empty="hideEmpty" />
        </div>

        <Modal :show="dialog !== null" max-width="lg" @close="dialog = null">
            <div class="p-6" data-dialog>
                <template v-if="dialog === 'help'">
                    <h2 class="text-xl font-bold text-ink-900">
                        このツールの使い方
                    </h2>
                    <ol
                        class="mt-4 list-decimal space-y-2 pl-5 text-base text-ink-900/70"
                    >
                        <li>
                            上の6つのタブから、わかるところを選んで入力します。
                        </li>
                        <li>
                            決まっていない項目は「確認待ちにする」で残せます。
                        </li>
                        <li>
                            プレビューで、ヒアリング内容をいつでも確認できます。
                        </li>
                        <li>
                            「内容を確認する」で、確認待ちと重点項目の未回答を整理します。
                        </li>
                        <li>
                            テキストコピー、または「PDF /
                            印刷」で書き出します。印刷画面で「PDFに保存」を選んでください。
                        </li>
                    </ol>
                    <p class="mt-4 text-base text-ink-900/70">
                        入力はこの端末・ブラウザのローカルストレージに自動保存されます。共有端末では、利用後にリセットしてください。別の端末へ移すときは、JSON保存・読込を使います。
                    </p>
                    <p class="mt-2 text-base text-ink-900/70">
                        <span class="text-rose-600">●</span>
                        は重点項目です。未回答でも保存・書き出しできます。確認待ちは、進捗の「回答済み」に含みません。
                    </p>
                    <div class="mt-6 flex justify-end">
                        <AppButton @click="dialog = null">はじめる</AppButton>
                    </div>
                </template>

                <template v-else-if="dialog === 'review'">
                    <h2 class="text-xl font-bold text-ink-900">
                        ヒアリング内容を確認
                    </h2>
                    <p class="mt-2 text-base text-ink-900/70">
                        回答済み・確認待ち・未回答を整理しました。途中の状態でも書き出せます。
                    </p>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-md bg-ink-900/10 p-3">
                            <strong class="block text-2xl">{{
                                counts.answered
                            }}</strong>
                            <span class="text-sm text-ink-900/60"
                                >回答済み / {{ allFields.length }}</span
                            >
                        </div>
                        <div class="rounded-md bg-ink-900/10 p-3">
                            <strong class="block text-2xl">{{
                                review.waiting
                            }}</strong>
                            <span class="text-sm text-ink-900/60">確認待ち</span>
                        </div>
                        <div class="rounded-md bg-ink-900/10 p-3">
                            <strong class="block text-2xl">{{
                                review.missingCore
                            }}</strong>
                            <span class="text-sm text-ink-900/60"
                                >重点項目の未回答</span
                            >
                        </div>
                    </div>
                    <p class="mt-4 text-base text-ink-900/70">
                        {{
                            review.items.length
                                ? '次のヒアリングで確認したい項目です。項目を選ぶと入力欄へ移動します。'
                                : '重点項目の確認ができています。合意した内容と次のアクションを最後に読み合わせましょう。'
                        }}
                    </p>
                    <ul class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                        <li v-for="item in review.items" :key="item.field.id">
                            <button
                                type="button"
                                class="flex w-full items-center justify-between rounded-md border border-ink-900/10 px-3 py-2 text-left text-base hover:bg-ink-900/5"
                                :data-jump="item.field.id"
                                @click="jumpTo(item.field.id)"
                            >
                                {{ item.field.label }}
                                <span class="text-sm text-ink-900/40"
                                    >{{ item.reason }} →</span
                                >
                            </button>
                        </li>
                    </ul>
                    <div class="mt-6 flex justify-end gap-2">
                        <button
                            type="button"
                            :class="buttonLight"
                            @click="dialog = null"
                        >
                            入力に戻る
                        </button>
                        <AppButton class="!px-4 !py-2" @click="printFromReview"
                            >PDF / 印刷</AppButton
                        >
                    </div>
                </template>

                <template v-else-if="dialog === 'reset'">
                    <h2 class="text-xl font-bold text-ink-900">
                        入力内容をリセットしますか？
                    </h2>
                    <p class="mt-3 text-base text-ink-900/70">
                        このシートの回答と確認待ちをすべて消去します。必要な場合は、先にJSON保存でバックアップしてください。
                    </p>
                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            :class="buttonLight"
                            @click="dialog = null"
                        >
                            キャンセル
                        </button>
                        <button
                            type="button"
                            :class="buttonLight"
                            @click="downloadJson"
                        >
                            JSON保存
                        </button>
                        <AppButton
                            class="!px-4 !py-2"
                            data-confirm="reset"
                            @click="applyReplace('reset')"
                            >リセットする</AppButton
                        >
                    </div>
                </template>

                <template
                    v-else-if="dialog === 'sample' || dialog === 'import'"
                >
                    <h2 class="text-xl font-bold text-ink-900">
                        {{
                            dialog === 'sample'
                                ? 'サンプルを表示する'
                                : '保存したシートを読み込む'
                        }}
                    </h2>
                    <p class="mt-3 text-base text-ink-900/70">
                        {{
                            dialog === 'sample'
                                ? '架空の工務店のヒアリング例に置き換えます。'
                                : '現在の入力内容を、JSONファイルの内容に置き換えます。'
                        }}
                    </p>
                    <p class="mt-2 text-base text-ink-900/70">
                        現在の内容を残す場合は、先に「JSON保存」をしてください。
                    </p>
                    <div class="mt-6 flex flex-wrap justify-end gap-2">
                        <button
                            type="button"
                            :class="buttonLight"
                            @click="dialog = null"
                        >
                            キャンセル
                        </button>
                        <button
                            type="button"
                            :class="buttonLight"
                            @click="downloadJson"
                        >
                            JSON保存
                        </button>
                        <AppButton
                            class="!px-4 !py-2"
                            data-confirm="replace"
                            @click="applyReplace(dialog)"
                            >置き換える</AppButton
                        >
                    </div>
                </template>
            </div>
        </Modal>

        <div
            v-if="toastMessage"
            class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-md bg-ink-900 px-4 py-3 text-base text-white shadow-lg"
            role="status"
            aria-live="polite"
        >
            {{ toastMessage }}
        </div>
    </PublicLayout>
</template>

<style>
#hearing-print-area {
    display: none;
}

@media print {
    body * {
        visibility: hidden;
    }

    #hearing-print-area,
    #hearing-print-area * {
        visibility: visible;
    }

    #hearing-print-area {
        display: block;
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
    }

    #hearing-print-area .hearing-paper {
        border: 0;
        padding: 0;
        font-size: 10.5pt;
    }

    #hearing-print-area .paper-row {
        break-inside: avoid;
    }

    #hearing-print-area .paper-section {
        break-inside: auto;
    }
}
</style>
