<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="mt-16 rounded-lg bg-gray-900 px-6 py-8 text-center text-white sm:px-12"
    >
        <p class="text-lg font-bold">もっと便利に使いたい方へ</p>
        <p class="mt-2 text-sm text-gray-300">
            Premiumならバッチ処理の上限アップ・利用履歴の閲覧ができます。
        </p>
        <Link
            href="/premium"
            class="mt-4 inline-block rounded-md bg-white px-6 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100"
        >
            Premiumを見る
        </Link>
    </div>
</template>
