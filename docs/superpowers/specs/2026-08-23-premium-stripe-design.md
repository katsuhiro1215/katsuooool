# Premium/Stripe連携 設計書

**日付:** 2026-08-23
**関連:** [SPEC.md §4.1 データベース設計](../../../SPEC.md)、[SPEC.md §20 スコープ外](../../../SPEC.md)、[project_katsuooool_premium_stripe メモリ](~/.claude/projects/-Users-katsuhiro-k1215-Project-katsuooool/memory/project_katsuooool_premium_stripe.md)、[project_katsuooool_roadmap_sequence メモリ](~/.claude/projects/-Users-katsuhiro-k1215-Project-katsuooool/memory/project_katsuooool_roadmap_sequence.md)

## 背景・目的

katsuoooolに月額課金のPremiumプランを導入する。Phase 20(User管理/CRM)までで「誰が利用しているか」を把握する土台が整ったため、次のステップとして課金機能を追加する。

ビジネス面の前提(ユーザーとのブレインストーミングで決定済み):

- 無料トライアルなし。登録後すぐに課金開始。
- 月額¥600・年額¥6,600(月額換算で約1ヶ月分お得。よくある「2ヶ月分無料」より控えめな割引)。
- 消費税は税込固定価格として扱う(Stripe Taxは使わない。日本国内向け固定価格販売のみのため過剰な仕組みは導入しない)。
- 返金なし。解約しても支払い済み期間の終了まではPremium機能を利用できる(`cancel_at_period_end`)。
- Premiumの価値は「既存の人気ツールを有料化する」のではなく「新しく生まれる価値を上乗せする」方向で設計する。既存の画像処理系ツールは無料のまま維持し、SEO集客・信頼を損なわない。

## アーキテクチャ

### 技術選定: Laravel Cashier + Stripe Checkout

