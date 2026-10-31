<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="relative mt-16 overflow-hidden rounded-xl bg-ink-900 px-6 py-8 text-center text-white shadow-lg sm:px-12"
    >
        <div
            class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-gold-400/20 blur-2xl"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-12 -left-12 h-40 w-40 rounded-full bg-gold-400/10 blur-2xl"
        ></div>

        <p class="relative text-lg font-bold">もっと便利に使いたい方へ</p>
        <p class="relative mt-2 text-sm text-white/70">
            Premiumならバッチ処理の上限アップ・利用履歴の閲覧ができます。
        </p>
        <Link
            href="/premium"
            class="relative mt-4 inline-block rounded-md bg-gradient-to-r from-gold-400 via-gold-300 to-gold-400 px-6 py-2 text-sm font-semibold text-ink-900 shadow-sm transition hover:brightness-105"
        >
            Premiumを見る
        </Link>
    </div>
</template>
