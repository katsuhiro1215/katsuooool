# SPEC.md — katsuooool 仕様書

最終更新: 2026-08-25
対象範囲: Phase 0(基盤整備)〜Phase 2(Admin) ※Phase 3以降(他ツール・Premium・User認証)は本書のスコープ外(概要のみ記載)

## 1. プロダクト概要

「katsuooool」は Web制作者向けの実用ツール集サイト。iLoveIMG や Color Hunt のような、ブラウザから直接使えるシンプルなオンラインツール群を提供する。

- URL: `katsuooool.katsucode.jp`
- 現状: WordPress によるリンク集として稼働中 → 本リポジトリ(Laravel + Inertia + Vue)のツール集に置き換えて廃止する
- 基本方針: Free で誰でも自由に使えるツールを中心に構築し、Premium機能・User認証・決済は後続フェーズで追加する

### 将来のツール全体像(参考・未実装)

| カテゴリ | ツール例 |
|---|---|
| Image | 画像圧縮、WebP変換、AVIF変換、画像リサイズ、画像形式変換、複数画像一括変換、画像から色を抽出、画像の回転、画像の切り抜き |
| Color | カラーパレット生成、グラデーション生成、色の組み合わせ、HEX/RGB/HSL変換、コントラストチェック |
| Web | OGP画像サイズ作成、favicon生成、SVG最適化、HTML Formatter、JSON Formatter、URL Encode/Decode、Base64 Encode/Decode |
| SEO | title文字数チェック、description文字数チェック、OGPチェック、構造化データ確認 |

本フェーズで実装するのは **Image > 画像圧縮** の1ツールのみ。他は将来のPhaseで個別に仕様化する。

## 2. 技術スタック・前提

- PHP 8.3 / Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript
- DB: MySQL(ローカル: MAMP、本番: Xserver 共有サーバー)
- 認証: Laravel Fortify(既存の2FA・パスキー機能を継続利用。Breezeへの置き換えは行わない)
- 画像処理: Intervention Image(GD/Imagickをランタイム検出、CLIバイナリ非依存)
- **インフラ制約**: Xserver共有サーバーのため `exec()`・シェルコマンド実行・常駐キューワーカーは前提にしない。バッチ処理も含め同期処理(リクエスト内順次処理)で完結させる。
- **ロケール・タイムゾーン**: `APP_LOCALE=ja`(`APP_FALLBACK_LOCALE=en`)、`config('app.timezone')`は`Asia/Tokyo`に統一。バリデーションエラー等Laravelが自動生成するメッセージは`lang/ja/*`(`laravel-lang/lang`パッケージで生成)経由で日本語化する。日時はアプリ全体でJST基準(DB保存・`now()`・Admin入力すべて)で一貫させ、UTC/JSTの変換をコントローラー個別に行わない。

## 3. UI/UX方針

- **既存画面(ログイン・登録・設定など)**: スターターキット標準の shadcn-vue(reka-ui)ベースUIをそのまま使用。改修しない。
- **新規ツール画面(トップページ・画像圧縮ページなど)**: shadcn-vueを使わず、Tailwind CSSで独自にコンポーネントを作り込む。デザイントーンは iLoveIMG 型(白基調・大きなドロップエリア・最小限の装飾)の実用一辺倒のミニマルサイト。
- トップページ: 検索窓 + カテゴリ別カード一覧(将来ツール追加を見越した構成。現時点でカードは「画像圧縮」のみ表示)。
- URL規則: ツールごとに1URL、`/image-compress` のようなslugベースで統一。将来ツールも同じ命名規則に従う。

## 4. データベース設計

### 4.1 既存テーブル

- `users` — Fortify標準。Premium判定用の独自カラム(`is_premium`等)は追加しない。Laravel Cashier導入(Phase 21)により`Billable`トレイトを付与し、Premium状態はCashierが管理する`subscriptions`テーブルを`subscribed('default')`で都度判定する(§30参照)。

### 4.2 新規テーブル

**`admins`**

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | |
| remember_token | string nullable | |
| timestamps | | |

Admin専用の `admin` guard で認証する。ユーザー登録画面は作らず、Artisanコマンド(`admin:create` 等)で初期アカウントを作成する。

**`tool_events`**

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| user_id | bigint FK nullable | `users`参照、削除時は`SET NULL`。ログイン中の利用のみ記録(§29参照)、未ログインは常にnull |
| tool_slug | string | 例: `image-compress` |
| event_type | string | `upload` / `process` / `failed` |
| file_count | unsigned int nullable | 一括処理の件数 |
| input_bytes | unsigned bigint nullable | 処理前合計サイズ |
| output_bytes | unsigned bigint nullable | 処理後合計サイズ |
| ip_hash | string nullable | SHA-256 + 日次ソルトでハッシュ化。生IPは保存しない |
| user_agent | string nullable | |
| created_at | timestamp | `updated_at` は不要 |

- 30日経過分は Scheduler により自動削除する。
- 個人を特定できる情報(生IP・メールアドレス等)は保存しない。

**`posts`**(Blog/News、§31参照)

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| title | string | |
| slug | string unique | URL用(例: `v2-0-release`) |
| type | string | `update` / `fix` / `announcement` の3値固定 |
| body_markdown | text | Markdown形式の本文 |
| published_at | timestamp nullable | nullまたは未来日時は非公開(下書き) |
| timestamps | | |

**`ui_pattern_snippets`**(UIパターン集ギャラリー、§33参照)

| カラム | 型 | 備考 |
|---|---|---|
| id | bigint PK | |
| category | string, index | 現状は`button`のみ(将来カード等に拡張予定) |
| title | string | |
| html | text | プレビュー用HTMLスニペット |
| css | text | プレビュー用CSSスニペット |
| timestamps | | |

## 5. ファイルライフサイクル(共通基盤)

1. アップロード: `storage/app/private/tool-tmp/{uuid}/` に保存(Webから直接アクセス不可な非公開ディレクトリ)。
2. 検証: `finfo` による実体MIMEチェック(拡張子偽装対策)。画像圧縮ツールの許可形式は **JPEG / PNG / WebP** のみ(GIF・SVGは対象外)。
3. 処理: ツール固有のServiceが変換・圧縮を実行(同期処理、1リクエスト内で完結)。
4. ダウンロード: 単体は署名付き一時URL(`URL::temporarySignedRoute`)、複数枚は `ZipArchive` でZIP化した上で同様に署名付きURLを発行。
5. 削除: Laravel Scheduler(Xserver cronで `schedule:run` を1分間隔起動)により、1〜24時間経過した一時ファイルを自動削除する。

### 上限値

- 1ファイルあたり最大 10MB
- 1回の処理で最大 10ファイル

## 6. 共通基盤(Service層)

ツールが今後数十個に増えることを前提に、以下の共通処理をService/Traitとして切り出し、各ツール固有ロジックのみ個別実装する(過度な抽象化は避け、DB駆動のツール定義テーブルなどは導入しない)。

- `ToolUploadService` — アップロード受付・検証・一時保存
- `ToolEventLogger` — `tool_events` への記録
- `ToolDownloadService` — ZIP生成・署名付きダウンロードURL発行
- 個別ツールロジック: 例 `app/Services/Tools/ImageCompressService`

## 7. 画像圧縮ツール仕様(Phase 1)

