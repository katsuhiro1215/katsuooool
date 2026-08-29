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
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

class TwoFactorChallengeController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('admin/TwoFactorChallenge');
    }

    public function store(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $admin = Admin::where('id', $request->session()->get('admin_login.id'))->firstOrFail();

        $code = (string) $request->input('code', '');
        $recoveryCode = (string) $request->input('recovery_code', '');

        $validCode = $code !== '' && ! is_null($admin->two_factor_secret) && $provider->verify(
            Fortify::currentEncrypter()->decrypt($admin->two_factor_secret),
            $code,
        );

        $validRecoveryCode = $recoveryCode !== '' && ! is_null($admin->two_factor_recovery_codes) && in_array($recoveryCode, $admin->recoveryCodes(), true);

        if (! $validCode && ! $validRecoveryCode) {
            throw ValidationException::withMessages([
                'code' => '認証コードが正しくありません。',
            ]);
        }

        if ($validRecoveryCode) {
            $admin->replaceRecoveryCode($recoveryCode);
        }

        $remember = (bool) $request->session()->pull('admin_login.remember', false);
        $request->session()->forget('admin_login.id');

        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }
}
