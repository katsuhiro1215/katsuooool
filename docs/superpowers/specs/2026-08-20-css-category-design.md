# CSSカテゴリ(Phase 10)設計書

**日付:** 2026-08-20
**関連:** [SPEC.md §21 拡張ビジョン](../../../SPEC.md)、[project_katsuooool_expanded_vision メモリ](~/.claude/projects/-Users-katsuhiro-k1215-Project-katsuooool/memory/project_katsuooool_expanded_vision.md)

## 背景・目的

katsuoooolを画像・色系ツールだけでなく、フロントエンド制作者向けの総合的なツールボックス/スニペット集サイトへと拡張する長期ビジョン(SPEC.md §21)の一環として、新カテゴリ「CSS」を追加する。CSSカテゴリは、入力値を調整するとCSSコードがリアルタイムで生成される5つのジェネレーターで構成する:

- Box Shadow
- Border Radius
- Glassmorphism
- Flexbox
- Grid

この設計書に先立ち、既存の`web`カテゴリ(OGP画像作成・favicon生成・SVG最適化・各種フォーマッタ)は`generator`/`developer`の2カテゴリに再分類済み(別コミット、設計書化不要のbounded task)。CSSカテゴリはこの再編後のタクソノミーに新規追加するカテゴリという位置づけ。

## アーキテクチャ

既存のColorカテゴリ(Phase 6)・Webテキスト処理ツール(Phase 8)と同じ「ブラウザ内完結型」パターンを踏襲する。ファイルアップロードは発生せず、サーバー側の役割はページ表示とイベントログ記録のみ。

- **Controller**: `App\Http\Controllers\Tools\Concerns\TrackedToolController`を継承する5つのController(`CssBoxShadowController`等)。`slug()`/`page()`の実装のみ。
- **ロジック**: `resources/js/lib/cssGenerators.ts`に各ツールのCSS文字列生成関数を集約。純粋関数として実装し、Vitestで単体テストする。
- **ルート**: `/css-box-shadow`・`/css-border-radius`・`/css-glassmorphism`・`/css-flexbox`・`/css-grid`(`show`表示 + `track`ログ用POST、既存パターンと同じ`throttle:30,1`)。
- **`config/tools.php`**: 5エントリを`category: 'css'`で追加。

## 各ツールの仕様

### Box Shadow(`/css-box-shadow`)

- 入力: Xオフセット(-50〜50px)・Yオフセット(-50〜50px)・blur(0〜100px)・spread(-50〜50px)・色(カラーピッカー、デフォルト`#000000`)・inset(チェックボックス)
- 出力: `box-shadow: <x>px <y>px <blur>px <spread>px <color>`(insetチェック時は先頭に`inset `を付与)
- スコープ: 単一シャドウのみ(複数レイヤーの重ね合わせは対象外)

### Border Radius(`/css-border-radius`)

- 入力: 左上・右上・右下・左下の4隅を個別にスライダーで指定(各0〜200px)
- 出力: `border-radius: <top-left>px <top-right>px <bottom-right>px <bottom-left>px`(4値とも同じ場合でも単一値に丸めない。CSS仕様どおりの順序で常に4値出力し、実装をシンプルに保つ)

### Glassmorphism(`/css-glassmorphism`)

- 入力: 背景の透明度(0〜1、デフォルト0.2)・`backdrop-filter`のblur量(0〜40px、デフォルト10)・境界線の透明度(0〜1、デフォルト0.3)・影の強さ(box-shadowのalpha値、0〜1、デフォルト0.37)
- 出力: 複数プロパティをまとめたCSSブロック(`background: rgba(255, 255, 255, <opacity>);` `backdrop-filter: blur(<blur>px);` `border: 1px solid rgba(255, 255, 255, <border-opacity>);` `box-shadow: 0 8px 32px 0 rgba(31, 38, 135, <shadow-opacity>);` の4行)

### Flexbox(`/css-flexbox`)

