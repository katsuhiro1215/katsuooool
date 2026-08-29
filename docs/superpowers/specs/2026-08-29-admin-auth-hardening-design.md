# Admin認証機能の強化(2FA必須化・パスワードリセット・設定画面) 設計書

## 背景・目的

Adminガード(`admins`テーブル)は`/admin/login`によるログイン・ログアウトのみを実装しており、パスワードを忘れた場合の再設定手段、ログイン中のパスワード変更・メールアドレス変更、2段階認証(2FA)のいずれも存在しない。Admin認証情報の唯一の管理手段は、SSH経由の`admin:create`Artisanコマンドのみである。

Adminはこのアプリの中で最も強い権限(User個人情報の閲覧、Blog投稿の編集、カラー例・UIパターンのCRUD等)を持つアカウントであり、本番投入前にこの状態を放置するのはセキュリティ上望ましくない。ユーザーからの指摘を受け、本番投入前に以下を実装する。

- Admin向けのパスワードリセット(`/admin/login`からの「パスワードをお忘れですか?」導線)
- ログイン中のパスワード変更・メールアドレス変更
- **2段階認証(TOTP)を必須化**(Adminはこのアプリで唯一、2FAを必須とするアカウント種別)
- Admin・User双方のパスワード強度ルールの見直し

なお、User側(`web`ガード)の認証機能(パスワードリセット・変更、メール変更・確認、2FA、パスキー)はLaravel Fortifyの標準スターターキット構成のまま実装済みで、`tests/Feature/Auth/`配下21件のテストがすべて通ることを確認済み。User側の2FAは従来通り任意のままとし、本設計のスコープ外とする(SPEC.md記載の既存方針を維持)。

## スコープ

- 対象: `admins`テーブル・`admin`ガードの認証機能全般。
- Adminの新規登録(register)機能は作らない(Adminは`admin:create`コマンドおよび本設計で追加するリカバリーコマンドのみで作成・復旧する)。
- User側の2FAを必須化する変更は含まない(既存の任意運用を維持)。
- Admin間での権限差(スーパー管理者/一般管理者などのロール分け)は本設計の対象外。全Adminは同一権限のまま。
- 2FAの「無効化」機能はUIに設けない(必須運用のため)。デバイス・リカバリーコードを両方紛失した場合のみ、Artisanコマンドによるサーバー側リセットで復旧する。

## 設計方針: FortifyのAction/トレイトを直接再利用する

`Laravel\Fortify\TwoFactorAuthenticatable`トレイト、および`Laravel\Fortify\Actions\{EnableTwoFactorAuthentication, ConfirmTwoFactorAuthentication, GenerateNewRecoveryCodes}`の各Actionクラスは、いずれも特定のガードやFortifyのHTTPルーティングに依存しない設計になっている(`__invoke($user, ...)`が任意のEloquentモデルを受け取る、`Fortify::currentEncrypter()`・`app(TwoFactorAuthenticationProvider::class)`は共にガード非依存のグローバルなサービス)。

そのため、`Admin`モデルに`User`モデルと全く同じ形でこのトレイトを適用し、Action クラスをそのままコンテナ経由で呼び出すことで、TOTPシークレット生成・QRコード生成・コード検証・リカバリーコード生成といったコアロジックを一切re-implementせずに再利用できる。自前で書く必要があるのは、Adminガード用のルーティング・コントローラー・Vueページと、ログイン時の2段階チャレンジの状態管理のみ。

`Laravel\Fortify\Http\Controllers\TwoFactorAuthenticatedSessionController`自体は`StatefulGuard`をコンストラクタ注入しておりコンテナのデフォルトガード(`web`)に固定されるため、これはそのまま使えない。ロジック(セッションに`login.id`を保持→コード検証→`Auth::guard('admin')->login()`)は完全に踏襲しつつ、Admin専用に薄いコントローラーを新規に書く。

パスワードリセットはFortifyを経由せず、Laravel本体の`Password Broker`(`Illuminate\Support\Facades\Password`)を使う。これはもとから複数ガード・複数プロバイダに対応した設計であり、`config/auth.php`に`admins`用のブローカーを追加するだけで済む。

## データモデル

### `admins`テーブルへのカラム追加(migration)

```php
Schema::table('admins', function (Blueprint $table) {
    $table->text('two_factor_secret')->nullable()->after('password');
    $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
    $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
});
```

`User`モデルの2FAカラムと同一の型・命名(`database/migrations`内の既存User向けmigrationを参照)。

### `admin_password_reset_tokens`テーブル(新規)

```php
Schema::create('admin_password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
```

