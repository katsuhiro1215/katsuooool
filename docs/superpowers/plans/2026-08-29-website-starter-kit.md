# Webサイトスターターキット(MVP) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** サイト名・ロゴ・業種などを入力すると、favicon一式・OGP画像・推奨title・meta タグ・HTML head一括コードをまとめて生成する新ツール「Webサイトスターターキット」(`/website-starter`)を追加する。

**Architecture:** 既存の`FaviconGenerateController`/`OgpImageController`と同じService層(`FaviconGenerateService`/`OgpImageService`)をそのまま再利用し、新規`WebsiteStarterController`から呼び出す。titleやmeta/HTML head文字列の組み立てだけを担当する新規`WebsiteStarterService`を追加する。業種・都道府県・ターゲット層・目的の4項目のみを新規テーブル`website_starter_submissions`に長期保存し、サイト名・URL・ロゴ・説明文は保存しない。ログイン必須(`auth`ミドルウェア)。

**Tech Stack:** Laravel 13 / Inertia.js v2 / Vue 3 / TypeScript / Pest / Intervention Image(既存Service経由)

**Spec:** `docs/superpowers/specs/2026-08-29-website-starter-kit-design.md`

## Global Constraints

- ドキュメント成果物は日本語で作成する(CLAUDE.md)。
- ログイン必須(`auth`ミドルウェア)。匿名利用は不可。
- `website_starter_submissions`に保存するのは業種・都道府県・ターゲット層(年代/性別)・目的のみ。サイト名・URL・ロゴ・meta descriptionは一切保存しない。
- 画像アップロードの上限は既存ツールと同じ10MB・25,000,000ピクセル。
- レートリミッターは既存の`throttle:image-tools`をそのまま使う(新規の名前付きリミッターは追加しない)。
- AIによる自由記述の構造化・Webサイト診断・チェックリスト化は本計画のスコープ外(将来のPhase)。

---

## Task 1: 設定・DBモデルの追加(業種/都道府県/ターゲット層/目的)

**Files:**
- Create: `config/website_starter.php`
- Create: `database/migrations/2026_08_29_000001_create_website_starter_submissions_table.php`
- Create: `app/Models/WebsiteStarterSubmission.php`
- Test: `tests/Feature/Models/WebsiteStarterSubmissionTest.php`

**Interfaces:**
- Produces: `config('website_starter.industries')`(連想配列 `string $key => string $label`)、`config('website_starter.prefectures')`(`list<string>`)、`config('website_starter.target_age_ranges')`・`config('website_starter.target_genders')`・`config('website_starter.purposes')`(いずれも連想配列 `string $key => string $label`)。`App\Models\WebsiteStarterSubmission`(`fillable`: `user_id`, `industry`, `prefecture`, `target_age_range`, `target_gender`, `purpose`。`updated_at`カラムなし)。

- [ ] **Step 1: `config/website_starter.php`を作成**

```php
<?php

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

    'prefectures' => [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ],

    'target_age_ranges' => [
        '10s' => '10代',
        '20s' => '20代',
        '30s' => '30代',
        '40s' => '40代',
        '50s' => '50代',
        '60s' => '60代以上',
        'all' => '指定なし',
    ],

    'target_genders' => [
        'male' => '男性',
        'female' => '女性',
        'all' => '指定なし',
    ],

    'purposes' => [
        'new_customers' => '新規顧客獲得',
        'hiring' => '採用',
        'branding' => 'ブランディング',
        'information' => '情報発信',
        'reservation' => '予約受付',
    ],
];
```

- [ ] **Step 2: migrationを作成**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_starter_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('industry');
            $table->string('prefecture');
            $table->string('target_age_range')->nullable();
            $table->string('target_gender')->nullable();
            $table->string('purpose')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['industry', 'prefecture']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_starter_submissions');
    }
};
```

- [ ] **Step 3: migrationを実行**

Run: `php artisan migrate`
Expected: `website_starter_submissions`テーブルが作成される

- [ ] **Step 4: 失敗するテストを書く**

```php
<?php

use App\Models\User;
use App\Models\WebsiteStarterSubmission;

test('only the whitelisted category columns are mass-assignable', function () {
    $user = User::factory()->create();

    $submission = WebsiteStarterSubmission::create([
        'user_id' => $user->id,
        'industry' => 'beauty',
        'prefecture' => '奈良県',
        'target_age_range' => '20s',
        'target_gender' => 'female',
        'purpose' => 'new_customers',
        'site_name' => 'この値は保存されないはず',
    ]);

    expect($submission->fresh())
        ->user_id->toBe($user->id)
        ->industry->toBe('beauty')
        ->prefecture->toBe('奈良県')
        ->target_age_range->toBe('20s')
        ->target_gender->toBe('female')
        ->purpose->toBe('new_customers');

    expect($submission->getAttributes())->not->toHaveKey('site_name');
});