- URL: `/image-compress`
- 操作: ドラッグ&ドロップ or ファイル選択で複数画像(最大10枚)をアップロード → 「圧縮する」ボタン1つで自動最適化(品質スライダー等のユーザー調整UIは持たない)
- 圧縮パラメータ: JPEG品質80相当など、ツール内に固定値として実装(将来調整可能にする場合は別途仕様化)
- 結果表示: ファイルごとに 圧縮前サイズ → 圧縮後サイズ・削減率(%)を一覧表示
- ダウンロード: 1枚なら単体ダウンロード、複数枚ならZIPで一括ダウンロード
- エラーハンドリング:
  - アップロード時点の上限超過・非対応形式 → バリデーションエラーとしてInertiaのフラッシュメッセージで表示
  - 処理中に個別ファイルが失敗(破損画像等) → そのファイルはスキップし、成功分のみ結果に含める。失敗理由を一覧に表示する
- レート制限: IP単位でアップロードAPIにthrottleミドルウェアを適用

## 8. Admin仕様(Phase 2)

- `/admin/login` — Admin専用ログインフォーム(独自Tailwind UI)。ログイン試行のレート制限あり。
- `/admin/dashboard` — ツール別の集計表示(実行回数・エラー数・日次推移グラフ・カテゴリ内訳など。詳細はPhase 15〜19、TASKS.md参照)。`tool_events` を集計して表示する。
- `/admin/users`・`/admin/users/{user}` — User(一般利用者)の一覧・検索・詳細表示(Phase 20、§29参照)。
- 左サイドバー(`AdminLayout.vue`)で各画面を切り替える。
- 初期Adminアカウントは登録画面を用意せず、Artisanコマンドで作成する(攻撃面を増やさない)。

## 9. セキュリティ方針

- アップロードファイルは実体MIMEチェックを行い、拡張子偽装を防止する。
- 保存ファイル名はUUID採番とし、元のファイル名はダウンロード時の表示名としてのみ利用する(パストラバーサル対策)。
- 一時ファイルは非公開ディレクトリに保存し、ダウンロードは署名付き一時URL経由のみとする。
- アップロード・ログインともにレート制限を設ける。
- イベントログのIPは日次ソルト付きハッシュで保存し、生IPは保存しない。

## 10. 画像リサイズ・画像形式変換ツール仕様(Phase 3)

Phase 1で実装した画像圧縮ツールと同じ共通基盤(`ToolUploadService`・`ToolDownloadService`・`ToolEventLogger`)を使い、Image共通の実装パターン(ドラッグ&ドロップ・最大10枚・単体/ZIP一括ダウンロード・個別失敗はスキップ)を踏襲する。

### 10.1 画像形式変換(`/image-convert`)

- 対象: `Katsucode Tools` 全体像で挙げた「WebP変換」「AVIF変換」「画像形式変換」は、出力形式を選択できる1つのツールに統合する(同じ変換ロジックの繰り返しを避けるため。UI上は出力形式の選択肢として WebP / AVIF / JPEG / PNG を提示し、事実上「WebP変換」「AVIF変換」双方のニーズを満たす)。
- 入力許可形式: JPEG / PNG / WebP / AVIF(圧縮ツールと異なりAVIFも入力として受け付ける。ローカル環境のGDでAVIF読み込み・書き込み両対応を確認済み)。
- 操作: ファイルをアップロード → 出力形式(JPEG/PNG/WebP/AVIF)をラジオボタン等で選択 → 「変換する」ボタンで一括変換。
- 出力: 選択した形式に変換したファイルをダウンロード(1枚は単体、複数枚はZIP)。
- 上限値: 画像圧縮ツールと同じ(1ファイル10MB、最大10枚、解像度上限あり)。
- AVIF出力の既知の制約: Imagick非搭載でGDのみの環境(GitHub Actions CIのUbuntuランナーで実際に確認)では、実写JPEGをAVIFへ再エンコードすると `imageavif()` が値を返さず出力が空になるケースがある(空キャンバスからのAVIF生成やAVIF入力の読み込みは問題なく動作する)。共有サーバーもGDのみの可能性が高いため、`ImageConvertService` はエンコード結果が0バイトの場合に例外を投げ、当該ファイルを「失敗」として扱う(バッチ全体は継続、他ファイルの処理・ダウンロードには影響しない)。本番(Xserver)でのAVIF出力の実際の成功率は要実機確認。

### 10.2 画像リサイズ(`/image-resize`)

- 対象形式: JPEG / PNG / WebP(圧縮ツールと同じ)。
- 操作: ファイルをアップロード → 幅・高さを指定(どちらか一方のみでもよく、その場合はアスペクト比を保持して自動計算) → 「リサイズする」ボタン。
- 指定値のバリデーション: 幅・高さともに正の整数、上限8000px程度(共有サーバーのメモリ制約を踏まえた安全値)。
- 出力: リサイズ後のファイルをダウンロード(1枚は単体、複数枚はZIP)。フォーマット変換は行わない(入力と同じ形式で出力)。

## 12. 画像から色を抽出ツール仕様(Phase 4)

- URL: `/image-color-extract`
- 対象形式: JPEG / PNG / WebP / AVIF(画像形式変換ツールと同じ入力許可形式)。
- 操作: ファイルをアップロード(最大10枚)→ 自動的に画像ごとの主要色を抽出して一覧表示(ボタン操作は「抽出する」のみ、パラメータ調整UIは持たない)。
- 抽出色数: 固定6色。画像を100×100px程度にダウンスケールした上でRGBを量子化し、出現頻度の高い色クラスタ上位6件を代表色として算出する(k-meansのような外部ライブラリ依存を避けた軽量な自前実装。GD/Imagickいずれでも同じロジックで動作)。
- 出力: 他の画像ツールと異なり、ファイルダウンロードは行わない。画面上に色ごとのスウォッチ + HEXコードを表示し、クリックでHEXコードをクリップボードにコピーできる。そのためAPIレスポンスに `batch_download_url` は含まない。
- 上限値: 他の画像ツールと同じ(1ファイル10MB、最大10枚、解像度上限あり)。
- エラーハンドリング: 個別ファイルの失敗(非対応形式・破損・解像度上限超過・不透明ピクセルが存在しない等)はそのファイルのみ「失敗」として結果に表示し、バッチ全体は継続する(既存ツールと同じパターン)。

## 14. 画像の回転ツール仕様(Phase 5)

- URL: `/image-rotate`
- 対象形式: JPEG / PNG / WebP(画像リサイズツールと同じ)。
- 操作: ファイルをアップロード(最大10枚)→ 回転角度を「左に90度」「180度」「右に90度」の3ボタンから選択 → 「回転する」ボタンでアップロード済みの全画像に同一角度を一括適用する(iLoveIMGの回転ツールと同様、任意角度の数値入力は持たない)。
- 出力: 回転後のファイルをダウンロード(1枚は単体、複数枚はZIP)。フォーマット変換は行わない(入力と同じ形式で出力)。90度・270度回転では幅と高さが入れ替わる。
- 上限値: 他の画像ツールと同じ(1ファイル10MB、最大10枚、解像度上限あり)。

## 15. 実装順序の方針

画像カテゴリの残タスクのうち「画像の切り抜き」は選択範囲UIなど実装量が多いため後回しとし、次の順序で進めた: 画像の回転(Phase 5)→ Colorカテゴリ → Webカテゴリ → SEOカテゴリ → CSS/Pattern/Generator/UI Patternカテゴリ → 画像の切り抜き(Phase 14、§28参照)。Imageカテゴリの計画分はこれで完了した。

## 17. Colorカテゴリツール仕様(Phase 6)

画像系ツールと異なり、いずれもファイルアップロードを伴わずブラウザ内で完結する計算・生成ツール。共通のTypeScript色変換ユーティリティ(`resources/js/lib/color.ts` 等、HEX⇔RGB⇔HSL相互変換・WCAGコントラスト比計算・色相操作)を切り出して5ツールで共用する。

