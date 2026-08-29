<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePendingTwoFactorChallenge
{
    /**
     * 受信したリクエストを処理する。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('admin_login.id')) {
            return redirect()->route('admin.login');
        }

        return $next($request);
    }
}