test('target fields are nullable', function () {
    $user = User::factory()->create();

    $submission = WebsiteStarterSubmission::create([
        'user_id' => $user->id,
        'industry' => 'corporate',
        'prefecture' => '東京都',
    ]);

    expect($submission->fresh())
        ->target_age_range->toBeNull()
        ->target_gender->toBeNull()
        ->purpose->toBeNull();
});
```

- [ ] **Step 5: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Models/WebsiteStarterSubmissionTest.php`
Expected: FAIL(`App\Models\WebsiteStarterSubmission`が存在しない)

- [ ] **Step 6: `App\Models\WebsiteStarterSubmission`を実装**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebsiteStarterSubmission extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'industry',
        'prefecture',
        'target_age_range',
        'target_gender',
        'purpose',
    ];
}
```

- [ ] **Step 7: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Models/WebsiteStarterSubmissionTest.php`
Expected: PASS

- [ ] **Step 8: コミット**

```bash
git add config/website_starter.php database/migrations/2026_08_29_000001_create_website_starter_submissions_table.php app/Models/WebsiteStarterSubmission.php tests/Feature/Models/WebsiteStarterSubmissionTest.php
git commit -m "feat: add website_starter_submissions table and category config"
```

---

## Task 2: title/meta/HTML head組み立てロジック(`WebsiteStarterService`)

**Files:**
- Create: `app/Services/Tools/WebsiteStarterService.php`
- Test: `tests/Feature/Tools/WebsiteStarterServiceTest.php`

**Interfaces:**
- Consumes: なし(純粋なロジック)
- Produces: `App\Services\Tools\WebsiteStarterService::buildTitle(string $siteName, string $industryLabel, string $prefecture): string`、`::buildMetaTagsBlock(string $title, string $description, string $siteUrl): string`、`::buildHtmlHeadBlock(string $title, string $description, string $siteUrl): string`。Task 3のコントローラーがこの3メソッドを呼び出す。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Services\Tools\WebsiteStarterService;

test('buildTitle formats prefecture, industry label, and site name', function () {
    $service = new WebsiteStarterService;

    $title = $service->buildTitle('株式会社カツコード', '美容・サロン', '奈良県');

    expect($title)->toBe('奈良県の美容・サロン｜株式会社カツコード');
});

test('buildMetaTagsBlock includes description and OGP tags with escaped values', function () {
    $service = new WebsiteStarterService;

    $block = $service->buildMetaTagsBlock(
        title: '奈良県の美容・サロン｜"かつ" & Co.',
        description: '奈良市の美容室です。',
        siteUrl: 'https://example.com',
    );

    expect($block)
        ->toContain('<meta name="description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:title" content="奈良県の美容・サロン｜&quot;かつ&quot; &amp; Co.">')
        ->toContain('<meta property="og:description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:type" content="website">')
        ->toContain('<meta property="og:url" content="https://example.com">')
        ->toContain('og:image');
});

