<?php

use App\Models\Interest;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the profile form exposes the English interest label for an English request', function () {
    config()->set('inertia.testing.ensure_pages_exist', false);
    $interest = Interest::factory()->create(['name' => 'Attractions de test', 'name_en' => 'Test rides']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get(route('member-profile.create'))
        ->assertInertia(fn (Assert $page) => $page->where('interests.0.name', 'Test rides'));
});
