<?php

namespace App\Providers;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
        Authenticate::redirectUsing(
            fn (Request $request) => $request->is('admin/*')
                ? route('admin.login')
                : route('login')
        );
    }
}
