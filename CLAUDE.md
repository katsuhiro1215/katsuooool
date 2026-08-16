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
- **DB**: MySQL(ローカルは MAMP、本番は Xserver)
- **認証**: Laravel Fortify(User向け、既存の2FA・パスキー機能を継続利用)+ `admins` テーブルによる Admin 専用 guard
- **決済**: Stripe(Premium機能実装時に導入、現フェーズでは未着手)
- **画像処理**: Intervention Image(GD/Imagickをランタイム検出して使用。共有サーバーのため CLI バイナリ(cwebp/pngquant等)には依存しない)

## インフラ制約(重要)

- 本番は **Xserver 共有サーバー**。`exec()` やシェルコマンド実行、常駐キューワーカー(`queue:work --daemon`)は使用不可という前提で設計する。
- 非同期処理が必要な場合も、常駐デーモンではなく Xserver の cron による `schedule:run` ベースの仕組みを使う。
- 画像圧縮などの重い処理は同期処理(リクエスト内で順次処理)とし、ファイル数・サイズの上限で安全性を担保する(現行仕様: 10MB/枚・10枚まで)。

## UI方針

- 既存のログイン・登録・設定画面などは shadcn-vue(reka-ui)ベースのスターターキット UI をそのまま使用する。
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
