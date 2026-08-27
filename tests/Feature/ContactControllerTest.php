<?php

use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.spra.contact_api_base' => 'https://smartsprouts.jp/api/contacts',
        'services.spra.contact_api_key' => 'test-key',
    ]);
});

test('the contact page renders with categories fetched from the Spra API', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response([
            'categories' => [
                ['id' => '1', 'name' => '不具合の報告'],
                ['id' => '2', 'name' => 'その他'],
            ],
        ], 200),
    ]);

    $response = $this->get(route('contact.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Contact')
        ->where('categories', [
            ['id' => '1', 'name' => '不具合の報告'],
            ['id' => '2', 'name' => 'その他'],
        ])
    );
});

test('submitting valid contact data forwards it to the Spra API and redirects with success', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response(['categories' => [
            ['id' => '2', 'name' => 'その他'],
        ]], 200),
        'smartsprouts.jp/api/contacts' => Http::response(['id' => 'abc'], 201),
    ]);

    $response = $this->post(route('contact.store'), [
        'referral' => 'Google',
        'category' => '2',
        'name' => '山田太郎',
        'name_kana' => 'やまだたろう',
        'company' => '',
        'tel' => '090-1234-5678',
        'email' => 'yamada@example.com',
        'message' => 'お問い合わせ内容です。',
        'privacy_agree' => true,
        'not_sales_agree' => true,
    ]);

    $response->assertRedirect(route('contact.show'));
    $response->assertSessionHas('contact_success', true);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://smartsprouts.jp/api/contacts'
            && $request->method() === 'POST'
            && $request['name'] === '山田太郎'
            && $request['email'] === 'yamada@example.com'
            && $request['phone'] === '090-1234-5678'
            && $request['contact_category_id'] === '2'
            && $request['subject'] === 'その他'
            && str_contains((string) $request['message'], 'お問い合わせ内容です。')
            && str_contains((string) $request['message'], 'ふりがな: やまだたろう')
            && str_contains((string) $request['message'], '当サイトを知ったきっかけ: Google')
            && $request['company'] === null
            && $request->hasHeader('X-Api-Key', 'test-key');
    });
});

test('submitting contact data redirects with an error flash when the Spra API fails', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response(['categories' => [
            ['id' => 'other', 'name' => 'その他'],
        ]], 200),
        'smartsprouts.jp/api/contacts' => Http::response('error', 500),
    ]);

    $response = $this->post(route('contact.store'), [
        'category' => 'other',
        'name' => '山田太郎',
        'name_kana' => 'やまだたろう',
        'tel' => '090-1234-5678',
        'email' => 'yamada@example.com',
        'message' => 'お問い合わせ内容です。',
        'privacy_agree' => true,
        'not_sales_agree' => true,
    ]);

    $response->assertRedirect(route('contact.show'));
    $response->assertSessionHas('contact_error', true);
    $response->assertSessionMissing('contact_success');
});

test('submitting a category id that is not in the fetched category list fails validation', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response(['categories' => [
            ['id' => '2', 'name' => 'その他'],
        ]], 200),
        'smartsprouts.jp/api/contacts' => Http::response(['id' => 'abc'], 201),
    ]);

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('contact.store'), [
            'category' => 'not-a-real-category-id',
            'name' => '山田太郎',
            'name_kana' => 'やまだたろう',
            'tel' => '090-1234-5678',
            'email' => 'yamada@example.com',
            'message' => 'お問い合わせ内容です。',
            'privacy_agree' => true,
            'not_sales_agree' => true,
        ]);

    $response->assertStatus(422);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://smartsprouts.jp/api/contacts');
});

test('submitting without required fields fails validation', function () {
    Http::fake();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('contact.store'), []);

    $response->assertStatus(422);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://smartsprouts.jp/api/contacts');
});

test('submitting without agreeing to the privacy policy fails validation', function () {
    Http::fake([
        'smartsprouts.jp/api/contacts/categories' => Http::response(['categories' => [
            ['id' => 'other', 'name' => 'その他'],
        ]], 200),
    ]);

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('contact.store'), [
            'category' => 'other',
            'name' => '山田太郎',
            'name_kana' => 'やまだたろう',
            'tel' => '090-1234-5678',
            'email' => 'yamada@example.com',
            'message' => 'お問い合わせ内容です。',
            'privacy_agree' => false,
            'not_sales_agree' => true,
        ]);

    $response->assertStatus(422);
    Http::assertNotSent(fn ($request) => $request->method() === 'POST' && $request->url() === 'https://smartsprouts.jp/api/contacts');
});
