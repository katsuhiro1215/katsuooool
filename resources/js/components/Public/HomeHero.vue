<script setup lang="ts">
defineProps<{
    toolCount: number;
    categoryCount: number;
}>();
</script>

<template>
    <section
        class="relative overflow-hidden bg-ink-900 px-6 py-14 text-white sm:py-16"
    >
        <div
            class="pointer-events-none absolute inset-0 opacity-[0.07]"
            style="
                background-image: radial-gradient(
                    rgba(255, 255, 255, 0.8) 1px,
                    transparent 1px
                );
                background-size: 20px 20px;
            "
        ></div>
        <div
            class="pointer-events-none absolute -left-24 -top-24 h-80 w-80 rounded-full bg-gold-400/25 blur-[90px]"
        ></div>
        <div
            class="pointer-events-none absolute -bottom-32 right-0 h-96 w-96 rounded-full bg-sprout-500/20 blur-[100px]"
        ></div>

        <div class="relative mx-auto max-w-3xl">
            <span
                class="inline-flex items-center gap-2 rounded-full border border-gold-400/30 bg-gold-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-gold-300"
            >
                SmartSprouts Toolkit
            </span>
            <h1 class="mt-4 text-3xl font-bold tracking-tight sm:text-4xl">
                Web制作を、もっと軽やかに。
            </h1>
            <p class="mt-3 max-w-xl text-sm text-white/60 sm:text-base">
                画像・色・CSS・SEOまで、現場でそのまま使える実用ツールを無料で。
                不完全でもまず試せる、育てていくツール集です。
            </p>

            <div class="mt-8 grid grid-cols-2 gap-3 sm:max-w-md">
                <div
                    class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                >
                    <p class="text-2xl font-bold text-gold-300">
                        {{ toolCount }}
                    </p>
                    <p class="mt-1 text-xs text-white/50">公開中のツール</p>
                </div>
                <div
                    class="rounded-xl border border-white/10 bg-white/5 p-4 backdrop-blur"
                >
                    <p class="text-2xl font-bold text-sprout-400">
                        {{ categoryCount }}
                    </p>
                    <p class="mt-1 text-xs text-white/50">カテゴリ</p>
                </div>
            </div>
        </div>
    </section>
</template>