**アクセス解析との整合性**: 既存の画像ツールは `ToolUploadService` の `store()` 呼び出し時に `upload` イベント、処理完了時に `process` イベントを記録している。Colorツールにはアップロードという概念がないため、各ツールに軽量な `Controller` を用意し、`show()` でページ表示、フロントエンドがメイン操作(変換・生成・チェック)を行うたびに専用のPOSTエンドポイントへ非同期リクエストを送って `ToolEventLogger::log(eventType: 'process')` のみ記録する(ファイルサイズ等のデータは持たない)。これにより既存のAdmin集計ダッシュボード(ツール別実行回数)がColorツールでもそのまま機能する。

### 17.1 HEX/RGB/HSL変換(`/color-convert`)

- 操作: カラーピッカー(`<input type="color">`)またはHEX/RGB/HSLいずれかのテキスト欄に入力すると、他の2形式がリアルタイムに追従して表示される。
- 各値にコピー用ボタンを設置。

### 17.2 コントラストチェック(`/color-contrast`)

- 操作: 前景色・背景色をそれぞれ選択すると、WCAG 2.1の相対輝度計算に基づくコントラスト比(例: `4.53:1`)と、通常テキスト/大きな文字 × AA/AAA の合否バッジを表示する。
- 入力した組み合わせでのテキストプレビューも表示する。

### 17.3 カラーパレット生成(`/color-palette`)

- 操作: 「生成する」ボタンで5色のランダムなパレットを生成する。各スウォッチに固定(ロック)ボタンを設け、ロック中の色は再生成時に維持される。
- 各スウォッチのHEXコードをクリックでコピーできる。

### 17.4 グラデーション生成(`/color-gradient`)

- 操作: 開始色・終了色・角度(0〜360度)を指定すると、線形グラデーションのプレビューと対応するCSSコード(`background: linear-gradient(...)`)を表示する。CSSコードはコピーボタンで取得できる。

### 17.5 色の組み合わせ(`/color-scheme`)

- 操作: 基準となる色を選択すると、補色・類似色・三色配色・分割補色・四色配色の5パターンをHSLの色相操作で算出し、それぞれスウォッチ列として表示する。

## 19. Webカテゴリツール仕様(Phase 7 — 画像処理系2ツール)

Webカテゴリ7ツールのうち、ファイルアップロードを伴う画像処理系の2ツールを先に実装する(残る5ツール(SVG最適化・HTML/JSONフォーマッタ・URL Encode/Decode・Base64 Encode/Decode)はクライアントサイド完結のテキスト処理系のため、Colorカテゴリと同じパターンで別Phaseとする)。Image共通基盤(`ToolUploadService`・`ToolDownloadService`・`ToolEventLogger`)を流用する。

### 19.1 OGP画像サイズ作成(`/ogp-image`)

- 対象形式: JPEG / PNG / WebP(画像リサイズツールと同じ)。
- 操作: ファイルをアップロード(最大10枚)→ 「作成する」ボタンでOGP標準サイズ(1200×630px)に一括変換する。
- リサイズ方式: 既存の画像リサイズツールの「アスペクト比を保って収まるように縮小(fit)」とは異なり、`cover`(中央基準でトリミングしながら1200×630を隙間なく埋める)を用いる。パラメータ調整UIは持たない。
- 出力: 1200×630pxの画像をダウンロード(1枚は単体、複数枚はZIP)。フォーマット変換は行わない(入力と同じ形式で出力)。
- 上限値: 他の画像ツールと同じ(1ファイル10MB、最大10枚、解像度上限あり)。

### 19.2 favicon生成(`/favicon-generate`)

- 対象形式: JPEG / PNG / WebP。
- 操作: 画像を**1枚**アップロード(他の画像ツールと異なり複数枚バッチには対応しない。1つのロゴ・アイコン画像から1セットのfaviconを生成する用途のため)→ 「生成する」ボタンで以下6サイズのPNGを一括生成する。
  - `favicon-16x16.png` / `favicon-32x32.png` / `favicon-48x48.png` / `apple-touch-icon.png`(180×180)/ `android-chrome-192x192.png` / `android-chrome-512x512.png`
- リサイズ方式: `cover` で正方形にトリミングしてから各サイズへ縮小する。
- 既知の制約: 従来型の `.ico` コンテナ形式は生成しない(GDに `.ico` 書き出し機能がなく、共有サーバーでの動作保証が難しいため)。現在主要ブラウザはPNG形式のfaviconを直接サポートしており、実務上の代替手段として十分と判断する。
- 出力: 6ファイルを常にZIPでダウンロード(入力1枚から複数ファイルを生成する性質上、`batch_download_url` の代わりに単一のZIPダウンロードのみを提供する)。
- 上限値: 1ファイル10MB、解像度上限は他の画像ツールと同じ。

## 20. スコープ外(将来のPhase)

- 構造化データ確認のMicrodata・RDFa対応(§23.5参照、v1はJSON-LDのみ)
- より詳細な分析基盤(時系列グラフ、ツール別ファイル形式内訳など)

## 21. 拡張ビジョン(参考・未確定)

2026-08-19時点でユーザーから共有された、当初の4カテゴリ(Image/Color/Web/SEO)を超える長期的な方向性。すぐに全て実装するものではなく、今後のカテゴリ再編・拡張の参考として記録する。詳細な仕様化・実装順序は都度あらためて決定する。

### 21.1 カテゴリ拡張案

既存のImage/Colorに加えて、以下のカテゴリを将来追加する方向性:

- **CSS**: Box Shadow・Border Radius・Glassmorphism・Flexbox・Grid ジェネレーター(Phase 10で実装済み、§24参照)
- **Pattern**: ドット・グリッド・ストライプ・チェッカーボード・ジグザグ・波模様など、背景パターン生成(HTML/CSSをコピペで使える形で提供)(Phase 11で実装済み、§25参照)
- **UI Pattern**: Button・Card・Pagination・Modal・Toast・Loading(Phase 13で実装済み、§27参照)・Skeleton・Form・Header・Footer など、UIコンポーネントのコードスニペット集
- **Generator**: OGP画像・favicon・ダミーデータ生成(すべて実装済み、ダミーデータ生成はPhase 12・§26参照)
- **Developer**: JSON Formatter・URL Encoder・HTML Formatter・Base64(Phase 8相当、実装済み)

カテゴリ体系の再編はPhase 8完了後に実施済みで、`config/tools.php` の `category` は `image`/`color`/`css`/`pattern`/`generator`/`developer`/`seo`/`ui-pattern` の値を持つ(WebカテゴリのPhase 8ツール群は「Developer」、Phase 7の画像処理系2ツールは「Generator」としてそれぞれ再分類済み)。UI Patternカテゴリは Phase 13 でButton・Card・Pagination・Modal・Toast・Loading・Skeleton・Form要素一式(Input・Textarea・Checkbox/Radio・Select・FormGroup)・Header・Footerに着手し、計画していたコンポーネントは全て完了した。

### 21.2 Premium/Stripe(Phase 21で実装済み)

当初は「Premium会員は全ツールを利用可能にする」方向性で検討していたが、ブレインストーミングの結果、既存ツールはiLoveIMG等の競合を踏まえて無料のまま維持し、バッチ件数上限・レート制限・マイページでPremiumの価値を提供する方針に転換して実装した。詳細は§30参照。

### 21.3 デザイン調整(要素が揃ってから着手)

- ボタンをやや大きくし、押しやすくする(未着手)。
- カテゴリ切り替えを「すべて/画像/色/CSS/Pattern/Generator/Developer/SEO」のタブ形式にする(実装済み。検索ボックスとAND条件で併用可能。`resources/js/lib/toolFilter.ts` にフィルタリングロジックを切り出し)。
- ユーザーの意向により、全カテゴリ・全ツールが出揃うのを待たず、タブ切り替えのみ先行して着手した。

## 22. Webカテゴリツール仕様(Phase 8 — テキスト処理系5ツール)

