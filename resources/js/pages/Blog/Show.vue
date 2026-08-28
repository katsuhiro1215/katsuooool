<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { POST_TYPE_LABELS } from '@/lib/postType';

interface PostDetail {
    title: string;
    type: string;
    published_at: string;
    body_html: string;
}

const props = defineProps<{ post: PostDetail }>();

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('ja-JP');
}

const description = `${POST_TYPE_LABELS[props.post.type]} ・ ${formatDate(props.post.published_at)}`;
</script>

<template>
    <Head :title="`${post.title} - katsuooool`" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                :title="post.title"
                :description="description"
                narrow
                back-href="/blog"
                back-label="← お知らせ一覧に戻る"
            />

            <PageMain narrow>
                <div
                    class="prose prose-sm max-w-none rounded-lg border border-gray-200 bg-white p-6"
                    v-html="post.body_html"
                ></div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
