<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppFooter from '@/components/AppFooter.vue';
</script>

<template>
    <Head title="About - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">About</h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
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
        </main>

        <AppFooter />
    </div>
</template>