Webカテゴリ残り5ツール。Colorカテゴリ(Phase 6)と同じくファイルアップロードを伴わずブラウザ内で完結する。共通の軽量Controllerパターン(`Concerns/ColorToolController` と同様の構造)を `Concerns/WebToolController` として切り出し、`show()` + ログ専用POSTエンドポイントで `ToolEventLogger::log(eventType: 'process')` のみ記録する。

Phase 6で「今後の課題」としたJSユニットテスト基盤(Vitest)をPhase 8着手前に導入し、`resources/js/lib/color.ts` の既存ロジックに回帰テストを追加した上で、本Phaseで新設するテキスト処理ユーティリティにも単体テストを書く。

### 22.1 SVG最適化(`/svg-optimize`)

- 操作: テキストエリアにSVGコードを貼り付ける、または `.svg` ファイルを選択(選択時はブラウザの `FileReader` でテキストとして読み込み、サーバーには送信しない)→ 「最適化する」ボタンでコメント・XML宣言・エディタ由来のメタデータ(`inkscape:*`・`sodipodi:*`・`<metadata>` 要素等)・余分な空白を除去する。
- 出力: 最適化後のSVGコードと、元サイズ→最適化後サイズの削減率を表示する。コピー・ダウンロード(テキストファイルとして)に対応する。

### 22.2 HTML Formatter(`/html-format`)

- 操作: テキストエリアにHTMLを貼り付ける → 「整形する」ボタンでインデント付きの読みやすい形式に整形する。
- 出力: 整形後のHTMLをコピーできる。不正なHTMLでもエラーにせず可能な範囲で整形する(ブラウザのパーサーに一任するため)。

### 22.3 JSON Formatter(`/json-format`)

- 操作: テキストエリアにJSONを貼り付ける → 「整形する」ボタンで `JSON.parse` → 指定インデントでの `JSON.stringify` を行う。
- エラーハンドリング: 不正なJSONの場合はエラーメッセージ(構文エラーの内容)を表示し、整形結果は表示しない。
- 出力: 整形後のJSONをコピーできる。

### 22.4 URL Encode/Decode(`/url-encode`)

- 操作: テキストエリアに文字列を入力 → 「エンコード」「デコード」の2ボタンで `encodeURIComponent`/`decodeURIComponent` を実行する。
- エラーハンドリング: デコードに失敗した場合(不正なパーセントエンコーディング等)はエラーメッセージを表示する。

### 22.5 Base64 Encode/Decode(`/base64-encode`)

- 操作: テキストエリアに文字列を入力 → 「エンコード」「デコード」の2ボタンでBase64相互変換を行う(UTF-8文字列に対応するため `TextEncoder`/`TextDecoder` を介する)。
- エラーハンドリング: デコードに失敗した場合(不正なBase64文字列等)はエラーメッセージを表示する。

## 23. SEOカテゴリツール仕様(Phase 9)

SEOカテゴリ4ツール。「クライアント完結型」の文字数チェック2ツールと、「サーバーfetch型」のOGPチェック・構造化データ確認2ツールに分かれる。後者は本リポジトリ初のサーバー側外部URL取得機能であり、SSRF対策を備えた共通基盤 `UrlFetchService` を新設する。

### 23.1 title文字数チェック(`/seo-title-check`)

- 操作: テキストエリアにtitleタグの文字列を入力すると、リアルタイムで文字数・バイト数(UTF-8)を表示する。
- 目安表示: 全角30〜35字程度(半角換算60字程度)を推奨レンジとし、超過時は「Google検索結果で末尾が省略される可能性があります」等の警告を表示する。判定はフロントエンドのみで完結する(Colorカテゴリ・Phase 8と同じ `Concerns/TrackedToolController` パターンを使用)。

### 23.2 description文字数チェック(`/seo-description-check`)

- 操作: テキストエリアにmeta descriptionの文字列を入力すると、リアルタイムで文字数・バイト数を表示する。
- 目安表示: モバイル120字程度・デスクトップ160字程度を目安レンジとして併記し、超過時は省略される可能性がある旨を警告表示する。`23.1` と同じ軽量Controllerパターン。

### 23.3 UrlFetchService(共通基盤)

`app/Services/Tools/UrlFetchService.php` として新設し、23.4・23.5の両ツールで共用する。

- 許可スキームは `http`/`https` のみ。それ以外(`file://`・`ftp://`等)は即座に拒否する。
- ホスト名をDNS解決し、得られたIPアドレスが以下に該当する場合は拒否する: ループバック(`127.0.0.0/8`, `::1`)、プライベート(`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`)、リンクローカル(`169.254.0.0/16` — クラウドメタデータエンドポイントを含む、`fe80::/10`)、その他予約済みレンジ(`0.0.0.0/8` 等)、IPv6のユニークローカルアドレス(`fc00::/7`)。判定ロジックは `isBlockedIp(string $ip): bool` として純粋関数に切り出し、単体テストで境界値を網羅する。
- **DNS rebinding対策**: 検証に使ったIPアドレスへ直接接続する(検証後にホスト名で再解決させない)。
- リダイレクトは自動追従せず、`Location` ヘッダを受け取るたびに同じスキーム・IP検証を再実行する(最大3回まで、超過時は `too_many_redirects` エラー)。
- タイムアウト: 接続5秒/合計10秒。超過時は `timeout` エラー。
- レスポンスサイズ上限: 2MB。ストリーミング中に超過した場合は `too_large` エラーとして中断する。
- `Content-Type` が `text/html` 系以外の場合は本文を解析せず `non_html` エラーとする。
- 固定のUser-Agent文字列を付与する。Cookie・認証情報は一切送信しない。
- 取得結果は呼び出し元にHTML文字列として返す。取得失敗時は理由コード(`invalid_url` / `blocked_host` / `timeout` / `too_large` / `non_html` / `http_error` / `too_many_redirects`)を持つ例外を投げ、Controllerがそのままユーザー向けメッセージに変換する。
- 悪用防止のため、`UrlFetchService` を使う2つのcheckエンドポイント(23.4・23.5)にLaravelの `throttle` ミドルウェアを適用する(共有サーバーがSSRFの踏み台・スキャンツールとして悪用されるリスクを軽減する目的も兼ねる)。

### 23.4 OGPチェック(`/ogp-check`)

- 操作: URL入力欄にチェック対象のページURLを入力し「チェックする」ボタンを押すと、`UrlFetchService` でHTMLを取得し `og:*`・`twitter:*` 系metaタグを抽出する。
- 出力: 取得したタグを一覧表示するとともに、`og:title`・`og:description`・`og:image` を用いたSNSシェアカード風のプレビューを表示する。推奨タグ(`og:title`/`og:description`/`og:image`/`og:url`)が欠落している場合は警告として明示する。
- エラーハンドリング: `UrlFetchService` の理由コードに応じたメッセージ(例: 「プライベートIPアドレスへのアクセスはできません」「取得がタイムアウトしました」)を表示する。

### 23.5 構造化データ確認(`/structured-data-check`)

- 操作: URL入力欄にチェック対象のページURLを入力し「チェックする」ボタンを押すと、`UrlFetchService` でHTMLを取得し `<script type="application/ld+json">` を全て抽出してJSONパースする。
- 出力: 各スクリプトブロックのパース結果をツリー表示する。JSON構文エラーの場合はその旨を、`@context`・`@type` が欠落している場合は警告を表示する。
- スコープ: v1はJSON-LDのみを対象とし、Microdata・RDFaは対象外とする(将来検討)。

## 24. CSSカテゴリツール仕様(Phase 10)

