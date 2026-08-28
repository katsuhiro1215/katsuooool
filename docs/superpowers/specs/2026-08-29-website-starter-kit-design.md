# Webサイトスターターキット(MVP) 設計書

## 背景・目的

katsuooolはこれまで「便利なツールを集めたサイト」として画像・色・CSS・Pattern等のツールを提供してきたが、Webサイト制作の初心者は「そもそも何を用意すればいいか分からない」という問題を抱えている。サイト名・ロゴ・簡単な情報を入力するだけで、favicon・meta タグ・OGP画像・推奨titleといった「Webサイト制作の最初の一歩」に必要な素材を一括生成する新ツール「Webサイトスターターキット」を追加する。

この機能には2つの狙いがある。

1. **ユーザー価値**:初心者が個別に調べながら用意する必要がある基礎素材を、フォーム入力だけでまとめて揃えられる。
2. **事業価値**:入力される業種・地域・ターゲット層などのカテゴリデータを蓄積することで、将来的に「どんな業種のサイト制作ニーズが多いか」が見え、KatsuCodeのマーケティングに活用できる。また生成完了後の導線としてKatsuCodeへの問い合わせを自然に提示できる。

将来的にはAIによる自由記述からの構造化・Webサイト診断・チェックリスト化・AI Web Assistant化まで段階的に発展させる構想があるが、**本設計はStage 1(AIなし・機械的生成のみ)のMVPに限定する**。AI関連機能は本スコープに含めない。

## スコープ

- 対象:新規ツール「Webサイトスターターキット」(`/website-starter`)の追加。
- ログイン必須(`auth`ミドルウェア)。匿名利用は不可。
- 入力:サイト名・URL・業種・都道府県・ターゲット層(任意)・サイトの目的(任意)・ロゴ画像・meta description(直接入力)・キャッチコピー(任意)。
- 出力:推奨title(編集可能)・favicon一式(zipダウンロード)・OGP画像(ダウンロード)・meta タグ(コピー)・HTML head一括コード(コピー)・KatsuCodeへの問い合わせCTA。
- 業種・都道府県・ターゲット層・サイトの目的のみ`website_starter_submissions`に長期保存し、集計に使えるようにする。サイト名・URL・ロゴ・meta description・キャッチコピーは保存しない。
- AIによる自由記述の構造化、Webサイト診断機能、チェックリスト化は本スコープ外(将来のPhase)。
- Admin側の集計ダッシュボード(業種別・地域別の件数グラフ等)は本スコープ外。まずはデータを貯める設計のみ行い、可視化は別途ブレインストーミングする。

## データモデル

### `website_starter_submissions`(新規テーブル)

```php
Schema::create('website_starter_submissions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('industry');
    $table->string('prefecture');
    $table->string('target_age_range')->nullable(); // 例: '20s', '30s', 'all' 等
    $table->string('target_gender')->nullable();     // 例: 'male', 'female', 'all'
    $table->string('purpose')->nullable();            // 例: 'new_customers', 'hiring', 'branding'
    $table->timestamp('created_at');

    $table->index(['industry', 'prefecture']);
});
```

`updated_at`は不要(生成のたびに1レコード追加するだけの記録用途で、更新は発生しない)。

`App\Models\WebsiteStarterSubmission`:

```php
class WebsiteStarterSubmission extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'industry', 'prefecture', 'target_age_range', 'target_gender', 'purpose'];
}
```

**保存期間について**:既存の`tool_events`は約30日で自動削除される運用だが、本テーブルは「将来の集計・マーケティング分析用データ」という目的のため、自動削除の対象**外**とし長期保存する。個人情報(氏名・メール・IP・サイト名・URL等)を一切含まないため、既存のプライバシー方針と矛盾しない。ただしプライバシーポリシー(`legal/PrivacyPolicy.vue`)に「業種・地域等のカテゴリ情報をサービス改善・分析目的で保存する」旨を追記する。

### 業種・都道府県・ターゲット層・目的の選択肢

`App\Enums`ではなく、既存の`config/tools.php`のような配列定義を新設する(`config/website_starter.php`)。

