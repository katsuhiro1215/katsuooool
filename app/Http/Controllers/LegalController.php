<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function tokushoho(): Response
    {
        return Inertia::render('legal/Tokushoho');
    }

    public function privacyPolicy(): Response
    {
        return Inertia::render('legal/PrivacyPolicy');
    }

    public function terms(): Response
    {
        return Inertia::render('legal/Terms');
    }

    public function cookiePolicy(): Response
    {
        return Inertia::render('legal/CookiePolicy');
    }
}
