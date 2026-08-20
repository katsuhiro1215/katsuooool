# TASKS.md — 実装タスク一覧

対象範囲: SPEC.md の Phase 0〜Phase 2。GitHubリポジトリ作成もPhase 0に含む。

## Phase 0 — 基盤整備

### 0.1 リポジトリ・環境整備

- [x] Git初期化(`git init`)、`.gitignore` 確認
- [x] GitHub上にPrivateリポジトリを新規作成し、リモート登録
- [x] 初回コミット・push
- [x] `.env` の DB接続先を MySQL(MAMP)に設定し、ローカルで疎通確認 — 原因は `.env` の `DB_USERNAME=katsuooool` に対しMAMP側のMySQLユーザーが `katsuoool`(oが1つ少ないtypo)だったこと。`.env` の値に合わせて `katsuooool`@`localhost`/`127.0.0.1` ユーザーを作成し解消、`php artisan migrate` 成功済み
- [ ] Xserverの実際の制限値(upload_max_filesize / post_max_size / max_execution_time、GD/Imagick/ZipArchive拡張の有無)を確認し、SPEC.mdの前提と齟齬がないか検証

### 0.2 不要依存の整理

- [x] `composer.json` から未使用の `laravel/breeze` を削除(Fortify構成を正式採用するため)
- [x] `composer update` して整合性確認

### 0.3 Admin認証基盤

- [x] `admins` テーブルのマイグレーション作成
- [x] `Admin` モデル作成(`Authenticatable` 実装)
- [x] `config/auth.php` に `admin` guard・providerを追加
- [x] `admin:create` Artisanコマンド作成(name/email/passwordを対話入力または引数で受け取り初期アカウント作成)
- [x] Admin用ミドルウェア(`auth:admin`)によるルート保護の動作確認

### 0.4 イベントログ基盤

- [x] `tool_events` テーブルのマイグレーション作成
- [x] `ToolEvent` モデル作成
- [x] `ToolEventLogger` サービス作成(IPを日次ソルト付きSHA-256でハッシュ化して記録)
- [x] 30日超過ログを削除するArtisanコマンド作成 + `routes/console.php` の Scheduler に登録
- [ ] Xserver cron設定手順をドキュメント化(README or docs配下)

### 0.5 共通ツール基盤(Service層)

- [x] `ToolUploadService` 作成(アップロード検証: 実体MIMEチェック、サイズ/件数上限チェック、`storage/app/private/tool-tmp/{uuid}/` への保存)
- [x] `ToolDownloadService` 作成(単体は署名付き一時URL、複数は `ZipArchive` でZIP化した上で署名付きURL発行)
- [x] 一時ファイルの自動削除コマンド作成(1〜24時間経過分)+ Schedulerに登録
- [x] 上記共通処理の単体テスト(Pest)作成

### 0.6 トップページ雛形

- [x] `/` ルート・Inertiaページ作成(独自Tailwind UI)
- [x] 検索窓UI(将来のツール検索を見据えたコンポーネント、現時点はクライアント側フィルタのみで可)
- [x] ツールカードコンポーネント(カテゴリ別、現時点は「画像圧縮」の1枚のみ表示)

## Phase 1 — 画像圧縮ツール

### 1.1 バックエンド

- [x] Intervention Image導入(`composer require intervention/image`)
- [x] GD/Imagickのランタイム検出ロジック実装(利用可能なドライバを自動選択)
- [x] `ImageCompressService` 作成(JPEG/PNG/WebP対応、固定品質値での圧縮処理)
- [x] `ImageCompressController` 作成(アップロード→検証→圧縮→結果返却)
- [x] ルート `/image-compress`(GET: ページ表示, POST: 処理実行)追加、アップロードAPIにthrottleミドルウェア適用
- [x] 個別ファイル失敗時のスキップ処理・エラー理由の返却
- [x] `ToolEventLogger` 連携(upload/processイベントの記録。download/errorは現状のイベント種別に含めず、upload/process/rejected/failedの結果情報で代替)
- [x] バックエンドテスト(正常系・上限超過・非対応形式・破損ファイル・重複ファイル名・巨大画像・圧縮で肥大化するケース)

### 1.2 フロントエンド

