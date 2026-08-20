<?php

namespace App\Http\Controllers\Tools;

use App\Http\Controllers\Tools\Concerns\TrackedToolController;

class PatternStripeController extends TrackedToolController
{
    protected function slug(): string
    {
        return 'pattern-stripe';
    }

    protected function page(): string
    {
        return 'PatternStripe';
    }
}
