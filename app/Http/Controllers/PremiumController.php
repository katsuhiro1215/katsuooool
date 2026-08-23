<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Cashier\Checkout;

class PremiumController extends Controller
{
    private const PLAN_PRICE_CONFIG_KEYS = [
        'monthly' => 'services.stripe.price_monthly',
        'yearly' => 'services.stripe.price_yearly',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Premium', [
            'isPremium' => $user instanceof User && $user->isPremium(),
        ]);
    }

    public function checkout(Request $request): RedirectResponse|Checkout
    {
        $request->validate([
            'plan' => ['required', 'string', 'in:'.implode(',', array_keys(self::PLAN_PRICE_CONFIG_KEYS))],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isPremium()) {
            return Redirect::route('premium.index');
        }

        $plan = $request->string('plan')->toString();
        $priceId = (string) config(self::PLAN_PRICE_CONFIG_KEYS[$plan]);

        abort_if($priceId === '', 503, 'Premiumプランの受付を準備中です。しばらくしてから再度お試しください。');

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('premium.thanks'),
            'cancel_url' => route('premium.index'),
        ]);
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
