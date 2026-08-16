<?php

use App\Models\ToolEvent;
use Illuminate\Support\Carbon;

test('tool-events:prune deletes events older than 30 days and keeps recent ones', function () {
    // created_at is not mass-assignable (it's not in ToolEvent's #[Fillable] list),
    // so it's set explicitly via forceFill() + save() after creation.
    $old = ToolEvent::create(['tool_slug' => 'image-compress', 'event_type' => 'upload']);
    $old->forceFill(['created_at' => Carbon::now()->subDays(31)])->save();

    $recent = ToolEvent::create(['tool_slug' => 'image-compress', 'event_type' => 'upload']);
    $recent->forceFill(['created_at' => Carbon::now()->subDays(10)])->save();

    $this->artisan('tool-events:prune')->assertSuccessful();

    expect(ToolEvent::find($old->id))->toBeNull();
    expect(ToolEvent::find($recent->id))->not->toBeNull();
});
