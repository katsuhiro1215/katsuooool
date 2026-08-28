<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

interface LegalSection {
    heading: string;
    paragraphs: string[];
}

const SECTIONS: LegalSection[] = [
    {
        heading: '1. 基本方針',
        paragraphs: [
            '【事業者名を入力してください】(以下「当社」といいます)は、当社が提供するWeb制作者向けツール集サイト「katsuooool」(以下「本サービス」といいます)における、利用者の個人情報の取り扱いについて、以下のとおりプライバシーポリシー(以下「本ポリシー」といいます)を定めます。',
        ],
    },
    {
        heading: '2. 取得する情報',
        paragraphs: [
            'アカウント情報: 会員登録(Premiumプランへの登録を含む)の際に、氏名・メールアドレス・パスワード(ハッシュ化して保存)をご提供いただきます。',
            'アップロードファイル: 画像圧縮などの各ツールにアップロードされたファイルは、処理のために一時的にサーバーへ保存され、一定時間の経過後に自動的に削除されます。',
            '利用ログ: 各ツールの利用状況(ツール名・処理件数・処理日時など)を記録します。このとき、IPアドレスは日次のソルト値を用いてハッシュ化した状態で保存し、元のIPアドレスは保存しません。',
            '決済情報: Premiumプランのお申し込みの際、クレジットカード情報を含む決済情報は決済代行会社であるStripe, Inc.が直接取り扱い、当社のサーバーには保存されません。',
        ],
    },
    {
        heading: '3. 利用目的',
        paragraphs: [
            '本サービスの提供・維持・改善のため。',
            'Premiumプランの契約管理・料金請求のため。',
            'ツールの利用状況を集計し、サービス改善の参考にするため。',
            '不正利用の防止、利用規約に違反する行為への対応のため。',
            'お問い合わせへの対応のため。',
        ],
    },
    {
        heading: '4. Cookieの使用について',
        paragraphs: [
            '本サービスは、ログイン状態の維持やセキュリティ確保のためにCookieを使用します。詳細は「クッキーポリシー」をご覧ください。',
        ],
    },
    {
        heading: '5. 第三者提供',
        paragraphs: [
            '当社は、法令に基づく場合を除き、あらかじめ利用者の同意を得ることなく、個人情報を第三者に提供することはありません。ただし、決済処理をStripe, Inc.に委託する場合や、お問い合わせフォームの内容(氏名・メールアドレス・IPアドレス等)を問い合わせ管理システム(Spra)に送信する場合など、本サービスの提供に必要な範囲で外部事業者に取り扱いを委託する場合があります。',
        ],
    },
    {
        heading: '6. データの保存期間',
        paragraphs: [
            'アップロードファイルは処理完了後、一定時間(数時間〜24時間程度)の経過をもって自動的に削除されます。',
            '利用ログは取得から概ね30日を目安に自動的に削除されます。',
            'アカウント情報は、退会手続きが完了するまで保存されます。',
        ],
    },
    {
        heading: '7. 開示・訂正・削除等のご請求',
        paragraphs: [
            '利用者は、当社の定める手続きに従い、当社が保有する自己の個人情報の開示・訂正・削除等を請求することができます。ご請求は下記お問い合わせ窓口までご連絡ください。',
        ],
    },
    {
        heading: '8. 本ポリシーの変更',
        paragraphs: [
            '当社は、必要に応じて本ポリシーの内容を変更することがあります。変更後のプライバシーポリシーは、本ページに掲載した時点から効力を生じるものとします。',
        ],
    },
    {
        heading: '9. お問い合わせ窓口',
        paragraphs: ['【メールアドレス等の連絡先を入力してください】'],
    },
];
</script>

<template>
    <Head title="プライバシーポリシー - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader title="プライバシーポリシー" description="" narrow />

            <PageMain narrow>
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
            </PageMain>
        </div>
    </PublicLayout>
</template>
