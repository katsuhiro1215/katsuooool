<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin向けパスワードリセットリンク送信コントローラ。
 */
class PasswordResetLinkController extends Controller
{
    /**
     * パスワードリセットリンク送信フォームを表示する。
     */
    public function create(Request $request): Response
    {
        return Inertia::render('admin/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * 指定されたメールアドレス宛にパスワードリセットリンクを送信する。
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('admins')->sendResetLink(
            $request->only('email'),
        );

        return back()->with('status', 'パスワードリセット用のメールを送信しました(該当するアカウントが存在する場合)。');
    }
}
