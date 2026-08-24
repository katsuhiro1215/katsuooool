# コンテンツ基盤(固定ページ・Blog・FAQ)設計

## 背景・目的

katsuoooolは「ツール集サイト」から、法務ページ・会社紹介・更新情報を備えた「プロダクトサイト」へと拡張を進めている。前段のフェーズで法務ページ4種(特商法・プライバシーポリシー・利用規約・クッキーポリシー)とトップページのレイアウト刷新(max-w-7xl・4列グリッド)を実装済み。

本フェーズでは、残りのコンテンツ基盤として以下を実装する:

- **About**(このツールについての紹介ページ)
- **Service**(ツールの内訳などサービス紹介ページ)
- **FAQ**(よくある質問)
- **Blog(News)**(バージョンアップ・不具合修正・お知らせを発信する機能。唯一DB駆動でAdmin管理を伴う)

Contact(SmartSproutsへのAPI連携)、ツール画面のUI改善、カラーパレット/ボタン集のwishlist投票機能は、本設計のスコープ外(それぞれ別途ブレインストーミングする)。

## スコープ

### 含むもの

- About・Service・FAQページ(静的、コード直接編集)
- Blog(News)機能: 公開側の一覧・詳細ページ、Admin側のフルCRUD管理画面
- 上記ページへの導線(AppFooter更新)

### 含まないもの

- About・Service・FAQのAdmin編集機能(静的ページとして実装するため)
- Blogのカテゴリ・タグ機能(「種別」バッジのみで足りると判断)
- コメント機能・RSSフィード
- Contact機能(別サブプロジェクト)

## 固定ページ(About・Service・FAQ)

法務ページ(`legal/Tokushoho.vue`等)と同じ実装パターンを踏襲する。

- `app/Http/Controllers/PageController.php`(新規)に`about()`・`service()`を追加し、それぞれ`Inertia::render('About')`・`Inertia::render('Service')`を返す
- `app/Http/Controllers/FaqController.php`(新規)に`index()`を追加し、`Inertia::render('Faq')`を返す
- ルート: `/about`(`page.about`)・`/service`(`page.service`)・`/faq`(`faq.index`)、いずれも認証不要
- Vueページ: `resources/js/pages/About.vue`・`Service.vue`・`Faq.vue`
  - About: ツールの紹介文(一般的な内容をこちらで作成。事業者固有の情報は含めない想定のため、法務ページのようなプレースホルダーは不要)
  - Service: 現在提供中のツールカテゴリ(Image/Color/CSS/Pattern/Generator/Developer/SEO/UI Pattern)の紹介。カテゴリ一覧は`resources/js/lib/toolFilter.ts`の`CATEGORY_TABS`を再利用してハードコードの重複を避ける
  - FAQ: `{ question: string; answer: string }[]`の配列をテンプレート内で`<details>`/`<summary>`によるアコーディオン表示。想定質問例(無料/Premiumの違い、対応ファイル形式、データの保存期間、退会方法など)

## Blog(News)

### データモデル

新規マイグレーション`create_posts_table`:

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| title | string | |
| slug | string unique | URLに使用(例: `v2-0-release`)。手入力(自動生成はしない、Admin側でtitleから生成するのはUXの改善として後回しにしてよい) |
| type | string | `update` / `fix` / `announcement` の3値のみ(Enumではなくstring + バリデーションの`in:`ルールで制御。将来の値追加を容易にするため) |
| body_markdown | text | Markdown形式の本文 |
| published_at | timestamp nullable | nullまたは未来日時の投稿は非公開(下書き)として扱う |
| created_at / updated_at | timestamp | |

`App\Models\Post`:
- `published()`ローカルスコープ: `whereNotNull('published_at')->where('published_at', '<=', now())`
- キャストは不要(型はstring/text/timestampのまま)

### Markdown→HTML変換

`league/commonmark`(既にcomposer.lockに間接依存として存在)を`composer.json`のrequireに直接追加する。`app/Services/MarkdownRenderer.php`(新規、`CommonMarkConverter`の薄いラッパー)で変換を行い、Blog詳細ページ表示時にサーバー側でHTMLへ変換して渡す(クライアント側でのMarkdownパースは行わない。XSS面でも変換をサーバー側に閉じ込める方が安全)。

### 公開側

