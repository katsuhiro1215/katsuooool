<?php

use App\Models\ToolEvent;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.users.index'));

    $response->assertRedirect(route('admin.login'));
});

test('the user list renders all users ordered by newest first', function () {
    actingAsAdmin();

    $older = User::factory()->create(['name' => '古い太郎']);
    $older->forceFill(['created_at' => now()->subDays(5)])->save();

    $newer = User::factory()->create(['name' => '新しい花子']);
    $newer->forceFill(['created_at' => now()->subDay()])->save();

    $response = $this->get(route('admin.users.index'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/Index')
        ->where('users.data.0.name', '新しい花子')
        ->where('users.data.1.name', '古い太郎')
    );
});

test('the user list can be filtered by a search query matching name or email', function () {
    actingAsAdmin();

    User::factory()->create(['name' => '山田太郎', 'email' => 'yamada@example.com']);
    User::factory()->create(['name' => '鈴木一郎', 'email' => 'suzuki@example.com']);

    $response = $this->get(route('admin.users.index', ['q' => '山田']));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/Index')
        ->has('users.data', 1)
        ->where('users.data.0.name', '山田太郎')
    );
});

test('the user detail page shows account status and paginated tool history', function () {
    actingAsAdmin();

    $user = User::factory()->create(['email_verified_at' => now()]);

    ToolEvent::create([
        'user_id' => $user->id,
        'tool_slug' => 'image-crop',
        'event_type' => 'process',
    ]);

    ToolEvent::create([
        'user_id' => null,
        'tool_slug' => 'image-crop',
        'event_type' => 'process',
    ]);

    $response = $this->get(route('admin.users.show', $user));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/users/Show')
        ->where('user.id', $user->id)
        ->where('user.email_verified', true)
        ->where('user.two_factor_enabled', false)
        ->has('toolEvents.data', 1)
        ->where('toolEvents.data.0.tool_slug', 'image-crop')
    );
});