test('buildHtmlHeadBlock includes title, canonical, favicon links, and the meta tags block', function () {
    $service = new WebsiteStarterService;

    $block = $service->buildHtmlHeadBlock(
        title: '奈良県の美容・サロン｜株式会社カツコード',
        description: '奈良市の美容室です。',
        siteUrl: 'https://example.com',
    );

    expect($block)
        ->toContain('<title>奈良県の美容・サロン｜株式会社カツコード</title>')
        ->toContain('<link rel="canonical" href="https://example.com">')
        ->toContain('<link rel="icon" href="/favicon.ico">')
        ->toContain('<link rel="apple-touch-icon" href="/apple-touch-icon.png">')
        ->toContain('<meta name="description" content="奈良市の美容室です。">')
        ->toContain('<meta property="og:type" content="website">');
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/WebsiteStarterServiceTest.php`
Expected: FAIL(`App\Services\Tools\WebsiteStarterService`が存在しない)

- [ ] **Step 3: `WebsiteStarterService`を実装**

```php
<?php

namespace App\Services\Tools;

class WebsiteStarterService
{
    public function buildTitle(string $siteName, string $industryLabel, string $prefecture): string
    {
        return "{$prefecture}の{$industryLabel}｜{$siteName}";
    }

    public function buildMetaTagsBlock(string $title, string $description, string $siteUrl): string
    {
        $escapedTitle = $this->escape($title);
        $escapedDescription = $this->escape($description);
        $escapedUrl = $this->escape($siteUrl);

        return implode("\n", [
            '<meta name="description" content="'.$escapedDescription.'">',
            '<meta property="og:title" content="'.$escapedTitle.'">',
            '<meta property="og:description" content="'.$escapedDescription.'">',
            '<meta property="og:type" content="website">',
            '<meta property="og:url" content="'.$escapedUrl.'">',
            '<!-- ダウンロードしたOGP画像を配置したURLに置き換えてください -->',
            '<meta property="og:image" content="">',
        ]);
    }

    public function buildHtmlHeadBlock(string $title, string $description, string $siteUrl): string
    {
        $escapedTitle = $this->escape($title);
        $escapedUrl = $this->escape($siteUrl);

        return implode("\n", [
            '<title>'.$escapedTitle.'</title>',
            '<link rel="canonical" href="'.$escapedUrl.'">',
            '<!-- ダウンロードしたfaviconファイルを配置したパスに置き換えてください -->',
            '<link rel="icon" href="/favicon.ico">',
            '<link rel="apple-touch-icon" href="/apple-touch-icon.png">',
            $this->buildMetaTagsBlock($title, $description, $siteUrl),
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
```

- [ ] **Step 4: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/WebsiteStarterServiceTest.php`
Expected: PASS

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/WebsiteStarterService.php tests/Feature/Tools/WebsiteStarterServiceTest.php
git commit -m "feat: add WebsiteStarterService for title/meta/head generation"
```

---

## Task 3: `WebsiteStarterController`とルーティング

**Files:**
- Create: `app/Http/Controllers/Tools/WebsiteStarterController.php`
- Modify: `routes/web.php`
- Modify: `config/tools.php`
- Test: `tests/Feature/Tools/WebsiteStarterControllerTest.php`

**Interfaces:**
- Consumes: `App\Services\Tools\FaviconGenerateService::generate(string $inputPath, string $outputDir): array`(Task前提、既存)、`App\Services\Tools\OgpImageService::generate(string $inputPath, string $outputPath, string $mimeType): int`(既存)、`App\Services\Tools\WebsiteStarterService::buildTitle()/buildMetaTagsBlock()/buildHtmlHeadBlock()`(Task 2)、`App\Models\WebsiteStarterSubmission::create()`(Task 1)、`App\Services\Tools\ToolDownloadService::zip()/signedUrl()`(既存)、`App\Services\Tools\ToolEventLogger::log()`(既存)。
- Produces: `GET /website-starter`(ルート名`website-starter.show`)、`POST /website-starter`(ルート名`website-starter.process`)。レスポンスJSON形状:成功時`{status: 'success', title: string, favicon_download_url: string, ogp_image_download_url: string, meta_tags: string, html_head: string}`、失敗時`{status: 'failed', reason: string}`。Task 5のフロントエンドがこの形状を消費する。

- [ ] **Step 1: 失敗するテストを書く**

```php
<?php

use App\Models\ToolEvent;
use App\Models\User;
use App\Models\WebsiteStarterSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postWebsiteStarter(User $user, array $overrides = [])
{
    $payload = array_merge([
        'site_name' => '株式会社カツコード',
        'site_url' => 'https://example.com',
        'industry' => 'beauty',
        'prefecture' => '奈良県',
        'target_age_range' => '20s',
        'target_gender' => 'female',
        'purpose' => 'new_customers',
        'logo' => UploadedFile::fake()->image('logo.png', 1000, 1000),
        'meta_description' => '奈良市の美容室です。カット・カラー・ヘッドスパを提供しています。',
    ], $overrides);

    return test()->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('website-starter.process'), $payload);
}

test('the website-starter page renders industry/prefecture/target options as props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('website-starter.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('WebsiteStarter')
        ->has('industries')
        ->has('prefectures')
        ->has('targetAgeRanges')
        ->has('targetGenders')
        ->has('purposes'));
});

test('a guest is redirected to login', function () {
    $response = $this->get(route('website-starter.show'));

    $response->assertRedirect(route('login'));
});

test('a valid submission stores only category fields and returns generated assets', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('title', '奈良県の美容・サロン｜株式会社カツコード');
    $response->assertJsonPath('favicon_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));
    $response->assertJsonPath('ogp_image_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));
    $response->assertJsonPath('meta_tags', fn ($value) => is_string($value) && str_contains($value, 'og:title'));
    $response->assertJsonPath('html_head', fn ($value) => is_string($value) && str_contains($value, '<title>'));

    expect(WebsiteStarterSubmission::count())->toBe(1);
    $submission = WebsiteStarterSubmission::first();
    expect($submission)
        ->user_id->toBe($user->id)
        ->industry->toBe('beauty')
        ->prefecture->toBe('奈良県')
        ->target_age_range->toBe('20s')
        ->target_gender->toBe('female')
        ->purpose->toBe('new_customers');

    expect(ToolEvent::where('tool_slug', 'website-starter')->where('event_type', 'process')->exists())->toBeTrue();
});

test('an invalid industry is rejected and no submission is stored', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['industry' => 'not-a-real-industry']);

    $response->assertStatus(422);
    expect(WebsiteStarterSubmission::count())->toBe(0);
});

test('an invalid prefecture is rejected', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['prefecture' => '存在しない県']);

    $response->assertStatus(422);
});

test('a missing logo is rejected', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['logo' => null]);

    $response->assertStatus(422);
});

