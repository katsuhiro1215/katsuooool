<?php

use App\Mail\ContactFormSubmitted;
use Illuminate\Support\Facades\Mail;

test('the contact page renders', function () {
    $response = $this->get(route('contact.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Contact'));
});

test('submitting valid contact data sends an email and redirects with success', function () {
    Mail::fake();

    $response = $this->post(route('contact.store'), [
        'referral' => 'Google',
        'category' => 'premium',
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
    Mail::assertSent(ContactFormSubmitted::class, function (ContactFormSubmitted $mail) {
        return $mail->data['name'] === '山田太郎' && $mail->data['email'] === 'yamada@example.com';
    });
});

test('submitting without required fields fails validation', function () {
    Mail::fake();

    $response = $this
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('contact.store'), []);

    $response->assertStatus(422);
    Mail::assertNotSent(ContactFormSubmitted::class);
});

test('submitting without agreeing to the privacy policy fails validation', function () {
    Mail::fake();

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
    Mail::assertNotSent(ContactFormSubmitted::class);
});
