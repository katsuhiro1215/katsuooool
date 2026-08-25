<?php

test('the application timezone is Asia/Tokyo', function () {
    expect(config('app.timezone'))->toBe('Asia/Tokyo');
});