CSSカテゴリ5ツール(Box Shadow・Border Radius・Glassmorphism・Flexbox・Grid)。いずれもColorカテゴリ・Webテキスト処理系ツールと同じ「ブラウザ内完結型」パターンで、ファイルアップロードを伴わず、共通の軽量Controller(`Concerns/TrackedToolController`)を継承してページ表示とイベントログ記録のみを行う。CSSコード生成ロジックは `resources/js/lib/cssGenerators.ts` に切り出し、5ツールで共用する。

### 24.1 Box Shadow(`/css-box-shadow`)

- 操作: X/Yオフセット(各-50〜50px)・ぼかし(0〜100px)・スプレッド(-50〜50px)・色・inset有無を調整すると、`box-shadow` のCSSコードがリアルタイムに生成される。単一シャドウのみ対応(複数シャドウの重ね合わせは非対応)。

### 24.2 Border Radius(`/css-border-radius`)

- 操作: 4隅(左上・右上・右下・左下、各0〜200px)を個別に調整すると、`border-radius` のCSSコードが生成される。4値がすべて同じ場合でも単一値には丸めず、常に4値並べて出力する。

### 24.3 Glassmorphism(`/css-glassmorphism`)

- 操作: 背景の透明度(0〜1)・ぼかし量(0〜40px)・境界線の透明度(0〜1)・影の強さ(0〜1)を調整すると、ガラス風カードのCSSコード(`background`・`backdrop-filter`・`border`・`box-shadow` の4行)が生成される。

### 24.4 Flexbox(`/css-flexbox`)

- 操作: `flex-direction`・`justify-content`・`align-items`・`flex-wrap` をドロップダウンで、`gap`(0〜50px)をスライダーで調整すると、画面下のプレイグラウンド領域(ダミーboxを表示)がリアルタイムに並び替わり、対応するCSSコードが生成される。

### 24.5 Grid(`/css-grid`)

- 操作: 列数(1〜6)・行数(1〜6)・`gap`(0〜50px)を調整すると、画面下のプレイグラウンド領域(列数×行数分のダミーboxを表示)に反映され、CSS Grid(`grid-template-columns`/`grid-template-rows`/`gap`)のコードが生成される。

## 25. Patternカテゴリツール仕様(Phase 11)

Patternカテゴリ6ツール(ドット・グリッド・ストライプ・チェッカーボード・ジグザグ・波模様)。CSSカテゴリ(Phase 10)と同じ「ブラウザ内完結型」パターンで、ファイルアップロードを伴わず、共通の軽量Controller(`Concerns/TrackedToolController`)を継承してページ表示とイベントログ記録のみを行う。CSSコード生成ロジックは `resources/js/lib/patternGenerators.ts` に切り出し、6ツールで共用する。

### 25.1 ドット(`/pattern-dot`)

- 操作: 背景色・パターン色・ドットサイズ(半径1〜20px)・間隔(10〜100px)を調整すると、`radial-gradient` による水玉模様の背景CSSコードがリアルタイムに生成される。

### 25.2 グリッド(`/pattern-grid`)

- 操作: 背景色・線の色・線の太さ(1〜5px)・マス目サイズ(10〜100px)を調整すると、縦横2方向の `linear-gradient` を重ねた格子柄の背景CSSコードが生成される。

### 25.3 ストライプ(`/pattern-stripe`)

- 操作: 背景色・パターン色・ストライプ幅(2〜50px)・角度(0〜180度)を調整すると、`repeating-linear-gradient` による斜線模様の背景CSSコードが生成される。

### 25.4 チェッカーボード(`/pattern-checkerboard`)

- 操作: 背景色・パターン色・マス目サイズ(10〜100px)を調整すると、`linear-gradient` を2枚重ねてずらした市松模様の背景CSSコードが生成される。

### 25.5 ジグザグ(`/pattern-chevron`)

- 操作: 背景色・パターン色・幅(10〜100px)・高さ(5〜50px)を調整すると、山形(V字)を描くインラインSVGを `data:image/svg+xml,...` としてURLエンコードした `background-image` のCSSコードが生成される。幅・高さの比に関わらず正しいシェブロン(山形)柄になるよう、グラデーションではなくSVG画像を用いる。

### 25.6 波模様(`/pattern-wave`)

- 操作: 背景色・パターン色・波の高さ(5〜30px)・波長(20〜200px)を調整すると、波形を描くインラインSVGを `data:image/svg+xml,...` としてURLエンコードした `background-image` のCSSコードが生成される。ジグザグと同様、グラデーションではなくSVG画像を用いる点が特徴。

## 26. Generatorカテゴリ拡張ツール仕様(Phase 12 — ダミーデータ生成)

既存のGeneratorカテゴリ(OGP画像作成・favicon生成)に、§21.1で挙げていた3つ目のツールを追加する。他のColor/CSS/Patternカテゴリと同じくブラウザ内完結型で、`TrackedToolController` を継承する。

### 26.1 ダミーデータ生成(`/generator-dummy-data`)

- 操作: 生成したいフィールド(連番ID・氏名・メールアドレス・電話番号・住所・会社名・日付・数値・UUID・真偽値の10種類からチェックボックスで複数選択)と件数(1〜1000)を指定し、「生成する」ボタンを押すとJSON配列が生成される。
- データ内容: 氏名・住所・会社名は日本語のサンプルデータ(苗字・名前・都道府県・市区町村・企業名のプールからランダム抽出)で構成し、日本国内向けWeb制作でそのまま使える形にする。
- 出力: 整形済みJSON(`JSON.stringify(..., null, 2)`)を表示し、コピーボタンでクリップボードにコピーできる(ファイルダウンロードは提供しない)。

## 27. UI Patternカテゴリツール仕様(Phase 13 — ボタン・カード・ページネーション・モーダル・トースト通知・ローディング・スケルトンローディング・フォーム要素一式・ヘッダー・フッター)

新カテゴリ「UI Pattern」を追加する。他のColor/CSS/Patternカテゴリと同じくブラウザ内完結型だが、生成物がCSS宣言のみではなくHTML+CSSの2ブロックになる点が異なる。フレームワーク非依存の素のHTML+CSSを出力し、Tailwind等特定のCSSフレームワークには依存しない。

### 27.1 ボタン(`/ui-pattern-button`)

- 操作: 背景色・文字色・サイズ(小/中/大のプリセット)・角丸(0〜24px)・ボーダー有無+色を調整すると、`<button class="btn">ボタン</button>` のHTMLと、対応する `.btn { ... }` のCSSがそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際に生成したスタイルを適用したボタンをその場に表示する。

### 27.2 カード(`/ui-pattern-card`)

- 操作: 「通常」「画像付き」の2バリエーションをラジオボタンで切り替え、タイトル・本文テキスト・背景色・角丸(0〜24px)・パディング(8〜32px)・影の有無を調整する。
- 通常バリエーション: `<div class="card">` の中にタイトル(`h3.card-title`)と本文(`p.card-text`)のみを配置する。
- 画像付きバリエーション: `<img class="card-image" src="https://placehold.co/400x200" alt="">` をカード上部に配置し(プレースホルダーURL、利用者側で差し替え前提)、タイトル・本文は `.card-body` でラップしてパディングを適用する(画像自体にはパディングを付けない)。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際に生成したスタイルを適用したカードをその場に表示する。

### 27.3 ページネーション(`/ui-pattern-pagination`)

- 操作: 現在ページ・総ページ数(数値入力)・アクセントカラーを調整すると、`<nav class="pagination">` の中に「前へ」リンク・ページ番号リンク・「次へ」リンクを並べたHTMLと対応するCSSが生成される。
- ページ番号表示ロジック: 常に1ページ目・最終ページ・現在ページの前後1ページを表示し、間が空く場合は省略記号(…)を挿入する(例: 総10ページ・現在5ページ目 → `1 … 4 5 6 … 10`)。現在ページは `pagination-page-active` クラスでアクセントカラーに強調表示する。
- 最初のページでは「前へ」リンクに、最後のページでは「次へ」リンクに `is-disabled` クラスを付与する。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際のページ番号表示をその場に表示する。

