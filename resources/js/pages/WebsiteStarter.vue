<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { checkDescriptionLength } from '@/lib/seoTextCheck';

const props = defineProps<{
    industries: Record<string, string>;
    prefectures: string[];
    targetAgeRanges: Record<string, string>;
    targetGenders: Record<string, string>;
    purposes: Record<string, string>;
}>();

interface SuccessResponse {
    status: 'success';
    title: string;
    favicon_download_url: string;
    ogp_image_download_url: string;
    meta_tags: string;
    html_head: string;
}

interface FailedResponse {
    status: 'failed';
    reason: string;
}

type GenerateResponse = SuccessResponse | FailedResponse;

const siteName = ref('');
const siteUrl = ref('');
const industry = ref('');
const prefecture = ref('');
const targetAgeRange = ref('');
const targetGender = ref('');
const purpose = ref('');
const metaDescription = ref('');
const logoFile = ref<File | null>(null);

const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<GenerateResponse | null>(null);
const generatedTitle = ref('');
const copiedField = ref('');

const descriptionResult = computed(() =>
    checkDescriptionLength(metaDescription.value),
);

const canGenerate = computed(
    () =>
        !isProcessing.value &&
        siteName.value.trim() !== '' &&
        siteUrl.value.trim() !== '' &&
        industry.value !== '' &&
        prefecture.value !== '' &&
        metaDescription.value.trim() !== '' &&
        logoFile.value !== null,
);

function onLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    logoFile.value =
        target.files && target.files.length > 0 ? target.files[0] : null;
}

async function generate() {
    if (!canGenerate.value || !logoFile.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    response.value = null;

    const formData = new FormData();
    formData.append('site_name', siteName.value);
    formData.append('site_url', siteUrl.value);
    formData.append('industry', industry.value);
    formData.append('prefecture', prefecture.value);

    if (targetAgeRange.value) {
        formData.append('target_age_range', targetAgeRange.value);
    }

    if (targetGender.value) {
        formData.append('target_gender', targetGender.value);
    }

    if (purpose.value) {
        formData.append('purpose', purpose.value);
    }

    formData.append('meta_description', metaDescription.value);
    formData.append('logo', logoFile.value);

    try {
        const { data } = await axios.post<GenerateResponse>(
            '/website-starter',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        response.value = data;

        if (data.status === 'success') {
            generatedTitle.value = data.title;
        }
    } catch (error) {
        errorMessage.value =
            axios.isAxiosError(error) && error.response?.status === 422
                ? '入力内容をご確認ください。'
                : '生成に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}

async function copyText(text: string, field: string) {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) {
                copiedField.value = '';
            }
        }, 2000);
    } catch {
        // クリップボードAPIが使えない環境では何もしない
    }
}
</script>

