<?php

test('the application health endpoint responds successfully', function () {
    $this->get('/up')->assertOk();
});

test('the core member and administration routes are named', function () {
    expect(route('app', absolute: false))->toBe('/app')
        ->and(route('member-profile.show', absolute: false))->toBe('/profile')
        ->and(route('dashboard', absolute: false))->toBe('/dashboard');
});