- [x] `/image-compress` Inertiaページ作成(独自Tailwind UI、shadcn-vue不使用)
- [x] ドラッグ&ドロップ + ファイル選択のアップロードUI(最大10枚のクライアント側バリデーション)
- [x] 「圧縮する」ボタン(品質調整UIなし、自動最適化のみ)
- [x] 結果一覧表示(ファイルごとの圧縮前後サイズ・削減率)
- [x] 単体/ZIP一括ダウンロードボタン
- [x] アップロード時・処理時のエラーメッセージ表示
- [ ] レスポンシブ確認(モバイル幅での動作確認)— 未実施

### 1.3 動作確認

- [x] ローカル(SQLite)環境での一連の動作確認(Playwrightで実ブラウザ操作: アップロード→圧縮→単体/ZIPダウンロード)
- [ ] MAMP(MySQL)環境での動作確認 — MySQL接続エラーのため未実施(0.1参照)
- [x] 大きめファイル・上限超過・非対応形式でのエラーハンドリング確認(自動テストで確認)
- [ ] ブラウザでの実機確認(Chrome/Safari、モバイル表示)— Playwright(Chromium)のみ確認済み、実機・Safari・モバイル表示は未実施

## Phase 2 — Admin

### 2.1 Admin認証画面

- [x] `/admin/login` ページ作成(独自Tailwind UI)
- [x] `AuthenticatedSessionController` 作成(ログイン/ログアウト、`app/Http/Controllers/Admin/` 配下)
- [x] ログイン試行のレート制限設定(`admin-login` 名前付きリミッター、5回/分・メール+IP)

### 2.2 Adminダッシュボード

- [x] `/admin/dashboard` ルート・ページ作成(`auth:admin` ミドルウェア保護)
- [x] `tool_events` 集計クエリ実装(ツール別 実行回数・ダウンロード回数(概算)、直近7日/30日推移)
- [x] 集計結果のテーブル表示コンポーネント作成(簡易グラフは見送り、表形式のみ)
- [x] 動作確認(データが無い場合の表示含む、Playwrightで実ブラウザ確認済み)

## Phase 3 — 画像形式変換・画像リサイズ

### 3.1 画像形式変換(`/image-convert`)

- [x] `ImageConvertService` 作成(JPEG/PNG/WebP/AVIF間の相互変換、GD/Imagickランタイム検出は既存 `ImageCompressService` と同様の方式を流用)
- [x] `ImageConvertController` 作成(GET: ページ表示, POST: 出力形式パラメータ付きで変換実行)
- [x] ルート `/image-convert` 追加、config/tools.php にエントリ追加
- [x] `/image-convert` Inertiaページ作成(出力形式選択UI、既存ツール群と統一感のあるTailwind UI)
- [x] バックエンドテスト(4形式それぞれへの変換、非対応形式・上限超過・破損ファイル)

### 3.2 画像リサイズ(`/image-resize`)

- [x] `ImageResizeService` 作成(幅/高さ指定、片方省略時はアスペクト比維持)
- [x] `ImageResizeController` 作成(GET/POST)
- [x] ルート `/image-resize` 追加、config/tools.php にエントリ追加
- [x] `/image-resize` Inertiaページ作成(幅・高さ入力UI)
- [x] バックエンドテスト(幅のみ・高さのみ・両方指定、不正な値、上限超過)

### 3.3 動作確認

- [x] `composer test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで実ブラウザ動作確認(スクリーンショット提示)

## Phase 4 — 画像から色を抽出

### 4.1 画像から色を抽出(`/image-color-extract`)

- [x] `ImageColorExtractService` 作成(100×100pxへのダウンスケール + RGB量子化による頻度カウントで上位6色を算出、GD/Imagickランタイム検出は既存Serviceと同様の方式を流用)
- [x] `ImageColorExtractController` 作成(GET: ページ表示, POST: 抽出実行。ダウンロードURLは発行しない)
- [x] ルート `/image-color-extract` 追加、config/tools.php にエントリ追加
- [x] `/image-color-extract` Inertiaページ作成(色スウォッチ + HEXコード表示、クリックでクリップボードコピー)
- [x] バックエンドテスト(正常系の色抽出、非対応形式・上限超過・破損ファイル、全透明画像などのエッジケース)

### 4.2 動作確認

- [x] `composer test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで実ブラウザ動作確認(スクリーンショット提示)

## Phase 5 — 画像の回転

### 5.1 画像の回転(`/image-rotate`)