```php
return [
    'industries' => [
        'beauty' => '美容・サロン',
        'restaurant' => '飲食店',
        'construction' => '工務店・建築',
        'professional' => '士業',
        'ec' => 'EC',
        'school' => '教室・スクール',
        'medical' => '医療・クリニック',
        'corporate' => '企業サイト',
        'individual' => '個人事業',
        'other' => 'その他',
    ],
    'prefectures' => ['北海道', '青森県', /* ... 47都道府県 ... */ '沖縄県'],
    'target_age_ranges' => ['10s' => '10代', '20s' => '20代', /* ... */ '60s' => '60代以上', 'all' => '指定なし'],
    'target_genders' => ['male' => '男性', 'female' => '女性', 'all' => '指定なし'],
    'purposes' => [
        'new_customers' => '新規顧客獲得',
        'hiring' => '採用',
        'branding' => 'ブランディング',
        'information' => '情報発信',
        'reservation' => '予約受付',
    ],
];
```

フロントエンドには`show()`のInertia propsとして渡す(ハードコードの二重管理を避ける)。

## バックエンド設計

### `App\Http\Controllers\Tools\WebsiteStarterController`

```php
public function show(): Response
{
    return Inertia::render('WebsiteStarter', [
        'industries' => config('website_starter.industries'),
        'prefectures' => config('website_starter.prefectures'),
        'targetAgeRanges' => config('website_starter.target_age_ranges'),
        'targetGenders' => config('website_starter.target_genders'),
        'purposes' => config('website_starter.purposes'),
    ]);
}

public function process(
    Request $request,
    WebsiteStarterService $service,
    FaviconGenerateService $faviconService,
    OgpImageService $ogpImageService,
    ToolDownloadService $downloadService,
    ToolEventLogger $eventLogger,
): JsonResponse
```

`process()`のバリデーション:

```php
$request->validate([
    'site_name' => ['required', 'string', 'max:100'],
    'site_url' => ['required', 'url', 'max:255'],
    'industry' => ['required', Rule::in(array_keys(config('website_starter.industries')))],
    'prefecture' => ['required', Rule::in(config('website_starter.prefectures'))],
    'target_age_range' => ['nullable', Rule::in(array_keys(config('website_starter.target_age_ranges')))],
    'target_gender' => ['nullable', Rule::in(array_keys(config('website_starter.target_genders')))],
    'purpose' => ['nullable', Rule::in(array_keys(config('website_starter.purposes')))],
    'logo' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
    'meta_description' => ['required', 'string', 'max:160'],
    'tagline' => ['nullable', 'string', 'max:100'],
]);
```

処理の流れ:

1. `website_starter_submissions`に業種/都道府県/ターゲット層/目的のみ`create()`(`$request->user()->id`を紐付け)。
2. アップロードされたロゴを一時保存(既存ツールと同じ`tool-tmp/{batchId}/`配下)。
3. `FaviconGenerateService::generate()`を呼び出しfavicon一式を生成、zip化。
4. `OgpImageService::generate()`を呼び出しOGP画像(1200×630)を生成。
5. `WebsiteStarterService::buildTitle()`でtitleを組み立てる:`"{$prefectureLabel}の{$industryLabel}｜{$siteName}"`。
6. `WebsiteStarterService::buildMetaTags()`でmeta description・OGPタグ・HTML head一括コードの文字列を組み立てる(og:image・favicon linkのパスはプレースホルダー)。
7. `ToolEventLogger::log()`で`website-starter`ツールの利用イベントを記録(`tool_events`側、既存の匿名ログ運用のまま。個人を特定できるデータは含めない)。
8. レスポンスとして、生成titleの文字列・favicon zipのダウンロードURL・OGP画像のダウンロードURL・meta タグ文字列・HTML head文字列をJSONで返す。

### `App\Services\Tools\WebsiteStarterService`(新規)

