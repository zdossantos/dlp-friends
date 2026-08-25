<?php

use App\Models\Avatar;
use App\Models\Interest;
use App\Models\InterestCategory;
use App\Models\InterestSetting;
use App\Models\User;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Support\Facades\Storage;

test('the avatar catalog renders images color gradients and admin controls', function () {
    Storage::fake('local');
    $avatar = Avatar::factory()->create([
        'name' => 'Aurore',
        'image_path' => 'avatars/aurore.png',
        'primary_color' => '#7C3AED',
        'secondary_color' => '#EC4899',
        'sort_order' => 0,
    ]);
    Storage::disk('local')->put($avatar->image_path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL8WQAAAABJRU5ErkJggg==',
    ));
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/avatars')
        ->assertSee('Avatars')
        ->assertValue("#avatar-name-{$avatar->id}", 'Aurore')
        ->assertValue("#avatar-primary-{$avatar->id}", '#7c3aed')
        ->assertValue("#avatar-secondary-{$avatar->id}", '#ec4899')
        ->assertPresent('input[name="image"][type="file"]')
        ->assertPresent('input[name="primary_color"][type="color"]')
        ->assertPresent('input[name="secondary_color"][type="color"]')
        ->assertPresent('img[alt="Avatar Aurore"]')
        ->assertScript(
            "document.querySelector('[data-test=avatar-preview-{$avatar->id}]').style.backgroundImage.includes('rgb(124, 58, 237)')",
            true,
        )
        ->assertPresent('[aria-label="Archiver Aurore"]')
        ->assertPresent('[aria-label="Supprimer Aurore"]')
        ->assertNoJavaScriptErrors();
});

