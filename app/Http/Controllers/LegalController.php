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
}