- 入力: `flex-direction`(row/row-reverse/column/column-reverseのドロップダウン)・`justify-content`(flex-start/flex-end/center/space-between/space-around/space-evenly)・`align-items`(stretch/flex-start/flex-end/center/baseline)・`flex-wrap`(nowrap/wrap/wrap-reverse)・`gap`(0〜50px)
- プレビュー: 調整結果を反映したビジュアルプレイグラウンド(親コンテナ内に固定4個のダミーbox〈番号付き〉を表示し、選択したプロパティに応じて実際に並び替わる)
- 出力: `display: flex;`から始まる、上記5プロパティ+`display`の計6行のCSSブロック(初期値と同じプロパティも省略せず出力する)

### Grid(`/css-grid`)

- 入力: 列数(1〜6の整数、スライダーまたは数値入力→`grid-template-columns: repeat(<n>, 1fr);`を生成)・行数(1〜6の整数、同様に`grid-template-rows`)・`gap`(0〜50px)
- プレビュー: 調整結果を反映したビジュアルプレイグラウンド(列数×行数分のダミーboxを表示)
- 出力: `display: grid;`から始まる、`grid-template-columns`・`grid-template-rows`・`gap`を含む計4行のCSSブロック

## 共通UIパターン

- 左側(またはモバイルでは上部)に調整パネル(スライダー・ドロップダウン・カラーピッカー)
- Flexbox/Gridは調整パネルの下にビジュアルプレビュー領域を追加
- 全ツール共通で、生成されたCSSコードを`<pre>`または読み取り専用`<textarea>`で表示し、コピーボタンを設置(既存のSVG最適化・HTML Formatter等と同じコピーパターンを流用)
- 既存ツール群(Color/Web)と統一感のあるTailwind UI(shadcn-vue不使用、CLAUDE.md方針どおり)

## データフロー

1. ユーザーが調整パネルの値を変更(`v-model`でリアクティブなrefにバインド)
2. `computed`プロパティが`cssGenerators.ts`の対応する関数を呼び出し、CSS文字列を算出
3. プレビュー領域(Flexbox/Grid)は同じref値を`:style`バインディングで直接適用し、実際のレンダリングに反映
4. コピーボタンクリックで`navigator.clipboard.writeText()`
5. 入力変更から500ms後(既存のtitle/descriptionチェックツールと同じdebounceパターン)に`track`エンドポイントへPOSTし、`ToolEventLogger`でイベント記録

## エラーハンドリング

クライアント完結型のため、サーバー側のバリデーションエラーは発生しない。フロント側は数値入力にmin/max制約(例: blur 0〜100px、透明度 0〜1)を設け、範囲外の値は入力できないようスライダーの`min`/`max`属性で防ぐ。

## テスト

- `resources/js/lib/cssGenerators.test.ts`: 各生成関数(`buildBoxShadow()`・`buildBorderRadius()`・`buildGlassmorphism()`・`buildFlexbox()`・`buildGrid()`)の単体テスト。境界値(0・最大値)・insetありなし等のケースを網羅。
- `tests/Feature/Tools/Css*ControllerTest.php`: 各Controllerの`show()`ページレンダリングと`track()`のイベントログ記録を確認(既存のSEOテキストチェックツールのFeatureテストと同じ形)。
- `tests/Feature/Admin/DashboardControllerTest.php`のツール数アサーションを21→26に更新。

## スコープ外

- 複数box-shadowレイヤーの重ね合わせ(Box Shadow)
- プリセット保存・共有機能
- Pattern・UI Pattern等、他の新規カテゴリ(別フェーズで個別に設計)

## 実装順序

Colorカテゴリ(Phase 6)・SEOカテゴリ(Phase 9)と同様、共通基盤(`cssGenerators.ts`のテスト・実装)を先に固めてから、5ツールを順に実装する。5ツールは相互に依存しないため、実装順序に技術的な制約はない(Box Shadow → Border Radius → Glassmorphism → Flexbox → Gridの順を想定、シンプルなものから着手)。
