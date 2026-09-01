<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="mt-8 rounded-lg border border-green-100 bg-gradient-to-br from-green-50 via-white to-white px-6 py-8 text-center sm:px-12"
    >
        <p class="text-lg font-bold text-gray-900">
            Webサイトに必要なものをまとめて準備
        </p>
        <p class="mt-2 text-sm text-gray-600">
            サイト名・ロゴ・業種を入力するだけ。favicon・meta
            タグ・OGP画像をまとめて生成します。
        </p>
        <Link
            href="/website-starter"
            class="mt-4 inline-block rounded-md bg-gradient-to-r from-[#62b324] to-[#139744] px-6 py-2 text-sm font-medium text-white shadow-sm transition hover:brightness-110"
        >
            Webサイトを準備する
        </Link>
    </div>
</template>
