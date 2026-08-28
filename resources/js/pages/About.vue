<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
</script>

<template>
    <Head title="About - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader title="About" description="" narrow />

            <PageMain narrow>
                <div
                    class="space-y-6 rounded-lg border border-gray-200 bg-white p-6 text-sm leading-relaxed text-gray-700"
                >
                    <p>
                        katsuoooolは、Web制作に携わる方向けの実用ツール集サイトです。画像圧縮やカラーパレット生成、CSSジェネレーターなど、日々の制作作業でよく使う小さなツールを、ブラウザから会員登録なしですぐに使える形で提供しています。
                    </p>
                    <p>
                        「ちょっとした変換や生成のために、いちいちアプリを開いたり環境を構築したりするのが面倒」という制作者自身の実感から生まれたサイトです。派手な機能を詰め込むよりも、必要な処理を最短の手順で終えられることを大切にしています。
                    </p>
                    <p>
                        無料でご利用いただけるツールを中心に据えつつ、より多くのファイルをまとめて処理したい方向けにPremiumプランもご用意しています。詳しくは<a
                            href="/premium"
                            class="underline hover:text-gray-900"
                            >Premiumページ</a
                        >をご覧ください。
                    </p>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
