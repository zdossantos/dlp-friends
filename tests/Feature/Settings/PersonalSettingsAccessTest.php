<?php

use App\Enums\RoleName;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function personalSettingsUser(string $role): User
{
    $user = User::factory()->create();
    $user->roles()->sync([Role::query()->where('name', $role)->firstOrFail()->id]);

    return $user;
}

test('personal settings are available without a social profile or tutorial', function (string $role) {
    $user = personalSettingsUser($role);
    $this->actingAs($user)->get(route('account.edit'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('discoveryPreferences', null));
    $this->get(route('appearance.edit'))->assertOk();
    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('security.edit'))->assertOk();
    $this->patch(route('account.update'), ['email' => $user->email])->assertRedirect(route('account.edit'));
    $this->post(route('data-export.store'))->assertOk()->assertDownload();
    if ($role !== RoleName::User->value) {
        $this->patch(route('discovery-preferences.update'), ['min_age' => 18, 'max_age' => 99, 'min_common_interests' => 0])->assertForbidden();
        $this->get(route('discovery.index'))->assertForbidden();
    } else {
        $this->patch(route('discovery-preferences.update'), ['min_age' => 18, 'max_age' => 99, 'min_common_interests' => 0])->assertRedirect(route('member-profile.create'));
    }
})->with(['user', 'partner', 'admin']);

test('all roles can request their own deletion with the existing confirmation', function (string $role) {
    Queue::fake();
    $user = personalSettingsUser($role);
    $this->actingAs($user)->delete(route('account.destroy'), ['password' => 'incorrect'])->assertSessionHasErrors('password');
    $this->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect('/');
    $this->assertGuest();
    expect($user->fresh()->status->value)->toBe('pending_deletion');
})->with(['user', 'partner', 'admin']);
