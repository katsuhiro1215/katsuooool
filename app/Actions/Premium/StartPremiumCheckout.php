<?php

namespace App\Actions\Premium;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Laravel\Cashier\Checkout;

class StartPremiumCheckout
{
    public const PLAN_PRICE_CONFIG_KEYS = [
        'monthly' => 'services.stripe.price_monthly',
        'yearly' => 'services.stripe.price_yearly',
    ];

    public function handle(User $user, string $plan): RedirectResponse|Checkout
    {
        if ($user->isPremium()) {
            return Redirect::route('premium.index');
        }

        $priceId = (string) config(self::PLAN_PRICE_CONFIG_KEYS[$plan]);

        abort_if($priceId === '', 503, 'Premiumプランの受付を準備中です。しばらくしてから再度お試しください。');

        return $user->newSubscription('default', $priceId)->checkout([
            'success_url' => route('premium.thanks'),
            'cancel_url' => route('premium.index'),
        ]);
    }
}
