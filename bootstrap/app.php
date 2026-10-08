<?php

use App\Http\Middleware\EnsureAdminTwoFactorConfirmed;
use App\Http\Middleware\EnsurePendingTwoFactorChallenge;
use App\Http\Middleware\EnsurePremium;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 本番は Caddy（同一 Docker ネットワーク＝プライベート IP）経由。プライベートアドレスからの
        // X-Forwarded-* だけを信頼する。設定しないと IP ハッシュ・レート制限のキーが Caddy の内部 IP になる。
        // 直接接続（開発・Xserver）はクライアント IP がパブリックなので従来どおり影響しない。
        $middleware->trustProxies(at: ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);

        $middleware->alias([
            'premium' => EnsurePremium::class,
            'admin.2fa-confirmed' => EnsureAdminTwoFactorConfirmed::class,
            'admin.pending-2fa' => EnsurePendingTwoFactorChallenge::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
