<?php

use App\Services\SpraContactApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    config([
        'services.spra.contact_api_base' => 'https://smartsprouts.jp/api/contacts',
        'services.spra.contact_api_key' => 'test-key',
    ]);
});

test('getCategories fetches and caches categories from the API', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response([
            'categories' => [
                ['id' => '1', 'name' => '不具合の報告'],
                ['id' => '2', 'name' => 'その他'],
            ],
        ], 200),
    ]);

    $client = new SpraContactApiClient;

    $categories = $client->getCategories();

    expect($categories)->toBe([
        ['id' => '1', 'name' => '不具合の報告'],
        ['id' => '2', 'name' => 'その他'],
    ]);
    expect(Cache::get('spra_contact_categories'))->toBe($categories);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://smartsprouts.jp/api/contacts/categories'
            && $request->header('X-Api-Key') === ['test-key'];
    });
});

test('getCategories returns the cached value without calling the API again', function () {
    Cache::put('spra_contact_categories', [['id' => '9', 'name' => 'キャッシュ済み']], now()->addHour());

    Http::fake();

    $client = new SpraContactApiClient;

    expect($client->getCategories())->toBe([['id' => '9', 'name' => 'キャッシュ済み']]);
    Http::assertNothingSent();
});

test('getCategories returns an empty array and does not cache on API failure', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response('error', 500),
    ]);

    $client = new SpraContactApiClient;

    expect($client->getCategories())->toBe([]);
    expect(Cache::get('spra_contact_categories'))->toBeNull();
});

test('getCategories filters out malformed category rows', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response([
            'categories' => [
                ['id' => '1', 'name' => '不具合の報告'],
                ['id' => '2'],
                ['name' => 'idが無い行'],
                'not-an-array',
                ['id' => '3', 'name' => 'その他'],
            ],
        ], 200),
    ]);

    $client = new SpraContactApiClient;

    expect($client->getCategories())->toBe([
        ['id' => '1', 'name' => '不具合の報告'],
        ['id' => '3', 'name' => 'その他'],
    ]);
});

test('getCategories does not cache an empty category list', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response(['categories' => []], 200),
    ]);

    $client = new SpraContactApiClient;

    expect($client->getCategories())->toBe([]);
    expect(Cache::get('spra_contact_categories'))->toBeNull();
});

test('getCategories returns an empty array when the API base or key is not configured', function () {
    config(['services.spra.contact_api_key' => null]);
    Http::fake();

    $client = new SpraContactApiClient;

    expect($client->getCategories())->toBe([]);
    Http::assertNothingSent();
});

test('submit posts the payload to the API and returns true on success', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts' => Http::response(['id' => 'abc'], 201),
    ]);

    $client = new SpraContactApiClient;

    $result = $client->submit(['name' => '山田太郎', 'email' => 'yamada@example.com']);

    expect($result)->toBeTrue();
    Http::assertSent(function ($request) {
        return $request->url() === 'https://smartsprouts.jp/api/contacts'
            && $request->header('X-Api-Key') === ['test-key']
            && $request['name'] === '山田太郎';
    });
});

test('submit returns false on API failure', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts' => Http::response('error', 500),
    ]);

    $client = new SpraContactApiClient;

    expect($client->submit(['name' => '山田太郎']))->toBeFalse();
});

test('submit returns false when the API base or key is not configured', function () {
    config(['services.spra.contact_api_base' => null]);
    Http::fake();

    $client = new SpraContactApiClient;

    expect($client->submit(['name' => '山田太郎']))->toBeFalse();
    Http::assertNothingSent();
});
