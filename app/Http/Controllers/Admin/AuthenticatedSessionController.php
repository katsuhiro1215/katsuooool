<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/Login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::guard('admin')->validate($credentials)) {
            throw ValidationException::withMessages([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ]);
        }

        $admin = Admin::where('email', $credentials['email'])->firstOrFail();

        if (! is_null($admin->two_factor_confirmed_at)) {
            $request->session()->put('admin_login.id', $admin->id);
            $request->session()->put('admin_login.remember', $remember);

            return redirect()->route('admin.two-factor.challenge');
        }

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        // セッション全体を破棄するため、同一ブラウザで web ガードにもログインして
        // いる場合はそちらも同時にログアウトされる(Laravel Breeze 標準の挙動を踏襲)。
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
