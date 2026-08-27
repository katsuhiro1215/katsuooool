# UIパターン集 Premium限定パターン機能 設計書

## 背景・目的

`docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md`で実装したUIパターン集ギャラリー(現在14カテゴリ: button/card/pagination/modal/toast/loading/skeleton/form-input/form-textarea/form-check/form-select/form-group/header/footer)は、登録済みパターンをすべて無条件で公開している。

複雑なデザイン(和模様の背景など将来のPattern(背景)カテゴリ展開も見据える)や、手間のかかった凝ったパターンについては、プレビュー(見た目)は誰でも閲覧できるが、コピー用のHTML/CSSコードはPremium会員限定にしたい、という要望。既存のPremium/Stripe決済連携(`docs/superpowers/specs/2026-08-23-premium-stripe-design.md`、SPEC.md §30)を前提に、「制限緩和型」の価値提供リストへ新たに「Premium限定パターンのコード閲覧」を追加する。

## スコープ

- 既存の14カテゴリすべてに共通で適用する仕組みを作る(カテゴリごとの個別実装はしない)。
- 対象は`ui_pattern_snippets`テーブルの各行(パターン単位)。Admin側で行ごとに「Premium限定にするか」を自由に選べる。
- Pattern(背景: Dot/Wave/Stripe/Chevron/Grid/Checkerboard)カテゴリへの適用は将来のスコープとし、本設計には含めない(ただし今回の設計はそのまま流用できる形にする)。
- wishlist投票機能・並び替え機能は引き続きスコープ外(既存の`docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md`のGlobal Constraintsを継承)。

## データモデル

`ui_pattern_snippets`テーブルに`is_premium`(boolean, default `false`)カラムを追加する。既存行はマイグレーションでdefault値`false`が入るため、今回のリリースでは既存パターンはすべて引き続き無料公開のままとなる(Adminが個別に編集して`true`に変更しない限り)。

```php
Schema::table('ui_pattern_snippets', function (Blueprint $table) {
    $table->boolean('is_premium')->default(false)->after('css');
});
```

`UiPatternSnippet`モデルの`#[Fillable]`属性に`is_premium`を追加する。

## サーバー側の公開制御(コア設計)

**原則: 非Premium閲覧者にはInertia propsの`html`/`css`キー自体を一切送らない。** フロントエンドでの見た目のぼかしだけでは、ページのJSON(Inertia props)経由でコードが閲覧可能になってしまうため、公開判定はサーバー側で行う(これによりフロントエンドのバグや改ざんに対しても、`html`/`css`キーが存在しない以上コードは取得できない)。

ただし、この「キーを送らない」保証はプレビュー用の`srcdoc`の中身までは及ばない。`srcdoc`はiframeでライブプレビューを描画するために、元の`html`/`css`をそのまま固定のラッパーテンプレートに埋め込んだ完全なHTML文書であり、DevToolsで`srcdoc`の文字列を開いてラッパー部分を読み飛ばせば、元のコードを人手で復元できてしまう。これはiframeの`srcdoc`でライブプレビューを描画する以上、構造的に避けられない制約であり、バグではない。したがって本機能が実現しているのは「コードを技術的に閲覧不可能にする」ことではなく、「別キーとしての生データ送信をなくし、コピーボタン・コード欄表示を外すことでコピー&ペーストの手間を増やす」UIレベルの抑止(コンビニエンスバリア)である、という点を正しく認識しておく。

### `PatternDocumentRenderer`(新規PHPサービス)

`resources/js/lib/uiPatternSnippet.ts`の`buildPatternDocument(html, css)`と全く同じ出力(doctype・style・body構造)を生成するPHP版を追加する。

```php
namespace App\Services;

class PatternDocumentRenderer
{
    public function build(string $html, string $css): string
    {
        return <<<HTML
<!doctype html>
<html lang="ja">
<head>
<meta charset="UTF-8" />
<style>
  html, body {
    margin: 0;
    padding: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100%;
    box-sizing: border-box;
  }
  {$css}
</style>
</head>
<body>
{$html}
</body>
</html>
HTML;
    }
}
```