```php
class WebsiteStarterService
{
    public function buildTitle(string $siteName, string $industryLabel, string $prefecture): string
    {
        return "{$prefecture}の{$industryLabel}｜{$siteName}";
    }

    public function buildMetaTagsBlock(array $params): string
    {
        // description / og:title / og:description / og:type / og:url / og:image(プレースホルダー) を
        // 改行区切りの1文字列として組み立てる
    }

    public function buildHtmlHeadBlock(array $params): string
    {
        // title / meta description / canonical / OGPタグ / favicon linkタグ(プレースホルダー) を
        // <head> にそのまま貼れる1つのコードブロックとして組み立てる
    }
}
```

og:image・favicon linkのプレースホルダーは、実際にユーザーが生成物を自分のサーバーへ配置した後のパスが分からないため、`<!-- ダウンロードしたfaviconファイルを配置したパスに置き換えてください: /favicon.ico -->`のようなコメント付きの相対パス例を出力する(完全なダミーではなく、実際に使うファイル名をそのまま使えるようにする)。

### ロゴ・生成物のライフサイクル

既存の画像系ツールと同じ`tool-tmp/{batchId}/`の仕組みをそのまま使う。ロゴ原本・生成されたfavicon/OGP画像は、既存のScheduler(短時間での自動削除)の対象に含める(新規の削除ロジックは不要、既存の`tool-tmp`ディレクトリ配下である限り自動的に対象になる)。

## フロントエンド設計

### `resources/js/pages/WebsiteStarter.vue`(新規)

既存ツールと同じ`PublicLayout` + `PageHeader` + `PageMain`構成。1ページ完結のフォーム→結果表示(別ページ遷移はしない、`ImageCompress.vue`等と同様の非同期処理パターン)。

**フォーム部分**:

- サイト名・サイトURL・キャッチコピー(任意):テキスト入力
- 業種・都道府県・サイトの目的(任意):`<select>`(propsで渡された選択肢を使用)
- ターゲット層(任意):年代`<select>`+性別`<select>`の2つ(どちらも「指定なし」を含む)
- ロゴ:ファイル選択(既存の画像アップロードUIパターンを流用)
- meta description:`<textarea>`+文字数カウンター(既存`SeoDescriptionCheck.vue`の文字数カウンター表示ロジックを流用、推奨120〜160文字の目安を表示)
- 「まとめて生成」ボタン

**結果部分**(生成成功後に表示):

1. 推奨title:編集可能な`<input>`+「コピー」ボタン
2. favicon一式:「ダウンロード」ボタン(zip)
3. OGP画像:プレビュー画像+「ダウンロード」ボタン
4. meta タグ:`<pre>`表示+「コピー」ボタン
5. HTML head一括コード:`<pre>`表示+「コピー」ボタン
6. CTAセクション:「Webサイトの準備ができました。制作のご相談はこちら」+`/contact`へのリンク(新規コンポーネント不要、既存の`Contact`ルートへの`<Link>`のみ)

処理中は既存の`ProcessingBanner`パターンを流用してスピナー表示する。

### `resources/js/components/WebsiteStarterBanner.vue`(新規)

`PremiumBanner.vue`と同じ構成のバナーコンポーネント。`Home.vue`の`PremiumBanner`の近くに追加する(ログイン有無に関わらず表示し、未ログインでリンクをクリックした場合は`auth`ミドルウェアによって自然にログイン画面へリダイレクトされる。バナー側で特別な出し分けは行わない)。

```vue
<template>
    <div class="mt-8 rounded-lg border border-gray-200 bg-white px-6 py-8 text-center sm:px-12">
        <p class="text-lg font-bold text-gray-900">Webサイトに必要なものをまとめて準備</p>
        <p class="mt-2 text-sm text-gray-600">
            サイト名・ロゴ・業種を入力するだけ。favicon・meta タグ・OGP画像をまとめて生成します。
        </p>
        <Link href="/website-starter" class="mt-4 inline-block rounded-md bg-gray-900 px-6 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Webサイトを準備する
        </Link>
    </div>
</template>
```

### `config/tools.php`への追加