- [x] `ImageRotateService` 作成(90/180/270度回転、GD/Imagickランタイム検出は既存Serviceと同様の方式を流用)
- [x] `ImageRotateController` 作成(GET: ページ表示, POST: 回転角度パラメータ付きで一括回転実行)
- [x] ルート `/image-rotate` 追加、config/tools.php にエントリ追加
- [x] `/image-rotate` Inertiaページ作成(左90度/180度/右90度のボタン選択UI)
- [x] バックエンドテスト(各角度での回転、90/270度での幅高さ入れ替わり確認、非対応形式・上限超過・破損ファイル)

### 5.2 動作確認

- [x] `composer test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで実ブラウザ動作確認(スクリーンショット提示)

## Phase 6 — Colorカテゴリ(5ツール一括)

### 6.0 共通基盤

- [x] `resources/js/lib/color.ts` 作成(HEX⇔RGB⇔HSL相互変換、WCAGコントラスト比計算、色相操作の共通ユーティリティ)— この時点でVitest等のJSユニットテスト基盤が未導入のため、既知の参照値(#767676 vs 白≈4.54:1、黒/白=21:1等)との突き合わせ・50,000件のランダムサンプルでの往復検証をNode一時スクリプトで手動実施。`rotateHue`の0度回転が非恒等になる不具合(89.4%のケースで発生)をこの過程で発見・修正済み
- [x] Colorツール共通の軽量Controllerパターン確立(`show()` + ログ専用POSTエンドポイント、`ToolEventLogger` 連携)

### 6.1 HEX/RGB/HSL変換(`/color-convert`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(カラーピッカー + HEX/RGB/HSL相互入力、コピー機能)

### 6.2 コントラストチェック(`/color-contrast`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(前景色/背景色選択、コントラスト比・AA/AAA判定表示、プレビュー)

### 6.3 カラーパレット生成(`/color-palette`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(5色ランダム生成、色ごとのロック機能、コピー機能)

### 6.4 グラデーション生成(`/color-gradient`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(開始色/終了色/角度指定、プレビュー、CSSコードコピー)

### 6.5 色の組み合わせ(`/color-scheme`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(基準色選択、補色/類似色/三色配色/分割補色/四色配色の表示)

### 6.6 動作確認

- [x] `composer test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで5ツールすべての実ブラウザ動作確認(スクリーンショット提示)

## Phase 7 — Webカテゴリ(画像処理系2ツール)

### 7.1 OGP画像サイズ作成(`/ogp-image`)

- [x] `OgpImageService` 作成(`cover()`で1200×630pxにトリミング+リサイズ、GD/Imagickランタイム検出は既存Serviceと同様の方式を流用)
- [x] `OgpImageController` 作成(GET: ページ表示, POST: 一括変換実行)
- [x] ルート `/ogp-image` 追加、config/tools.php にエントリ追加
- [x] `/ogp-image` Inertiaページ作成(既存の画像ツール群と統一感のあるUI)
- [x] バックエンドテスト(横長/縦長/正方形いずれの入力でも1200×630になること、非対応形式・上限超過・破損ファイル)

### 7.2 favicon生成(`/favicon-generate`)

- [x] `FaviconGenerateService` 作成(`cover()`で正方形化した上で6サイズ(16/32/48/180/192/512px)のPNGを生成。レビュー指摘によりメモリ効率化のため最大サイズ512pxで一度だけ正方形にクロップし、残り5サイズはそこから縮小)
- [x] `FaviconGenerateController` 作成(GET: ページ表示, POST: 1ファイルアップロード→6ファイルZIP生成。他ツールと異なり単一ファイル限定)
- [x] ルート `/favicon-generate` 追加、config/tools.php にエントリ追加
- [x] `/favicon-generate` Inertiaページ作成(単一ファイルアップロードUI、6サイズ生成後は常にZIPダウンロード)
- [x] バックエンドテスト(6サイズすべて正しい寸法で生成されること、非対応形式・上限超過・破損ファイル)

### 7.3 動作確認