Laravel標準の`password_reset_tokens`テーブルと同一構造。User用のテーブルと分離することで、Admin・Userで万一同じメールアドレスが使われていた場合でもトークンの取り違えが起きないようにする。

### `App\Models\Admin`の変更

```php
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class Admin extends Authenticatable
{
    use TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
```

`User`モデルと完全に同じパターン。

### `config/auth.php`への追加

```php
'providers' => [
    // 既存の 'admins' => ['driver' => 'eloquent', 'model' => Admin::class] はそのまま
],

'passwords' => [
    // 既存の 'users' => [...] はそのまま
    'admins' => [
        'provider' => 'admins',
        'table' => 'admin_password_reset_tokens',
        'expire' => 60,
        'throttle' => 60,
    ],
],
```

## パスワード強度ルール

### Admin用(新規、`app/Concerns/AdminPasswordValidationRules.php`)

```php
protected function passwordRules(): array
{
    return [
        'required',
        'string',
        Password::min(12)->mixedCase()->numbers()->symbols()->uncompromised(),
        'confirmed',
    ];
}
```

- 12文字以上・大文字小文字混在・数字・記号をすべて必須とし、`uncompromised()`で既知の漏洩パスワードデータベース(Have I Been Pwned、k-anonymity方式で実パスワードは送信されない)と照合する。
- `admin:create`コマンド・パスワード変更・パスワードリセットのすべてに同一ルールを適用する。
- `uncompromised()`は本番(Xserver)からの外部HTTPS通信(`api.pwnedpasswords.com`)を必要とするため、実機での動作確認が必要(§エラーハンドリングに記載)。

### User用(既存`app/Concerns/PasswordValidationRules.php`の変更)

```php
protected function passwordRules(): array
{
    return ['required', 'string', Password::min(10)->uncompromised(), 'confirmed'];
}
```

現状の`Password::default()`(8文字以上のみ、Laravelのフレームワークデフォルト)から、10文字以上+漏洩チェックに強化する。大文字小文字混在・記号必須は課さない(一般利用者向けの登録UXを損なわないため)。

## バックエンド設計

### ログイン・2FAチャレンジのフロー

```
POST /admin/login (guest:admin)
  ├─ 認証情報が正しい
  │   ├─ two_factor_confirmed_at が設定済み
  │   │   └─ セッションに 'login.id' = $admin->id を保存(remember指定があれば 'login.remember' も)
  │   │       → redirect GET /admin/two-factor-challenge
  │   └─ two_factor_confirmed_at が未設定(初回ログイン)
  │       └─ Auth::guard('admin')->login($admin) で通常通り即ログイン
  │           → redirect GET /admin/dashboard
  │              (以降、EnsureAdminTwoFactorConfirmed ミドルウェアが
  │               /admin/two-factor-setup 以外への全アクセスをブロックする)
  └─ 認証情報が誤り → ValidationException
```

### 新規コントローラー(`app/Http/Controllers/Admin/`)

- `TwoFactorChallengeController`(`create()`/`store()`とも、`login.id`セッションの有無チェックは後述の`EnsurePendingTwoFactorChallenge`ミドルウェアが担い、コントローラー側では再チェックしない)
  - `create()`: ビューを返す。
  - `store(Request $request)`: `code`または`recovery_code`を受け取り、`TwoFactorAuthenticationProvider::verify()`で検証。リカバリーコードの場合は`$admin->recoveryCodes()`と照合し、一致すれば`$admin->replaceRecoveryCode($code)`を呼ぶ(Fortifyのトレイトのメソッドをそのまま利用)。成功時は`Auth::guard('admin')->login($admin, $remember)`→セッション再生成→`/admin/dashboard`へ。

- `TwoFactorAuthenticationController`(`auth:admin`、2FA確認済みチェックの対象外)
  - `create()`: 2FA未設定なら`EnableTwoFactorAuthentication`アクションを呼びシークレット・リカバリーコードを生成した上でQRコード(`$admin->twoFactorQrCodeSvg()`)を表示。設定済みなら「設定済み」表示のみ。
  - `confirm(Request $request)`: `ConfirmTwoFactorAuthentication`アクションを呼びコードを検証、`two_factor_confirmed_at`を設定。レスポンスでリカバリーコードを1回だけ返す。
  - `regenerateRecoveryCodes()`: 確認済みAdminのみ、`GenerateNewRecoveryCodes`アクションを呼ぶ(現在のパスワードでの再確認を必須にする)。

- `PasswordResetLinkController`(`guest:admin`)
  - `create()`: フォーム表示。
  - `store(Request $request)`: `Password::broker('admins')->sendResetLink(['email' => ...])`。