サブスクリプション管理・Webhook処理・請求書発行を自前実装せず、Laravel公式パッケージ[Cashier](https://laravel.com/docs/cashier)に委ねる。決済画面もCashierの`checkout()`が返すStripeホスト型Checkoutページにリダイレクトする方式とし、カード情報を自サーバーで扱わない(PCI DSSスコープを最小化)。プラン変更・解約・支払い方法の変更はStripeの「Billing Portal」(ホスト型管理画面、`$user->billingPortalUrl()`で発行したURLへリンクするだけ)に任せ、独自の管理UIを作らない。

理由:
- Xserver共有サーバー(`exec()`不可・常駐ワーカー不可)という制約下でも、CashierのWebhook処理は通常のHTTPルートとして同期的に動作するため問題なく使える。
- 決済関連は最もバグの影響が大きい領域であり、自前実装よりも実績のあるパッケージに乗る方が安全。
- 個人開発規模でBilling Portal・Checkoutという「作り込まない」選択肢を取ることで、実装・保守コストを最小化する。

### サブスクリプション状態の管理

独自の`is_premium`カラムは`users`テーブルに追加しない。Cashierが`subscriptions`/`subscription_items`テーブルを管理し、状態は`$user->subscribed('default')`で判定する(状態の二重管理・同期ズレを避ける)。

`User`モデルに`Laravel\Cashier\Billable`トレイトを追加する。Cashierのマイグレーション(`stripe_id`・`pm_type`・`pm_last_four`・`trial_ends_at`を`users`に追加、`subscriptions`・`subscription_items`テーブルを新規作成)を導入する。

### 環境変数

既存の`.env`には`STRIPE_PUBLIC_KEY`/`STRIPE_SECRET_KEY`が開発用キーとして設定済み。Cashierのデフォルト変数名(`STRIPE_KEY`/`STRIPE_SECRET`)とは異なるため、`.env`の変数名は変更せず`config/cashier.php`側で既存の変数名を参照するようマッピングする。Webhook検証用の`STRIPE_WEBHOOK_SECRET`は本番Webhookエンドポイント作成時に発行されるため、ローカル開発時は未設定でも動作するようにする(Cashierは未設定時、署名検証をスキップする)。

## プラン設計

| プラン | 価格 | Stripe Price | 請求間隔 |
|---|---|---|---|
| 月額 | ¥600(税込) | `price_monthly`(Stripeダッシュボードで作成) | 1ヶ月ごと |
| 年額 | ¥6,600(税込) | `price_yearly`(Stripeダッシュボードで作成) | 1年ごと |

Price IDは環境ごとに異なる(開発用Stripeアカウントと本番アカウントでID体系が変わる)ため、`.env`に`STRIPE_PRICE_MONTHLY`・`STRIPE_PRICE_YEARLY`として保持し、`config/cashier.php`または専用の`config/premium.php`から参照する。

## Premiumの境界線(今回の合意事項)

### 1. バッチ処理件数の上限

画像系ツールのうち、複数ファイルの一括処理に対応する6ツール(画像圧縮・画像形式変換・画像リサイズ・画像の回転・画像から色を抽出・OGP画像作成)が対象。

- 無料: 1リクエストあたり最大3ファイル
- Premium: 1リクエストあたり最大10ファイル(現行の上限のまま)

画像の切り抜き・favicon生成はもともと単一ファイル処理のため対象外(現状維持)。

実装方針: 各コントローラーの`private const MAX_FILE_COUNT = 10;`を、リクエストごとに`$request->user()?->subscribed('default') ? 10 : 3`で決定する値に置き換える。定数を直接使っている6箇所を修正する。

### 2. レート制限の緩和

画像系ツールの`throttle`ミドルウェア(現状`throttle:10,1`など、IP単位)を、ログイン済みPremiumユーザーはより緩い制限が適用されるよう変更する。

- 無料/未ログイン: 現状維持(IP単位、現行の分間上限)
- Premium(ログイン中): ユーザー単位で現行の3倍程度に緩和(例: 10回/分 → 30回/分)

実装方針: `RateLimiter::for('image-tools', ...)`のようなカスタムリミッターを`AppServiceProvider`に定義し、`$request->user()?->subscribed('default')`で分岐する。ルート側の`throttle:10,1`をこの名前付きリミッターに置き換える。画像系の全コントローラーのルート定義(routes/web.php)を一括で見直す。

### 3. マイページ(Premium限定)

自分自身のツール利用履歴を確認できる画面を、Premium会員限定の特典として新設する。

- URL: `/mypage`
- 中身は、Phase 20で作った`Admin\UserController@show`のツール利用履歴表示と同じ仕組みを流用し、対象を`auth()->user()`に固定したもの(Adminのアカウント状態カード相当の表示は不要、自分の`tool_events`一覧のみ)
- ミドルウェア: `auth`(Fortifyの`web`ガード)+ 新設する`EnsurePremium`ミドルウェア(未加入なら`/premium`へリダイレクト)

## 決済フロー

1. ログイン済みユーザーが`/premium`ページで「月額で登録」または「年額で登録」を選択
2. `PremiumController@checkout`が`$user->newSubscription('default', $priceId)->checkout()`を呼び出し、Stripe Checkoutセッションへリダイレクト
3. ユーザーがStripeのホスト画面でカード情報を入力し決済完了
4. Stripeが`checkout.session.completed`・`customer.subscription.created`等のWebhookイベントを`/stripe/webhook`(Cashierが自動登録)に送信、Cashierが`subscriptions`テーブルへ反映
5. 決済完了後、Stripeの`success_url`(`/premium/thanks`等)にリダイレクト

### 解約・プラン変更

- `/mypage/billing`(または`/settings/billing`)に「請求ポータルを開く」ボタンを設置し、`$user->billingPortalUrl(route('...'))`で生成したURLへリンクするのみ
- 解約はStripe Billing Portal上で完結。解約後もCashierの`subscription->onGracePeriod()`が`true`の間(支払い済み期間の終了まで)は`subscribed('default')`が`true`を返すため、追加実装なしで「期間末まで利用可」の挙動になる

## エラーハンドリング

- Checkoutセッション作成時にStripe APIエラーが発生した場合、フラッシュメッセージで「決済ページの作成に失敗しました。時間をおいて再度お試しください。」を表示し`/premium`に戻す
- Webhook処理はCashierの`WebhookController`に委譲するため、署名検証エラー等は同パッケージの既定動作(400エラー)に従う
- 画像系ツールでPremium判定ができない(未ログイン)場合は、常に無料枠(3件・現行レート制限)として扱う(エラーにはしない)

## 未確定・要準備(実装前に必要)

- **特定商取引法に基づく表記ページ**: 有料サービス提供には法的に必須。事業者名・所在地・連絡先・価格・支払い方法・返金ポリシー・提供時期などをユーザー(事業者)から提供してもらう必要がある。ページ自体(`/legal/tokushoho`)の雛形は実装時に作成できるが、内容確定はブロッカーになる。
- **Stripeダッシュボードでの商品/価格作成**: 月額¥600・年額¥6,600のPrice IDを開発用・本番用それぞれのStripeアカウントで作成する必要がある(ユーザー側の作業)。
- **本番Webhookエンドポイントの登録**: 本番デプロイ時にStripeダッシュボードでWebhookエンドポイントを登録し、`STRIPE_WEBHOOK_SECRET`を本番`.env`に設定する。

## テスト

- `tests/Feature/Premium/CheckoutTest.php`: `/premium`ページの表示、Checkoutセッション作成(Stripe APIはモック)
- `tests/Feature/Premium/PremiumGateTest.php`: 画像系6ツールのバッチ上限が無料/Premiumで切り替わることを確認(`$user->subscribed('default')`をモックまたはCashierのテストヘルパーで擬似的にPremium状態を作る)
- `tests/Feature/Premium/RateLimitTest.php`: レート制限が無料/Premiumで異なる値になることを確認
- `tests/Feature/MyPageControllerTest.php`: 未加入ユーザーは`/mypage`にアクセスできず`/premium`へリダイレクトされること、Premiumユーザーは自分の`tool_events`のみが表示されること
- 既存の画像系Featureテスト(8コントローラー分)に、無料ユーザー/Premiumユーザーそれぞれでバッチ上限が異なることを確認するケースを追加

## スコープ外

- Stripe Taxによる自動税額計算
- 海外通貨・多言語対応
- クーポン・紹介コードなどのプロモーション機能
- 年額プランのみの追加特典(バッチ上限・レート制限はプラン共通、月額/年額で差をつけない)
- Premiumの境界線の将来的な追加(新機能のPremium先行提供など)は本設計のスコープ外、必要になった時点で個別に検討する

## 実装順序

1. Cashier導入(`composer require laravel/cashier`、マイグレーション、`User`モデルへの`Billable`トレイト追加、`config/cashier.php`の環境変数マッピング)
2. `/premium`ページ + Checkoutフロー(月額・年額それぞれの登録導線)
3. Billing Portalへのリンク設置(`/mypage/billing`等)
4. Premiumの境界線実装: ①バッチ上限の切り替え(6コントローラー) → ②レート制限の切り替え → ③マイページ(`/mypage`)
5. 特定商取引法ページの雛形作成(内容はユーザーからの情報提供待ち)
6. 一連のテスト整備・`composer ci:check`
7. Playwrightでの実ブラウザ確認(Stripeテストモードのテストカードを使った決済フロー、Premium/無料それぞれでのバッチ上限・レート制限の切り替わり)
