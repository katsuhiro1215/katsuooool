<script setup lang="ts">
import { HEARING_STEPS } from '@/lib/hearing/questions';
import {
    allFields,
    answerText,
    dateText,
    hasAnswer,
    isAnswered,
    stepFields,
    visibleFields
    
} from '@/lib/hearing/sheet';
import type {SheetState} from '@/lib/hearing/sheet';

defineProps<{ state: SheetState; hideEmpty: boolean }>();
</script>

<template>
    <article
        class="hearing-paper border border-gray-300 bg-white p-6 text-base leading-relaxed text-gray-900 sm:p-10"
    >
        <p class="text-sm font-semibold tracking-widest text-gray-500">
            KATSUOOOOL <span class="ml-2">PROJECT DISCOVERY</span>
        </p>
        <h2 class="mt-3 text-2xl font-bold">ヒアリングシート</h2>
        <p class="mt-4 text-xl font-bold">
            {{ state.answers.project || 'プロジェクト名未入力' }}
        </p>
        <p class="mt-1 text-gray-600">
            {{
                state.answers.company
                    ? `${state.answers.company} 御中`
                    : '会社名を入力すると、ここに表示されます。'
            }}
        </p>
        <p class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600">
            <span>ヒアリング日：{{ state.answers.hearingDate || '未定' }}</span>
            <span>更新：{{ dateText(state) }}</span>
            <span
                >回答
                {{ allFields.filter((f) => isAnswered(state, f)).length }} /
                {{ allFields.length }}</span
            >
        </p>

        <template v-for="(step, i) in HEARING_STEPS" :key="step.title">
            <section
                v-if="visibleFields(state, i, hideEmpty).length"
                class="paper-section mt-8"
            >
                <h3
                    class="flex items-baseline gap-3 border-b-2 border-gray-900 pb-2 text-lg font-bold"
                >
                    <span class="text-sm text-gray-500">0{{ i + 1 }}</span>
                    {{ step.title }}
                    <small class="ml-auto text-sm font-normal text-gray-500">
                        {{
                            stepFields(i).filter((f) => isAnswered(state, f))
                                .length
                        }}
                        / {{ stepFields(i).length }}
                    </small>
                </h3>
                <dl>
                    <div
                        v-for="f in visibleFields(state, i, hideEmpty)"
                        :key="f.id"
                        class="paper-row grid gap-1 border-b border-gray-200 py-3 sm:grid-cols-[12rem_1fr] sm:gap-4"
                    >
                        <dt class="text-sm font-semibold text-gray-600">
                            {{ f.label }}
                        </dt>
                        <dd
                            class="whitespace-pre-wrap break-words text-base"
                            :class="
                                state.pending[f.id]
                                    ? 'font-medium text-amber-700'
                                    : !hasAnswer(state, f)
                                      ? 'text-gray-400'
                                      : ''
                            "
                        >
                            {{ answerText(state, f) }}
                        </dd>
                    </div>
                </dl>
            </section>
        </template>

        <p
            class="mt-8 flex flex-wrap justify-between gap-2 border-t border-gray-300 pt-4 text-xs text-gray-500"
        >
            <span>katsuooool / HEARING SHEET</span>
            <span>制作の、はじめの一歩。</span>
        </p>
    </article>
</template>