- `NewPasswordController`(`guest:admin`)
  - `create(Request $request)`: トークン付きフォーム表示。
  - `store(Request $request)`: Admin用パスワードルールでバリデーションし`Password::broker('admins')->reset(...)`。

- `SettingsController`(`auth:admin`)
  - `edit()`: 設定画面表示(現在のメールアドレス、2FA状態を渡す)。
  - `updatePassword(Request $request)`: `current_password`確認+Admin用パスワードルールで更新。
  - `updateEmail(Request $request)`: `current_password`確認+`unique:admins,email`で更新(Adminにはメール確認フローを設けない。既存の`admins`テーブルに`email_verified_at`は存在せず、本設計でも追加しない)。

### 新規ミドルウェア

`App\Http\Middleware\EnsureAdminTwoFactorConfirmed`:

```php
public function handle(Request $request, Closure $next): Response
{
    $admin = $request->user('admin');

    if ($admin instanceof Admin && is_null($admin->two_factor_confirmed_at)) {
        return redirect()->route('admin.two-factor.setup');
    }

    return $next($request);
}
```

`bootstrap/app.php`にエイリアス`'admin.2fa-confirmed'`として登録する。

### ルート構成(`routes/admin.php`の変更)

```php
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', ...)->name('login');
        Route::post('login', ...)->middleware('throttle:admin-login')->name('login.store');
        Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
        Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
            ->middleware('throttle:admin-login')->name('password.email');
        Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
        Route::post('reset-password', [NewPasswordController::class, 'store'])
            ->middleware('throttle:admin-login')->name('password.store');
    });

    // ログイン済みだが2FAチャレンジ未通過(完全ログイン前)
    Route::middleware('admin.pending-2fa')->group(function () {
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-login')->name('two-factor.challenge.store');
    });

    Route::middleware('auth:admin')->group(function () {
        // 2FA設定はミドルウェア適用前(未確認Adminでもアクセスできる必要があるため)
        Route::get('two-factor-setup', [TwoFactorAuthenticationController::class, 'create'])->name('two-factor.setup');
        Route::post('two-factor-setup/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('two-factor-setup/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');
        Route::post('logout', ...)->name('logout');

        Route::middleware('admin.2fa-confirmed')->group(function () {
            Route::get('dashboard', ...)->name('dashboard');
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password');
            Route::put('settings/email', [SettingsController::class, 'updateEmail'])->name('settings.email');
            // 既存の users / posts / ui-pattern-snippets / color-examples ルート群はここに移動
        });
    });
});
```

`admin.pending-2fa`(実装クラス`EnsurePendingTwoFactorChallenge`)は「セッションに`login.id`はあるが`auth:admin`としては未ログイン」という中間状態を検証する小さなミドルウェア(新規)で、`login.id`が存在しない・有効期限切れの場合は`/admin/login`へリダイレクトする。この存在チェックをチャレンジ関連の2ルートに集約することで、コントローラー側は「必ず`login.id`が有効な状態で呼ばれる」ことを前提にできる。

### Artisanコマンド(新規)

`admin:reset-two-factor {email}`: 指定Adminの`two_factor_secret`・`two_factor_recovery_codes`・`two_factor_confirmed_at`をすべて`null`に戻す。実行後は次回ログイン後に`/admin/two-factor-setup`から再設定を求められる。`admin:create`と同じくSSHアクセスを前提とした復旧経路。

`admin:create`コマンド自体も、パスワードのバリデーションルールを既存の`min:8`から`AdminPasswordValidationRules`と同じ内容に変更する。

## フロントエンド設計

新規Vueページ(`resources/js/pages/admin/`):

- `TwoFactorChallenge.vue`: コード入力フォーム+「リカバリーコードを使う」切り替えリンク。
- `TwoFactorSetup.vue`: QRコード(SVG)+シークレットのテキスト表示(手入力用)+確認コード入力フォーム。確認成功後、8個のリカバリーコードを1度だけ表示し「保存しました」チェックを求める(User側の`ManageTwoFactor.vue`と同様のUXパターンを踏襲するが、必須運用のため「スキップ」は存在しない)。
- `ForgotPassword.vue` / `ResetPassword.vue`: User側の`auth/ForgotPassword.vue`等と同じ構成をAdmin用に複製。
- `Settings.vue`: パスワード変更フォーム・メールアドレス変更フォーム・2FAリカバリーコード再生成ボタン(現在のパスワード確認付き)の3セクション。`AdminLayout.vue`のサイドバーナビに「設定」項目を追加する。

いずれも既存の`resources/js/pages/admin/Login.vue`と同じく、素のTailwind CSS(shadcn-vue不使用)で作成する。

