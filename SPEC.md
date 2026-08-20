# SPEC.md — katsuooool 仕様書

最終更新: 2026-08-16
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

## 3. UI/UX方針

- **既存画面(ログイン・登録・設定など)**: スターターキット標準の shadcn-vue(reka-ui)ベースUIをそのまま使用。改修しない。
- **新規ツール画面(トップページ・画像圧縮ページなど)**: shadcn-vueを使わず、Tailwind CSSで独自にコンポーネントを作り込む。デザイントーンは iLoveIMG 型(白基調・大きなドロップエリア・最小限の装飾)の実用一辺倒のミニマルサイト。
- トップページ: 検索窓 + カテゴリ別カード一覧(将来ツール追加を見越した構成。現時点でカードは「画像圧縮」のみ表示)。
- URL規則: ツールごとに1URL、`/image-compress` のようなslugベースで統一。将来ツールも同じ命名規則に従う。

## 4. データベース設計

### 4.1 既存テーブル

- `users` — Fortify標準。Premium導入時に `is_premium` 等のカラムを追加予定(今回は未使用・変更しない)。

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
| tool_slug | string | 例: `image-compress` |
| event_type | string | `upload` / `process` / `download` / `error` |
| file_count | unsigned int nullable | 一括処理の件数 |
| input_bytes | unsigned bigint nullable | 処理前合計サイズ |
| output_bytes | unsigned bigint nullable | 処理後合計サイズ |
| ip_hash | string nullable | SHA-256 + 日次ソルトでハッシュ化。生IPは保存しない |
| user_agent | string nullable | |
| created_at | timestamp | `updated_at` は不要 |

- 30日経過分は Scheduler により自動削除する。
- 個人を特定できる情報(生IP・メールアドレス等)は保存しない。

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
- `/admin/dashboard` — ツール別の集計表示(ツール名・実行回数・ダウンロード回数、直近7日/30日の推移)。`tool_events` を集計して表示する最小構成(高度な分析基盤は将来拡張)。
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

画像カテゴリの残タスクのうち「画像の切り抜き」は選択範囲UIなど実装量が多いため後回しとし、次の順序で進める: 画像の回転(Phase 5)→ Colorカテゴリ → Webカテゴリ → SEOカテゴリ → 画像の切り抜き。

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

- 画像の切り抜き(Image内で最後に着手)
- 構造化データ確認のMicrodata・RDFa対応(§23.5参照、v1はJSON-LDのみ)
- Premium機能・Stripe決済連携(方向性は§21.2参照、範囲は未確定)
- User(一般利用者)向けログイン・マイページ
- より詳細な分析基盤(時系列グラフ、ツール別ファイル形式内訳など)
- §21に記載のUI Patternカテゴリへのツール追加(方向性のみ、詳細仕様は未着手)

## 21. 拡張ビジョン(参考・未確定)

2026-08-19時点でユーザーから共有された、当初の4カテゴリ(Image/Color/Web/SEO)を超える長期的な方向性。すぐに全て実装するものではなく、今後のカテゴリ再編・拡張の参考として記録する。詳細な仕様化・実装順序は都度あらためて決定する。

### 21.1 カテゴリ拡張案

既存のImage/Colorに加えて、以下のカテゴリを将来追加する方向性:

- **CSS**: Box Shadow・Border Radius・Glassmorphism・Flexbox・Grid ジェネレーター(Phase 10で実装済み、§24参照)
- **Pattern**: ドット・グリッド・ストライプ・チェッカーボード・ジグザグ・波模様など、背景パターン生成(HTML/CSSをコピペで使える形で提供)(Phase 11で実装済み、§25参照)
- **UI Pattern**: Button・Card・Form・Header・Footer・Pagination・Modal・Toast・Loading・Skeleton など、UIコンポーネントのコードスニペット集
- **Generator**: OGP画像・favicon・ダミーデータ生成(すべて実装済み、ダミーデータ生成はPhase 12・§26参照)
- **Developer**: JSON Formatter・URL Encoder・HTML Formatter・Base64(Phase 8相当、実装済み)

カテゴリ体系の再編はPhase 8完了後に実施済みで、`config/tools.php` の `category` は `image`/`color`/`css`/`pattern`/`generator`/`developer`/`seo` の値を持つ(WebカテゴリのPhase 8ツール群は「Developer」、Phase 7の画像処理系2ツールは「Generator」としてそれぞれ再分類済み)。上記案のうちUI Patternの追加は未着手のまま残っている。

### 21.2 Premium/Stripe(方向性のみ・未確定)

- 月額500円のサブスクリプションで、Premium会員は全ツールを利用可能にする方向性。
- どのツール・カテゴリを無料/有料の境界にするかは未決定。ユーザーとの合意により、Phase 8以降のツール増設を優先し、Premium設計は別途ブレインストーミングの場で決定する。
- 開発用のStripe APIキー(`STRIPE_PUBLIC_KEY`・`STRIPE_SECRET_KEY`)は `.env` に設定済みだが、決済フロー自体は未着手。

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
