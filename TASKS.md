# TASKS.md — 実装タスク一覧

対象範囲: SPEC.md の Phase 0〜Phase 2。GitHubリポジトリ作成もPhase 0に含む。

## Phase 0 — 基盤整備

### 0.1 リポジトリ・環境整備

- [x] Git初期化(`git init`)、`.gitignore` 確認
- [x] GitHub上にPrivateリポジトリを新規作成し、リモート登録
- [x] 初回コミット・push
- [ ] `.env` の DB接続先を MySQL(MAMP)に設定し、ローカルで疎通確認 — MAMP側のMySQLユーザーで `Access denied` が発生(要ユーザー側でのMAMP設定確認)
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

## 完了の定義(Definition of Done)

- [ ] `composer test`(Pint/PHPStan/Pest)がグリーン
- [ ] `npm run lint:check` / `npm run format:check` / `npm run types:check` がグリーン
- [ ] SPEC.mdに記載の上限値・エラーハンドリングがすべて実装・確認済み
- [ ] 本番相当環境(Xserver共有サーバーの制限値)を想定した動作確認メモが残っている