## エラーハンドリング・エッジケース

- **`uncompromised()`のAPI疎通**: 本番Xserverから`api.pwnedpasswords.com`への外部HTTPS通信が失敗した場合、Laravelはデフォルトで検証をスキップせず例外を投げる可能性があるため、実機での疎通確認を実装後に行う。疎通できない場合は`uncompromised()`を一時的に外す判断もありうる(パスワード強度ルール自体は`min(12)+mixedCase+numbers+symbols`で十分に機能する)。
- **2FAチャレンジ中のセッション期限切れ**: `login.id`セッションキーに有効期限(例: 5分)を持たせ、期限切れなら`/admin/login`へやり直しさせる。
- **リカバリーコードの使い切り**: 8個すべて使用済みになった場合、設定画面から再生成できるようにする(`GenerateNewRecoveryCodes`、現在のパスワード確認必須)。
- **2FAデバイス・リカバリーコード両方紛失**: `admin:reset-two-factor`コマンドでのSSH復旧のみ(UI上の代替手段は用意しない)。
- **既存Adminアカウント(初回マイグレーション後)**: 2FA未設定の状態で作られているため、次回ログイン時から強制的に`/admin/two-factor-setup`に誘導される。これは意図した挙動。
- **既存の管理系Featureテストへの影響**: `tests/Pest.php`の共有ヘルパー`actingAsAdmin()`(`DashboardControllerTest`・`UserControllerTest`等、多数の既存テストが利用)は、素の`Admin::factory()->create()`を`actingAs()`するだけで`two_factor_confirmed_at`を設定していない。`EnsureAdminTwoFactorConfirmed`ミドルウェアを`/admin/dashboard`等の既存ルートに適用すると、これらの既存テストはすべて2FA未確認によるリダイレクトで失敗する。実装計画では、`actingAsAdmin()`ヘルパー自体を「2FA確認済みのAdminとしてログインする」デフォルト挙動に更新し(`two_factor_confirmed_at`をセットする)、「2FA未確認Adminがブロックされること」を検証する新規テストのみ別途、未確認のAdminを明示的に用意する形にする。

## テスト方針

- `AuthenticatedSessionController`(ログイン): 2FA未設定Adminはログイン後`two-factor-setup`以外にアクセスできないこと、2FA設定済みAdminはパスワード認証だけでは完全ログインせず`two-factor-challenge`に遷移すること。
- `TwoFactorChallengeController`: 正しいコードで完全ログインできること、誤ったコードで失敗すること、リカバリーコードでログインでき使用済みコードが入れ替わること、`login.id`セッションがない状態でのアクセスは`/admin/login`にリダイレクトされること。
- `TwoFactorAuthenticationController`: QRコード・シークレットが表示されること、正しいコードで確認でき`two_factor_confirmed_at`が設定されること、誤ったコードでは確認できないこと、リカバリーコード再生成で古いコードが無効になること。
- `EnsureAdminTwoFactorConfirmed`ミドルウェア: 未確認Adminが`/admin/dashboard`等にアクセスすると`/admin/two-factor-setup`にリダイレクトされること、確認済みAdminは通常通りアクセスできること。
- `PasswordResetLinkController` / `NewPasswordController`: 有効なメールでリンクが送信されること、不正なトークンでは失敗すること、リセット後のパスワードがAdmin用強度ルールを満たしていること。
- `SettingsController`: 現在のパスワード確認なしでは変更できないこと、Admin用パスワードルールを満たさない新パスワードは拒否されること、メールアドレス重複は拒否されること。
- `admin:reset-two-factor`コマンド: 実行後に対象Adminの2FAカラムがすべて`null`になること。
- `AdminPasswordValidationRules` / User側`PasswordValidationRules`: それぞれの強度ルールを満たさない入力(短すぎる・記号なし等)が拒否されること。

## 影響範囲まとめ(実装計画のためのファイルリスト予告)

- 新規: migration 2本、`AdminPasswordValidationRules`、コントローラー5本、ミドルウェア2本(`EnsureAdminTwoFactorConfirmed`・`EnsurePendingTwoFactorChallenge`)、Artisanコマンド`ResetAdminTwoFactorCommand`、Vueページ5本
- 変更: `App\Models\Admin`、`config/auth.php`、`routes/admin.php`、`app/Http/Controllers/Admin/AuthenticatedSessionController.php`、`app/Console/Commands/CreateAdminCommand.php`、`app/Concerns/PasswordValidationRules.php`、`bootstrap/app.php`(ミドルウェアエイリアス登録)、`resources/js/layouts/admin/AdminLayout.vue`(設定リンク追加)
