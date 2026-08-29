<?php

use App\Http\Controllers\Admin\AuthenticatedSessionController;
use App\Http\Controllers\Admin\ColorExampleController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PostController;
use App\Http\Controllers\Admin\TwoFactorAuthenticationController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use App\Http\Controllers\Admin\UiPatternSnippetController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:admin')->group(function () {
        Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
        Route::post('login', [AuthenticatedSessionController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('login.store');
    });

    Route::middleware('admin.pending-2fa')->group(function () {
        Route::get('two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
        Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:admin-login')
            ->name('two-factor.challenge.store');
    });

    Route::middleware('auth:admin')->group(function () {
        Route::get('two-factor-setup', [TwoFactorAuthenticationController::class, 'create'])->name('two-factor.setup');
        Route::post('two-factor-setup/confirm', [TwoFactorAuthenticationController::class, 'confirm'])->name('two-factor.confirm');
        Route::post('two-factor-setup/recovery-codes', [TwoFactorAuthenticationController::class, 'regenerateRecoveryCodes'])->name('two-factor.recovery-codes');

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('posts', [PostController::class, 'index'])->name('posts.index');
        Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('posts/{post}/edit', [PostController::class, 'edit'])->name('posts.edit');
        Route::put('posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::get('ui-pattern-snippets', [UiPatternSnippetController::class, 'index'])->name('ui-pattern-snippets.index');
        Route::get('ui-pattern-snippets/create', [UiPatternSnippetController::class, 'create'])->name('ui-pattern-snippets.create');
        Route::post('ui-pattern-snippets', [UiPatternSnippetController::class, 'store'])->name('ui-pattern-snippets.store');
        Route::get('ui-pattern-snippets/{snippet}/edit', [UiPatternSnippetController::class, 'edit'])->name('ui-pattern-snippets.edit');
        Route::put('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'update'])->name('ui-pattern-snippets.update');
        Route::delete('ui-pattern-snippets/{snippet}', [UiPatternSnippetController::class, 'destroy'])->name('ui-pattern-snippets.destroy');
        Route::get('color-examples', [ColorExampleController::class, 'index'])->name('color-examples.index');
        Route::get('color-examples/create', [ColorExampleController::class, 'create'])->name('color-examples.create');
        Route::post('color-examples', [ColorExampleController::class, 'store'])->name('color-examples.store');
        Route::get('color-examples/{colorExample}/edit', [ColorExampleController::class, 'edit'])->name('color-examples.edit');
        Route::put('color-examples/{colorExample}', [ColorExampleController::class, 'update'])->name('color-examples.update');
        Route::delete('color-examples/{colorExample}', [ColorExampleController::class, 'destroy'])->name('color-examples.destroy');
        Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    });
});
