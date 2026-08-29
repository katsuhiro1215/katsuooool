<?php

namespace App\Providers;

use App\Models\Admin;
use App\Models\User;
use App\Services\Tools\Dns\DnsResolver;
use App\Services\Tools\Dns\SystemDnsResolver;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DnsResolver::class, SystemDnsResolver::class);

        // Cashier registers its routes (including POST /stripe/webhook) from
        // its own ServiceProvider::boot(), which runs after every
        // provider's register(). This must happen here, not in this
        // provider's own boot(), or boot-order could let Cashier register
        // the webhook route before this flag takes effect — leaving it
        // reachable with signature verification skipped (STRIPE_WEBHOOK_SECRET
        // unset) while Premium is meant to be fully disabled.
        //
        // $registersRoutes is a static property that outlives a single
        // request in a long-running process (e.g. repeated application
        // boots within one test run), so both branches are set explicitly
        // rather than relying on Cashier's own true-by-default value.
        Cashier::$registersRoutes = (bool) config('premium.enabled');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        RateLimiter::for('admin-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower((string) $request->input('email')).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('image-tools', function (Request $request) {
            $user = $request->user();
            $isPremium = $user instanceof User && $user->isPremium();
            $perMinute = $isPremium ? 30 : 10;
            $key = $user !== null ? $user->id : $request->ip();

            return Limit::perMinute($perMinute)->by($key);
        });

        // Laravel's default RedirectIfAuthenticated (the 'guest' middleware
        // alias) redirects to route('dashboard') whenever ANY of the
        // checked guards is authenticated — which would send an
        // already-logged-in admin visiting /admin/login to the Fortify
        // user dashboard at /dashboard instead of /admin/dashboard. This
        // override checks the admin guard specifically and only changes
        // behavior for that case; the 'web' guest middleware used
        // elsewhere (Fortify's own /login) is unaffected since an
        // unauthenticated-as-admin visitor falls through to the default.
        RedirectIfAuthenticated::redirectUsing(
            fn (Request $request) => Auth::guard('admin')->check()
                ? route('admin.dashboard')
                : route('dashboard')
        );

        // Laravel's default Authenticate middleware (the 'auth' alias) has no
        // guard awareness either: it just redirects any unauthenticated
        // visitor to route('login') if it exists — which is Fortify's
        // web-guard login page. Without this override, an unauthenticated
        // visitor hitting an 'auth:admin' route (e.g. GET /admin/dashboard)
        // would be sent to /login instead of /admin/login. Detect admin
        // routes by their URL prefix, since the callback only receives the
        // Request (no guard parameter) — the 'admin/*' prefix matches every
        // route inside routes/admin.php's Route::prefix('admin') group.
        // Non-admin routes fall through to the default web-guard login.
        $redirectUnauthenticatedTo = fn (Request $request) => $request->is('admin/*')
            ? route('admin.login')
            : route('login');

        Authenticate::redirectUsing($redirectUnauthenticatedTo);

        // Laravel's redirectTo() configuration normally applies the same
        // closure to Authenticate, AuthenticateSession, and
        // AuthenticationException together. This app only routes auth
        // failures through Authenticate today, but AuthenticationException
        // is kept in sync here too so a future bare-exception path under
        // /admin/* doesn't silently fall back to the web-guard login route.
        AuthenticationException::redirectUsing($redirectUnauthenticatedTo);

        // Illuminate\Auth\Notifications\ResetPassword builds its reset link
        // via route('password.reset', ...) by default, which always
        // resolves to Fortify's user-facing route — even when the
        // notifiable is an Admin. Without this override, an admin's
        // password-reset email would link to /reset-password/{token}
        // (the User form) instead of /admin/reset-password/{token} (the
        // Admin form registered in routes/admin.php), and the User form
        // authenticates against the wrong password broker. This callback is
        // a single global static hook (not guard-scoped), so it must branch
        // on the notifiable's class to keep the existing User behavior
        // unchanged.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            if ($notifiable instanceof Admin) {
                return url(route('admin.password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false));
            }

            return url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));
        });
    }
}
