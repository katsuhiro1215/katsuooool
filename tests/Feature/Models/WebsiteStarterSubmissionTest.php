<?php

use App\Models\User;
use App\Models\WebsiteStarterSubmission;

test('only the whitelisted category columns are mass-assignable', function () {
    $user = User::factory()->create();

    $submission = WebsiteStarterSubmission::create([
        'user_id' => $user->id,
        'industry' => 'beauty',
        'prefecture' => '奈良県',
        'target_age_range' => '20s',
        'target_gender' => 'female',
        'purpose' => 'new_customers',
        'site_name' => 'この値は保存されないはず',
    ]);

    expect($submission->fresh())
        ->user_id->toBe($user->id)
        ->industry->toBe('beauty')
        ->prefecture->toBe('奈良県')
        ->target_age_range->toBe('20s')
        ->target_gender->toBe('female')
        ->purpose->toBe('new_customers');

    expect($submission->getAttributes())->not->toHaveKey('site_name');
});

test('target fields are nullable', function () {
    $user = User::factory()->create();

    $submission = WebsiteStarterSubmission::create([
        'user_id' => $user->id,
        'industry' => 'corporate',
        'prefecture' => '東京都',
    ]);

    expect($submission->fresh())
        ->target_age_range->toBeNull()
        ->target_gender->toBeNull()
        ->purpose->toBeNull();
});
