<?php

use App\Models\User;

test('a visitor changes locale from the public language selector', function () {
    visit('/')
        ->assertVisible('[data-test="locale-switcher"]')
        ->select('locale', 'en')
        ->assertSee('Strictly friendly connections between adult fans')
        ->assertSee('Create my account')
        ->assertScript('document.documentElement.lang', 'en')
        ->assertNoJavaScriptErrors();
});

test('the visitor locale remains active on authentication pages', function () {
    visit('/')
        ->select('locale', 'en')
        ->navigate('/login')
        ->assertSee('Welcome back')
        ->assertSee('Sign in')
        ->assertScript('document.documentElement.lang', 'en')
        ->assertNoJavaScriptErrors();
});

test('an account locale translates member settings', function () {
    $this->actingAs(User::factory()->withProfile()->create(['locale' => 'en']));

    visit('/settings/account')
        ->assertSee('Account settings')
        ->assertSee('Email address')
        ->assertScript('document.documentElement.lang', 'en')
        ->assertNoJavaScriptErrors();
});

test('an English member sees translated profile and discovery pages', function () {
    $member = User::factory()->withProfile()->create(['locale' => 'en']);
    $this->actingAs($member);

    visit('/profile')
        ->assertSee('My profile')
        ->assertSee('About')
        ->assertNoJavaScriptErrors();

    visit('/discover')
        ->assertSee('Discover')
        ->assertSee('Members who share your interests.')
        ->assertNoJavaScriptErrors();
});

test('an English administrator sees translated catalog navigation and dashboard copy', function () {
    $admin = User::factory()->withProfile()->admin()->create(['locale' => 'en']);
    $this->actingAs($admin);

    visit('/dashboard')
        ->assertSee('Track member account activity and completion.')
        ->assertSee('Accounts created')
        ->assertSee('Recent registrations')
        ->assertNoJavaScriptErrors();

    visit('/admin/interests')
        ->assertSee('Add an interest')
        ->assertSee('Selection limit')
        ->assertNoJavaScriptErrors();
});
