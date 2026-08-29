<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The frontend build (public/build/manifest.json) is not produced during
        // the test run, and pre-existing frontend module resolution issues in
        // this starter kit are out of scope here. Feature tests only need to
        // verify the backend response and Inertia payload, not compiled assets.
        $this->withoutVite();

        // Password::uncompromised() calls out to the Have I Been Pwned API via
        // the Http facade. Fake it globally so tests never make a real network
        // call and never fail due to that service being unreachable; a test
        // that specifically wants to verify "known-compromised password
        // rejected" behavior can override this with its own Http::fake().
        Http::fake([
            'api.pwnedpasswords.com/*' => Http::response('', 200),
        ]);
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
