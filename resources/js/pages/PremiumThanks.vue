<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="ご登録ありがとうございます - katsuooool" />

    <div class="flex min-h-screen items-center justify-center bg-gray-50 px-6">
        <div class="max-w-md text-center">
            <h1 class="text-2xl font-bold text-gray-900">
                Premiumへのご登録ありがとうございます
            </h1>
            <p class="mt-4 text-sm text-gray-600">
                決済が完了しました。反映まで数秒かかる場合があります。
            </p>
            <Link
                href="/"
                class="mt-6 inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
            >
                トップに戻る
            </Link>
        </div>
    </div>
</template>
