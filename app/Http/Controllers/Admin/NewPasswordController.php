<?php

namespace App\Http\Controllers\Admin;

use App\Concerns\AdminPasswordValidationRules;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin向け新しいパスワード設定コントローラ。
 */
class NewPasswordController extends Controller
{
    use AdminPasswordValidationRules;

    /**
     * パスワード再設定フォームを表示する。
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/ResetPassword', [
            'token' => $request->route('token'),
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * トークンを検証し、新しいパスワードを保存する。
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => $this->passwordRules(),
        ]);

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $admin, string $password) {
                $admin->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($admin));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return redirect()->route('admin.login')->with('status', __($status));
    }
}