TS版と出力構造が一致していることを保証するテストを両言語側に置く(片方だけ変更されたら気づけるようにする)。

### 公開パターン一覧の組み立て

既存の14個の`Tools\UiPattern{Category}Controller::extraProps()`は、いずれも次のような同じ形をしている(重複コード):

```php
protected function extraProps(): array
{
    return [
        'patterns' => UiPatternSnippet::query()
            ->forCategory('button')
            ->get(['id', 'title', 'html', 'css']),
    ];
}
```

Premium判定ロジックを14箇所に複製しないよう、共通処理を1箇所にまとめる。既存の`TrackedToolController::show()`は現在`Inertia::render($this->page(), $this->extraProps())`という形で`extraProps()`に引数を渡していない。`extraProps()`をオーバーライドしているのは14個の`UiPattern{Category}Controller`のみ(他の約38個のColor/Web系サブクラスはデフォルトの`[]`のまま)であることを確認済みなので、`show()`・`extraProps()`のシグネチャに`Request $request`を追加しても影響範囲は限定的。既存コードの慣習(グローバルヘルパーではなくメソッドインジェクション)に合わせ、`Request`を明示的に受け渡す。

```php
// app/Http/Controllers/Tools/Concerns/TrackedToolController.php

public function show(Request $request): Response
{
    return Inertia::render($this->page(), $this->extraProps($request));
}

/**
 * @return array<string, mixed>
 */
protected function extraProps(Request $request): array
{
    return [];
}

/**
 * @return array{patterns: array<int, array<string, mixed>>}
 */
protected function patternGalleryProps(Request $request, string $category): array
{
    $isPremiumViewer = $request->user()?->isPremium() ?? false;
    $renderer = app(PatternDocumentRenderer::class);

    $patterns = UiPatternSnippet::query()
        ->forCategory($category)
        ->get(['id', 'title', 'html', 'css', 'is_premium'])
        ->map(function (UiPatternSnippet $pattern) use ($isPremiumViewer, $renderer) {
            $locked = $pattern->is_premium && ! $isPremiumViewer;

            return [
                'id' => $pattern->id,
                'title' => $pattern->title,
                'locked' => $locked,
                ...($locked
                    ? ['srcdoc' => $renderer->build($pattern->html, $pattern->css)]
                    : ['html' => $pattern->html, 'css' => $pattern->css]),
            ];
        });

    return ['patterns' => $patterns];
}
```

各`UiPatternButtonController`等は次の形になる:

```php
protected function extraProps(Request $request): array
{
    return $this->patternGalleryProps($request, 'button');
}
```

この変更に伴い、既存の全38+サブクラスの`extraProps()`のデフォルト実装(引数なし`[]`)を呼び出している箇所は影響を受けない(シグネチャ変更後もオーバーライドしていないクラスは自動的に新シグネチャを継承するだけで、呼び出し元の`show()`側の変更のみで完結する)。

### レスポンス形状

- ロックなし(無料パターン、またはPremium会員が閲覧): `{ id, title, locked: false, html, css }`
- ロックあり(Premium限定パターンを非Premium閲覧者が閲覧): `{ id, title, locked: true, srcdoc }`(`html`/`css`キー自体を含めない)

## フロントエンド

### `PatternCard.vue`

`locked`propを追加し、`html`/`css`は`locked: false`のときのみ必須(TypeScript側もそれに合わせて型を分岐、もしくはオプショナルにする)。

- `locked: false` → 現状通り。`buildPatternDocument(html, css)`をクライアント側で計算してiframeに渡す。HTML/CSSタブ切り替え・コピー機能も現状通り。
- `locked: true` → サーバーから渡された`srcdoc`をそのままiframeに渡す(プレビューは表示される)。HTML/CSSタブの中身は以下のようなアップセル表示に置き換える:
  - 「このパターンのコードはPremium会員限定です」といった文言
  - `/premium`へのリンク(ボタンまたはテキストリンク)
  - コピーボタンは非表示

