# UIパターン集(ボタン集)設計

## 背景・目的

`/ui-pattern-button`(ボタン生成ツール)の下に、あらかじめ用意した多彩なボタンデザインをGridで並べ、ユーザーがそのままコピーして使えるギャラリーを追加する。ユーザーからサンプル(`sample/btn1/index.html`)が提供されており、`<a>`要素セレクタに直接スタイルを当てる形式のHTML/CSSスニペットになっている。

将来的にはボタン以外のUIパターン(カード・フォーム等)にも同じ仕組みを横展開する想定のため、データモデル・実装パターンは特定のUI種別に固定しない設計とする。

## スコープ

### 含むもの

- `ui_pattern_snippets`テーブル(category列で種別を区別する共通テーブル)
- 公開側: `/ui-pattern-button`ページの生成ツール下に、`category='button'`のパターンをGridギャラリー表示(iframeで個別に隔離したプレビュー + カードごとのHTML/CSSタブ切り替え + コピー機能)
- Admin側: `/admin/ui-pattern-snippets`にフルCRUD(一覧・新規作成・編集・削除)、入力中のライブプレビュー付き

### 含まないもの

- 並び替え機能(`sort_order`等)。当面は作成順(id昇順)で固定。
- wishlist投票機能(人気パターンの上位表示)。将来の別課題として保留。
- ボタン以外のUI種別(カード・フォーム等)の実装。今回はボタンのみだが、後から`category`の値を追加するだけで同じ仕組みを再利用できるようにする。

## データモデル

**`ui_pattern_snippets`**

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| category | string | `UiPatternSnippet::CATEGORIES`(初期値`['button']`)に含まれる値のみ許可。新しいUI種別を追加する際はこの配列に値を1つ足すだけでよい |
| title | string | Admin管理用の表示名(例: 「斜線シャドウボタン」) |
| html | text | プレビュー用HTMLフラグメント(`<body>`の中身に相当) |
| css | text | プレビュー用CSS(`<style>`の中身に相当) |
| created_at / updated_at | timestamp | |

`App\Models\UiPatternSnippet`:
- `scopeForCategory(Builder $query, string $category): Builder` — `category`で絞り込み、`id`昇順で並べる。

## 公開側(`/ui-pattern-button`)

### バックエンドの拡張ポイント

既存の`TrackedToolController`(Color/Web系のクライアント完結ツールの共通基底クラス)の`show()`は`Inertia::render($this->page())`のみを返し、追加propsを渡す仕組みがない。以下のフックメソッドを追加して、必要なコントローラーだけオーバーライドできるようにする(既存のColor/Web系ツールへの影響なし):

```php
public function show(): Response
{
    return Inertia::render($this->page(), $this->extraProps());
}

/**
 * @return array<string, mixed>
 */
protected function extraProps(): array
{
    return [];
}
```

`UiPatternButtonController`は`extraProps()`をオーバーライドし、`category='button'`のスニペット一覧を渡す。

### プレビューの隔離(iframe sandbox)

サンプルのCSSは`a { ... }`のような要素セレクタ直書きのため、複数パターンを1ページに並べるとCSS同士・サイト本体のスタイルと衝突する。各カードのプレビューは`<iframe sandbox="">`(空のsandbox属性 = スクリプト実行・フォーム送信等をすべて禁止する最も制限的な設定)に、HTML/CSSを埋め込んだ独立ドキュメントを`srcdoc`として渡すことで、スタイル衝突とスクリプト実行の両方を防ぐ。管理者のみが登録できるとはいえ、多層防御として空sandboxを採用する(これらのプレビューは静的なCSS表現のみが目的で、スクリプト実行を必要とする正当な理由がないため)。

`srcdoc`を組み立てる純粋関数`buildPatternDocument(html: string, css: string): string`を`resources/js/lib/uiPatternSnippet.ts`に切り出し、公開側のプレビューとAdmin編集画面のライブプレビューの両方で再利用する。

### UIコンポーネント

- `resources/js/components/tools/PatternCard.vue`(新規、再利用可能なコンポーネント): props `{ title: string; html: string; css: string }`。内部で`activeTab: 'html' | 'css'`を保持し、iframeプレビュー + タブ切り替え + コードブロック + コピーボタンを表示する。
- `resources/js/pages/UiPatternButton.vue`: 既存の生成ツール部分はそのまま維持し、その下に新しい`<section>`(見出し「いろんなボタン」+ `grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6` + `PatternCard`の繰り返し)を追加する。ページ全体の`max-w-3xl`を`max-w-7xl`に拡張する(Home.vueと同じ幅)。

## Admin側(`/admin/ui-pattern-snippets`)

既存の`Admin\PostController`(Blog投稿管理)と同じ構成を踏襲する。

- `Admin\UiPatternSnippetController`: `index()`(一覧)・`create()`・`store()`・`edit()`・`update()`・`destroy()`。`auth:admin`ガード配下。
- バリデーション: `category`は`Rule::in(UiPatternSnippet::CATEGORIES)`、`title`・`html`・`css`は`required|string`。
- 共有フォームコンポーネント`resources/js/components/admin/SnippetForm.vue`(`PostForm.vue`と同じ設計: `form: InertiaForm<...>`をpropsで受け取り`v-model`で直接バインド。Task 7で承認済みの`eslint-disable vue/no-mutating-props`パターンを踏襲)。HTML/CSS入力欄の下に、`buildPatternDocument()`を使ったライブプレビューiframeを表示し、保存前に見た目を確認できるようにする。
- `resources/js/pages/admin/ui-pattern-snippets/{Index,Create,Edit}.vue`(`admin/posts/*`と同じパターン)。
- `AdminLayout.vue`の`NAV_ITEMS`に「UIパターン集」を追加。

## テスト方針

- 公開側: `/ui-pattern-button`が`category='button'`のスニペットのみをid昇順で`patterns` propとして返すことを検証するFeatureテスト。他カテゴリのスニペットが混入しないことも確認する。
- Admin側: 未認証時のリダイレクト、一覧表示、作成・編集・削除の一連の動作、`category`が許可リスト外の場合のバリデーションエラーを検証するFeatureテスト(`Admin\PostControllerTest`と同じ形式)。
- `buildPatternDocument()`: html/cssが正しく埋め込まれた文字列になることを検証するVitestユニットテスト。

## 実装順序案

1. `ui_pattern_snippets`テーブル・`UiPatternSnippet`モデル
2. `buildPatternDocument()`ヘルパー(Vitestユニットテスト)
3. `TrackedToolController`への`extraProps()`フック追加 + `UiPatternButtonController`のオーバーライド + 公開側`PatternCard.vue`・`UiPatternButton.vue`のギャラリー表示
4. Admin側 `UiPatternSnippetController`・`SnippetForm.vue`・一覧/新規作成画面
5. Admin側 編集・削除画面
6. `AdminLayout`ナビゲーション追加 + ドキュメント更新

## 未確定・要準備

- 実際のボタンパターン(サンプル含め何種類用意するか)は、機能実装完了後にユーザー自身がAdmin画面から登録する運用想定。本設計・実装計画では登録機能の実装のみを扱う。