- [x] `composer test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで2ツールすべての実ブラウザ動作確認(スクリーンショット提示)

## Phase 8 — Webカテゴリ(テキスト処理系5ツール)

### 8.0 共通基盤

- [x] Vitest導入(`npm run test` 等のスクリプト追加)
- [x] 既存 `resources/js/lib/color.ts` に単体テスト追加(Phase 6で手動検証した参照値・`rotateHue`の0度恒等性等を自動テスト化)
- [x] Webテキストツール共通の軽量Controllerパターン確立 — 設計時点では新規 `Concerns/WebToolController` を想定していたが、Color専用ロジックが無いことに気づき既存の `Concerns/ColorToolController` を `Concerns/TrackedToolController` にリネームしてColor/Web両カテゴリで共用する形に一般化(重複クラス回避)

### 8.1 SVG最適化(`/svg-optimize`)

- [x] `resources/js/lib/svg.ts` 作成 — 初版は正規表現ベースだったが、レビューでCDATA境界誤爆・DOCTYPE内部サブセット未対応・シングルクォート属性未対応の3件の実データ破損バグが発覚したため、`DOMParser`+`XMLSerializer`によるDOMベース実装に全面書き換え(単体テスト18件)
- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(テキストエリア + ファイル選択、削減率表示、コピー/ダウンロード。ファイル読み込みのサイズ上限・エラーハンドリングもレビュー指摘により追加)

### 8.2 HTML Formatter(`/html-format`)

- [x] `resources/js/lib/htmlFormat.ts` 作成 — レビューで発見されたエスケープ漏れ(既エスケープ済みマークアップが実行可能なタグに戻る不具合)・属性値未エスケープ・`<head>`欠落・`<pre>`/`<textarea>`のエスケープ漏れと子要素喪失を全て修正済み(単体テスト16件)
- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(テキストエリア、整形結果表示、コピー機能)

### 8.3 JSON Formatter(`/json-format`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(テキストエリア、構文エラー表示、コピー機能)

### 8.4 URL Encode/Decode(`/url-encode`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(エンコード/デコードボタン、エラー表示)

### 8.5 Base64 Encode/Decode(`/base64-encode`)

- [x] Controller・ルート・config/tools.php エントリ追加
- [x] Inertiaページ作成(エンコード/デコードボタン、エラー表示。UTF-8対応のためTextEncoder/TextDecoder経由)

### 8.6 動作確認

- [x] `composer test` / `npm run test` / `npm run lint:check` / `format:check` / `types:check` / `npm run build` がグリーン
- [x] Playwrightで5ツールすべての実ブラウザ動作確認(スクリーンショット提示、レビュー修正後のXSS再現テスト・`<pre><code>`保持テストも実機確認済み)

## Phase 9 — SEOカテゴリ(4ツール)

### 9.0 共通基盤

- [x] `App\Services\Tools\Dns\IpGuard`・`DnsResolver`(インターフェース)・`SystemDnsResolver` 作成(private/reserved/link-local/IPv6ユニークローカルIPのブロック判定)
- [x] `UrlFetchService` 作成(SSRF対策済みの共通URL取得基盤。許可スキームhttp/https限定、DNS rebinding対策として検証済みIPへ直接接続、リダイレクト再検証(最大3回)、接続5秒/合計10秒タイムアウト、レスポンス2MB上限、`text/html`以外拒否、固定User-Agent・Cookie/認証情報非送信)
- [x] `UrlFetchException` 作成(理由コード: invalid_url/blocked_host/timeout/too_large/non_html/http_error/too_many_redirects)
- [x] `AppServiceProvider` に `DnsResolver` → `SystemDnsResolver` のバインド追加
- [x] 単体テスト(IpGuard 6件・UrlFetchService 9件)

### 9.1 title文字数チェック(`/seo-title-check`)

- [x] `resources/js/lib/seoTextCheck.ts` 作成(文字数・UTF-8バイト数計算、推奨レンジ判定)
- [x] `SeoTitleCheckController` 作成(`TrackedToolController` 継承、クライアント完結)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/seo-title-check` Inertiaページ作成(全角30〜35字目安の警告表示)
- [x] フロント単体テスト・バックエンドFeatureテスト

### 9.2 description文字数チェック(`/seo-description-check`)