test('target_age_range, target_gender, and purpose are optional', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, [
        'target_age_range' => null,
        'target_gender' => null,
        'purpose' => null,
    ]);

    $response->assertSuccessful();
    $submission = WebsiteStarterSubmission::first();
    expect($submission)
        ->target_age_range->toBeNull()
        ->target_gender->toBeNull()
        ->purpose->toBeNull();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/WebsiteStarterControllerTest.php`
Expected: FAIL(ルート`website-starter.show`が存在しない)

- [ ] **Step 3: `WebsiteStarterController`を実装**

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use App\Models\WebsiteStarterSubmission;
use App\Services\Tools\FaviconGenerateService;
use App\Services\Tools\OgpImageService;
use App\Services\Tools\ToolDownloadService;
use App\Services\Tools\ToolEventLogger;
use App\Services\Tools\WebsiteStarterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class WebsiteStarterController extends Controller
{
    private const MAX_FILE_BYTES = 10 * 1024 * 1024;

    private const MAX_PIXELS = 25_000_000;

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
        FaviconGenerateService $faviconService,
        OgpImageService $ogpImageService,
        WebsiteStarterService $starterService,
        ToolDownloadService $downloadService,
        ToolEventLogger $eventLogger,
    ): JsonResponse {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'site_url' => ['required', 'url', 'max:255'],
            'industry' => ['required', Rule::in(array_keys(config('website_starter.industries')))],
            'prefecture' => ['required', Rule::in(config('website_starter.prefectures'))],
            'target_age_range' => ['nullable', Rule::in(array_keys(config('website_starter.target_age_ranges')))],
            'target_gender' => ['nullable', Rule::in(array_keys(config('website_starter.target_genders')))],
            'purpose' => ['nullable', Rule::in(array_keys(config('website_starter.purposes')))],
            'logo' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(self::MAX_FILE_BYTES / 1024),
            ],
            'meta_description' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        WebsiteStarterSubmission::create([
            'user_id' => $user->id,
            'industry' => $validated['industry'],
            'prefecture' => $validated['prefecture'],
            'target_age_range' => $validated['target_age_range'] ?? null,
            'target_gender' => $validated['target_gender'] ?? null,
            'purpose' => $validated['purpose'] ?? null,
        ]);

        $logo = $request->file('logo');
        $batchId = (string) Str::uuid();
        $disk = Storage::disk('local');

        $disk->makeDirectory("tool-tmp/{$batchId}/source");
        $disk->makeDirectory("tool-tmp/{$batchId}/favicons");
        $disk->makeDirectory("tool-tmp/{$batchId}/ogp");

        $extension = $logo->guessExtension() ?? $logo->getClientOriginalExtension();
        $sourceStoredName = Str::uuid().'.'.$extension;
        $sourceRelativePath = "tool-tmp/{$batchId}/source/{$sourceStoredName}";

        $disk->putFileAs("tool-tmp/{$batchId}/source", $logo, $sourceStoredName);

        $eventLogger->log(
            toolSlug: 'website-starter',
            eventType: 'upload',
            data: ['file_count' => 1],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $user->id,
        );

        try {
            $dimensions = getimagesize($disk->path($sourceRelativePath));

            if ($dimensions === false) {
                throw new RuntimeException('Failed to read image dimensions.');
            }

            if ($dimensions[0] * $dimensions[1] > self::MAX_PIXELS) {
                $eventLogger->log(
                    toolSlug: 'website-starter',
                    eventType: 'failed',
                    data: ['file_count' => 1],
                    ip: $request->ip(),
                    userAgent: $request->userAgent(),
                    userId: $user->id,
                );

                return response()->json([
                    'status' => 'failed',
                    'reason' => '画像の解像度が大きすぎます。',
                ]);
            }

            $favicons = $faviconService->generate(
                inputPath: $disk->path($sourceRelativePath),
                outputDir: $disk->path("tool-tmp/{$batchId}/favicons"),
            );

            $zipEntries = array_map(
                fn (array $favicon) => [
                    'path' => "tool-tmp/{$batchId}/favicons/{$favicon['filename']}",
                    'download_name' => $favicon['filename'],
                ],
                $favicons,
            );

            $faviconZipRelativePath = $downloadService->zip($batchId, $zipEntries, 'favicon.zip');
            $faviconDownloadUrl = $downloadService->signedUrl($faviconZipRelativePath, 'favicon.zip');

            $ogpMimeType = match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'webp' => 'image/webp',
                default => 'image/png',
            };
            $ogpExtension = match ($ogpMimeType) {
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                default => 'png',
            };
            $ogpRelativePath = "tool-tmp/{$batchId}/ogp/ogp-image.{$ogpExtension}";

            $ogpImageService->generate(
                inputPath: $disk->path($sourceRelativePath),
                outputPath: $disk->path($ogpRelativePath),
                mimeType: $ogpMimeType,
            );

            $ogpDownloadUrl = $downloadService->signedUrl($ogpRelativePath, "ogp-image.{$ogpExtension}");
        } catch (Throwable) {
            $eventLogger->log(
                toolSlug: 'website-starter',
                eventType: 'failed',
                data: ['file_count' => 1],
                ip: $request->ip(),
                userAgent: $request->userAgent(),
                userId: $user->id,
            );

            return response()->json([
                'status' => 'failed',
                'reason' => '素材の生成に失敗しました。',
            ]);
        }

        $industryLabel = config('website_starter.industries')[$validated['industry']];
        $title = $starterService->buildTitle($validated['site_name'], $industryLabel, $validated['prefecture']);
        $metaTags = $starterService->buildMetaTagsBlock($title, $validated['meta_description'], $validated['site_url']);
        $htmlHead = $starterService->buildHtmlHeadBlock($title, $validated['meta_description'], $validated['site_url']);

        $eventLogger->log(
            toolSlug: 'website-starter',
            eventType: 'process',
            data: ['file_count' => 1, 'input_bytes' => $logo->getSize()],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
            userId: $user->id,
        );

        return response()->json([
            'status' => 'success',
            'title' => $title,
            'favicon_download_url' => $faviconDownloadUrl,
            'ogp_image_download_url' => $ogpDownloadUrl,
            'meta_tags' => $metaTags,
            'html_head' => $htmlHead,
        ]);
    }
}
```