### 27.4 モーダル(`/ui-pattern-modal`)

- 操作: タイトル・本文テキスト・モーダル背景色・角丸(0〜24px)・オーバーレイの不透明度(0〜1)を調整する。UI Patternカテゴリで初めてHTML・CSSに加えJSも生成するツール(モーダルの開閉はCSSのみでは実現できないため)。
- 出力: トリガーボタン・オーバーレイ・モーダル本体(タイトル・本文・閉じるボタン)のHTML、`.modal-overlay`の表示切り替え(`is-open`クラス)を含むCSS、トリガー/閉じるボタンのクリックで`is-open`クラスを追加・削除する最小限のJSの3ブロックを、それぞれ個別のコピーボタンで提供する。
- プレビュー: ページ上の「開く」ボタンを押すと実際にモーダルが開閉する動作を確認できる。

### 27.5 トースト通知(`/ui-pattern-toast`)

- 操作: バリエーション(success/error/info/warningの4色プリセット)・メッセージテキスト・表示時間(1〜10秒)を調整する。
- 出力: トリガーボタン+トースト本体(メッセージ・閉じるボタン)のHTML、画面右下固定表示+4色分の`.toast-{variant}`クラスをすべて含むCSS(コンポーネントライブラリとして他バリエーションもそのまま再利用できる)、トリガークリックで表示し指定秒数後に自動的に非表示にする(`setTimeout`)JSの3ブロックを、それぞれ個別のコピーボタンで提供する。
- プレビュー: ページ上の「表示する」ボタンを押すと実際にトーストが表示され、指定時間後に自動的に消える動作(および閉じるボタンによる即時消去)を確認できる。

### 27.6 ローディング(`/ui-pattern-loading`)

- 操作: タイプ(スピナー/ドット/バーの3種類)・色・サイズ(16〜80px)を調整する。3タイプともCSSアニメーション(`@keyframes`)のみで実現し、JSは不要。
- スピナー: `border`の一部を色付けし回転させる。ドット: 3つの`<span>`を`nth-child`で時間差(0.15秒刻み)をつけてバウンスさせる。バー: トラック内をハイライトが左右に移動する不確定プログレスバー(幅はサイズの4倍)。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは選択中のタイプが実際にアニメーションする状態でその場に表示する。

### 27.7 スケルトンローディング(`/ui-pattern-skeleton`)

- 操作: タイプ(テキスト行/アバター行/カード型の3種類)・行数(1〜5)・色を調整する。すべてCSSアニメーション(`@keyframes`によるシマー: `linear-gradient`+`background-position`の移動)のみで実現し、JSは不要。
- テキスト行: 指定行数の横棒を縦に並べる(1行のみの場合を除き、最終行は60%幅にして自然なテキストの見た目にする)。アバター行: 円形アバター(48px)の右にテキスト行を配置する。カード型: 上部に画像相当の矩形ブロック、下部にテキスト行を配置する。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは選択中のタイプ・行数・色が実際にシマーアニメーションする状態でその場に表示する。

### 27.8 フォーム入力欄(`/ui-pattern-form-input`)

Formは「Login Form」「Register Form」のような完成形テンプレートではなく、Input・Textarea・Checkbox/Radio・Select・FormGroup(ラベル+入力+ヘルパー/エラー表示)といった要素単位のツール群として提供する方針とした(既存のUI Patternツールと同じ「パーツを組み合わせて使う」設計に揃え、1ツールあたりのスコープを小さく保つため)。本節はその1つ目、Input(`.form-input`)。

- 操作: タイプ(text/email/password/numberの4種類)・プレースホルダーテキスト・角丸(0〜24px)・ボーダー色・無効化(disabled)有無を調整する。
- 出力: `<input class="form-input" type="...">` のHTML(disabled時は`disabled`属性を付与)と、対応する`.form-input`のCSS(`:focus`のフォーカスリング、`:disabled`時のグレーアウトスタイルを含む)がそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際の`<input>`要素にスタイルを適用し、タイプ・disabled状態の変化も反映してその場に表示する。

### 27.9 フォームテキストエリア(`/ui-pattern-form-textarea`)

- 操作: プレースホルダーテキスト・行数(2〜10)・角丸(0〜24px)・ボーダー色・無効化(disabled)有無を調整する。
- 出力: `<textarea class="form-textarea" rows="...">` のHTML(disabled時は`disabled`属性を付与)と、対応する`.form-textarea`のCSS(縦方向のみリサイズ可能`resize: vertical`、`:focus`のフォーカスリング、`:disabled`時はリサイズ不可+グレーアウトを含む)がそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際の`<textarea>`要素にスタイルを適用してその場に表示する。

### 27.10 チェックボックス/ラジオボタン(`/ui-pattern-form-check`)

- 操作: タイプ(checkbox/radio)・ラベルテキスト・選択状態(checked)・無効化(disabled)有無を調整する。
- 出力: `<label class="form-check">` の中に`<input class="form-check-input">`とラベル用の`<span class="form-check-label">`を配置したHTML(checked/disabled時はそれぞれ属性を付与)と、対応するCSS(`.form-check-input`はcheckboxで角丸4px、radioで角丸50%、`accent-color`でチェック時の色を指定)がそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューはタイプ・checked・disabledの状態を反映した実際の入力要素をその場に表示する。

### 27.11 セレクトボックス(`/ui-pattern-form-select`)

- 操作: 選択肢リスト(1行に1つのテキストエリアで編集し、`<option>`へ可変変換)・角丸(0〜24px)・ボーダー色・無効化(disabled)有無を調整する。
- 出力: `<select class="form-select">` の中に選択肢の数だけ`<option>`を並べたHTML(disabled時は`disabled`属性を付与)と、対応する`.form-select`のCSS(`:focus`のフォーカスリング、`:disabled`時のグレーアウトスタイルを含む)がそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューは実際の`<select>`要素にスタイルを適用し、選択肢の追加・編集も反映してその場に表示する。

### 27.12 フォームグループ(`/ui-pattern-form-group`)

- 操作: ラベルテキスト・プレースホルダーテキスト・ヘルパーテキスト・エラー状態(hasError)の有無を調整する。
- 出力: `<div class="form-group">` の中にラベル(`.form-group-label`)・入力欄(`.form-group-input`)・ヘルパーテキスト(`.form-group-helper`)を配置したHTML(エラー状態時は入力欄・ヘルパーテキストの両方に`is-invalid`クラスを付与)と、対応するCSS(通常時のスタイルに加え、`.is-invalid`時は入力欄のボーダー色・ヘルパーテキストの文字色が赤系に変化)がそれぞれ独立して生成される。
- 出力: HTML・CSSそれぞれに個別のコピーボタンを設置する。プレビューはラベル・入力欄・ヘルパーテキストを実際に組み合わせた状態で表示し、エラー状態の切り替えによる配色変化も反映する。

### 27.13 ヘッダー(`/ui-pattern-header`)

ナビゲーションとレスポンシブ対応のモバイル用トグル(ハンバーガーメニュー)を含める方針とした。ロゴ+ナビリンク+モバイル時の開閉ボタンが揃って初めて実用的なパーツになるため。UI Patternカテゴリで唯一Modalと並びJSを含むツール(トグルの開閉にJSが必要なため)。