視覚的に「Premium限定」であることが一目でわかるよう、カード全体に小さなバッジ(例: 右上に「Premium」ラベル)を表示する。

### Admin側

- `resources/js/components/admin/SnippetForm.vue`: 「Premium限定パターンにする」チェックボックス(`v-model="form.is_premium"`)を追加。Adminのライブプレビューは常に非ロック表示(html/css入力欄・プレビューともに現状通り、Admin自身の作業を妨げない)。
- `Admin\UiPatternSnippetController`のバリデーションルールに`'is_premium' => ['boolean']`を追加(create/update両方)。
- `resources/js/pages/admin/ui-pattern-snippets/Index.vue`: 一覧の各行に「Premium」バッジを表示し、どのパターンがロック対象か一目でわかるようにする。

## エラーハンドリング・エッジケース

- 未ログインの訪問者は`$request->user()`が`null`のため、`?->isPremium() ?? false`で安全に非Premium扱いとなる。
- サブスクリプションが失効したユーザーも`User::isPremium()`(Cashierの`subscribed('default')`を都度参照)により自動的に非Premium扱いに戻るため、追加の失効処理は不要。
- フロントエンドのバグでロックUIが破られても、`html`/`css`キー自体がレスポンスに含まれていないため、別キー経由でコードが漏れることはない(サーバー側が唯一の防御線)。ただし前述の通り`srcdoc`には元のコードがそのまま埋め込まれているため、DevToolsで`srcdoc`を直接読めば復元は可能であり、これは技術的な閲覧不可を意味するものではない点に注意する。

## テスト方針

- `PatternDocumentRenderer`のユニットテスト: TS版の`buildPatternDocument()`テストと同じ入出力を検証し、出力構造(doctype・style・body)が一致することを保証する。
- `patternGalleryProps()`共通ロジックのテスト: 代表として1〜2個のコントローラー(例: `UiPatternButtonController`)で以下を検証する。
  - 非ログイン/非Premiumユーザーが`is_premium=true`のパターンを含むページを見た場合、該当パターンの`html`/`css`キーがレスポンスに一切含まれず、`locked: true`と`srcdoc`のみが返る
  - Premiumユーザー(テスト用にCashierのサブスクリプション状態を偽装、または`isPremium()`をモック)が同じページを見た場合、`html`/`css`が含まれ`locked: false`
  - 14個全部のコントローラーに同じテストを複製することはしない(共通ロジックの単体テストで十分担保する)
- Admin側`is_premium`保存・バリデーションのFeatureテスト追加。
- `PatternCard.vue`のロック時UI(コピーボタン非表示・アップセル文言・`/premium`リンク表示)のコンポーネントテスト追加。

## 影響範囲まとめ(実装計画のためのファイルリスト予告)

- Migration: `ui_pattern_snippets`に`is_premium`追加
- `app/Models/UiPatternSnippet.php`: Fillable追加
- `app/Services/PatternDocumentRenderer.php`(新規)
- `app/Http/Controllers/Tools/Concerns/TrackedToolController.php`: `patternGalleryProps()`追加
- 14個の`app/Http/Controllers/Tools/UiPattern{Category}Controller.php`: `extraProps()`を`patternGalleryProps()`呼び出しに置き換え
- `app/Http/Controllers/Admin/UiPatternSnippetController.php`: バリデーション追加
- `resources/js/components/tools/PatternCard.vue`: `locked`分岐追加
- `resources/js/components/admin/SnippetForm.vue`: チェックボックス追加
- `resources/js/pages/admin/ui-pattern-snippets/Index.vue`: バッジ表示追加
- `resources/js/lib/uiPatternSnippet.ts`: 型定義調整(必要に応じて)
- テスト一式(上記テスト方針を参照)
