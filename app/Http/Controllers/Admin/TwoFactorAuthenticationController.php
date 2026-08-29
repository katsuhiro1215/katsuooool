<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;

/**
 * Admin向け二段階認証(2FA)の設定画面を制御するコントローラー。
 *
 * 未確認の管理者に対しては表示時にシークレットを自動生成してQRコードを提示し、
 * コード確認・リカバリーコード再生成をそれぞれ担う。
 */
class TwoFactorAuthenticationController extends Controller
{
    /**
     * 二段階認証の設定画面を表示する。
     * 未確認の管理者であればシークレットを生成してQRコードを提示する。
     */
    public function create(Request $request, EnableTwoFactorAuthentication $enable): Response
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        if (is_null($admin->two_factor_confirmed_at)) {
            $enable($admin);
            $admin->refresh();
        }

        $confirmed = ! is_null($admin->two_factor_confirmed_at);

        return Inertia::render('admin/TwoFactorSetup', [
            'confirmed' => $confirmed,
            'qrCodeSvg' => $confirmed ? null : $admin->twoFactorQrCodeSvg(),
            'recoveryCodes' => $request->session()->get('two_factor_recovery_codes'),
        ]);
    }

    /**
     * 認証アプリのコードを検証し、二段階認証を確定する。
     */
    public function confirm(Request $request, ConfirmTwoFactorAuthentication $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $confirm($admin, $request->string('code')->toString());

        $admin->refresh();

        return redirect()->route('admin.two-factor.setup')
            ->with('two_factor_recovery_codes', $admin->recoveryCodes());
    }

    /**
     * 現在のパスワードを確認したうえでリカバリーコードを再生成する。
     */
    public function regenerateRecoveryCodes(Request $request, GenerateNewRecoveryCodes $generate): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:admin']]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $generate($admin);
        $admin->refresh();

        return redirect()->route('admin.two-factor.setup')
            ->with('two_factor_recovery_codes', $admin->recoveryCodes());
    }
}