- [ ] **Step 4: ルートを追加**

`routes/web.php`の`use`宣言に以下を追加(既存の`use App\Http\Controllers\Tools\...`のアルファベット順の並びに合わせる):

```php
use App\Http\Controllers\Tools\WebsiteStarterController;
```

`favicon-generate`のルート定義の直後に以下を追加:

```php
Route::get('/website-starter', [WebsiteStarterController::class, 'show'])
    ->middleware('auth')
    ->name('website-starter.show');

Route::post('/website-starter', [WebsiteStarterController::class, 'process'])
    ->middleware(['auth', 'throttle:image-tools'])
    ->name('website-starter.process');
```

- [ ] **Step 5: `config/tools.php`にエントリを追加**

`generator`カテゴリの並び(`ogp-image`・`favicon-generate`・`generator-dummy-data`)の末尾に追加:

```php
[
    'slug' => 'website-starter',
    'name' => 'Webサイトスターターキット',
    'category' => 'generator',
    'description' => 'サイト名・ロゴ・業種を入力するだけで、favicon・meta タグ・OGP画像をまとめて生成します。',
    'href' => '/website-starter',
],
```

- [ ] **Step 6: テストを実行して成功を確認**

Run: `./vendor/bin/pest tests/Feature/Tools/WebsiteStarterControllerTest.php`
Expected: PASS

- [ ] **Step 7: PHPStan・Pintを実行**

Run: `composer test`
Expected: Pint/PHPStan/Pestすべてグリーン

- [ ] **Step 8: コミット**

```bash
git add app/Http/Controllers/Tools/WebsiteStarterController.php routes/web.php config/tools.php tests/Feature/Tools/WebsiteStarterControllerTest.php
git commit -m "feat: add WebsiteStarterController and /website-starter route"
```

---

## Task 4: フロントエンド`WebsiteStarter.vue`

**Files:**
- Create: `resources/js/pages/WebsiteStarter.vue`
- Create: `resources/js/pages/WebsiteStarter.test.ts`

**Interfaces:**
- Consumes: `GET /website-starter`のInertia props(`industries: Record<string,string>`, `prefectures: string[]`, `targetAgeRanges: Record<string,string>`, `targetGenders: Record<string,string>`, `purposes: Record<string,string>`)、`POST /website-starter`のレスポンス(Task 3で定義したJSON形状)、`checkDescriptionLength`(`@/lib/seoTextCheck`、既存)。
- Produces: `WebsiteStarter`という名前のInertiaページコンポーネント(Task 3の`Inertia::render('WebsiteStarter', ...)`と対応)。

- [ ] **Step 1: 失敗するコンポーネントテストを書く**

```typescript
import { mount } from '@vue/test-utils';
import { describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<div />' },
    Link: { template: '<a><slot /></a>' },
}));

import WebsiteStarter from './WebsiteStarter.vue';

const baseProps = {
    industries: { beauty: '美容・サロン', corporate: '企業サイト' },
    prefectures: ['奈良県', '東京都'],
    targetAgeRanges: { '20s': '20代', all: '指定なし' },
    targetGenders: { female: '女性', all: '指定なし' },
    purposes: { new_customers: '新規顧客獲得' },
};

describe('WebsiteStarter', () => {
    test('shows a character counter for the meta description field', async () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const textarea = wrapper.find('textarea#meta-description');
        await textarea.setValue('奈良市の美容室です。');

        expect(wrapper.text()).toContain('文字数');
    });

    test('renders an option for every industry passed in props', () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const options = wrapper.findAll('select#industry option');
        expect(options.map((option) => option.text())).toContain('美容・サロン');
        expect(options.map((option) => option.text())).toContain('企業サイト');
    });

    test('the generate button stays disabled until required fields are filled', async () => {
        const wrapper = mount(WebsiteStarter, { props: baseProps });

        const button = wrapper.find('button[type="button"].generate-button');
        expect(button.attributes('disabled')).toBeDefined();
    });
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `npx vitest run resources/js/pages/WebsiteStarter.test.ts`
Expected: FAIL(`./WebsiteStarter.vue`が存在しない)

- [ ] **Step 3: `WebsiteStarter.vue`を実装**

```vue
<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

import PageHeader from '@/components/Public/PageHeader.vue';
import PageMain from '@/components/Public/PageMain.vue';
import AppButton from '@/components/tools/AppButton.vue';
import PublicLayout from '@/layouts/Public/PublicLayout.vue';
import { checkDescriptionLength } from '@/lib/seoTextCheck';

