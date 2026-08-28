<?php

use App\Models\ColorExample;

test('the index page lists color examples', function () {
    actingAsAdmin();
    ColorExample::create(['type' => 'palette', 'title' => 'テスト例', 'colors' => ['#111111', '#222222']]);

    $response = $this->get(route('admin.color-examples.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/color-examples/Index')
        ->has('examples.data', 1)
    );
});

test('storing a valid palette example succeeds', function () {
    actingAsAdmin();

    $response = $this->post(route('admin.color-examples.store'), [
        'type' => 'palette',
        'title' => '夏のパレット',
        'colors' => ['#3366ff', '#ff9933'],
    ]);

    $response->assertRedirect(route('admin.color-examples.index'));
    expect(ColorExample::where('title', '夏のパレット')->exists())->toBeTrue();
});

test('storing a gradient example without an angle fails validation', function () {
    actingAsAdmin();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.color-examples.store'), [
            'type' => 'gradient',
            'title' => '夕焼け',
            'colors' => ['#3366ff', '#ff9933'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['angle']);
});

test('storing an example with an invalid hex color fails validation', function () {
    actingAsAdmin();

    $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post(route('admin.color-examples.store'), [
            'type' => 'palette',
            'title' => '不正な色',
            'colors' => ['not-a-color', '#222222'],
        ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['colors.0']);
});

test('copy_count cannot be set via the form', function () {
    actingAsAdmin();

    $this->post(route('admin.color-examples.store'), [
        'type' => 'palette',
        'title' => 'テスト',
        'colors' => ['#111111', '#222222'],
        'copy_count' => 999,
    ]);

    expect(ColorExample::where('title', 'テスト')->first()->copy_count)->toBe(0);
});

test('updating an example works', function () {
    actingAsAdmin();
    $example = ColorExample::create(['type' => 'palette', 'title' => '旧タイトル', 'colors' => ['#111111', '#222222']]);

    $response = $this->put(route('admin.color-examples.update', $example), [
        'type' => 'palette',
        'title' => '新タイトル',
        'colors' => ['#333333', '#444444'],
    ]);

    $response->assertRedirect(route('admin.color-examples.index'));
    expect($example->fresh()->title)->toBe('新タイトル');
});

test('destroying an example removes it', function () {
    actingAsAdmin();
    $example = ColorExample::create(['type' => 'palette', 'title' => '削除対象', 'colors' => ['#111111', '#222222']]);

    $response = $this->delete(route('admin.color-examples.destroy', $example));

    $response->assertRedirect(route('admin.color-examples.index'));
    expect(ColorExample::find($example->id))->toBeNull();
});

test('guests cannot access color example admin routes', function () {
    $response = $this->get(route('admin.color-examples.index'));

    $response->assertRedirect(route('admin.login'));
});
