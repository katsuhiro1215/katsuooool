<?php

use App\Models\Admin;
use App\Models\ToolEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function actingAsAdmin(): Admin
{
    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => Hash::make('password1234'),
    ]);

    test()->actingAs($admin, 'admin');

    return $admin;
}

test('unauthenticated visitors are redirected to the admin login page', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
});

test('a web-authenticated user (not an admin) cannot access the admin dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertRedirect(route('admin.login'));
    $this->assertGuest('admin');
});

test('the dashboard renders zeroed stats when there is no data', function () {
    actingAsAdmin();

    $response = $this->get(route('admin.dashboard'));

    $response->assertSuccessful();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->has('stats', 1)
        ->where('stats.0.slug', 'image-compress')
        ->where('stats.0.total_executions', 0)
        ->where('stats.0.total_files', 0)
        ->where('stats.0.last_7_days', 0)
        ->where('stats.0.last_30_days', 0)
    );
});

test('the dashboard aggregates tool_events correctly', function () {
    actingAsAdmin();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 2,
    ])->forceFill(['created_at' => Carbon::now()->subDays(2)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 3,
    ])->forceFill(['created_at' => Carbon::now()->subDays(20)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'process',
        'file_count' => 1,
    ])->forceFill(['created_at' => Carbon::now()->subDays(40)])->save();

    ToolEvent::create([
        'tool_slug' => 'image-compress',
        'event_type' => 'upload',
        'file_count' => 5,
    ]);

    $response = $this->get(route('admin.dashboard'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/Dashboard')
        ->where('stats.0.total_executions', 3)
        ->where('stats.0.total_files', 6)
        ->where('stats.0.last_7_days', 1)
        ->where('stats.0.last_30_days', 2)
    );
});