- `app/Http/Controllers/BlogController.php`(新規)
  - `index()`: `Post::published()->orderByDesc('published_at')->paginate(10)`。一覧には`title`・`type`・`published_at`・冒頭の抜粋(Markdownの最初の1〜2行程度をプレーンテキスト化したもの)を表示
  - `show(string $slug)`: `Post::published()->where('slug', $slug)->firstOrFail()`。見つからない/非公開の場合は404(`firstOrFail()`が自動的にAbort 404を投げる)。`MarkdownRenderer`でHTML変換して渡す
- ルート: `/blog`(`blog.index`)・`/blog/{slug}`(`blog.show`)、認証不要
- Vueページ: `resources/js/pages/Blog/Index.vue`・`Blog/Show.vue`。種別に応じたバッジ色(update=青系、fix=赤系、announcement=グレー系)

### Admin側

- `app/Http/Controllers/Admin/PostController.php`(新規)、`auth:admin`ガード配下
  - `index()`: 全件(下書き含む)を`published_at`降順で一覧表示、ページネーション20件
  - `create()` / `store()`: 新規作成フォーム。バリデーション(`title`必須、`slug`必須・unique・英数ハイフンのみ、`type`は`in:update,fix,announcement`、`body_markdown`必須、`published_at`はnullable date)
  - `edit($post)` / `update($post)`: 編集フォーム(store同様のバリデーション、slugのuniqueルールは自身を除外)
  - `destroy($post)`: 削除(確認ダイアログはフロント側で実装)
- ルート(`routes/admin.php`に追加、`auth:admin`グループ内):
  ```php
  Route::get('posts', [PostController::class, 'index'])->name('posts.index');
  Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
  Route::post('posts', [PostController::class, 'store'])->name('posts.store');
  Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
  Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
  Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
  ```
- Vueページ: `resources/js/pages/admin/posts/Index.vue`・`Create.vue`・`Edit.vue`(既存の`admin/users/Index.vue`・`Show.vue`と同じ素のTailwindスタイルを踏襲)
- `AdminLayout.vue`の`NAV_ITEMS`に「投稿管理」(`admin.posts.index`、activePattern `admin.posts.*`)を追加
- Markdownのプレビュー機能(入力しながらHTMLプレビューを見る)は本フェーズのスコープ外(v1はシンプルなtextarea入力のみ)

## AppFooter更新

既存の`AppFooter.vue`(法務4リンク)に、About・Service・FAQ・Blogへのリンクを追加する。法務リンクと区別するため、2グループ(コンテンツ/法務)に分けて表示する。

## セキュリティ・データ方針

- Markdown→HTML変換はサーバー側(`league/commonmark`)で行い、CommonMarkのデフォルト設定(HTMLタグは無効化・エスケープ)をそのまま使用する。Admin(信頼できる入力者)のみが本文を投稿できるが、念のためデフォルトの安全側設定を維持する
- 下書き投稿(`published_at`がnullまたは未来)は、公開側の一覧・詳細のどちらからも一切参照できないようにする(スコープで一貫して絞り込む)
- 既存のAdmin認証(`auth:admin`ガード)をそのまま利用し、新規の認可ロジックは不要

## テスト方針

- 固定ページ(About/Service/FAQ): `LegalControllerTest`と同様、ルートが`assertSuccessful()`+ Inertiaコンポーネント名を検証するFeatureテスト
- Blog公開側: 公開済み投稿が一覧・詳細に表示されること、下書き投稿が一覧に出ないこと・詳細で404になることを検証するFeatureテスト
- Blog Admin側: 未認証/一般Userでは`auth:admin`により弾かれること、CRUD一連の動作(作成→一覧に反映→編集→削除)を検証するFeatureテスト
- `MarkdownRenderer`: Markdown→HTMLの基本的な変換(見出し・リンク・リストなど)を検証するUnitテスト

## 実装順序案

1. 固定ページ3種(About・Service・FAQ) — 独立していて小さい
2. `posts`テーブル・`Post`モデル・`MarkdownRenderer`
3. Blog公開側(一覧・詳細)
4. Blog Admin側(CRUD一式)・AdminLayoutへのナビ追加
5. AppFooter更新(全リンク反映)

## 未確定・要準備

- About・Serviceページの本文は一般的な内容でこちらが作成する(法務ページのようなプレースホルダーは想定していない)。ただし公開前にユーザーによる内容確認・調整を推奨する
- 実際のBlog投稿(v2.0リリースのお知らせ等)はユーザー側でAdmin画面から作成する運用想定