- 操作: ロゴテキスト・ナビリンク(可変長、1行に1つ)・背景色・影の有無(box-shadow)を調整する。
- 出力: `<header class="site-header">` の中にロゴ・トグルボタン(`.site-header-toggle`、3本線アイコン)・ナビ(`.site-nav`)を配置したHTML、`max-width: 768px`のブレークポイントでトグルボタンを表示しナビを非表示に切り替える`@media`クエリを含むCSS、トグルボタンのクリックで`.site-nav`に`is-open`クラスを付け外しし`aria-expanded`属性も更新する最小限のJSの3ブロックを、それぞれ個別のコピーボタンで提供する。
- プレビュー: 実際の`@media`クエリはビューポート幅に依存しプレビュー領域の見た目上の幅では再現できないため、「モバイル表示をシミュレート」チェックボックスでモバイル相当の表示(トグルボタン+折りたたみナビ)に切り替えられるようにし、トグルボタンをクリックすると実際にナビが開閉する動作を確認できる。

### 27.14 フッター(`/ui-pattern-footer`)

トグル操作は不要なため、Headerとは異なりHTML/CSSの2ブロックのみで完結するシンプルな構成とした。

- 操作: コピーライトテキスト・リンク一覧(可変長、1行に1つ)・背景色を調整する。
- 出力: `<footer class="site-footer">` の中にリンク一覧(`.site-footer-nav`)とコピーライトテキスト(`.site-footer-copyright`)を横並びに配置したHTML、`max-width: 768px`のブレークポイントで縦積み+中央寄せに切り替える`@media`クエリを含むCSSがそれぞれ独立して生成される。
- プレビュー: 「モバイル表示をシミュレート」チェックボックスで、縦積み+中央寄せのモバイルレイアウトを確認できる。

## 28. 画像の切り抜き仕様(Phase 14)

§15で「選択範囲UIなど実装量が多いため後回し」としていたImageカテゴリ最後のツール。複数枚バッチ処理(比率ベースの範囲を一律適用)ではなく、単一画像処理(ドラッグで正確に選択)を採用した。画像ごとに構図が異なるため、比率の一律適用では実用性が低いという判断による。

- アップロード: 1枚のみ。JPEG/PNG/WebP、10MB以下、最大2500万ピクセル(他の画像ツールと同じ制約)。
- 操作: アップロード後、プレビュー画像上に切り抜き矩形を表示する(初期状態は画像中央80%)。矩形はドラッグで移動、四隅のハンドルでリサイズできる(ライブラリ非依存、`pointerdown`/`pointermove`/`pointerup`によるVueコンポーネント内の自前実装)。アスペクト比プリセット(フリー/1:1/4:3/16:9/3:2)をボタンで切り替えると、リサイズ時もその比率を維持する。
- 処理: 「切り抜く」ボタンで、プレビュー上の矩形座標を元画像の実ピクセル座標にスケール変換してサーバーに送信する。バックエンドは`ImageCropController`+`ImageCropService`(Intervention Imageの`crop(width, height, x, y)`)で処理し、他の画像ツールと同じ`ToolUploadService`/`ToolEventLogger`/`ToolDownloadService`のパターンに乗せる(単一ファイルのためZIPは不要、署名付きダウンロードURLを1件返す)。切り抜き範囲が画像サイズを超える場合はエラーとして返す。
- 出力: 切り抜き後の画像をダウンロードできるリンクを表示する。

## 29. User管理(CRM)仕様(Phase 20)

Admin側からUser(一般利用者)を閲覧・検索できる機能。ブレインストーミングにより、今回は「一覧・検索・詳細表示」のみに絞り、アカウントの停止・削除やメール認証の手動完了・2FAリセットなどの変更系操作は含めないこととした(必要になれば別途ブレインストーミングする)。

- `/admin/users` — 名前・メールアドレスの部分一致検索、ページネーション(20件/ページ)。
- `/admin/users/{user}` — 登録日・メール認証状態・2FA有効化状態・パスキー登録件数のアカウント情報に加え、そのUserの`tool_events`(§4.2参照)をページネーションで表示する「ツール利用履歴」。
- `tool_events`に`user_id`(nullable、`users`外部キー、削除時は`SET NULL`)を追加し、ログイン中に各ツールを利用した場合は`ToolEventLogger`が自動的に記録するようにした(未ログイン利用は従来通り`user_id`がnullのまま、IPハッシュのみで記録される)。

## 30. Premium/Stripe決済連携仕様(Phase 21)

Laravel Cashier + Stripe Checkout / Billing Portalによるサブスクリプション機能。詳細な設計判断の経緯は`docs/superpowers/specs/2026-08-23-premium-stripe-design.md`、実装タスクは`docs/superpowers/plans/2026-08-23-premium-stripe.md`を参照。

- **料金**: 月額¥600(税込)・年額¥6,600(税込)。無料トライアルなし、返金なし、解約は期間末まで利用可能。
- **Premium判定**: 独自カラムは持たず、`User::isPremium()`が`$this->subscribed('default')`を呼び出してCashierの`subscriptions`テーブルを都度参照する。
- **登録導線**: `/premium`(料金ページ)→ `POST /premium/checkout`(Stripe Checkoutへリダイレクト)→ 決済完了後`/premium/thanks`。Webhook(`POST /stripe/webhook`)はCashierが自動登録。
- **解約・支払い管理**: `POST /premium/billing-portal`からStripe Billing Portalへ遷移し、Stripeのホスト型UIで解約・支払い方法変更を行う(独自UIは実装しない)。
- **Premiumの価値提供**(既存ツールは無料のまま維持):
  - 画像系6ツール(圧縮・変換・リサイズ・回転・色抽出・OGP画像)のバッチアップロード上限を無料3件/Premium10件に拡大。
  - 画像系8ツールのレート制限(`image-tools`リミッター)を無料10回/分・Premium30回/分に緩和。
  - Premium限定の`/mypage`(`EnsurePremium`ミドルウェア)で、自分のツール利用履歴(`tool_events`)をページネーション表示。
  - UIパターン集の一部パターンのコード閲覧(§33参照)。
- **特定商取引法表記**: `/legal/tokushoho`に雛形を用意。事業者名・所在地・連絡先はプレースホルダーのままで、本番デプロイ前に実データへの差し替えが必要。
- **未実施**: Stripeダッシュボードでの実商品・価格作成と`.env`の`STRIPE_PRICE_MONTHLY`/`STRIPE_PRICE_YEARLY`への実値設定はユーザー側の作業として残る。また、本番Webhookエンドポイントの登録(Stripeダッシュボード)と`.env`の`STRIPE_WEBHOOK_SECRET`への実値設定も必須(未設定のままだとCashierの`WebhookController`が署名検証ミドルウェアを登録せず、誰でも偽のWebhookリクエストでPremium状態を偽装できてしまう)。

## 31. コンテンツ基盤(固定ページ・Blog・FAQ)仕様(Phase 22)

katsuoooolを「ツール集サイト」から法務ページ・会社紹介・更新情報を備えた「プロダクトサイト」へ拡張するための、残りのコンテンツ基盤。詳細は`docs/superpowers/specs/2026-08-24-content-platform-design.md`、実装計画は`docs/superpowers/plans/2026-08-24-content-platform.md`を参照。

