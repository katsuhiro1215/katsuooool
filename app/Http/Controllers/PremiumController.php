<?php

namespace App\Http\Controllers;

use App\Actions\Premium\StartPremiumCheckout;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Checkout;

class PremiumController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Premium', [
            'isPremium' => $user instanceof User && $user->isPremium(),
        ]);
    }

    public function checkout(Request $request, StartPremiumCheckout $startPremiumCheckout): RedirectResponse|Checkout
    {
        $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(StartPremiumCheckout::PLAN_PRICE_CONFIG_KEYS))],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $startPremiumCheckout->handle($user, $request->string('plan')->toString());
    }

    public function thanks(): Response
    {
        return Inertia::render('PremiumThanks');
    }

    public function billingPortal(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        abort_unless($user->isPremium(), 403);

        return $user->redirectToBillingPortal(route('premium.index'));
    }
}
