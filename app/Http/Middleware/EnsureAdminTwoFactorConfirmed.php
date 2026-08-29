<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorConfirmed
{
    /**
     * 受信したリクエストを処理する。
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');

        if ($admin instanceof Admin && is_null($admin->two_factor_confirmed_at)) {
            return redirect()->route('admin.two-factor.setup');
        }

        return $next($request);
    }
}