<template>
    <Head title="Webサイトスターターキット - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Webサイトスターターキット"
                description="サイト名・ロゴ・業種を入力するだけで、favicon・meta タグ・OGP画像をまとめて生成します。"
                narrow
            />

            <PageMain narrow>
                <div class="space-y-4">
                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="site-name"
                            >サイト名</label
                        >
                        <input
                            id="site-name"
                            v-model="siteName"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="site-url"
                            >サイトURL</label
                        >
                        <input
                            id="site-url"
                            v-model="siteUrl"
                            type="url"
                            placeholder="https://example.com"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="industry"
                            >業種</label
                        >
                        <select
                            id="industry"
                            v-model="industry"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択してください</option>
                            <option
                                v-for="(label, key) in props.industries"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="prefecture"
                            >都道府県</label
                        >
                        <select
                            id="prefecture"
                            v-model="prefecture"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択してください</option>
                            <option
                                v-for="value in props.prefectures"
                                :key="value"
                                :value="value"
                            >
                                {{ value }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700"
                                for="target-age"
                                >ターゲット年代(任意)</label
                            >
                            <select
                                id="target-age"
                                v-model="targetAgeRange"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">選択しない</option>
                                <option
                                    v-for="(
                                        label, key
                                    ) in props.targetAgeRanges"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700"
                                for="target-gender"
                                >ターゲット性別(任意)</label
                            >
                            <select
                                id="target-gender"
                                v-model="targetGender"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">選択しない</option>
                                <option
                                    v-for="(label, key) in props.targetGenders"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="purpose"
                            >サイトの目的(任意)</label
                        >
                        <select
                            id="purpose"
                            v-model="purpose"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択しない</option>
                            <option
                                v-for="(label, key) in props.purposes"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="logo"
                            >ロゴ画像</label
                        >
                        <input
                            id="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 w-full text-sm"
                            @change="onLogoChange"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-sm font-medium text-gray-700"
                            for="meta-description"
                        >
                            サイトの説明(meta description)
                        </label>
                        <textarea
                            id="meta-description"
                            v-model="metaDescription"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            文字数: {{ descriptionResult.characterCount }}
                            <span
                                v-if="!descriptionResult.withinRecommended"
                                class="text-amber-600"
                            >
                                (推奨160字程度を超えています)
                            </span>
                        </p>
                    </div>

                    <p v-if="errorMessage" class="text-sm text-red-600">
                        {{ errorMessage }}
                    </p>

                    <AppButton
                        type="button"
                        class="generate-button w-full"
                        :disabled="!canGenerate"
                        @click="generate"
                    >
                        {{
                            isProcessing ? '生成しています...' : 'まとめて生成'
                        }}
                    </AppButton>
                </div>

                <div v-if="response" class="mt-8 space-y-6">
                    <template v-if="response.status === 'success'">
                        <div
                            class="rounded-md border border-gray-200 bg-white p-4"
                        >
                            <label
                                class="block text-sm font-medium text-gray-700"
                                for="generated-title"
                                >推奨title</label
                            >
                            <input
                                id="generated-title"
                                v-model="generatedTitle"
                                type="text"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(generatedTitle, 'title')"
                            >
                                {{
                                    copiedField === 'title'
                                        ? 'コピーしました'
                                        : 'コピー'
                                }}
                            </AppButton>
                        </div>

                        <div
                            class="rounded-md border border-gray-200 bg-white p-4"
                        >
                            <p class="text-sm font-medium text-gray-700">
                                favicon一式
                            </p>
                            <AppButton
                                as="a"
                                :href="response.favicon_download_url"
                                class="mt-2 inline-block"
                            >
                                まとめてダウンロード(ZIP)
                            </AppButton>
                        </div>

                        <div
                            class="rounded-md border border-gray-200 bg-white p-4"
                        >
                            <p class="text-sm font-medium text-gray-700">
                                OGP画像
                            </p>
                            <AppButton
                                as="a"
                                :href="response.ogp_image_download_url"
                                class="mt-2 inline-block"
                            >
                                ダウンロード
                            </AppButton>
                        </div>

                        <div
                            class="rounded-md border border-gray-200 bg-white p-4"
                        >
                            <p class="text-sm font-medium text-gray-700">
                                meta タグ
                            </p>
                            <pre
                                class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs"
                                >{{ response.meta_tags }}</pre>
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(response.meta_tags, 'meta')"
                            >
                                {{
                                    copiedField === 'meta'
                                        ? 'コピーしました'
                                        : 'コピー'
                                }}
                            </AppButton>
                        </div>

                        <div
                            class="rounded-md border border-gray-200 bg-white p-4"
                        >
                            <p class="text-sm font-medium text-gray-700">
                                HTML head一括コード
                            </p>
                            <pre
                                class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs"
                                >{{ response.html_head }}</pre>
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(response.html_head, 'head')"
                            >
                                {{
                                    copiedField === 'head'
                                        ? 'コピーしました'
                                        : 'コピー'
                                }}
                            </AppButton>
                        </div>

                        <div
                            class="rounded-lg bg-gray-900 px-6 py-8 text-center text-white"
                        >
                            <p class="text-base font-bold">
                                Webサイトの準備ができました
                            </p>
                            <p class="mt-2 text-sm text-gray-300">
                                サイト本体の制作についてもご相談いただけます。
                            </p>
                            <a
                                href="/contact"
                                class="mt-4 inline-block rounded-md bg-white px-6 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100"
                            >
                                制作のご相談はこちら
                            </a>
                        </div>
                    </template>
                    <p v-else class="text-sm text-red-600">
                        {{ response.reason }}
                    </p>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
