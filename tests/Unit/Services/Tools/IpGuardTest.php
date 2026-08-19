<?php

use App\Services\Tools\Dns\IpGuard;

test('blocks loopback addresses', function () {
    expect(IpGuard::isBlocked('127.0.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('::1'))->toBeTrue();
});

test('blocks private ranges', function () {
    expect(IpGuard::isBlocked('10.0.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('172.16.0.1'))->toBeTrue();
    expect(IpGuard::isBlocked('192.168.1.1'))->toBeTrue();
});

test('blocks link-local addresses including the cloud metadata endpoint', function () {
    expect(IpGuard::isBlocked('169.254.169.254'))->toBeTrue();
    expect(IpGuard::isBlocked('169.254.1.1'))->toBeTrue();
});

test('blocks IPv6 unique local addresses', function () {
    expect(IpGuard::isBlocked('fc00::1'))->toBeTrue();
    expect(IpGuard::isBlocked('fd12:3456:789a::1'))->toBeTrue();
});

test('allows public IPv4 and IPv6 addresses', function () {
    expect(IpGuard::isBlocked('8.8.8.8'))->toBeFalse();
    expect(IpGuard::isBlocked('2001:4860:4860::8888'))->toBeFalse();
});

test('blocks a malformed IP string', function () {
    expect(IpGuard::isBlocked('not-an-ip'))->toBeTrue();
});