test('the admin dashboard renders account statistics and recent registrations', function () {
    User::factory()->create(['email' => 'recent@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/dashboard')
        ->assertSee('Administration')
        ->assertSee('Comptes créés')
        ->assertSee('Comptes actifs')
        ->assertSee('Emails vérifiés')
        ->assertSee('Profils complétés')
        ->assertSee('recent@example.test')
        ->assertSee('Profil à compléter')
        ->assertNoJavaScriptErrors();
});

test('the catalog shows state history limit ordering and deletion boundaries', function () {
    InterestSetting::current()->update(['max_selections' => 5]);
    $archived = Interest::factory()->create([
        'name' => 'Chill',
        'is_active' => false,
        'sort_order' => 0,
    ]);
    $used = Interest::factory()->create([
        'name' => 'Spectacles',
        'is_active' => true,
        'sort_order' => 1,
    ]);
    Interest::factory()->create([
        'name' => 'Parades',
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $member = User::factory()->withProfile()->create();
    $member->profile?->interestHistory()->attach($archived, ['is_selected' => false]);
    $member->profile?->interestHistory()->attach($used, ['is_selected' => true]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertValue('input[name="max_selections"]', '5')
        ->assertSee('Archivé')
        ->assertSee('1 profil dans l’historique')
        ->assertScript(
            "document.querySelector('[aria-label=\"Monter Chill\"]').disabled",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Parades\"]').disabled",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Chill\"]').disabled",
            false,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Monter Spectacles\"]').disabled",
            false,
        )
        ->assertScript(
            "document.querySelector('[aria-label=\"Supprimer Spectacles\"]').disabled",
            true,
        )
        ->assertSee('Cet intérêt doit être archivé avant de pouvoir être supprimé.')
        ->assertScript(
            "document.querySelector('[aria-label=\"Supprimer Chill\"]').disabled",
            false,
        );
});

test('catalog controls expose generated forms and compact accessible layout', function () {
    $interest = Interest::factory()->create([
        'name' => 'Chill',
        'sort_order' => 0,
    ]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertPresent('[data-test="catalog-controls"]')
        ->assertPresent('[data-test="create-interest-form"]')
        ->assertPresent("input[aria-label='Nom de l’intérêt Chill']")
        ->assertScript(
            "document.querySelector('[data-test=\"catalog-controls\"] input[name=max_selections]') !== null",
            true,
        )
        ->assertScript(
            "document.querySelector('[data-test=\"catalog-controls\"] [aria-labelledby=interest-catalog-title]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('#interest-name-{$interest->id}').closest('form').action.endsWith('/admin/interests/{$interest->id}?_method=PUT')",
            true,
        )
        ->assertScript(
            "document.querySelector('input[name=max_selections]').closest('form').action.endsWith('/admin/interest-setting?_method=PATCH')",
            true,
        )
        ->assertScript(
            "document.querySelector('input[name=is_active][value=\"0\"]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-labelledby=interest-catalog-title] [class~=\"py-0\"]') !== null",
            true,
        )
        ->assertScript(
            "document.querySelector('[aria-labelledby=interest-catalog-title] [class~=\"p-3\"]') !== null",
            true,
        );
});

test('catalog actions stay aligned with their inputs and reserve validation space', function () {
    Interest::factory()->create(['name' => 'Chill']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertScript(
            "document.querySelector('#new_interest_name').parentElement === document.querySelector('#new_interest_name').closest('form').querySelector('button[type=submit]').parentElement",
            true,
        )
        ->assertScript(
            "document.querySelector('#new_interest_name').closest('form').querySelector('[data-test=input-error]') === null",
            true,
        )
        ->assertScript(
            "document.querySelector('#max_selections').parentElement === document.querySelector('#max_selections').closest('form').querySelector('button[type=submit]').parentElement",
            true,
        )
        ->assertNoJavaScriptErrors();
});

test('duplicate interest validation keeps the catalog unchanged', function () {
    Interest::factory()->create(['name' => 'Chill']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->app->instance(ExceptionHandlerContract::class, new ExceptionHandler($this->app));
    $this->actingAs($admin);

    visit('/admin/interests')
        ->fill('new_interest_name', 'Chill')
        ->click('Ajouter')
        ->assertSee('Le nom a déjà été utilisé.')
        ->assertValue('new_interest_name', 'Chill');

    $this->assertDatabaseCount('interests', 1);
});

test('an admin manages interests through confirmations and generated actions', function () {
    InterestCategory::factory()->create(['name' => 'Général']);
    $interest = Interest::factory()->create([
        'name' => 'Chill',
        'sort_order' => 0,
    ]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/interests')
        ->clear('max_selections')
        ->fill('max_selections', '7')
        ->click('Enregistrer')
        ->assertSee('Limite mise à jour.')
        ->fill('new_interest_name', 'Parades')
        ->click('Ajouter')
        ->assertSee('Intérêt ajouté.');

    $this->assertDatabaseHas('interest_settings', ['max_selections' => 7]);
    $this->assertDatabaseHas('interests', ['name' => 'Parades']);

    $page->click('[aria-label="Descendre Chill"]')
        ->assertScript(
            "document.querySelector('[aria-label=\"Descendre Chill\"]').disabled",
            true,
        );

    expect($interest->fresh()?->sort_order)->toBe(1);

    $page->fill("interest-name-{$interest->id}", 'Chill renommé');
    $page->script("document.querySelector('#interest-name-{$interest->id}').closest('form').querySelector('button[type=submit]').click()");
    $page->assertSee('Intérêt modifié.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'name' => 'Chill renommé',
    ]);

    $page->click('[aria-label="Archiver Chill renommé"]')
        ->assertSee('Archiver l’intérêt Chill renommé')
        ->assertSee('Son historique sera conservé.')
        ->assertSee('Annuler');
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt archivé.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'is_active' => false,
    ]);

    $page->click('[aria-label="Réactiver Chill renommé"]')
        ->assertSee('Intérêt réactivé.');

    $this->assertDatabaseHas('interests', [
        'id' => $interest->id,
        'is_active' => true,
    ]);

    $page->click('[aria-label="Archiver Chill renommé"]');
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt archivé.');

    $page->click('[aria-label="Supprimer Chill renommé"]')
        ->assertSee('Supprimer l’intérêt Chill renommé')
        ->assertSee('Cette action est définitive.')
        ->assertSee('Annuler');
    $page->script("document.querySelector('[role=dialog] button[type=submit]').click()");
    $page->assertSee('Intérêt supprimé.');

    $this->assertDatabaseMissing('interests', ['id' => $interest->id]);
});

test('a catalog move disables its control and preserves the scroll position', function () {
    $interests = collect(range(0, 11))->map(fn (int $index): Interest => Interest::factory()->create([
        'name' => "Intérêt {$index}",
        'sort_order' => $index,
    ]));
    $interest = $interests->get(5);
    expect($interest)->toBeInstanceOf(Interest::class);

    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    $page = visit('/admin/interests')->assertValue("#interest-name-{$interest->id}", $interest->name);
    $page->script(<<<JS
        window.__realAdminXhrSend = XMLHttpRequest.prototype.send;
        window.__releaseAdminRequest = null;
        XMLHttpRequest.prototype.send = function (body) {
            if (String(this.responseURL || this.__browserTestUrl || '').includes('/admin/interests/{$interest->id}/move')) {
                window.__releaseAdminRequest = () => window.__realAdminXhrSend.call(this, body);

                return;
            }

            return window.__realAdminXhrSend.call(this, body);
        };
        window.__realAdminXhrOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url, ...rest) {
            this.__browserTestUrl = String(url);

            return window.__realAdminXhrOpen.call(this, method, url, ...rest);
        };
        true;
    JS);
    $page->script('window.scrollTo(0, document.body.scrollHeight); true;');
    $scrollY = $page->script('window.scrollY');

    $page->script("document.querySelector('[aria-label=\"Descendre {$interest->name}\"]').click()");
    $page->assertScript('window.__releaseAdminRequest !== null', true)
        ->assertDisabled("[aria-label=\"Descendre {$interest->name}\"]");
    $page->script('window.__releaseAdminRequest(); true;');
    $page->assertEnabled("[aria-label=\"Descendre {$interest->name}\"]")
        ->assertScript("Math.abs(window.scrollY - {$scrollY}) <= 1", true);

    expect($interest->fresh()?->sort_order)->toBe(6);
});
