<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import type { Company } from '@/types';

const props = defineProps<{
    company: Company;
}>();

interface LegalSection {
    heading: string;
    paragraphs: string[];
}

const companyName = computed(
    () => props.company.name ?? '【事業者名を入力してください】',
);

const SECTIONS = computed<LegalSection[]>(() => [
    {
        heading: '第1条(適用)',
        paragraphs: [
            `本利用規約(以下「本規約」といいます)は、${companyName.value}(以下「当社」といいます)が提供するWeb制作者向けツール集サイト「katsuooool」(以下「本サービス」といいます)の利用条件を定めるものです。利用者は、本サービスを利用することにより、本規約に同意したものとみなします。`,
        ],
    },
    {
        heading: '第2条(サービス内容)',
        paragraphs: [
            '本サービスは、画像圧縮・カラーパレット生成などのWeb制作向けツールを提供します。一部の機能は無料でご利用いただけますが、Premiumプラン(有料)にご登録いただくことで、追加の機能・処理件数の拡大などをご利用いただけます。',
        ],
    },
    {
        heading: '第3条(利用登録)',
        paragraphs: [
            'Premiumプランのお申し込みなど一部の機能のご利用には、会員登録が必要です。利用者は、真実かつ正確な情報を登録するものとします。',
            '当社は、登録内容に虚偽がある場合、その他当社が利用登録を適当でないと判断した場合、利用登録を拒否または取り消すことがあります。',
        ],
    },
    {
        heading: '第4条(禁止事項)',
        paragraphs: [
            '利用者は、本サービスの利用にあたり、以下の行為をしてはなりません。',
            '法令または公序良俗に違反する行為。',
            '本サービスのサーバーやネットワークの機能を破壊・妨害する行為(過度な連続アクセス等を含む)。',
            '当社または第三者の知的財産権、プライバシー等の権利を侵害する行為。',
            '違法なコンテンツ、第三者の権利を侵害するファイルのアップロード。',
            'その他、当社が不適切と判断する行為。',
        ],
    },
    {
        heading: '第5条(知的財産権)',
        paragraphs: [
            '本サービスに関する著作権・商標権その他の知的財産権は、当社または正当な権利を有する第三者に帰属します。利用者が本サービスを利用してアップロード・生成したファイルの権利は、法令に反しない限り利用者に帰属します。',
        ],
    },
    {
        heading: '第6条(料金・決済)',
        paragraphs: [
            'Premiumプランの料金は、本サービス上に表示する金額(税込)とします。決済はStripe, Inc.を通じたクレジットカード決済により行われ、ご登録と同時に課金が開始されます。無料トライアルはなく、既にお支払いいただいた料金の返金は行いません。',
            '解約はいつでも可能で、解約後もお支払い済みの期間終了まではPremiumプランの機能をご利用いただけます。',
        ],
    },
    {
        heading: '第7条(免責事項)',
        paragraphs: [
            '当社は、本サービスに事実上または法律上の瑕疵(安全性・信頼性・正確性・完全性・有効性・特定の目的への適合性等に関する欠陥を含みます)がないことを明示的にも黙示的にも保証しません。',
            '当社は、本サービスに起因して利用者に生じたあらゆる損害について、当社の故意または重過失による場合を除き、一切の責任を負いません。',
        ],
    },
    {
        heading: '第8条(サービス内容の変更・中断・終了)',
        paragraphs: [
            '当社は、利用者への事前の通知なく、本サービスの内容を変更、または提供を中断・終了することができるものとします。これによって利用者に生じた損害について、当社は一切の責任を負いません。',
        ],
    },
    {
        heading: '第9条(利用規約の変更)',
        paragraphs: [
            '当社は、必要と判断した場合には、利用者への事前の通知なく本規約を変更することができるものとします。変更後の規約は、本サービス上に掲載した時点から効力を生じるものとします。',
        ],
    },
    {
        heading: '第10条(準拠法・裁判管轄)',
        paragraphs: [
            '本規約の解釈にあたっては、日本法を準拠法とします。本サービスに関して紛争が生じた場合には、当社の本店所在地を管轄する裁判所を専属的合意管轄とします。',
        ],
    },
]);
</script>

<template>
    <Head title="利用規約 - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader title="利用規約" description="" narrow />

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
