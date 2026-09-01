<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative mt-16 overflow-hidden rounded-lg bg-gradient-to-br from-[#a7cc0a] via-[#62b324] to-[#139744] px-6 py-8 text-center text-white shadow-lg sm:px-12"
    >
        <div
            class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-white/10 blur-2xl"
        ></div>

        <p class="relative text-lg font-bold">もっと便利に使いたい方へ</p>
        <p class="relative mt-2 text-sm text-white/90">
            Premiumならバッチ処理の上限アップ・利用履歴の閲覧ができます。
        </p>
        <Link
            href="/premium"
            class="relative mt-4 inline-block rounded-md bg-white px-6 py-2 text-sm font-semibold text-[#139744] shadow-sm transition hover:bg-green-50"
        >
            Premiumを見る
        </Link>
    </div>
</template>
