# Phase 0 基盤整備 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** katsuooool の Phase 1(画像圧縮ツール)・Phase 2(Admin)を実装できる状態にするため、Admin認証基盤・イベントログ基盤・ツール共通Service層・トップページの雛形を作る。

**Architecture:** 既存の Laravel 13 + Inertia v2 + Vue 3 スターターキット(Fortify認証継続)に、`admins` guardによる管理者専用認証、匿名利用も記録できる `tool_events` テーブル、ファイルの一時保存・検証・ダウンロード・自動削除を担う共通Serviceを追加する。トップページは shadcn-vue を使わず Tailwind CSS のみで独自実装する。

**Tech Stack:** PHP 8.3 / Laravel 13 / Pest 4 / Inertia.js v2 / Vue 3 + TypeScript / Tailwind CSS / MySQL(本番・ローカルとも)

**Spec:** `/Users/katsuhiro.k1215/Project/katsuooool/SPEC.md`(セクション 4〜6, 9)/ `TASKS.md`(Phase 0)

## Global Constraints

- 本番は Xserver共有サーバー。`exec()`・シェルコマンド実行・常駐キューワーカーは使用不可という前提で実装する(SPEC.md §2)。
- 一時ファイルの保存先は `storage/app/private/tool-tmp/{uuid}/`(Webから直接アクセス不可)(SPEC.md §5)。
- アップロードの実体MIMEチェックを必須とする。許可形式は画像圧縮ツールでは JPEG/PNG/WebP のみ(SPEC.md §5, §7)。
- イベントログのIPアドレスは日次ソルト付きハッシュで保存し、生IPは保存しない(SPEC.md §4.2, §9)。
- 新規ツール画面・トップページは shadcn-vue(reka-ui)を使わず Tailwind CSS で独自実装する(CLAUDE.md「UI方針」)。
- 既存のログイン・登録・設定画面(Fortifyベース)は変更しない。
- モデルの `$fillable` / `$hidden` は本コードベースの慣例に従い PHP属性(`#[Fillable]` / `#[Hidden]`)で定義する(`app/Models/User.php` 参照)。
- テストは Pest。`tests/Pest.php` の設定により `tests/Feature` 配下は自動的に `RefreshDatabase` が適用される。

---

## Task 1: 未使用の laravel/breeze 依存を削除する

**Files:**
- Modify: `composer.json`

**Interfaces:**
- なし(依存関係の整理のみ)

- [ ] **Step 1: composer.json から laravel/breeze を削除**

`composer.json` の `require-dev` から以下の行を削除する。

```json
        "laravel/breeze": "^2.4",
```

- [ ] **Step 2: composer update を実行**

Run: `composer update laravel/breeze --with-all-dependencies`

Expected: `composer.lock` が更新され、`laravel/breeze` パッケージが削除される。

- [ ] **Step 3: アプリが正常に起動することを確認**

Run: `php artisan about`

Expected: エラーなくアプリケーション情報が出力される(Fortify関連のサービスプロバイダも問題なくロードされる)。

- [ ] **Step 4: コミット**

```bash
git add composer.json composer.lock
git commit -m "chore: remove unused laravel/breeze dependency"
```

---

## Task 2: Admin認証基盤(admins テーブル・モデル・guard)

**Files:**
- Create: `database/migrations/2026_08_16_000001_create_admins_table.php`
- Create: `app/Models/Admin.php`
- Modify: `config/auth.php`
- Test: `tests/Feature/Admin/AdminGuardTest.php`

**Interfaces:**
- Produces: `App\Models\Admin`(`Authenticatable`実装。カラム: `id`, `name`, `email`, `password`, `remember_token`, `created_at`, `updated_at`)、`auth:admin` ミドルウェアで使用する `admin` guard、`admins` provider

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/AdminGuardTest.php` を作成:

```php
<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

test('admin can authenticate via the admin guard', function () {
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(Auth::guard('admin')->attempt([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))->toBeTrue();

    expect(Auth::guard('admin')->id())->toBe($admin->id);
});

test('the default web guard does not authenticate admins', function () {
    Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password'),
    ]);

    expect(Auth::guard('web')->attempt([
        'email' => 'admin@example.com',
        'password' => 'password',
    ]))->toBeFalse();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=AdminGuardTest`
Expected: FAIL(`admins` テーブルが存在しない / `App\Models\Admin` が存在しないエラー)

- [ ] **Step 3: マイグレーションを作成**

`database/migrations/2026_08_16_000001_create_admins_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admins');
    }
};
```

- [ ] **Step 4: Admin モデルを作成**

`app/Models/Admin.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class Admin extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }
}
```

- [ ] **Step 5: config/auth.php に admin guard と provider を追加**

`config/auth.php` の `use` 文に追加:

```php
use App\Models\Admin;
use App\Models\User;
```

`'guards'` 配列を以下のように変更:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'admin' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],
```

