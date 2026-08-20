# Patternカテゴリ(Phase 11)設計書

**日付:** 2026-08-20
**関連:** [SPEC.md §21 拡張ビジョン](../../../SPEC.md)、[docs/superpowers/specs/2026-08-20-css-category-design.md](2026-08-20-css-category-design.md)(直前に実装したCSSカテゴリと同アーキテクチャ)

## 背景・目的

katsuoooolをフロントエンド制作者向けの総合ツールボックスへ拡張する長期ビジョン(SPEC.md §21)の一環として、新カテゴリ「Pattern」を追加する。背景パターンを調整し、CSSコードとしてコピーできる6つのジェネレーターで構成する:

- ドット(Polka Dot)
- グリッド(格子)
- ストライプ(縦・斜め縞)
- チェッカーボード(市松模様)
- ジグザグ(Chevron)
- 波模様(Wave)

SPEC.md §21.1の原案は「Background・Dot・Grid・Wave」だったが、ブレインストーミングでストライプ・チェッカーボード・ジグザグを追加し、「Background」という単独ツールは他5ツールと役割が重複するため採用しない方向で合意した。

## アーキテクチャ

直前に実装したCSSカテゴリ(Phase 10)と同一のパターンを踏襲する。ファイルアップロードは発生せず、サーバー側の役割はページ表示とイベントログ記録のみ。

- **Controller**: `App\Http\Controllers\Tools\Concerns\TrackedToolController`を継承する6つのController(`PatternDotController`等)。`slug()`/`page()`の実装のみ。
- **ロジック**: `resources/js/lib/patternGenerators.ts`に各ツールのCSS文字列生成関数を集約。純粋関数として実装し、Vitestで単体テストする。
- **ルート**: `/pattern-dot`・`/pattern-grid`・`/pattern-stripe`・`/pattern-checkerboard`・`/pattern-chevron`・`/pattern-wave`(`show`表示 + `track`ログ用POST、`throttle:30,1`)。
- **`config/tools.php`**: 6エントリを`category: 'pattern'`で追加。

## 共通仕様

全6ツールで「背景色」「パターン色」の2色構成に統一する(UIの一貫性のため、色の役割が対称なチェッカーボードも同じラベルを使う)。プレビュー領域は各ツール共通で、生成したCSSの`background-color`/`background-image`をそのまま適用した`div`で実際の見た目を表示する。

出力はすべて1〜数行のCSS宣言(`background-color`・`background-image`・`background-size`(必要な場合))で、コピーボタンで取得できる。波模様のみ`background-image`の値がSVGのdata URIになる点が他5ツールと異なるが、扱い(コピー対象がCSSテキストである点)は同じ。

## 各ツールの仕様

以下の「実装:」に示すCSSは設計方針を示す例であり、正確な値・グラデーション境界は実装フェーズのTDDで確定する(特にチェッカーボード・ジグザグは既知のCSSグラデーション技法の応用のため、実装時にブラウザで見た目を確認しながら微調整が発生し得る)。

### ドット(`/pattern-dot`)

- 入力: 背景色(デフォルト`#ffffff`)・パターン色(デフォルト`#000000`)・ドット半径(1〜20px、デフォルト3)・間隔(10〜100px、デフォルト20)
- 実装: `radial-gradient(circle, <パターン色> <半径>px, transparent <半径>px)` を`background-image`に、`<間隔>px <間隔>px`を`background-size`に設定
- 出力例:
  ```css
  background-color: #ffffff;
  background-image: radial-gradient(circle, #000000 3px, transparent 3px);
  background-size: 20px 20px;
  ```

### グリッド(`/pattern-grid`)

- 入力: 背景色・パターン色(線の色)・線の太さ(1〜5px、デフォルト1)・マス目サイズ(10〜100px、デフォルト20)
- 実装: 縦横2本の`linear-gradient`を重ねて格子線を表現する
  ```css
  background-color: #ffffff;
  background-image:
      linear-gradient(to right, #000000 1px, transparent 1px),
      linear-gradient(to bottom, #000000 1px, transparent 1px);
  background-size: 20px 20px;
  ```

### ストライプ(`/pattern-stripe`)

- 入力: 背景色・パターン色・ストライプ幅(2〜50px、デフォルト10)・角度(0〜180度、デフォルト45)
- 実装: `repeating-linear-gradient(<角度>deg, <パターン色> 0 <幅>px, <背景色> <幅>px <幅*2>px)`
  ```css
  background-color: #ffffff;
  background-image: repeating-linear-gradient(45deg, #000000 0 10px, #ffffff 10px 20px);
  ```

### チェッカーボード(`/pattern-checkerboard`)

