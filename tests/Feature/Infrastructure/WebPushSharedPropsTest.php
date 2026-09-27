<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Http\Request;

test('the web push public key is shared with every Inertia page', function () {
    config()->set('services.web_push.public_key', 'public-test-key');

    $shared = app(HandleInertiaRequests::class)->share(Request::create('/'));

    expect($shared['webPush']['vapidPublicKey'])->toBe('public-test-key');
});
