# 公開ページ共通ヘッダー/フッター(PublicHeader/PublicFooter)設計書

## 背景・目的

katsuoooolの公開ページ(Home・About・Service・FAQ・Contact・Blog・legal系など)は、それぞれが独自にヘッダーブロックを持ち、フッターは共通の`AppFooter.vue`(12ページで使用)だが内容がシンプルすぎる。また調査の過程で以下2点が判明した:

- `resources/js/components/Public/PublicHeader.vue`・`PublicFooter.vue`・`resources/js/layouts/Public/PublicLayout.vue`はすでにファイルとして用意されているが、いずれも空のスキャフォールドで実装されていない。
- `resources/js/layouts/GuestLayout.vue`が削除された状態(未コミット)で放置されており、これをimportする`auth/Login.vue`・`Register.vue`・`ForgotPassword.vue`・`ResetPassword.vue`・`ConfirmPassword.vue`・`VerifyEmail.vue`の6ページが壊れている(ビルドエラーになる)。

本設計では、共通ヘッダー・フッターを実装し、まずHome.vueに適用する。残り約11ページへの展開は別途サブプロジェクトBとして扱う(本設計のスコープ外)。GuestLayout.vueの復旧もあわせて行う。

## スコープ

- `PublicHeader.vue`・`PublicFooter.vue`・`PublicLayout.vue`の実装
- `ApplicationLogo.vue`を`public/upload/logo.svg`に置き換え
- `GuestLayout.vue`の復旧(バグ修正)
- `Home.vue`を`PublicLayout`に載せ替え、ツール一覧下にPremiumバナーを追加
- 以下は対象外(サブプロジェクトB、または別途ブレスト):
  - About/Service/FAQ/Contact/Blog/legal系ページへの`PublicLayout`展開
  - 特定商取引法ページのフッターへの追加
  - Admin側UI

## データ・状態

新規のデータモデル変更はなし。ログイン状態の判定は既存の共有Inertia props(`app/Http/Middleware/HandleInertiaRequests.php`が提供する`auth.user`)をそのまま使う。

## コンポーネント設計

### `ApplicationLogo.vue`

```vue
<template>
    <img src="/upload/logo.svg" alt="katsuooool" class="h-8 w-auto" />
</template>
```

既存の呼び出し側(GuestLayout.vue)はSVGインラインの`fill-current`前提クラス(`class="h-20 w-20 fill-current text-gray-500"`)を渡していたが、`<img>`タグに合わせて`class="h-12 w-auto"`程度のサイズ指定のみに変更する。

### `PublicHeader.vue`

- **左側**: ロゴ(`ApplicationLogo`、`h-8`)+「katsuooool」(見出し)+「Web制作に役立つ実用ツール集」相当のキャッチコピー(小さめ、`hidden sm:inline`でモバイルでは省略可)。
- **右側**(左から順に):
  1. 「Premium」リンク(`/premium`)
  2. ログイン状態による分岐:
     - 未ログイン: 「ログイン」(`route('login')`)・「新規登録」(`route('register')`)の2リンク
     - ログイン中: `Dropdown`(`@/components/Dropdown.vue`、既存の認証済みレイアウトと同じ再利用)+ `DropdownLink`(`@/components/DropdownLink.vue`)によるユーザーメニュー。トリガーはログイン中ユーザーの名前(`$page.props.auth.user?.name`)+シェブロンアイコン(`AuthenticatedLayout.vue`と同じSVGパターン)。中身は「マイページ」(`route('mypage.index')`)・「ログアウト」(`route('logout')`、`method="post"` `as="button"`)。
  3. 会社情報ドロップダウン(`Dropdown`、`align="right"`)。トリガーは「その他」テキスト+シェブロンアイコン(既存の`AuthenticatedLayout.vue`のユーザーメニュートリガーと同じSVGパターンを再利用)。中身:
     - かつコード(外部リンク、`https://katsucode.jp/`、`target="_blank"`)
     - katsulog(外部リンク、`https://katsu-coach.com/`、`target="_blank"`)
     - SmartSprouts(外部リンク、`https://smartsprouts.jp/`、`target="_blank"`)
     - お問い合わせ(内部リンク、`/contact`)

構造は`<header class="border-b border-gray-200 bg-white"><div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">...</div></header>`。既存の`Home.vue`ヘッダーが`py-10`だったのに対し、ロゴ入りの通常ヘッダーとして`py-4`程度に抑える(検索窓・タブはHome.vue側の`<main>`に残るため、ヘッダー自体は薄くしてよい)。

### `PublicFooter.vue`

4カラムのFooterNav+コピーライトの構成。