`'providers'` 配列に追加:

```php
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
    ],
```

- [ ] **Step 6: マイグレーションを実行しテストを再実行**

Run: `php artisan migrate && php artisan test --filter=AdminGuardTest`
Expected: PASS(2 tests)

- [ ] **Step 7: コミット**

```bash
git add database/migrations/2026_08_16_000001_create_admins_table.php app/Models/Admin.php config/auth.php tests/Feature/Admin/AdminGuardTest.php
git commit -m "feat: add admins table, Admin model, and admin auth guard"
```

---

## Task 3: admin:create Artisanコマンド

**Files:**
- Create: `app/Console/Commands/CreateAdminCommand.php`
- Test: `tests/Feature/Admin/CreateAdminCommandTest.php`

**Interfaces:**
- Consumes: `App\Models\Admin`(Task 2)
- Produces: `admin:create {name} {email} {password}` Artisanコマンド

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Admin/CreateAdminCommandTest.php`:

```php
<?php

use App\Models\Admin;

test('admin:create creates a new admin account', function () {
    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'password1234',
    ])->assertSuccessful();

    $admin = Admin::where('email', 'katsuhiro@example.com')->first();

    expect($admin)->not->toBeNull();
    expect($admin->name)->toBe('Katsuhiro');
});

test('admin:create rejects a duplicate email', function () {
    Admin::create([
        'name' => 'Existing',
        'email' => 'katsuhiro@example.com',
        'password' => bcrypt('password1234'),
    ]);

    $this->artisan('admin:create', [
        'name' => 'Katsuhiro',
        'email' => 'katsuhiro@example.com',
        'password' => 'password1234',
    ])->assertFailed();

    expect(Admin::count())->toBe(1);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=CreateAdminCommandTest`
Expected: FAIL(コマンド `admin:create` が存在しない)

- [ ] **Step 3: コマンドを実装**

`app/Console/Commands/CreateAdminCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create {name} {email} {password}';

    protected $description = 'Create a new admin account for the /admin dashboard';

    public function handle(): int
    {
        $validator = Validator::make(
            [
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
                'password' => $this->argument('password'),
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $admin = Admin::create([
            'name' => $this->argument('name'),
            'email' => $this->argument('email'),
            'password' => Hash::make($this->argument('password')),
        ]);

        $this->info("Admin account created: {$admin->email}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: テストを再実行**

Run: `php artisan test --filter=CreateAdminCommandTest`
Expected: PASS(2 tests)

- [ ] **Step 5: コミット**

```bash
git add app/Console/Commands/CreateAdminCommand.php tests/Feature/Admin/CreateAdminCommandTest.php
git commit -m "feat: add admin:create artisan command"
```

---

## Task 4: tool_events テーブルとモデル

**Files:**
- Create: `database/migrations/2026_08_16_000002_create_tool_events_table.php`
- Create: `app/Models/ToolEvent.php`
- Test: `tests/Feature/Tools/ToolEventModelTest.php`

**Interfaces:**
- Produces: `App\Models\ToolEvent`(カラム: `id`, `tool_slug`, `event_type`, `file_count`, `input_bytes`, `output_bytes`, `ip_hash`, `user_agent`, `created_at`。`updated_at` は持たない)

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/ToolEventModelTest.php`:

```php
<?php

use App\Models\ToolEvent;

test('a tool event can be created and does not track updated_at', function () {
    $event = ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'upload',
        'file_count' => 3,
        'input_bytes' => 1_000_000,
        'output_bytes' => null,
        'ip_hash' => hash('sha256', 'test'),
        'user_agent' => 'PestTest/1.0',
    ]);

    expect($event->exists)->toBeTrue();
    expect($event->created_at)->not->toBeNull();
    expect(ToolEvent::UPDATED_AT)->toBeNull();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=ToolEventModelTest`
Expected: FAIL(`tool_events` テーブルが存在しない)

- [ ] **Step 3: マイグレーションを作成**

`database/migrations/2026_08_16_000002_create_tool_events_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tool_events', function (Blueprint $table) {
            $table->id();
            $table->string('tool_slug')->index();
            $table->string('event_type');
            $table->unsignedInteger('file_count')->nullable();
            $table->unsignedBigInteger('input_bytes')->nullable();
            $table->unsignedBigInteger('output_bytes')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_events');
    }
};
```

- [ ] **Step 4: モデルを作成**

`app/Models/ToolEvent.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $tool_slug
 * @property string $event_type
 * @property int|null $file_count
 * @property int|null $input_bytes
 * @property int|null $output_bytes
 * @property string|null $ip_hash
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 */
#[Fillable(['tool_slug', 'event_type', 'file_count', 'input_bytes', 'output_bytes', 'ip_hash', 'user_agent'])]
class ToolEvent extends Model
{
    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }
}
```

- [ ] **Step 5: マイグレーションを実行しテストを再実行**

Run: `php artisan migrate && php artisan test --filter=ToolEventModelTest`
Expected: PASS(1 test)

- [ ] **Step 6: コミット**

```bash
git add database/migrations/2026_08_16_000002_create_tool_events_table.php app/Models/ToolEvent.php tests/Feature/Tools/ToolEventModelTest.php
git commit -m "feat: add tool_events table and ToolEvent model"
```

---

## Task 5: ToolEventLogger サービス(IPハッシュ化)

**Files:**
- Create: `app/Services/Tools/ToolEventLogger.php`
- Test: `tests/Feature/Tools/ToolEventLoggerTest.php`

**Interfaces:**
- Consumes: `App\Models\ToolEvent`(Task 4)
- Produces: `App\Services\Tools\ToolEventLogger::log(string $toolSlug, string $eventType, array $data = [], ?string $ip = null, ?string $userAgent = null): ToolEvent`
  - `$data` は `file_count`, `input_bytes`, `output_bytes` キーを任意で受け取る
  - IPは生のまま保存せず、`hash('sha256', $ip . date('Y-m-d') . config('app.key'))` で日次ソルト付きハッシュ化する

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/ToolEventLoggerTest.php`:

```php
<?php

use App\Services\Tools\ToolEventLogger;

test('log records an event without storing the raw ip address', function () {
    $logger = new ToolEventLogger;

    $event = $logger->log(
        toolSlug: 'image-compress',
        eventType: 'upload',
        data: ['file_count' => 2, 'input_bytes' => 500_000],
        ip: '203.0.113.42',
        userAgent: 'PestTest/1.0',
    );

    expect($event->tool_slug)->toBe('image-compress');
    expect($event->event_type)->toBe('upload');
    expect($event->file_count)->toBe(2);
    expect($event->input_bytes)->toBe(500_000);
    expect($event->ip_hash)->not->toBeNull();
    expect($event->ip_hash)->not->toContain('203.0.113.42');
    expect(strlen($event->ip_hash))->toBe(64);
});

test('log produces the same hash for the same ip on the same day', function () {
    $logger = new ToolEventLogger;

    $first = $logger->log('image-compress', 'upload', ip: '203.0.113.42');
    $second = $logger->log('image-compress', 'upload', ip: '203.0.113.42');

    expect($first->ip_hash)->toBe($second->ip_hash);
});

test('log handles a null ip gracefully', function () {
    $logger = new ToolEventLogger;

    $event = $logger->log('image-compress', 'upload', ip: null);

    expect($event->ip_hash)->toBeNull();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=ToolEventLoggerTest`
Expected: FAIL(クラス `App\Services\Tools\ToolEventLogger` が存在しない)

- [ ] **Step 3: サービスを実装**

`app/Services/Tools/ToolEventLogger.php`:

```php
<?php

namespace App\Services\Tools;

use App\Models\ToolEvent;

class ToolEventLogger
{
    /**
     * @param  array{file_count?: int, input_bytes?: int, output_bytes?: int}  $data
     */
    public function log(
        string $toolSlug,
        string $eventType,
        array $data = [],
        ?string $ip = null,
        ?string $userAgent = null,
    ): ToolEvent {
        return ToolEvent::create([
            'tool_slug' => $toolSlug,
            'event_type' => $eventType,
            'file_count' => $data['file_count'] ?? null,
            'input_bytes' => $data['input_bytes'] ?? null,
            'output_bytes' => $data['output_bytes'] ?? null,
            'ip_hash' => $this->hashIp($ip),
            'user_agent' => $userAgent,
        ]);
    }

    private function hashIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        return hash('sha256', $ip.date('Y-m-d').config('app.key'));
    }
}
```

- [ ] **Step 4: テストを再実行**

Run: `php artisan test --filter=ToolEventLoggerTest`
Expected: PASS(3 tests)

- [ ] **Step 5: コミット**

```bash
git add app/Services/Tools/ToolEventLogger.php tests/Feature/Tools/ToolEventLoggerTest.php
git commit -m "feat: add ToolEventLogger service with hashed ip storage"
```

---

## Task 6: tool_events の自動削除コマンドとScheduler登録

**Files:**
- Create: `app/Console/Commands/PruneToolEventsCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Tools/PruneToolEventsCommandTest.php`

**Interfaces:**
- Consumes: `App\Models\ToolEvent`(Task 4)
- Produces: `tool-events:prune` Artisanコマンド。30日を超えた `tool_events` レコードを削除する。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/PruneToolEventsCommandTest.php`:

```php
<?php

use App\Models\ToolEvent;
use Illuminate\Support\Carbon;

test('tool-events:prune deletes events older than 30 days and keeps recent ones', function () {
    // created_at is not mass-assignable (it's not in ToolEvent's #[Fillable] list),
    // so it's set explicitly via forceFill() + save() after creation.
    $old = ToolEvent::create(['tool_slug' => 'image-compress', 'event_type' => 'upload']);
    $old->forceFill(['created_at' => Carbon::now()->subDays(31)])->save();

    $recent = ToolEvent::create(['tool_slug' => 'image-compress', 'event_type' => 'upload']);
    $recent->forceFill(['created_at' => Carbon::now()->subDays(10)])->save();

    $this->artisan('tool-events:prune')->assertSuccessful();

    expect(ToolEvent::find($old->id))->toBeNull();
    expect(ToolEvent::find($recent->id))->not->toBeNull();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=PruneToolEventsCommandTest`
Expected: FAIL(コマンドが存在しない)

- [ ] **Step 3: コマンドを実装**

`app/Console/Commands/PruneToolEventsCommand.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\ToolEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PruneToolEventsCommand extends Command
{
    protected $signature = 'tool-events:prune {--days=30 : Delete events older than this many days}';

    protected $description = 'Delete tool_events records older than the retention period';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        $deleted = ToolEvent::where('created_at', '<', Carbon::now()->subDays($days))->delete();

        $this->info("Deleted {$deleted} tool event(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: routes/console.php に Scheduler登録を追加**

`routes/console.php` に以下を追記(既存の `inspire` コマンド定義の下):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('tool-events:prune')->daily();
```

- [ ] **Step 5: テストを再実行**

Run: `php artisan test --filter=PruneToolEventsCommandTest`
Expected: PASS(1 test)

- [ ] **Step 6: コミット**

```bash
git add app/Console/Commands/PruneToolEventsCommand.php routes/console.php tests/Feature/Tools/PruneToolEventsCommandTest.php
git commit -m "feat: add tool-events:prune command and schedule it daily"
```

---

## Task 7: ToolUploadService(アップロード検証・一時保存)

**Files:**
- Create: `app/Services/Tools/ToolUploadService.php`
- Create: `app/Services/Tools/UploadedToolFile.php`
- Create: `app/Services/Tools/ToolUploadResult.php`
- Create: `app/Exceptions/Tools/TooManyFilesException.php`
- Test: `tests/Feature/Tools/ToolUploadServiceTest.php`

**Interfaces:**
- Produces:
  - `App\Services\Tools\UploadedToolFile`(readonly DTO: `string $path`, `string $originalName`, `int $size`, `string $mimeType`)
  - `App\Services\Tools\ToolUploadResult`(readonly DTO: `string $batchId`, `array<UploadedToolFile> $files`, `array<int,string> $rejected` — キーは入力配列のインデックス、値は拒否理由の文字列)
  - `App\Services\Tools\ToolUploadService::store(array $uploadedFiles, array $allowedMimeTypes, int $maxFileBytes, int $maxFileCount): ToolUploadResult`
    - `$uploadedFiles` は `Illuminate\Http\UploadedFile[]`
    - `$maxFileCount` を超える場合は `TooManyFilesException` を投げる
    - 個別ファイルがサイズ超過・許可MIME外の場合はその添字を `rejected` に理由付きで格納し、`files` には含めない(処理は継続)
    - 保存先は `local` ディスク(`storage/app/private`)配下の `tool-tmp/{batchId}/{uuid}.{ext}`

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/ToolUploadServiceTest.php`:

```php
<?php

use App\Exceptions\Tools\TooManyFilesException;
use App\Services\Tools\ToolUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('store saves valid files under a batch directory on the local disk', function () {
    $service = new ToolUploadService;

    $file = UploadedFile::fake()->image('photo.jpg', 100, 100)->size(50);

    $result = $service->store(
        uploadedFiles: [$file],
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->batchId)->not->toBeEmpty();
    expect($result->files)->toHaveCount(1);
    expect($result->rejected)->toBe([]);

    Storage::disk('local')->assertExists($result->files[0]->path);
    expect($result->files[0]->originalName)->toBe('photo.jpg');
});

test('store rejects a file that exceeds the size limit', function () {
    $service = new ToolUploadService;

    $tooLarge = UploadedFile::fake()->image('big.jpg')->size(11 * 1024);

    $result = $service->store(
        uploadedFiles: [$tooLarge],
        allowedMimeTypes: ['image/jpeg'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->files)->toBe([]);
    expect($result->rejected)->toHaveKey(0);
});

test('store rejects a file whose real content type is not allowed', function () {
    $service = new ToolUploadService;

    $textFile = UploadedFile::fake()->create('not-an-image.txt', 10, 'text/plain');

    $result = $service->store(
        uploadedFiles: [$textFile],
        allowedMimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    );

    expect($result->files)->toBe([]);
    expect($result->rejected)->toHaveKey(0);
});

test('store throws when the number of files exceeds the maximum', function () {
    $service = new ToolUploadService;

    $files = collect(range(1, 11))
        ->map(fn () => UploadedFile::fake()->image('photo.jpg')->size(10))
        ->all();

    expect(fn () => $service->store(
        uploadedFiles: $files,
        allowedMimeTypes: ['image/jpeg'],
        maxFileBytes: 10 * 1024 * 1024,
        maxFileCount: 10,
    ))->toThrow(TooManyFilesException::class);
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=ToolUploadServiceTest`
Expected: FAIL(関連クラスが存在しない)

- [ ] **Step 3: 例外クラスを作成**

`app/Exceptions/Tools/TooManyFilesException.php`:

```php
<?php

namespace App\Exceptions\Tools;

use RuntimeException;

class TooManyFilesException extends RuntimeException
{
    public static function make(int $count, int $max): self
    {
        return new self("Uploaded {$count} files, which exceeds the maximum of {$max}.");
    }
}
```

- [ ] **Step 4: DTOを作成**

`app/Services/Tools/UploadedToolFile.php`:

```php
<?php

namespace App\Services\Tools;

final readonly class UploadedToolFile
{
    public function __construct(
        public string $path,
        public string $originalName,
        public int $size,
        public string $mimeType,
    ) {}
}
```

`app/Services/Tools/ToolUploadResult.php`:

```php
<?php

namespace App\Services\Tools;

final readonly class ToolUploadResult
{
    /**
     * @param  array<int, UploadedToolFile>  $files
     * @param  array<int, string>  $rejected
     */
    public function __construct(
        public string $batchId,
        public array $files,
        public array $rejected,
    ) {}
}
```

- [ ] **Step 5: サービスを実装**

`app/Services/Tools/ToolUploadService.php`:

```php
<?php

namespace App\Services\Tools;

use App\Exceptions\Tools\TooManyFilesException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ToolUploadService
{
    /**
     * @param  array<int, UploadedFile>  $uploadedFiles
     * @param  array<int, string>  $allowedMimeTypes
     */
    public function store(
        array $uploadedFiles,
        array $allowedMimeTypes,
        int $maxFileBytes,
        int $maxFileCount,
    ): ToolUploadResult {
        if (count($uploadedFiles) > $maxFileCount) {
            throw TooManyFilesException::make(count($uploadedFiles), $maxFileCount);
        }

        $batchId = (string) Str::uuid();
        $files = [];
        $rejected = [];

        foreach ($uploadedFiles as $index => $uploadedFile) {
            if ($uploadedFile->getSize() > $maxFileBytes) {
                $rejected[$index] = 'ファイルサイズが上限を超えています。';

                continue;
            }

            $mimeType = $uploadedFile->getMimeType();

            if ($mimeType === null || ! in_array($mimeType, $allowedMimeTypes, true)) {
                $rejected[$index] = '対応していないファイル形式です。';

                continue;
            }

            $extension = $uploadedFile->guessExtension() ?? $uploadedFile->getClientOriginalExtension();
            $storedName = Str::uuid().'.'.$extension;
            $path = "tool-tmp/{$batchId}/{$storedName}";

            Storage::disk('local')->putFileAs(
                "tool-tmp/{$batchId}",
                $uploadedFile,
                $storedName,
            );

            $files[] = new UploadedToolFile(
                path: $path,
                originalName: $uploadedFile->getClientOriginalName(),
                size: $uploadedFile->getSize(),
                mimeType: $mimeType,
            );
        }

        return new ToolUploadResult($batchId, $files, $rejected);
    }
}
```

- [ ] **Step 6: テストを再実行**

Run: `php artisan test --filter=ToolUploadServiceTest`
Expected: PASS(4 tests)

- [ ] **Step 7: コミット**

```bash
git add app/Services/Tools/ToolUploadService.php app/Services/Tools/UploadedToolFile.php app/Services/Tools/ToolUploadResult.php app/Exceptions/Tools/TooManyFilesException.php tests/Feature/Tools/ToolUploadServiceTest.php
git commit -m "feat: add ToolUploadService for validated temp file uploads"
```

---

## Task 8: ダウンロード用ルート・コントローラーとToolDownloadService

**Files:**
- Create: `app/Services/Tools/ToolDownloadService.php`
- Create: `app/Http/Controllers/Tools/ToolDownloadController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Tools/ToolDownloadServiceTest.php`
- Test: `tests/Feature/Tools/ToolDownloadControllerTest.php`

**Interfaces:**
- Consumes: `UploadedToolFile`(Task 7)
- Produces:
  - `App\Services\Tools\ToolDownloadService::zip(string $batchId, array $files, string $zipFileName): string`(`local`ディスク上のZIPファイルへの相対パスを返す。`$files` は `array<int, array{path: string, download_name: string}>`)
  - `App\Services\Tools\ToolDownloadService::signedUrl(string $path, string $downloadName, int $expiresInMinutes = 60): string`
  - ルート名 `tools.download`(`GET /tool-download/{token}` に対応する署名付きURL)

- [ ] **Step 1: 失敗するテストを書く(ToolDownloadService)**

`tests/Feature/Tools/ToolDownloadServiceTest.php`:

```php
<?php

use App\Services\Tools\ToolDownloadService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('zip bundles multiple files into a single archive on the local disk', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');
    Storage::disk('local')->put('tool-tmp/batch-1/b.jpg', 'fake-image-b');

    $service = new ToolDownloadService;

    $zipPath = $service->zip('batch-1', [
        ['path' => 'tool-tmp/batch-1/a.jpg', 'download_name' => 'photo-a.jpg'],
        ['path' => 'tool-tmp/batch-1/b.jpg', 'download_name' => 'photo-b.jpg'],
    ], 'compressed-images.zip');

    Storage::disk('local')->assertExists($zipPath);
    expect($zipPath)->toEndWith('compressed-images.zip');
});

test('signedUrl generates a temporary signed URL for the download route', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $service = new ToolDownloadService;

    $url = $service->signedUrl('tool-tmp/batch-1/a.jpg', 'photo-a.jpg');

    expect($url)->toContain('/tool-download/');
    expect($url)->toContain('signature=');
});
```

- [ ] **Step 2: 失敗するテストを書く(ToolDownloadController)**

`tests/Feature/Tools/ToolDownloadControllerTest.php`:

```php
<?php

use App\Services\Tools\ToolDownloadService;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('a validly signed download URL streams the file', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $service = new ToolDownloadService;
    $url = $service->signedUrl('tool-tmp/batch-1/a.jpg', 'photo-a.jpg');

    $response = $this->get($url);

    $response->assertSuccessful();
});

test('a request without a valid signature is rejected', function () {
    Storage::disk('local')->put('tool-tmp/batch-1/a.jpg', 'fake-image-a');

    $response = $this->get('/tool-download/'.base64_encode('tool-tmp/batch-1/a.jpg').'?download_name=photo-a.jpg');

    $response->assertForbidden();
});

test('a path outside tool-tmp is rejected even with a valid signature', function () {
    $service = new ToolDownloadService;
    $url = $service->signedUrl('../.env', 'stolen.env');

    $response = $this->get($url);

    $response->assertNotFound();
});
```

- [ ] **Step 3: テストを実行して失敗を確認**

Run: `php artisan test --filter=ToolDownload`
Expected: FAIL(関連クラス・ルートが存在しない)

- [ ] **Step 4: ToolDownloadServiceを実装**

`app/Services/Tools/ToolDownloadService.php`:

```php
<?php

namespace App\Services\Tools;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use ZipArchive;

class ToolDownloadService
{
    /**
     * @param  array<int, array{path: string, download_name: string}>  $files
     */
    public function zip(string $batchId, array $files, string $zipFileName): string
    {
        $disk = Storage::disk('local');
        $zipRelativePath = "tool-tmp/{$batchId}/{$zipFileName}";
        $absoluteZipPath = $disk->path($zipRelativePath);

        $zip = new ZipArchive;
        $zip->open($absoluteZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $zip->addFile($disk->path($file['path']), $file['download_name']);
        }

        $zip->close();

        return $zipRelativePath;
    }

    public function signedUrl(string $path, string $downloadName, int $expiresInMinutes = 60): string
    {
        return URL::temporarySignedRoute(
            'tools.download',
            now()->addMinutes($expiresInMinutes),
            [
                'token' => base64_encode($path),
                'download_name' => $downloadName,
            ],
        );
    }
}
```

- [ ] **Step 5: ダウンロードコントローラーを実装**

`app/Http/Controllers/Tools/ToolDownloadController.php`:

```php
<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ToolDownloadController extends Controller
{
    public function show(Request $request, string $token): StreamedResponse
    {
        $path = base64_decode($token, strict: true);

        abort_if($path === false, 404);

        $normalized = str_replace('\\', '/', $path);

        abort_unless(str_starts_with($normalized, 'tool-tmp/'), 404);
        abort_if(str_contains($normalized, '..'), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($normalized), 404);

        $downloadName = $request->query('download_name', basename($normalized));

        return $disk->download($normalized, $downloadName);
    }
}
```

- [ ] **Step 6: ルートを追加**

`routes/web.php` に追記(`require __DIR__.'/auth.php';` の手前):

```php
use App\Http\Controllers\Tools\ToolDownloadController;

Route::get('/tool-download/{token}', [ToolDownloadController::class, 'show'])
    ->middleware('signed')
    ->name('tools.download');
```

- [ ] **Step 7: テストを再実行**

Run: `php artisan test --filter=ToolDownload`
Expected: PASS(5 tests)

- [ ] **Step 8: コミット**

```bash
git add app/Services/Tools/ToolDownloadService.php app/Http/Controllers/Tools/ToolDownloadController.php routes/web.php tests/Feature/Tools/ToolDownloadServiceTest.php tests/Feature/Tools/ToolDownloadControllerTest.php
git commit -m "feat: add signed download route and ToolDownloadService (zip + single file)"
```

---

## Task 9: 一時ファイルの自動削除コマンドとScheduler登録

**Files:**
- Create: `app/Console/Commands/PruneToolTempFilesCommand.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Tools/PruneToolTempFilesCommandTest.php`

**Interfaces:**
- Produces: `tool-tmp:prune {--hours=24}` Artisanコマンド。`storage/app/private/tool-tmp/{batchId}/` のうち、最終更新から指定時間を超えたディレクトリを削除する。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/Tools/PruneToolTempFilesCommandTest.php`:

```php
<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

test('tool-tmp:prune deletes batch directories older than the retention window', function () {
    $disk = Storage::disk('local');

    $disk->put('tool-tmp/old-batch/a.jpg', 'fake-old');
    $disk->put('tool-tmp/fresh-batch/b.jpg', 'fake-fresh');

    touch($disk->path('tool-tmp/old-batch/a.jpg'), now()->subHours(25)->getTimestamp());
    touch($disk->path('tool-tmp/fresh-batch/b.jpg'), now()->subHours(1)->getTimestamp());

    $this->artisan('tool-tmp:prune')->assertSuccessful();

    expect($disk->exists('tool-tmp/old-batch/a.jpg'))->toBeFalse();
    expect($disk->exists('tool-tmp/fresh-batch/b.jpg'))->toBeTrue();
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=PruneToolTempFilesCommandTest`
Expected: FAIL(コマンドが存在しない)

- [ ] **Step 3: コマンドを実装**

`app/Console/Commands/PruneToolTempFilesCommand.php`:

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneToolTempFilesCommand extends Command
{
    protected $signature = 'tool-tmp:prune {--hours=24 : Delete batch directories whose newest file is older than this many hours}';

    protected $description = 'Delete tool-tmp upload/output directories older than the retention window';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $disk = Storage::disk('local');
        $cutoff = now()->subHours($hours)->getTimestamp();

        $deletedCount = 0;

        foreach ($disk->directories('tool-tmp') as $batchDirectory) {
            $newestModifiedAt = collect($disk->allFiles($batchDirectory))
                ->map(fn (string $file) => $disk->lastModified($file))
                ->max();

            $newestModifiedAt ??= 0;

            if ($newestModifiedAt < $cutoff) {
                $disk->deleteDirectory($batchDirectory);
                $deletedCount++;
            }
        }

        $this->info("Deleted {$deletedCount} tool-tmp batch director(y/ies) older than {$hours} hours.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: routes/console.php に Scheduler登録を追加**

`routes/console.php` の `Schedule::command('tool-events:prune')->daily();` の下に追記:

```php
Schedule::command('tool-tmp:prune')->hourly();
```

- [ ] **Step 5: テストを再実行**

Run: `php artisan test --filter=PruneToolTempFilesCommandTest`
Expected: PASS(1 test)

- [ ] **Step 6: コミット**

```bash
git add app/Console/Commands/PruneToolTempFilesCommand.php routes/console.php tests/Feature/Tools/PruneToolTempFilesCommandTest.php
git commit -m "feat: add tool-tmp:prune command and schedule it hourly"
```

---

## Task 10: トップページ雛形(独自Tailwind UI)

**Files:**
- Create: `config/tools.php`
- Create: `app/Http/Controllers/HomeController.php`
- Modify: `routes/web.php`
- Create: `resources/js/pages/Home.vue`
- Create: `resources/js/components/tools/ToolCard.vue`
- Test: `tests/Feature/HomeControllerTest.php`

**Interfaces:**
- Produces: `config('tools')` — ツール定義の配列(`slug`, `name`, `category`, `description`, `href`)。`GET /` が Inertiaページ `Home` を `tools` propsで返す。

- [ ] **Step 1: 失敗するテストを書く**

`tests/Feature/HomeControllerTest.php`:

```php
<?php

test('the home page renders with the tool catalog', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Home')
        ->has('tools', 1)
        ->where('tools.0.slug', 'image-compress')
    );
});
```

- [ ] **Step 2: テストを実行して失敗を確認**

Run: `php artisan test --filter=HomeControllerTest`
Expected: FAIL(現行の `/` ルートは `Welcome` を返すため `component('Home')` の検証で失敗する)

- [ ] **Step 3: ツール定義の設定ファイルを作成**

`config/tools.php`:

```php
<?php

return [
    [
        'slug' => 'image-compress',
        'name' => '画像圧縮',
        'category' => 'image',
        'description' => 'JPEG・PNG・WebP画像を自動で最適化し、軽量化します。',
        'href' => '/image-compress',
    ],
];
```

- [ ] **Step 4: HomeControllerを作成**

`app/Http/Controllers/HomeController.php`:

```php
<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Home', [
            'tools' => config('tools'),
        ]);
    }
}
```

- [ ] **Step 5: routes/web.php の `/` ルートを差し替え**

`routes/web.php` の冒頭にある以下のクロージャルートを削除する:

```php
Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
```

代わりに以下を追加し、`use App\Http\Controllers\HomeController;` を追加する:

```php
Route::get('/', [HomeController::class, 'index'])->name('home');
```

未使用になった `use Illuminate\Foundation\Application;` の import は削除する(他で使われていなければ)。

- [ ] **Step 6: ToolCardコンポーネントを作成**

`resources/js/components/tools/ToolCard.vue`:

```vue
<script setup lang="ts">
interface Tool {
    slug: string;
    name: string;
    category: string;
    description: string;
    href: string;
}

defineProps<{ tool: Tool }>();
</script>

<template>
    <a
        :href="tool.href"
        class="block rounded-lg border border-gray-200 bg-white p-5 transition hover:border-gray-300 hover:shadow-sm"
    >
        <span class="text-xs font-medium tracking-wide text-gray-400 uppercase">{{ tool.category }}</span>
        <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ tool.name }}</h3>
        <p class="mt-1 text-sm text-gray-600">{{ tool.description }}</p>
    </a>
</template>
```

- [ ] **Step 7: Homeページを作成**

`resources/js/pages/Home.vue`:

```vue
<script setup lang="ts">
import ToolCard from '@/components/tools/ToolCard.vue';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Tool {
    slug: string;
    name: string;
    category: string;
    description: string;
    href: string;
}

const props = defineProps<{ tools: Tool[] }>();

const query = ref('');

const filteredTools = computed(() => {
    const keyword = query.value.trim().toLowerCase();

    if (keyword === '') {
        return props.tools;
    }

    return props.tools.filter(
        (tool) => tool.name.toLowerCase().includes(keyword) || tool.description.toLowerCase().includes(keyword),
    );
});
</script>

<template>
    <Head title="katsuooool - Web制作ツール集" />

    <div class="min-h-screen bg-gray-50">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto max-w-5xl px-6 py-10">
                <h1 class="text-2xl font-bold text-gray-900">katsuooool</h1>
                <p class="mt-2 text-sm text-gray-600">Web制作に役立つ実用ツール集</p>

                <input
                    v-model="query"
                    type="search"
                    placeholder="ツールを検索..."
                    class="mt-6 w-full max-w-md rounded-md border border-gray-300 px-4 py-2 text-sm focus:border-gray-400 focus:ring-0 focus:outline-none"
                />
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <div v-if="filteredTools.length === 0" class="text-sm text-gray-500">該当するツールが見つかりませんでした。</div>

            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <ToolCard v-for="tool in filteredTools" :key="tool.slug" :tool="tool" />
            </div>
        </main>
    </div>
</template>
```

- [ ] **Step 8: テストを再実行**

Run: `php artisan test --filter=HomeControllerTest`
Expected: PASS(1 test)

- [ ] **Step 9: フロントエンドの型・Lintチェック**

Run: `npm run types:check && npm run lint:check`
Expected: エラーなし

- [ ] **Step 10: コミット**

```bash
git add config/tools.php app/Http/Controllers/HomeController.php routes/web.php resources/js/pages/Home.vue resources/js/components/tools/ToolCard.vue tests/Feature/HomeControllerTest.php
git commit -m "feat: replace Welcome page with tool catalog home page"
```

---

## 完了確認(Phase 0 全体)

- [ ] `composer test`(Pint + PHPStan + Pest)がグリーン
- [ ] `npm run lint:check && npm run format:check && npm run types:check` がグリーン
- [ ] `php artisan migrate:fresh` からの一連の動作(admin:create → ログイン → tool_events記録 → ダウンロードURL発行 → prune系コマンド)をローカルで一度手動確認する
- [ ] TASKS.md の Phase 0 チェックリストを更新する