const props = defineProps<{
    industries: Record<string, string>;
    prefectures: string[];
    targetAgeRanges: Record<string, string>;
    targetGenders: Record<string, string>;
    purposes: Record<string, string>;
}>();

interface SuccessResponse {
    status: 'success';
    title: string;
    favicon_download_url: string;
    ogp_image_download_url: string;
    meta_tags: string;
    html_head: string;
}

interface FailedResponse {
    status: 'failed';
    reason: string;
}

type GenerateResponse = SuccessResponse | FailedResponse;

const siteName = ref('');
const siteUrl = ref('');
const industry = ref('');
const prefecture = ref('');
const targetAgeRange = ref('');
const targetGender = ref('');
const purpose = ref('');
const metaDescription = ref('');
const logoFile = ref<File | null>(null);

const isProcessing = ref(false);
const errorMessage = ref('');
const response = ref<GenerateResponse | null>(null);
const generatedTitle = ref('');
const copiedField = ref('');

const descriptionResult = computed(() =>
    checkDescriptionLength(metaDescription.value),
);

const canGenerate = computed(
    () =>
        !isProcessing.value &&
        siteName.value.trim() !== '' &&
        siteUrl.value.trim() !== '' &&
        industry.value !== '' &&
        prefecture.value !== '' &&
        metaDescription.value.trim() !== '' &&
        logoFile.value !== null,
);

function onLogoChange(event: Event) {
    const target = event.target as HTMLInputElement;
    logoFile.value = target.files && target.files.length > 0 ? target.files[0] : null;
}

async function generate() {
    if (!canGenerate.value || !logoFile.value) {
        return;
    }

    isProcessing.value = true;
    errorMessage.value = '';
    response.value = null;

    const formData = new FormData();
    formData.append('site_name', siteName.value);
    formData.append('site_url', siteUrl.value);
    formData.append('industry', industry.value);
    formData.append('prefecture', prefecture.value);
    if (targetAgeRange.value) formData.append('target_age_range', targetAgeRange.value);
    if (targetGender.value) formData.append('target_gender', targetGender.value);
    if (purpose.value) formData.append('purpose', purpose.value);
    formData.append('meta_description', metaDescription.value);
    formData.append('logo', logoFile.value);

    try {
        const { data } = await axios.post<GenerateResponse>(
            '/website-starter',
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );

        response.value = data;
        if (data.status === 'success') {
            generatedTitle.value = data.title;
        }
    } catch (error) {
        errorMessage.value =
            axios.isAxiosError(error) && error.response?.status === 422
                ? '入力内容をご確認ください。'
                : '生成に失敗しました。時間をおいて再度お試しください。';
    } finally {
        isProcessing.value = false;
    }
}

async function copyText(text: string, field: string) {
    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) copiedField.value = '';
        }, 2000);
    } catch {
        // クリップボードAPIが使えない環境では何もしない
    }
}
</script>

