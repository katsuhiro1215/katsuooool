<?php

use App\Models\ToolEvent;
use App\Models\User;
use App\Models\WebsiteStarterSubmission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function postWebsiteStarter(User $user, array $overrides = [])
{
    $payload = array_merge([
        'site_name' => '株式会社カツコード',
        'site_url' => 'https://example.com',
        'industry' => 'beauty',
        'prefecture' => '奈良県',
        'target_age_range' => '20s',
        'target_gender' => 'female',
        'purpose' => 'new_customers',
        'logo' => UploadedFile::fake()->image('logo.png', 1000, 1000),
        'meta_description' => '奈良市の美容室です。カット・カラー・ヘッドスパを提供しています。',
    ], $overrides);

    return test()->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('website-starter.process'), $payload);
}

test('the website-starter page renders industry/prefecture/target options as props', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('website-starter.show'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('WebsiteStarter')
        ->has('industries')
        ->has('prefectures')
        ->has('targetAgeRanges')
        ->has('targetGenders')
        ->has('purposes'));
});

test('a guest is redirected to login', function () {
    $response = $this->get(route('website-starter.show'));

    $response->assertRedirect(route('login'));
});

test('a valid submission stores only category fields and returns generated assets', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user);

    $response->assertSuccessful();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('title', '奈良県の美容・サロン｜株式会社カツコード');
    $response->assertJsonPath('favicon_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));
    $response->assertJsonPath('ogp_image_download_url', fn ($url) => is_string($url) && str_contains($url, '/tool-download/'));
    $response->assertJsonPath('meta_tags', fn ($value) => is_string($value) && str_contains($value, 'og:title'));
    $response->assertJsonPath('html_head', fn ($value) => is_string($value) && str_contains($value, '<title>'));

    expect(WebsiteStarterSubmission::count())->toBe(1);
    $submission = WebsiteStarterSubmission::first();
    expect($submission)
        ->user_id->toBe($user->id)
        ->industry->toBe('beauty')
        ->prefecture->toBe('奈良県')
        ->target_age_range->toBe('20s')
        ->target_gender->toBe('female')
        ->purpose->toBe('new_customers');

    expect(ToolEvent::where('tool_slug', 'website-starter')->where('event_type', 'process')->exists())->toBeTrue();
});

test('an invalid industry is rejected and no submission is stored', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['industry' => 'not-a-real-industry']);

    $response->assertStatus(422);
    expect(WebsiteStarterSubmission::count())->toBe(0);
});

test('an invalid prefecture is rejected', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['prefecture' => '存在しない県']);

    $response->assertStatus(422);
});

test('a missing logo is rejected', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, ['logo' => null]);

    $response->assertStatus(422);
});

test('target_age_range, target_gender, and purpose are optional', function () {
    $user = User::factory()->create();

    $response = postWebsiteStarter($user, [
        'target_age_range' => null,
        'target_gender' => null,
        'purpose' => null,
    ]);

    $response->assertSuccessful();
    $submission = WebsiteStarterSubmission::first();
    expect($submission)
        ->target_age_range->toBeNull()
        ->target_gender->toBeNull()
        ->purpose->toBeNull();
});