- 入力: 背景色・パターン色・マス目サイズ(10〜100px、デフォルト20)
- 実装: 45度回転させた2枚の`linear-gradient`(市松模様の定番実装)。マス目サイズを`s`とすると、実際の`background-size`は`s*2 s*2`、各グラデーションの色境界を`s`単位で設定する
  ```css
  background-color: #ffffff;
  background-image:
      linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%),
      linear-gradient(45deg, #000000 25%, transparent 25%, transparent 75%, #000000 75%);
  background-size: 40px 40px;
  background-position: 0 0, 20px 20px;
  ```

### ジグザグ(`/pattern-chevron`)

- 入力: 背景色・パターン色・幅(10〜100px、デフォルト40)・高さ(5〜50px、デフォルト20)
- 実装: 2枚の斜め`linear-gradient`を組み合わせた山形パターン。幅を`w`・高さを`h`とすると、`background-size`は`<w>px <h*2>px`
  ```css
  background-color: #ffffff;
  background-image:
      linear-gradient(135deg, #000000 25%, transparent 25%),
      linear-gradient(225deg, #000000 25%, transparent 25%),
      linear-gradient(315deg, #000000 25%, transparent 25%),
      linear-gradient(45deg, #000000 25%, transparent 25%);
  background-size: 40px 40px;
  ```

### 波模様(`/pattern-wave`)

- 入力: 背景色・パターン色・波の高さ(5〜30px、デフォルト10)・波長(20〜200px、デフォルト100)
- 実装: SVGの`<path>`(1周期分のサインカーブ)をdata URIにエンコードして`background-image`に設定し、`background-repeat-x`相当を`background-size`の幅で表現する
  ```css
  background-color: #ffffff;
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='20' viewBox='0 0 100 20'%3E%3Cpath d='M0 10 Q25 0 50 10 T100 10' stroke='%23000000' fill='none' stroke-width='2'/%3E%3C/svg%3E");
  background-size: 100px 20px;
  ```
  data URI内の`#`・空白等はURLエンコードが必要(`#000000`→`%23000000`)。生成関数側でエンコード処理を行う。

## 共通UIパターン

- 左側(またはモバイルでは上部)に調整パネル(カラーピッカー2つ+ツール固有のスライダー)
- その下にプレビュー領域(実際にパターンが敷き詰められた`div`、最低200px四方程度)
- 生成されたCSSコードを`<pre>`で表示し、コピーボタンを設置(CSSカテゴリと同じパターンを流用)
- 既存ツール群と統一感のあるTailwind UI(shadcn-vue不使用)

## データフロー

1. ユーザーが調整パネルの値を変更(`v-model`でリアクティブなrefにバインド)
2. `computed`プロパティが`patternGenerators.ts`の対応する関数を呼び出し、CSS文字列を算出
3. プレビュー領域は同じref値から算出した`:style`オブジェクト(`backgroundColor`/`backgroundImage`/`backgroundSize`等)で直接適用し、実際のレンダリングに反映(CSSカテゴリのFlexbox/Gridツールと同じ「テキスト表示用の文字列」と「プレビュー適用用のオブジェクト」を分離する設計)
4. コピーボタンクリックで`navigator.clipboard.writeText()`
5. 入力変更から500ms後に`track`エンドポイントへPOSTし、`ToolEventLogger`でイベント記録

## エラーハンドリング

クライアント完結型のため、サーバー側のバリデーションエラーは発生しない。フロント側は数値入力にmin/max制約を設け、範囲外の値は入力できないようスライダーの`min`/`max`属性で防ぐ。カラーピッカーは`<input type="color">`を使うため、常に有効なHEX値が保証される。

## テスト

- `resources/js/lib/patternGenerators.test.ts`: 各生成関数(`buildDotPattern()`・`buildGridPattern()`・`buildStripePattern()`・`buildCheckerboardPattern()`・`buildChevronPattern()`・`buildWavePattern()`)の単体テスト。境界値・デフォルト値・波模様のHEXエンコード処理を網羅。
- `tests/Feature/Tools/Pattern*ControllerTest.php`: 各Controllerの`show()`ページレンダリングと`track()`のイベントログ記録を確認(CSSカテゴリと同じ形)。
- `tests/Feature/Admin/DashboardControllerTest.php`のツール数アサーションを26→32に更新(CSSカテゴリのタスク運用と同じく、各ツール追加タスクで1ずつ段階的に更新する)。

## スコープ外

- パターンのプリセット保存・共有機能
- SVGパターンの直接ダウンロード(コピーのみ、CSSカテゴリと同じ方針)
- UI Pattern・Generator等、他の新規カテゴリ(別フェーズで個別に設計)

## 実装順序

CSSカテゴリ(Phase 10)と同様、共通基盤(`patternGenerators.ts`のテスト・実装)を先に固めてから、6ツールを順に実装する。ツール間に依存関係はない(ドット → グリッド → ストライプ → チェッカーボード → ジグザグ → 波模様の順を想定、CSSのみで完結するものから着手し、SVGを扱う波模様を最後に回す)。