```php
[
    'slug' => 'website-starter',
    'name' => 'Webサイトスターターキット',
    'category' => 'generator',
    'description' => 'サイト名・ロゴ・業種を入力するだけで、favicon・meta タグ・OGP画像をまとめて生成します。',
    'href' => '/website-starter',
],
```

## ルーティング

```php
Route::middleware('auth')->group(function () {
    Route::get('/website-starter', [WebsiteStarterController::class, 'show'])->name('website-starter.show');
    Route::post('/website-starter', [WebsiteStarterController::class, 'process'])
        ->middleware('throttle:10,1')
        ->name('website-starter.process');
});
```

他の重い処理系ツール(画像アップロード系)と同水準のthrottleを設定する。ログイン必須のため、匿名連打によるスパムリスクは他ツールより低い。

## エラーハンドリング・エッジケース

- ロゴの解像度が大きすぎる場合・画像処理に失敗した場合は、既存の`FaviconGenerateController`/`OgpImageController`と同じエラーメッセージ・イベントログ(`failed`)のパターンを踏襲する。
- `website_starter_submissions`への保存は、favicon/OGP生成より前に行う。仮に画像処理が失敗しても、集計データ自体は保存されたままにする(ユーザーの入力自体は価値のあるデータのため、生成失敗と切り離す)。
- meta descriptionが160文字を超える入力は、バリデーションエラーではなく「推奨文字数を超えています」という警告表示に留め、生成自体はブロックしない(SEO上の目安であり、必須要件ではないため)。
- 都道府県・業種のプルダウンは`config/website_starter.php`の値のみを許可し、それ以外はバリデーションエラーとする(集計データの一貫性を担保するため自由入力にしない)。

## テスト方針

- `WebsiteStarterService`のユニットテスト:`buildTitle()`が想定通りのフォーマットで文字列を組み立てること、`buildMetaTagsBlock()`/`buildHtmlHeadBlock()`が必要なタグをすべて含むこと。
- `WebsiteStarterController`のFeatureテスト:
  - 未ログインでは`/website-starter`が認証画面にリダイレクトされること。
  - 正常系:`website_starter_submissions`に1件保存され、`user_id`が紐付くこと。保存される列がカテゴリ項目のみで、サイト名・URL・meta descriptionが含まれないこと。
  - 業種・都道府県に選択肢以外の値を渡すとバリデーションエラーになること。
  - ロゴ未添付・不正なmimetypeでバリデーションエラーになること。
  - 生成成功時にfavicon zipのダウンロードURL・OGP画像のダウンロードURL・title文字列・meta タグ文字列がレスポンスに含まれること。
- `WebsiteStarterSubmission`モデル:`fillable`の範囲外(サイト名等)が渡されてもマスアサインメントされないこと。
- `WebsiteStarterBanner.vue`のコンポーネントテスト:リンク先が`/website-starter`であることの確認。
- `WebsiteStarter.vue`のコンポーネントテスト:meta descriptionの文字数カウンター表示、生成後の各コピー ボタンの動作(クリップボードAPIをモック)。

## 影響範囲まとめ(実装計画のためのファイルリスト予告)

- Migration:`website_starter_submissions`新規作成
- `config/website_starter.php`(新規、業種/都道府県/ターゲット層/目的の選択肢定義)
- `config/tools.php`:新規ツールのエントリ追加
- `app/Models/WebsiteStarterSubmission.php`(新規)
- `app/Services/Tools/WebsiteStarterService.php`(新規、title/meta/HTML head組み立てロジック)
- `app/Http/Controllers/Tools/WebsiteStarterController.php`(新規、既存`FaviconGenerateService`/`OgpImageService`を再利用)
- `routes/web.php`:`/website-starter`のGET/POST(`auth`+`throttle`ミドルウェア)追加
- `resources/js/pages/WebsiteStarter.vue`(新規)
- `resources/js/components/WebsiteStarterBanner.vue`(新規)
- `resources/js/pages/Home.vue`:バナー組み込み
- `resources/js/pages/legal/PrivacyPolicy.vue`:カテゴリデータの長期保存について追記
- テスト一式(上記テスト方針を参照)