<template>
    <Head title="Webサイトスターターキット - katsuooool" />

    <PublicLayout>
        <div class="min-h-screen w-full">
            <PageHeader
                title="Webサイトスターターキット"
                description="サイト名・ロゴ・業種を入力するだけで、favicon・meta タグ・OGP画像をまとめて生成します。"
                narrow
            />

            <PageMain narrow>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="site-name">サイト名</label>
                        <input
                            id="site-name"
                            v-model="siteName"
                            type="text"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="site-url">サイトURL</label>
                        <input
                            id="site-url"
                            v-model="siteUrl"
                            type="url"
                            placeholder="https://example.com"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="industry">業種</label>
                        <select
                            id="industry"
                            v-model="industry"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択してください</option>
                            <option
                                v-for="(label, key) in props.industries"
                                :key="key"
                                :value="key"
                            >
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="prefecture">都道府県</label>
                        <select
                            id="prefecture"
                            v-model="prefecture"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択してください</option>
                            <option v-for="value in props.prefectures" :key="value" :value="value">
                                {{ value }}
                            </option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="target-age">ターゲット年代(任意)</label>
                            <select
                                id="target-age"
                                v-model="targetAgeRange"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">選択しない</option>
                                <option
                                    v-for="(label, key) in props.targetAgeRanges"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700" for="target-gender">ターゲット性別(任意)</label>
                            <select
                                id="target-gender"
                                v-model="targetGender"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            >
                                <option value="">選択しない</option>
                                <option
                                    v-for="(label, key) in props.targetGenders"
                                    :key="key"
                                    :value="key"
                                >
                                    {{ label }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="purpose">サイトの目的(任意)</label>
                        <select
                            id="purpose"
                            v-model="purpose"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        >
                            <option value="">選択しない</option>
                            <option v-for="(label, key) in props.purposes" :key="key" :value="key">
                                {{ label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="logo">ロゴ画像</label>
                        <input
                            id="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="mt-1 w-full text-sm"
                            @change="onLogoChange"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700" for="meta-description">
                            サイトの説明(meta description)
                        </label>
                        <textarea
                            id="meta-description"
                            v-model="metaDescription"
                            rows="3"
                            class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                        />
                        <p class="mt-1 text-xs text-gray-500">
                            文字数: {{ descriptionResult.characterCount }}
                            <span v-if="!descriptionResult.withinRecommended" class="text-amber-600">
                                (推奨160字程度を超えています)
                            </span>
                        </p>
                    </div>

                    <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>

                    <AppButton
                        type="button"
                        class="generate-button w-full"
                        :disabled="!canGenerate"
                        @click="generate"
                    >
                        {{ isProcessing ? '生成しています...' : 'まとめて生成' }}
                    </AppButton>
                </div>

                <div v-if="response" class="mt-8 space-y-6">
                    <template v-if="response.status === 'success'">
                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <label class="block text-sm font-medium text-gray-700" for="generated-title">推奨title</label>
                            <input
                                id="generated-title"
                                v-model="generatedTitle"
                                type="text"
                                class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm"
                            />
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(generatedTitle, 'title')"
                            >
                                {{ copiedField === 'title' ? 'コピーしました' : 'コピー' }}
                            </AppButton>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <p class="text-sm font-medium text-gray-700">favicon一式</p>
                            <AppButton as="a" :href="response.favicon_download_url" class="mt-2 inline-block">
                                まとめてダウンロード(ZIP)
                            </AppButton>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <p class="text-sm font-medium text-gray-700">OGP画像</p>
                            <AppButton as="a" :href="response.ogp_image_download_url" class="mt-2 inline-block">
                                ダウンロード
                            </AppButton>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <p class="text-sm font-medium text-gray-700">meta タグ</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs">{{ response.meta_tags }}</pre>
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(response.meta_tags, 'meta')"
                            >
                                {{ copiedField === 'meta' ? 'コピーしました' : 'コピー' }}
                            </AppButton>
                        </div>

                        <div class="rounded-md border border-gray-200 bg-white p-4">
                            <p class="text-sm font-medium text-gray-700">HTML head一括コード</p>
                            <pre class="mt-2 overflow-x-auto rounded bg-gray-50 p-3 text-xs">{{ response.html_head }}</pre>
                            <AppButton
                                type="button"
                                class="mt-2"
                                @click="copyText(response.html_head, 'head')"
                            >
                                {{ copiedField === 'head' ? 'コピーしました' : 'コピー' }}
                            </AppButton>
                        </div>

                        <div class="rounded-lg bg-gray-900 px-6 py-8 text-center text-white">
                            <p class="text-base font-bold">Webサイトの準備ができました</p>
                            <p class="mt-2 text-sm text-gray-300">
                                サイト本体の制作についてもご相談いただけます。
                            </p>
                            <a
                                href="/contact"
                                class="mt-4 inline-block rounded-md bg-white px-6 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100"
                            >
                                制作のご相談はこちら
                            </a>
                        </div>
                    </template>
                    <p v-else class="text-sm text-red-600">{{ response.reason }}</p>
                </div>
            </PageMain>
        </div>
    </PublicLayout>
</template>
```

- [ ] **Step 4: テストを実行して成功を確認**

Run: `npx vitest run resources/js/pages/WebsiteStarter.test.ts`
Expected: PASS

- [ ] **Step 5: 型チェック・Lint・Formatを実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン(formatエラーが出た場合は`npm run format`を実行してから再確認)

- [ ] **Step 6: コミット**

```bash
git add resources/js/pages/WebsiteStarter.vue resources/js/pages/WebsiteStarter.test.ts
git commit -m "feat: add WebsiteStarter.vue page"
```

---

## Task 5: トップページへの導線(`WebsiteStarterBanner.vue`)

**Files:**
- Create: `resources/js/components/WebsiteStarterBanner.vue`
- Create: `resources/js/components/WebsiteStarterBanner.test.ts`
- Modify: `resources/js/pages/Home.vue`

**Interfaces:**
- Consumes: なし(静的バナー、`@inertiajs/vue3`の`Link`のみ)
- Produces: `Home.vue`に組み込まれるバナーコンポーネント

- [ ] **Step 1: 失敗するコンポーネントテストを書く**

```typescript
import { mount } from '@vue/test-utils';
import { describe, expect, test, vi } from 'vitest';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href"><slot /></a>',
    },
}));

import WebsiteStarterBanner from './WebsiteStarterBanner.vue';