```
Nav①: About(/about) / Service(/service) / 料金案内(/premium) / FAQ(/faq)
Nav②: かつコード(https://katsucode.jp/, 外部) / katsulog(https://katsu-coach.com/, 外部) / SmartSprouts(https://smartsprouts.jp/, 外部)
Nav③: プライバシーポリシー(/legal/privacy-policy) / 利用規約(/legal/terms) / クッキーポリシー(/legal/cookie-policy)
Nav④: 会社案内(/about、Nav①のAboutと同一ページ) / お問い合わせ(/contact) / ブログ・お知らせ(/blog)
```

コピーライト行: `© {{ currentYear }} katsuooool`(既存`AppFooter.vue`の`currentYear`ロジックをそのまま踏襲)。

レイアウトは`grid grid-cols-2 gap-8 sm:grid-cols-4`程度で4カラムを横並びに、モバイルでは2カラム折返し。既存の`AppFooter.vue`は本設計後も他11ページで使われ続けるため削除しない(サブプロジェクトBで順次`PublicFooter`に置き換える)。

### `PublicLayout.vue`

既存の骨格(`PublicHeader`→`<slot />`→`PublicFooter`)をそのまま活かす。`<div class="min-h-screen bg-white dark:bg-gray-900">`は、プロジェクトがダークモードを他ページで使っていないため`dark:`系クラスは削除し、既存ページと同じ`bg-gray-50`基調に合わせる。

### `GuestLayout.vue`(復旧)

削除前の内容をベースに、importパスの大文字小文字を現行規則に合わせて復旧する:

```vue
<script setup lang="ts">
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="flex min-h-screen flex-col items-center bg-gray-100 pt-6 sm:justify-center sm:pt-0"
    >
        <div>
            <Link href="/">
                <ApplicationLogo class="h-12 w-auto" />
            </Link>
        </div>

        <div
            class="mt-6 w-full overflow-hidden bg-white px-6 py-4 shadow-md sm:max-w-md sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
```

(`dark:`系クラスは他ページに合わせ削除)

### `PremiumBanner.vue`(新規)

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="mt-16 rounded-lg bg-gray-900 px-6 py-8 text-center text-white sm:px-12"
    >
        <p class="text-lg font-bold">
            もっと便利に使いたい方へ
        </p>
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
```

`resources/js/components/tools/`ではなく`resources/js/components/`直下(ツール専用コンポーネントではないため)。

### `Home.vue`の変更

- `<header>`ブロックを削除し、`PublicLayout`でラップする形に変更。検索窓・カテゴリタブは`PublicLayout`の`<slot />`内、`<main>`相当の位置に残す(見出し「katsuooool」とキャッチコピーはヘッダー側に移動するため、Home.vue独自の`<h1>`は不要になる)。
- 既存の`max-w-7xl`コンテナ構成は維持。
- ツール一覧(`grid`)の直後に`<PremiumBanner />`を追加。
- `AppFooter`のimportを削除し、`PublicLayout`が内部で`PublicFooter`を描画するためHome.vue側でのフッター記述自体が不要になる。

## エラーハンドリング・エッジケース

- 未ログイン時に`route('mypage.index')`等の認証必須ルートへのリンクを組み立てない(条件分岐で除外)。
- 会社情報ドロップダウンの外部リンクは`target="_blank" rel="noopener noreferrer"`を付与する(タブジャッキング対策、他の外部リンクがないため新規に気をつける点)。
- SmartSprouts側の「Spra」という名称は、ユーザーの説明によれば同一システムの本番未リリース機能を指す別名であり、今回はリンク自体を追加しない(将来別途追加)。

## テスト方針

- `PublicHeader.test.ts`(Vitest + `@vue/test-utils`): 未ログイン時に「ログイン」「新規登録」が表示されること、ログイン時にユーザーメニューが表示されログイン/登録リンクが表示されないこと、外部リンクの`href`と`target="_blank"`を検証。
- `PublicFooter.test.ts`: 4つのNavグループの各リンクの`href`を検証。
- `tests/Feature/HomeControllerTest.php`の既存テストが壊れていないことを確認(回帰)。
- Playwrightで実ブラウザ確認: 未ログイン/ログイン中それぞれでのヘッダー表示、フッター全リンクのクリック確認、`/login`ページ(GuestLayout復旧後)の表示崩れがないことの確認。

## 影響範囲まとめ

- 変更: `resources/js/components/ApplicationLogo.vue`
- 変更: `resources/js/components/Public/PublicHeader.vue`
- 変更: `resources/js/components/Public/PublicFooter.vue`
- 変更: `resources/js/layouts/Public/PublicLayout.vue`
- 新規: `resources/js/layouts/GuestLayout.vue`(復旧)
- 新規: `resources/js/components/PremiumBanner.vue`
- 変更: `resources/js/pages/Home.vue`
- 新規: `resources/js/components/Public/PublicHeader.test.ts`
- 新規: `resources/js/components/Public/PublicFooter.test.ts`
