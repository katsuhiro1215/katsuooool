<?php

namespace App\Http\Responses;

use App\Actions\Premium\StartPremiumCheckout;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Cashier\Checkout;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function __construct(private readonly StartPremiumCheckout $startPremiumCheckout) {}

    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|Checkout|JsonResponse
    {
        $plan = $request->session()->pull('premium_registration_plan');
        $user = $request->user();

        if (is_string($plan) && $user instanceof User) {
            return $this->startPremiumCheckout->handle($user, $plan);
        }

        return $request->wantsJson()
            ? new JsonResponse('', 201)
            : redirect()->intended(Fortify::redirects('register'));
    }
}