- **About(`/about`)・Service(`/service`)・FAQ(`/faq`)**: いずれも静的ページ(Admin編集機能を持たない、コード直接編集で更新)。特定商取引法ページ(`legal/Tokushoho.vue`)と同じ実装パターンを踏襲する。Serviceページはツールカテゴリ一覧を`resources/js/lib/toolFilter.ts`の`CATEGORY_TABS`から動的に取得し、ハードコードの重複を避ける。
- **Blog(News、`/blog`・`/blog/{slug}`)**: `posts`テーブル(§4.2参照)を持つ唯一のDB駆動コンテンツ。種別(`type`)は`update`(アップデート)/`fix`(不具合修正)/`announcement`(お知らせ)の3値固定で、カテゴリ・タグ機能は持たない。`published_at`がnullまたは未来日時の投稿は下書き扱いとし、`Post::published()`スコープにより公開側の一覧・詳細のどちらからも参照できない(直接slugを指定しても404)。
- **Markdown変換**: `league/commonmark`を用いた`MarkdownRenderer`サービスでサーバー側のみでHTML変換する(クライアント側でのMarkdownパースは行わない)。`html_input => escape`設定により生のHTMLタグはエスケープされ、XSS対策としている。
- **Admin投稿管理(`/admin/posts`)**: 既存の`auth:admin`ガード配下にフルCRUD(一覧・新規作成・編集・削除)を実装。共有フォームコンポーネント`PostForm.vue`を新規作成・削除フォーム間で再利用する。
- **導線**: サイト全体の`AppFooter`にAbout・Service・FAQ・Blogへのリンク(コンテンツ系)と、特商法・プライバシーポリシー・利用規約・クッキーポリシーへのリンク(法務系)を分けて設置する。AdminLayoutのサイドバーに「投稿管理」を追加する。

## 32. お問い合わせページ仕様(Phase 23・Phase 26)

既存のWordPress運用サイトのお問い合わせフォームと同じ項目構成を、このプロジェクトのTailwindスタイルで再実装したもの。Phase 23でUI・バリデーションのみを先行実装し、Phase 26で実際のSpra(中央問い合わせ管理システム、`https://smartsprouts.jp/api/contacts`)API連携に置き換えた。

- **ルート**: `/contact`(`ContactController@show`)・`POST /contact`(`ContactController@store`、`throttle:5,1`)。認証不要。
- **フィールド**: きっかけ(任意)・ご検討中の内容(必須、Spra API(`GET /categories`)から動的取得した選択肢)・お名前/ふりがな(必須)・会社名(任意)・電話番号/メールアドレス(必須)・ご相談内容(必須)・プライバシーポリシー同意/非営業目的の同意(いずれも必須チェックボックス)。
- **送信時の挙動**: バリデーション後、`App\Services\SpraContactApiClient::submit()`でSpra APIへ`POST`(氏名・メール・電話番号・会社名・カテゴリID・件名(カテゴリ名)・本文・ページURL・訪問者IP・User-Agentを送信)。成功時は`/contact`にリダイレクトして成功メッセージを、失敗時は同様にリダイレクトしてエラーメッセージを表示する。カテゴリ一覧はキャッシュ(成功時のみ1時間)して`GET /categories`の呼び出し回数を抑える。`SPRA_CONTACT_API_BASE`/`SPRA_CONTACT_API_KEY`が未設定の場合は両操作ともfail-closed(カテゴリは空配列、送信は失敗扱い)で例外を出さない。
- **IPアドレスの扱い(例外事項)**: `tool_events`のIPは日次ソルト付きハッシュ化して生IPを保存しない方針だが、お問い合わせフォームはSpra側のCRM機能(スパム対策・問い合わせ管理)のために生IP・User-Agentをそのまま送信する(既存WordPressサイトと同じ挙動)。この送信について[プライバシーポリシー](../resources/js/pages/legal/PrivacyPolicy.vue)の第三者提供の項目に明記している。本番(Xserver)はCDN/リバースプロキシを介していないため、`$request->ip()`は追加のTrustProxies設定なしで実際の訪問者IPを返す。
- **旧実装からの変更点**: 旧`App\Mail\ContactFormSubmitted`による自宛て通知メール送信・katsuooool独自のハードコードされたカテゴリ選択肢は廃止した。

## 33. UIパターン集ギャラリー仕様(Phase 24・Phase 25・Phase 27)

UIパターン系ジェネレーターツール(`/ui-pattern-*`)の下部に、Admin側で登録した実例パターンをカードGridで一覧表示するギャラリー機能。Phase 24でボタンカテゴリのみ実装し、Phase 25で全13カテゴリに横展開した。要求仕様・設計判断の詳細は`docs/superpowers/specs/2026-08-25-ui-pattern-gallery-design.md`、実装計画は`docs/superpowers/plans/2026-08-25-ui-pattern-gallery.md`を参照。

- **データモデル**: `ui_pattern_snippets`テーブル(§4.2参照)。`UiPatternSnippet::CATEGORIES`は`button`・`card`・`pagination`・`modal`・`toast`・`loading`・`skeleton`・`form-input`・`form-textarea`・`form-check`・`form-select`・`form-group`・`header`・`footer`の14種類(対応する`/ui-pattern-*`ツールのスラッグと一致)。新しいUIパターン種別を追加する場合は、この配列・`resources/js/lib/uiPatternCategory.ts`の`CATEGORY_LABELS`・対応する公開ツールコントローラーの`extraProps()`をそれぞれ追加する。
- **公開側(各`/ui-pattern-*`ページ)**: 既存のジェネレーターUIの下に「いろんな{カテゴリ名}」セクションを追加し、登録済みパターンをカードGridで表示する。各カードは、HTML/CSSタブ切り替え・コードのクリップボードコピー・プレビュー(後述)を備える。ページ幅は他のツールページと同じ`max-w-7xl`に統一(Phase 25で`max-w-3xl`から変更)。
- **プレビューの隔離方式(コア設計)**: 登録されたHTML/CSSは、共有ヘルパー`buildPatternDocument(html, css)`(`resources/js/lib/uiPatternSnippet.ts`)で完結したHTMLドキュメント文字列に組み立て、`sandbox=""`属性付きiframeの`srcdoc`に渡して描画する。これにより、パターンのCSS(例: `a { display: block; }`のような汎用セレクタ)がホストページの他要素に一切影響しない。同じ`buildPatternDocument()`はAdmin側の作成・編集画面のライブプレビューでも再利用する。
- **Admin側(`/admin/ui-pattern-snippets`)**: `auth:admin`ガード配下にフルCRUD(一覧・新規作成・編集・削除)を実装。作成・編集画面は共有フォームコンポーネント`SnippetForm.vue`を再利用し、入力中のHTML/CSSを即座に`buildPatternDocument()`でプレビューiframeに反映する。種別選択肢は全14カテゴリから選択可能。
- **導線**: `AdminLayout.vue`のサイドバーに「UIパターン集管理」を追加する。
- **Premium限定パターン(Phase 27)**: 各パターンに`is_premium`カラムを追加し、Admin側のチェックボックスでPremium会員限定に設定できる。共有ロジック`TrackedToolController::patternGalleryProps()`が、閲覧者が`isPremium()`でない場合に該当パターンの`html`/`css`キー自体をpropsから完全に除外し、代わりに`locked: true`とサーバー側で生成した`srcdoc`のみを返す(フロント側での非表示制御ではなく、サーバー側で非公開データを送らない設計)。この`srcdoc`はiframeでのライブプレビュー描画のため、元の`html`/`css`をそのまま固定のラッパーテンプレートに埋め込んだ完全なHTML文書であり、DevToolsで中身を読めば元コードを人手で復元することは技術的に可能である(iframe `srcdoc`によるライブプレビューという方式上、構造的に避けられない制約)。したがって本機能は「コードを技術的に閲覧不可能にする」ものではなく、「別キーとしての生データ送信をなくし、コピーボタン・コード欄表示を外す」ことでコピー&ペーストの手間を増やすUIレベルの抑止として設計している。公開側`PatternCard.vue`はロック時、プレビュー(iframe)はそのまま表示しつつコード欄を「このパターンのコードはPremium会員限定です。」というアップセルメッセージと`/premium`への導線リンクに置き換え、コピーボタンを表示しない。設計判断の詳細は`docs/superpowers/specs/2026-08-27-ui-pattern-premium-lock-design.md`を参照。
