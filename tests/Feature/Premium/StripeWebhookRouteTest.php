<?php

use App\Providers\AppServiceProvider;
use Laravel\Cashier\Cashier;

afterEach(function () {
    Cashier::$registersRoutes = true;
});

test('registering the app service provider disables Cashier routes when premium is off', function () {
    config(['premium.enabled' => false]);
    Cashier::$registersRoutes = true;

    (new AppServiceProvider(app()))->register();

    expect(Cashier::$registersRoutes)->toBeFalse();
});

test('registering the app service provider allows Cashier routes when premium is on', function () {
    config(['premium.enabled' => true]);
    Cashier::$registersRoutes = false;

    (new AppServiceProvider(app()))->register();

    expect(Cashier::$registersRoutes)->toBeTrue();
});
