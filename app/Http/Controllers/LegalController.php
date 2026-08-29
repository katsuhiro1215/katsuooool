<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function tokushoho(): Response
    {
        return Inertia::render('legal/Tokushoho', [
            'company' => config('company'),
        ]);
    }

    public function privacyPolicy(): Response
    {
        return Inertia::render('legal/PrivacyPolicy', [
            'company' => config('company'),
        ]);
    }

    public function terms(): Response
    {
        return Inertia::render('legal/Terms', [
            'company' => config('company'),
        ]);
    }

    public function cookiePolicy(): Response
    {
        return Inertia::render('legal/CookiePolicy');
    }
}