describe('WebsiteStarterBanner', () => {
    test('links to /website-starter', () => {
        const wrapper = mount(WebsiteStarterBanner);

        const link = wrapper.find('a');
        expect(link.attributes('href')).toBe('/website-starter');
    });
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `npx vitest run resources/js/components/WebsiteStarterBanner.test.ts`
Expected: FAIL(`./WebsiteStarterBanner.vue`が存在しない)

- [ ] **Step 3: `WebsiteStarterBanner.vue`を実装**

```vue
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div
        class="mt-8 rounded-lg border border-gray-200 bg-white px-6 py-8 text-center sm:px-12"
    >
        <p class="text-lg font-bold text-gray-900">
            Webサイトに必要なものをまとめて準備
        </p>
        <p class="mt-2 text-sm text-gray-600">
            サイト名・ロゴ・業種を入力するだけ。favicon・meta
            タグ・OGP画像をまとめて生成します。
        </p>
        <Link
            href="/website-starter"
            class="mt-4 inline-block rounded-md bg-gray-900 px-6 py-2 text-sm font-medium text-white hover:bg-gray-700"
        >
            Webサイトを準備する
        </Link>
    </div>
</template>
```

- [ ] **Step 4: テストを実行して成功を確認**

Run: `npx vitest run resources/js/components/WebsiteStarterBanner.test.ts`
Expected: PASS

- [ ] **Step 5: `Home.vue`に組み込む**

`resources/js/pages/Home.vue`の`import PremiumBanner from '@/components/PremiumBanner.vue';`の直後に追加:

```typescript
import WebsiteStarterBanner from '@/components/WebsiteStarterBanner.vue';
```

`<PremiumBanner />`の直後に追加:

```vue
<WebsiteStarterBanner />
```

- [ ] **Step 6: 型チェック・Lint・Formatを実行**

Run: `npm run types:check && npm run lint:check && npm run format:check`
Expected: すべてグリーン

- [ ] **Step 7: コミット**

```bash
git add resources/js/components/WebsiteStarterBanner.vue resources/js/components/WebsiteStarterBanner.test.ts resources/js/pages/Home.vue
git commit -m "feat: add Website Starter Kit banner to Home page"
```

---

## Task 6: プライバシーポリシーへの追記

**Files:**
- Modify: `resources/js/pages/legal/PrivacyPolicy.vue`

**Interfaces:**
- Consumes: 既存の`SECTIONS`配列構造(Phase 31で`PublicLayout`化済み)
- Produces: なし(表示テキストの追加のみ)

- [ ] **Step 1: `SECTIONS`配列に1項目追加し、以降の見出し番号を繰り下げる**

`resources/js/pages/legal/PrivacyPolicy.vue`の`SECTIONS`配列は現在、次の9見出しで構成されている:「1. 基本方針」「2. 取得する情報」「3. 利用目的」「4. Cookieの使用について」「5. 第三者提供」「6. データの保存期間」「7. 開示・訂正・削除等のご請求」「8. 本ポリシーの変更」「9. お問い合わせ窓口」。

「3. 利用目的」セクションの直後(「4. Cookieの使用について」の直前)に、次のオブジェクトを挿入する:

```typescript
{
    heading: '4. Webサイトスターターキットのカテゴリデータの取り扱い',
    paragraphs: [
        '「Webサイトスターターキット」機能のご利用時に入力いただく業種・都道府県・ターゲット層・サイトの目的は、サービス改善および統計的な分析目的で保存します。サイト名・URL・ロゴ画像・meta description等、個々のサイトやお客様を特定できる情報は保存しません。',
    ],
},
```

挿入した上で、既存の見出し文字列を次の通り書き換える(数字部分のみの変更、`paragraphs`の内容は変更しない):

- `'4. Cookieの使用について'` → `'5. Cookieの使用について'`
- `'5. 第三者提供'` → `'6. 第三者提供'`
- `'6. データの保存期間'` → `'7. データの保存期間'`
- `'7. 開示・訂正・削除等のご請求'` → `'8. 開示・訂正・削除等のご請求'`
- `'8. 本ポリシーの変更'` → `'9. 本ポリシーの変更'`
- `'9. お問い合わせ窓口'` → `'10. お問い合わせ窓口'`

- [ ] **Step 2: フォーマット・Lintを確認**

Run: `npm run format:check && npm run lint:check`
Expected: グリーン

- [ ] **Step 3: コミット**

```bash
git add resources/js/pages/legal/PrivacyPolicy.vue
git commit -m "docs: note Website Starter Kit category-data retention in privacy policy"
```

---

## Task 7: 最終検証

**Files:**
- なし(検証のみ)

**Interfaces:**
- Consumes: Task 1〜6ですべて実装済みの機能一式
- Produces: なし

- [ ] **Step 1: バックエンド全体チェック**

Run: `composer test`
Expected: Pint/PHPStan/Pestすべてグリーン

- [ ] **Step 2: フロントエンド全体チェック**

Run: `npm run lint:check && npm run format:check && npm run types:check && npx vitest run`
Expected: すべてグリーン

- [ ] **Step 3: 開発サーバーを起動しPlaywrightで実ブラウザ確認**

`php artisan serve`と`npm run dev`を起動し、Playwrightで以下を確認する:
- 未ログインで`/website-starter`にアクセスするとログイン画面にリダイレクトされること
- ログイン後、トップページに「Webサイトを準備する」バナーが表示されていること、クリックで`/website-starter`に遷移すること
- `/website-starter`のフォームにサイト名・URL・業種・都道府県・ロゴ・meta descriptionを入力し「まとめて生成」を押すと、推奨title・favicon一式ダウンロード・OGP画像ダウンロード・meta タグ・HTML head一括コード・「制作のご相談はこちら」CTAが表示されること
- favicon一式のZIPダウンロード、OGP画像のダウンロードが実際に機能すること
- meta タグ・HTML headの「コピー」ボタンでクリップボードにコピーされること

- [ ] **Step 4: 開発サーバーを停止**

```bash
pkill -f "artisan serve"; pkill -f "vite"
```