- [x] `SeoDescriptionCheckController` 作成(同上パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/seo-description-check` Inertiaページ作成(モバイル120字/デスクトップ160字目安の警告表示)
- [x] バックエンドFeatureテスト

### 9.3 OGPチェック(`/ogp-check`)

- [x] `OgpCheckService` 作成(`og:*`・`twitter:*` metaタグ抽出、推奨タグ欠落判定)— DOMDocumentの既定ISO-8859-1解釈による日本語文字化けを発見・修正(XMLエンコーディング宣言を先頭付与)
- [x] `OgpCheckController` 作成(`UrlFetchService` + `OgpCheckService` 連携、理由コード別エラーメッセージ返却)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/ogp-check` Inertiaページ作成(SNSシェアカード風プレビュー、推奨タグ欠落の警告表示)
- [x] 単体テスト3件・バックエンドFeatureテスト4件

### 9.4 構造化データ確認(`/structured-data-check`)

- [x] `StructuredDataCheckService` 作成(JSON-LDブロック抽出・パース、`@context`/`@type` 欠落警告。v1はJSON-LDのみ対応)
- [x] `StructuredDataCheckController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/structured-data-check` Inertiaページ作成(ブロックごとのツリー表示、構文エラー表示)
- [x] 単体テスト5件・バックエンドFeatureテスト3件

### 9.5 動作確認

- [x] `tests/Feature/Admin/DashboardControllerTest.php` のツール数を17→21に更新
- [x] `composer ci:check`(lint:check/format:check/types:check/npm test/composer test)がグリーン
- [x] `npm run build` がビルドエラーなしで成功

## Phase 10 — CSSカテゴリ(5ツール)

### 10.0 共通基盤

- [x] `resources/js/lib/cssGenerators.ts` 作成(Box Shadow/Border Radius/Glassmorphism/Flexbox/Gridの各入力型とCSSコード生成関数を集約。単体テスト8件)

### 10.1 Box Shadow(`/css-box-shadow`)

- [x] `CssBoxShadowController` 作成(`TrackedToolController` 継承、クライアント完結)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/css-box-shadow` Inertiaページ作成(X/Y/ぼかし/広がり・色・inset切り替えのスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 10.2 Border Radius(`/css-border-radius`)

- [x] `CssBorderRadiusController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/css-border-radius` Inertiaページ作成(4隅個別のスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 10.3 Glassmorphism(`/css-glassmorphism`)

- [x] `CssGlassmorphismController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/css-glassmorphism` Inertiaページ作成(背景不透明度・ぼかし・枠線不透明度・影不透明度のスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 10.4 Flexbox(`/css-flexbox`)

- [x] `CssFlexboxController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/css-flexbox` Inertiaページ作成(`flex-direction`/`justify-content`/`align-items`/`flex-wrap`/`gap`の切り替え、4つのboxがプレビュー上で実際に並び替わる、生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 10.5 Grid(`/css-grid`)

- [x] `CssGridController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/css-grid` Inertiaページ作成(列数・行数・gapの指定、プレビューのセル数が連動して変化、生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 10.6 動作確認

- [x] `tests/Feature/Admin/DashboardControllerTest.php` のツール数を21→26に更新(Task 2〜6で1ツールずつ段階的に反映済み)
- [x] `composer ci:check`(lint:check/format:check/types:check/npm test/composer test)がグリーン
- [x] `npm run build` がビルドエラーなしで成功
- [x] Playwrightで5ツールすべての実ブラウザ動作確認(各ツールでスライダー・セレクト操作により生成CSSコードが連動して変化すること、Flexbox/Gridはプレビューが実際に並び替わる/セル数が変わることを確認)

## Phase 11 — Patternカテゴリ(6ツール)

### 11.0 共通基盤

- [x] `resources/js/lib/patternGenerators.ts` 作成(ドット/グリッド/ストライプ/チェッカーボード/ジグザグ/波模様の各入力型とCSSコード生成関数を集約。単体テスト)

### 11.1 ドット(`/pattern-dot`)

- [x] `PatternDotController` 作成(`TrackedToolController` 継承、クライアント完結)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-dot` Inertiaページ作成(背景色・パターン色・ドットサイズ・間隔のスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.2 グリッド(`/pattern-grid`)

- [x] `PatternGridController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-grid` Inertiaページ作成(背景色・線の色・線の太さ・マス目サイズのスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.3 ストライプ(`/pattern-stripe`)

- [x] `PatternStripeController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-stripe` Inertiaページ作成(背景色・パターン色・ストライプ幅・角度のスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.4 チェッカーボード(`/pattern-checkerboard`)

- [x] `PatternCheckerboardController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-checkerboard` Inertiaページ作成(背景色・パターン色・マス目サイズのスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.5 ジグザグ(`/pattern-chevron`)

- [x] `PatternChevronController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-chevron` Inertiaページ作成(背景色・パターン色・幅・高さのスライダー、プレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.6 波模様(`/pattern-wave`)

- [x] `PatternWaveController` 作成(同パターン)
- [x] ルート・`config/tools.php` エントリ追加
- [x] `/pattern-wave` Inertiaページ作成(背景色・パターン色・波の高さ・波長のスライダー、インラインSVGによるプレビューと生成CSSコードのコピー機能)
- [x] バックエンドFeatureテスト

### 11.7 動作確認

- [x] `tests/Feature/Admin/DashboardControllerTest.php` のツール数を26→32に更新(Task 2〜7で1ツールずつ段階的に反映済み)
- [x] `composer ci:check`(lint:check/format:check/types:check/npm test/composer test)がグリーン
- [x] `npm run build` がビルドエラーなしで成功
- [x] Playwrightで6ツールすべての実ブラウザ動作確認(ドット・グリッド・ストライプ・チェッカーボード・ジグザグは色・サイズ調整で生成CSSコードが連動して変化すること、波模様は波の高さ・波長調整でSVGプレビューと生成CSSコードが連動して変化し、`background-image: url("data:image/svg+xml,...")` 形式であることを確認)

## トップページのカテゴリタブ(SPEC.md §21.3)

- [x] `resources/js/lib/toolFilter.ts` 作成(`filterTools()` でカテゴリ・キーワードのAND条件フィルタ、`CATEGORY_TABS` でタブ定義。単体テスト8件)
- [x] `Home.vue` にタブUI追加(すべて/画像/色/CSS/Pattern/Generator/Developer/SEOの8タブ、検索ボックスと併用可能)
- [x] Playwrightで実ブラウザ動作確認(タブ切り替え・検索との組み合わせフィルタが正しく動作することを確認)

## Phase 12 — Generatorカテゴリ拡張(ダミーデータ生成)

### 12.1 ダミーデータ生成(`/generator-dummy-data`)

- [x] `resources/js/lib/dummyData.ts` 作成(10種類のフィールド生成関数+`generateDummyData()`。単体テスト13件)
- [x] `GeneratorDummyDataController` 作成(`TrackedToolController` 継承)
- [x] ルート・`config/tools.php` エントリ追加(`category: 'generator'`)
- [x] `/generator-dummy-data` Inertiaページ作成(フィールド選択チェックボックス・件数入力・「生成する」ボタン・JSON表示とコピー機能)
- [x] バックエンドFeatureテスト
- [x] `tests/Feature/Admin/DashboardControllerTest.php` のツール数を32→33に更新
- [x] `composer ci:check` がグリーン
- [x] Playwrightで実ブラウザ動作確認(フィールド選択・件数指定・生成ボタンでJSON配列が正しく生成されることを確認)

## Phase 13 — UI Patternカテゴリ(ボタン)

### 13.1 ボタン(`/ui-pattern-button`)

- [x] `resources/js/lib/uiPatternGenerators.ts` 作成(`buildButton()`。素のHTML+CSS(フレームワーク非依存)を生成。単体テスト7件)
- [x] `UiPatternButtonController` 作成(`TrackedToolController` 継承)
- [x] ルート・`config/tools.php` エントリ追加(新カテゴリ`category: 'ui-pattern'`)
- [x] `/ui-pattern-button` Inertiaページ作成(背景色・文字色・サイズ・角丸・ボーダーの調整UI、プレビュー、HTML/CSSそれぞれ独立したコピー機能)
- [x] バックエンドFeatureテスト
- [x] `tests/Feature/Admin/DashboardControllerTest.php` のツール数を33→34に更新
- [x] `composer ci:check` がグリーン
- [x] Playwrightで実ブラウザ動作確認(サイズ・ボーダー変更でプレビューとCSS出力が連動することを確認)

## 今後の実装順序

UI Patternカテゴリの残り9コンポーネント(Card・Form・Header・Footer・Pagination・Modal・Toast・Loading・Skeleton)、画像の切り抜き(SPEC.md §15参照)。

## 完了の定義(Definition of Done)

- [ ] `composer test`(Pint/PHPStan/Pest)がグリーン
- [ ] `npm run lint:check` / `npm run format:check` / `npm run types:check` がグリーン
- [ ] SPEC.mdに記載の上限値・エラーハンドリングがすべて実装・確認済み
- [ ] 本番相当環境(Xserver共有サーバーの制限値)を想定した動作確認メモが残っている
