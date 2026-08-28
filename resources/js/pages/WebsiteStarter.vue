<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';

// NOTE: This is a placeholder page created only to satisfy Inertia's
// testing-time component-existence check for `website-starter.show`
// (see tests/Feature/Tools/WebsiteStarterControllerTest.php). The actual
// UI (form, props wiring, result display) is implemented in a later task.
</script>

<template>
    <PublicLayout>
        <Head title="Webサイトスターターキット" />

        <PageMain narrow>
            <PageHeader
                title="Webサイトスターターキット"
                description="サイト名・ロゴ・業種を入力するだけで、favicon・meta タグ・OGP画像をまとめて生成します。"
            />
        </PageMain>
    </PublicLayout>
</template>
