<?php

use App\Models\CopyEvent;
use App\Models\UiPatternSnippet;
use Illuminate\Support\Carbon;

test('copy-events:prune deletes events older than 7 days and keeps recent ones', function () {
    $snippet = UiPatternSnippet::create([
        'category' => 'button',
        'title' => 'テストボタン',
        'html' => '<a href="#">x</a>',
        'css' => 'a {}',
    ]);

    // created_at is not mass-assignable (it's not in CopyEvent's #[Fillable] list),
    // so it's set explicitly via forceFill() + save() after creation.
    $old = CopyEvent::create([
        'copyable_type' => UiPatternSnippet::class,
        'copyable_id' => $snippet->id,
        'ip_hash' => 'old-hash',
    ]);
    $old->forceFill(['created_at' => Carbon::now()->subDays(8)])->save();

    $recent = CopyEvent::create([
        'copyable_type' => UiPatternSnippet::class,
        'copyable_id' => $snippet->id,
        'ip_hash' => 'recent-hash',
    ]);
    $recent->forceFill(['created_at' => Carbon::now()->subDays(3)])->save();

    $this->artisan('copy-events:prune')->assertSuccessful();

    expect(CopyEvent::find($old->id))->toBeNull();
    expect(CopyEvent::find($recent->id))->not->toBeNull();
});
