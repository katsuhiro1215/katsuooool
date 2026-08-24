<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

interface LegalSection {
    heading: string;
    paragraphs: string[];
}

const SECTIONS: LegalSection[] = [
    {
        heading: '1. Cookieとは',
        paragraphs: [
            'Cookieとは、Webサイトを閲覧した際に、利用者のブラウザに保存される小さなテキストファイルです。Webサイトは、Cookieを通じて利用者のブラウザを識別し、ログイン状態の維持などに利用します。',
        ],
    },
    {
        heading: '2. 当サイトが使用するCookie',
        paragraphs: [
            'セッションCookie: ログイン状態の維持、フォーム送信時のセキュリティ確保(CSRF対策)など、本サービスの動作に必要な範囲で使用します。',
            '当サイトでは、広告配信や第三者による行動追跡を目的としたCookieは使用していません。',
        ],
    },
    {
        heading: '3. Cookieの管理方法',
        paragraphs: [
            '利用者は、ブラウザの設定によりCookieの受け入れを拒否したり、既に保存されたCookieを削除したりすることができます。ただし、Cookieを無効化した場合、ログインが必要な機能など、本サービスの一部がご利用いただけなくなることがあります。',
        ],
    },
    {
        heading: '4. 本ポリシーの変更',
        paragraphs: [
            '当社は、必要に応じて本ポリシーの内容を変更することがあります。変更後の内容は、本ページに掲載した時点から効力を生じるものとします。',
        ],
    },
];
</script>

<template>
    <Head title="クッキーポリシー - katsuooool" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-3xl px-6 py-10">
                <a href="/" class="text-sm text-gray-500 hover:text-gray-700"
                    >← トップに戻る</a
                >
                <h1 class="mt-2 text-2xl font-bold text-gray-900">
                    クッキーポリシー
                </h1>
            </div>
        </header>

        <main class="mx-auto max-w-3xl px-6 py-10">
            <div
                class="space-y-8 rounded-lg border border-gray-200 bg-white p-6"
            >
                <section v-for="section in SECTIONS" :key="section.heading">
                    <h2 class="text-sm font-semibold text-gray-900">
                        {{ section.heading }}
                    </h2>
                    <p
                        v-for="(paragraph, index) in section.paragraphs"
                        :key="index"
                        class="mt-2 text-sm leading-relaxed text-gray-700"
                    >
                        {{ paragraph }}
                    </p>
                </section>
            </div>
        </main>
    </div>
</template>
