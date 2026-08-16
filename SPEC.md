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
| Image | 画像圧縮、WebP変換、AVIF変換、画像リサイズ、画像形式変換、複数画像一括変換、画像から色を抽出 |
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

## 10. スコープ外(将来のPhase)

- WebP/AVIF変換、画像リサイズ、画像形式変換、複数画像一括変換以外のImageツール、Colorカテゴリ、Webカテゴリ、SEOカテゴリの各ツール
- Premium機能・Stripe決済連携
- User(一般利用者)向けログイン・マイページ
- より詳細な分析基盤(時系列グラフ、ツール別ファイル形式内訳など)
