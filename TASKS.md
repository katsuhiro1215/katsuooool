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

## 今後の課題

- [ ] Vitest等のJSユニットテスト基盤導入(Web/SEOカテゴリでもクライアントサイドの純粋関数が増える見込みのため、Phase 6で顕在化した`color.ts`の未検証ロジックによる不具合の再発を防ぐ)— Phase 8(Webカテゴリのテキスト処理系5ツール)着手前に導入する

## 今後の実装順序

画像の回転(Phase 5)→ Colorカテゴリ(Phase 6完了)→ Webカテゴリ 画像処理系2ツール(Phase 7完了)→ Webカテゴリ テキスト処理系5ツール(Phase 8)→ SEOカテゴリ → 画像の切り抜き(SPEC.md §15参照)。

## 完了の定義(Definition of Done)

- [ ] `composer test`(Pint/PHPStan/Pest)がグリーン
- [ ] `npm run lint:check` / `npm run format:check` / `npm run types:check` がグリーン
- [ ] SPEC.mdに記載の上限値・エラーハンドリングがすべて実装・確認済み
- [ ] 本番相当環境(Xserver共有サーバーの制限値)を想定した動作確認メモが残っている
