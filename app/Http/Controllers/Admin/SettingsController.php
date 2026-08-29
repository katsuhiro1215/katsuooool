<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\AdminPasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin向けの設定画面(パスワード変更・メールアドレス変更)を扱うコントローラー。
 */
class SettingsController extends Controller
{
    use AdminPasswordValidationRules;

    /**
     * 設定画面を表示する。
     */
    public function edit(Request $request): Response
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        return Inertia::render('admin/Settings', [
            'email' => $admin->email,
            'twoFactorConfirmed' => ! is_null($admin->two_factor_confirmed_at),
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * パスワードを変更する。
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => $this->passwordRules(),
        ]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $admin->forceFill([
            'password' => Hash::make($request->string('password')->toString()),
        ])->save();

        return back()->with('status', 'パスワードを変更しました。');
    }

    /**
     * メールアドレスを変更する。
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($admin->id)],
        ]);

        $admin->forceFill([
            'email' => $request->string('email')->toString(),
        ])->save();

        return back()->with('status', 'メールアドレスを変更しました。');
    }
}
