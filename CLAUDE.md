# CLAUDE.md

このファイルは、このリポジトリで Claude Code(および開発者)が作業する際の指針をまとめたものです。

## ドキュメント言語

- すべてのドキュメント成果物(設計書・README・仕様書・コメント含む)は **日本語** で作成すること。

## プロジェクト概要

「katsuooool」は Web制作者向けのツール集サイトです。現在は WordPress のリンク集として稼働している `katsuooool.katsucode.jp` を廃止し、本リポジトリ(Laravel + Inertia + Vue)によるツール集に置き換えます。

- 画像(圧縮・変換・リサイズ等)、カラー(パレット生成等)、Web(フォーマッタ等)、SEO(チェックツール等)の4カテゴリのツールを提供予定。
- 詳細な仕様は [SPEC.md](./SPEC.md)、実装タスクは [TASKS.md](./TASKS.md) を参照。

## 技術スタック

- **バックエンド**: PHP 8.3 / Laravel 13
- **フロントエンド**: Vue 3 / Inertia.js v2 / TypeScript
- **DB**: ローカルは MySQL(MAMP)または SQLite。移行後の本番は **SQLite（WAL）**。現行の Xserver 本番は MySQL（M4 のデータ移行まで）
- **認証**: Laravel Fortify(User向け、既存の2FA・パスキー機能を継続利用)+ `admins` テーブルによる Admin 専用 guard
- **決済**: Stripe(Premium機能実装時に導入、現フェーズでは未着手)
- **画像処理**: Intervention Image(GD/Imagickをランタイム検出して使用。新本番コンテナは Imagick 入り。CLI バイナリ(cwebp/pngquant等)には依存しない方針は維持)

## インフラ制約(重要)

- **移行先（Lightsail、2026-10-08 に M2 で構成作成・ローカル検証済み）**: 本体（`projects/Spra`）と同じインスタンスに別 Compose プロジェクトとして載せる。`Dockerfile.prod`・`compose.prod.yaml`・`.env.production.example`・`scripts/backup-db.sh` が正。サービスは `app`（php-fpm、`mem_limit: 512m`）・`web`（内側の HTTP 専用 Caddy。`public/` を配信し php-fpm へ fastcgi。`Caddyfile.prod`）・`scheduler`（`schedule:run` の60秒ループ）のみ。DB は SQLite（WAL、`storage/database/` を Docker ボリュームに置く）、セッション・キャッシュ・キューは `database`。ネットワークは本体の `spra_prod`（external）。本体 Caddy は `reverse_proxy katsuooool-web:80` で `web` に渡し、`web` が `katsuooool-app:9000` へ渡す（TLS は本体 Caddy で終端）。
- 画像処理は Imagick（AVIF 書き出しは `libheif-aom` が必須）。Caddy 経由なので `bootstrap/app.php` でプライベートアドレスからの `X-Forwarded-*` を信頼する。
- 現行の本番 **Xserver 共有サーバー**（M7 で停止予定）では、`exec()` やシェルコマンド実行、常駐キューワーカーは使えず、非同期処理は cron の `schedule:run` ベース。画像圧縮は同期処理で、ファイル数・サイズの上限（10MB/枚・10枚まで）で安全性を担保している。コードはどちらの環境でも動くよう、GD フォールバックと同期処理の設計を維持する。

## UI方針

- 既存のログイン・登録・ダッシュボード画面(`resources/js/pages/auth/*`, `Dashboard.vue`)は shadcn-vue ではなく、素の Tailwind CSS による Breeze 形式の UI(`AuthenticatedLayout.vue` / `GuestLayout.vue`)を正としている。未使用の shadcn-vue 製アプリシェル(AppShell/AppHeader/AppSidebar 等)は削除済み。
- `/settings/*`(Profile・Security・Appearance)のみ shadcn-vue(reka-ui)ベースの UI を使用する。
- **新規に作るツール画面(画像圧縮など)は shadcn-vue を使わず、Tailwind CSS で独自にコンポーネントを作り込む。** デザインは iLoveIMG のような実用一辺倒のミニマルなツールサイトを志向する。

## プライバシー・データ方針

- 未ログイン利用者のアップロードファイルはサーバーに短時間のみ保持し、Scheduler により自動削除する。
- 利用状況はイベントログ(`tool_events`)として記録するが、IPアドレスは日次ソルト付きハッシュ化して保存し、生IPは保存しない。ログも一定期間(30日目安)で自動削除する。

## 開発コマンド

- `composer dev` — Laravel dev server 一式(Vite含む)を起動
- `composer test` — Lint(Pint)・型チェック(PHPStan)・テスト(Pest)を一括実行
- `npm run lint` / `npm run format` — フロントエンドの Lint / Format

## ドキュメント運用

- 大きな設計判断や仕様変更は SPEC.md に反映し、実装タスクの粒度は TASKS.md で管理する。
- 新しいツールカテゴリやフェーズを追加する際は、既存の SPEC.md を上書きせず、対象範囲を明記した上でセクションを追加する。
