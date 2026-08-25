# Pest Browser Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace all 17 active Vitest files with behavior-oriented Pest Browser coverage and make Pest the project's single test entry point.

**Architecture:** Pest Browser drives Chromium against the real Laravel/Inertia routes while Laravel factories and `RefreshDatabase` prepare isolated MySQL state. Browser tests are grouped by product journey under `tests/Browser`; Wayfinder and Vite assets are built before Pest, and no test-only application routes are introduced.

**Tech Stack:** PHP 8.4, Laravel 13, Pest 5, `pestphp/pest-plugin-browser`, Playwright/Chromium, Bun 1.3.14, Inertia 3, Vue 3, MySQL 8.4, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-25-pest-browser-migration-design.md`

## Global Constraints

- Migrate every scenario represented by the 17 active `resources/js/**/*.spec.ts` files.
- Assert observable behavior on real application routes; do not add test-only pages or routes.
- Use Chromium only for this migration; Firefox and WebKit remain out of scope.
- Follow red, green, refactor for each product-journey group and delete an old spec only after its replacement passes.
- Keep ESLint, Prettier, TypeScript, Wayfinder generation, and Vite build checks independent of Pest.
- Preserve the MySQL test-database guard added by issue 68.
- Do not rewrite historical documents under `docs/superpowers/` other than this plan and its approved design.
- Never version Playwright screenshots, traces, or browser artifacts.

## File Structure

- `tests/Browser/WelcomeAndRegistrationTest.php`: public landing, authenticated landing, registration fields, and auth-card accessibility.
- `tests/Browser/ProfileAndNavigationTest.php`: onboarding/profile form, profile display, user identity fallback, role-specific navigation, member layout, and settings nesting.
- `tests/Browser/AdminTest.php`: dashboard data and observable interest-catalog workflows.
- `tests/Browser/DiscoveryTest.php`: discovery states, accessible decisions, gestures, locking, retry behavior, and match dialog.
- `tests/Browser/AppearanceTest.php`: stored/system appearance and stable initialization across Inertia navigation.
- `tests/Pest.php`: shared Laravel binding and `RefreshDatabase` for browser tests.
- `phpunit.xml`: Browser suite discovery.
- `composer.json`, `composer.lock`: Pest Browser dependency and the single Pest entry point.
- `package.json`, `bun.lock`: Playwright dependency; Vitest, jsdom, and Vue Test Utils removal.
- `.github/workflows/ci.yml`: Chromium installation, asset build, and complete Pest run.
- `.gitignore`: generated browser artifacts.
- `AGENTS.md`, `CONTRIBUTING.md`, `README.md`, `docs/technical-architecture.md`, `docs/quality-ci-cd.md`: active documentation.

---

### Task 1: Establish the Pest Browser runtime

**Files:**
- Create: `tests/Browser/BrowserRuntimeTest.php`
- Modify: `tests/Pest.php`
- Modify: `phpunit.xml`
- Modify: `.gitignore`
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `bun.lock`

**Interfaces:**
- Consumes: existing `Tests\TestCase`, `.env.testing`, MySQL guard, Laravel Vite manifest.
- Produces: `tests/Browser` suite using `Tests\TestCase` plus `RefreshDatabase`; installed `visit(string $url)` browser API; Bun-provided Playwright Chromium runtime.

- [ ] **Step 1: Add the browser smoke test before installing the plugin**

```php
<?php

test('the public application boots in a real browser', function () {
    visit('/')
        ->assertSee('DLP Friends')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Run the smoke test and confirm the red state**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/BrowserRuntimeTest.php`

Expected: FAIL because `visit()` is unavailable before `pestphp/pest-plugin-browser` is installed.

- [ ] **Step 3: Install compatible Pest Browser and Playwright dependencies**

```bash
composer require --dev pestphp/pest-plugin-browser:^5.0 --with-all-dependencies
bun add --dev playwright
bunx playwright install chromium
```

Confirm that Composer keeps Pest and its Laravel plugin on compatible `5.x` releases and that Bun updates `bun.lock`.

- [ ] **Step 4: Register the Browser suite and its Laravel test lifecycle**

Add to `tests/Pest.php`:

```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

pest()->browser()->timeout(10000);
```

Add inside `<testsuites>` in `phpunit.xml`:

```xml
<testsuite name="Browser">
    <directory>tests/Browser</directory>
</testsuite>
```

Add to `.gitignore`:

```gitignore
/tests/Browser/Screenshots
/tests/Browser/Traces
```

- [ ] **Step 5: Generate and build frontend assets, then verify green**

Run:

```bash
php artisan wayfinder:generate --with-form
bun run build
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/BrowserRuntimeTest.php
```

Expected: PASS with Chromium and no JavaScript error.

- [ ] **Step 6: Commit the browser runtime**

```bash
git add composer.json composer.lock package.json bun.lock phpunit.xml tests/Pest.php tests/Browser/BrowserRuntimeTest.php .gitignore
git commit -m "test: configure Pest browser runtime"
```

---

### Task 2: Migrate public, registration, and authentication-layout behavior

**Files:**
- Create: `tests/Browser/WelcomeAndRegistrationTest.php`
- Delete after green: `resources/js/pages/Welcome.spec.ts`
- Delete after green: `resources/js/pages/auth/Register.spec.ts`
- Delete after green: `resources/js/layouts/auth/AuthCardLayout.spec.ts`

**Interfaces:**
- Consumes: `visit()`, `User::factory()`, real `/`, `/register`, `/login` routes.
- Produces: browser coverage for guest/member landing variants, mobile header, registration field contract, auth form landmark, brand, and theme control.

- [ ] **Step 1: Write the public and registration browser tests**

Create tests with these concrete assertions:

```php
<?php

use App\Models\User;

test('the landing page presents the adult friendship service to guests', function () {
    visit('/')
        ->assertSee('DLP Friends')
        ->assertSee('Des rencontres strictement amicales entre fans adultes')
        ->assertSeeLink('Créer mon compte')
        ->assertSeeLink('Se connecter')
        ->assertNoJavaScriptErrors();
});

test('the landing page offers the member space when signed in', function () {
    $this->actingAs(User::factory()->withProfile()->create());

    visit('/')
        ->assertSeeLink('Ouvrir mon espace')
        ->assertDontSeeLink('Créer mon compte');
});

test('the registration form collects account data without a public name', function () {
    visit('/register')
        ->assertPresent('input[name="email"]')
        ->assertPresent('input[name="birth_date"]')
        ->assertPresent('input[name="password"]')
        ->assertPresent('input[name="password_confirmation"]')
        ->assertNotPresent('input[name="username"]');
});

test('the auth card exposes brand theme control and form landmark', function () {
    visit('/login')
        ->assertSee('DLP Friends')
        ->assertPresent('main form')
        ->assertPresent('button[aria-label*="thème" i]')
        ->assertNoAccessibilityIssues();
});

test('the landing header stacks correctly on a mobile viewport', function () {
    visit('/')
        ->on()->mobile()
        ->assertScript(
            "getComputedStyle(document.querySelector('header > div')).flexDirection",
            'column',
        );
});
```

- [ ] **Step 2: Run the new file and confirm the expected red assertions**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php`

Expected: FAIL on the mobile layout assertion before the test targets the actual `header` element rather than the nonexistent `header > div` wrapper. No test may mount a Vue component directly.

- [ ] **Step 3: Correct the mobile assertion against the real header**

Inspect the failing layout in headed mode:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --debug
```

Replace `document.querySelector('header > div')` with `document.querySelector('header')`; keep the expected computed `flexDirection` equal to `column`. Keep all other selectors on accessible names or stable semantic elements.

- [ ] **Step 4: Verify green, compare scenarios, and delete replaced specs**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php`

Expected: PASS for guest copy/actions, signed-in action, mobile header, registration inputs, auth form landmark, brand, and theme control.

Then delete the three listed spec files with `apply_patch` and run the test again.

- [ ] **Step 5: Commit the public/auth migration**

```bash
git add tests/Browser/WelcomeAndRegistrationTest.php resources/js/pages/Welcome.spec.ts resources/js/pages/auth/Register.spec.ts resources/js/layouts/auth/AuthCardLayout.spec.ts
git commit -m "test: migrate public frontend coverage to Pest"
```

---

### Task 3: Migrate profile, identity, layout, and navigation behavior

**Files:**
- Create: `tests/Browser/ProfileAndNavigationTest.php`
- Delete after green: `resources/js/components/AppSidebar.spec.ts`
- Delete after green: `resources/js/components/MemberBottomNavigation.spec.ts`
- Delete after green: `resources/js/components/UserInfo.spec.ts`
- Delete after green: `resources/js/components/profile/InterestTagSelector.spec.ts`
- Delete after green: `resources/js/components/profile/ProfileForm.spec.ts`
- Delete after green: `resources/js/layouts/MemberLayout.spec.ts`
- Delete after green: `resources/js/layouts/resolvePageLayout.spec.ts`
- Delete after green: `resources/js/pages/profile/Create.spec.ts`
- Delete after green: `resources/js/pages/profile/Show.spec.ts`

**Interfaces:**
- Consumes: `User::factory()->withProfile()`, `User::factory()->admin()`, `Interest` factories, `/profile/create`, `/profile`, `/profile/edit`, `/settings/account`.
- Produces: browser coverage of the profile form contract, interest-selection limit, display-name fallback, role navigation, settings shell, onboarding navigation visibility, logout cleanup, and mobile safe-area layout.

- [ ] **Step 1: Write profile contract and interest behavior tests**

Use factories and selectors from the actual form:

```php
test('profile onboarding exposes the complete accessible contract', function () {
    $user = User::factory()->create();
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    visit('/profile/create')
        ->on()->mobile()
        ->assertPresent('input[name="display_name"]')
        ->assertPresent('textarea[name="bio"]')
        ->assertPresent('[name="visit_frequency"]')
        ->assertSee('Attractions')
        ->assertSee('Spectacles')
        ->assertNoJavaScriptErrors();
});

test('interest selection disables only unselected choices at the limit', function () {
    $user = User::factory()->create();
    InterestSetting::current()->update(['max_selections' => 1]);
    Interest::factory()->create(['name' => 'Attractions']);
    Interest::factory()->create(['name' => 'Spectacles']);
    $this->actingAs($user);

    visit('/profile/create')
        ->click('Attractions')
        ->assertEnabled('Attractions')
        ->assertDisabled('Spectacles')
        ->click('Attractions')
        ->assertEnabled('Spectacles');
});
```

Import `App\Models\Interest`, `App\Models\InterestSetting`, and `App\Models\User` at the top of the file.

- [ ] **Step 2: Write profile identity and role-navigation tests**

```php
test('a completed member sees the public profile summary and edit action', function () {
    $user = User::factory()->withProfile()->create();
    $user->profile?->update(['display_name' => 'Aurore', 'bio' => 'Fan des attractions']);
    $this->actingAs($user);

    visit('/profile')
        ->assertSee('Aurore')
        ->assertSee('Fan des attractions')
        ->assertSeeLink('Modifier mon profil')
        ->assertSee('Réglages')
        ->assertDontSee('Administration');
});

test('an administrator sees administration navigation and member return', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/dashboard')
        ->assertSeeLink('Intérêts')
        ->assertSeeLink('Mon profil');
});

test('identity falls back to email until a profile is complete', function () {
    $user = User::factory()->create(['email' => 'membre@example.test']);
    $this->actingAs($user);

    visit('/profile/create')->assertSee('membre@example.test');
});

test('settings remain nested in the member shell', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/settings/account')
        ->assertSee('Compte')
        ->assertPresent('nav')
        ->assertPresent('main');
});
```

- [ ] **Step 3: Add mobile active-state, onboarding, and logout-state tests**

Drive the real `/discover`, `/settings/account`, and `/profile` pages on a mobile viewport. Assert `aria-current="page"` on the expected destination, absence of the dock before profile completion, and after clicking the real logout action assert the login page plus removal of Inertia/session keys previously inserted with `script("localStorage.setItem('dlp-test', 'value')")`.

Concrete final assertions:

```php
$page->assertPresent('a[aria-current="page"]')
    ->assertScript("localStorage.getItem('dlp-test')", null)
    ->assertNoJavaScriptErrors();
```

- [ ] **Step 4: Run the profile/navigation file to establish and resolve red states**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php`

Expected: FAIL initially on selectors or behavior not yet expressed through a route. Adjust only tests toward the existing observable UI; if a genuine accessibility gap prevents selection, add the smallest semantic attribute to the relevant Vue component and cover it here.

- [ ] **Step 5: Verify green and delete the nine replaced specs**

Run the same targeted command until PASS, compare every old `it(...)` title against this task's coverage, delete all nine listed specs with `apply_patch`, and rerun the file.

- [ ] **Step 6: Commit profile and navigation migration**

```bash
git add tests/Browser/ProfileAndNavigationTest.php resources/js/components resources/js/layouts resources/js/pages/profile
git commit -m "test: migrate profile frontend coverage to Pest"
```

---

### Task 4: Migrate dashboard and interest-catalog behavior

**Files:**
- Create: `tests/Browser/AdminTest.php`
- Delete after green: `resources/js/pages/Dashboard.spec.ts`
- Delete after green: `resources/js/pages/Admin/Interests/Index.spec.ts`

**Interfaces:**
- Consumes: admin factory state, interest/category/setting factories, `/dashboard`, `/admin/interests`, existing authorization and form endpoints.
- Produces: browser coverage for statistics, recent users, catalog state/history/order, validation, form actions, processing state, confirmations, and persistence.

- [ ] **Step 1: Write dashboard and catalog rendering tests**

```php
test('the admin dashboard renders account statistics and recent registrations', function () {
    User::factory()->create(['email' => 'recent@example.test']);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/dashboard')
        ->assertSee('Administration')
        ->assertSee('recent@example.test')
        ->assertNoJavaScriptErrors();
});

test('the catalog shows state history selection limit and ordering boundaries', function () {
    InterestSetting::current()->update(['max_selections' => 7]);
    $first = Interest::factory()->create(['name' => 'Chill', 'sort_order' => 10]);
    $last = Interest::factory()->create(['name' => 'Spectacles', 'sort_order' => 20, 'is_active' => false]);
    User::factory()->withProfile()->create()->profile?->interestHistory()->attach($last, ['is_selected' => false]);
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/interests')
        ->assertValue('input[name="max_selections"]', '7')
        ->assertSee('Chill')
        ->assertSee('Spectacles')
        ->assertDisabled('[aria-label="Monter Chill"]')
        ->assertDisabled('[aria-label="Descendre Spectacles"]');
});
```

- [ ] **Step 2: Write real form workflow tests**

Cover creation, rename, selection-limit update, move, archive confirmation, reactivate, delete confirmation, and validation. Each action must click or submit the rendered form and then assert both visible feedback and database state, for example:

```php
$page = visit('/admin/interests')
    ->fill('new_interest_name', 'Parades')
    ->click('Ajouter')
    ->assertSee('Intérêt ajouté.');

$this->assertDatabaseHas('interests', ['name' => 'Parades']);

$page->click('[aria-label="Archiver Parades"]')
    ->assertSee('Confirmer l’archivage')
    ->click('Archiver')
    ->assertSee('Intérêt archivé.');

$this->assertDatabaseHas('interests', ['name' => 'Parades', 'is_active' => false]);
```

Assert the dialog titles `Archiver l’intérêt Parades` and `Supprimer l’intérêt Parades`, and the `Archiver`, `Supprimer`, and `Annuler` buttons. For duplicate-name validation, submit `Chill` when that interest already exists, assert `Le nom a déjà été utilisé.` and assert the database count stays unchanged.

- [ ] **Step 3: Verify processing and scroll-preservation behavior observably**

Before a move/edit/reactivate click, record `window.scrollY` with `$page->script('window.scrollY')`; after the Inertia response, assert it remains within one pixel. To observe the processing state deterministically, inject a wrapper around `window.fetch` that stores the real request behind `window.__releaseRequest`, click the action, assert its rendered button is disabled, then call `window.__releaseRequest()` and assert the completed response. Do not assert Vue props or emitted events.

Inject this wrapper before the click:

```javascript
const originalFetch = window.fetch.bind(window);
window.fetch = (...args) => new Promise((resolve, reject) => {
    window.__releaseRequest = () => originalFetch(...args).then(resolve, reject);
});
```

- [ ] **Step 4: Run the admin test file and resolve red states**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/AdminTest.php`

Expected: the first run FAILS until the fetch wrapper releases the request; the corrected test PASSES without a sleep.

- [ ] **Step 5: Delete the two replaced specs and verify green**

Delete `Dashboard.spec.ts` and `Admin/Interests/Index.spec.ts` with `apply_patch`, then rerun the targeted Pest file.

- [ ] **Step 6: Commit administration migration**

```bash
git add tests/Browser/AdminTest.php resources/js/pages/Dashboard.spec.ts resources/js/pages/Admin/Interests/Index.spec.ts
git commit -m "test: migrate administration frontend coverage to Pest"
```

---

### Task 5: Migrate discovery cards, decisions, retries, and match dialog

**Files:**
- Create: `tests/Browser/DiscoveryTest.php`
- Delete after green: `resources/js/components/discovery/SwipeCard.spec.ts`
- Delete after green: `resources/js/pages/Discovery/Index.spec.ts`

**Interfaces:**
- Consumes: complete member factories, interests, reciprocal `Swipe`, `/discover`, `/discover/{target}/swipe`.
- Produces: real-browser coverage for loading/empty/five-card states, accessible and keyboard decisions, pointer threshold/diagonal/cancel behavior, decision lock/retry, stale retry protection, and match dialog lifecycle.

- [ ] **Step 1: Write loading, empty, and stack-size tests**

```php
test('discovery renders an empty state when no suggestion exists', function () {
    $actor = User::factory()->withProfile()->create();
    $this->actingAs($actor);

    visit('/discover')
        ->assertSee('Découvrir')
        ->assertSee('Aucun profil')
        ->assertNoJavaScriptErrors();
});

test('discovery preloads no more than five profiles', function () {
    $actor = User::factory()->withProfile()->create();
    User::factory()->withProfile()->count(6)->create();
    $this->actingAs($actor);

    visit('/discover')
        ->assertCount('[data-test="discovery-card-stack-item"]', 5)
        ->assertPresent('[aria-label="Profils à découvrir"]');
});
```

- [ ] **Step 2: Write accessible decision and keyboard tests**

Create an actor and target with complete profiles. Visit `/discover`, focus the top card, send `{ArrowLeft}` and `{ArrowRight}` through `keys()`, and assert the corresponding `swipes.decision` row. Separately click the reader-accessible pass/like controls and assert exactly one database row per decision. Assert those controls are present to assistive technology and not visually competing in the normal card presentation.

Concrete persistence assertion:

```php
$this->assertDatabaseHas('swipes', [
    'actor_user_id' => $actor->id,
    'target_user_id' => $target->id,
    'decision' => SwipeDecision::Pass->value,
]);
```

- [ ] **Step 3: Write pointer-gesture tests with Playwright script events**

Dispatch `pointerdown`, `pointermove`, `pointerup`, and `pointercancel` on the top card with explicit coordinates. Use a 71-pixel horizontal delta to assert no swipe, a 72-pixel delta to assert one swipe, and a diagonal delta with larger vertical movement to assert no swipe. During movement, assert the card transform changes and after a short/cancelled gesture assert it returns to center.

Use this browser-side event helper directly in `$page->script(...)`:

```javascript
const card = document.querySelector('[data-test="discovery-card-stack-item"]');
for (const [type, x, y] of [['pointerdown', 100, 100], ['pointermove', 172, 100], ['pointerup', 172, 100]]) {
    card.dispatchEvent(new PointerEvent(type, { pointerId: 1, clientX: x, clientY: y, bubbles: true }));
}
```

- [ ] **Step 4: Write error, locking, stale retry, and match-dialog tests**

For the 422 path, render the target, then update its profile visibility to `hidden` before clicking Like. Assert one request while locked, the original card remains, and the alert plus `Réessayer` control appear. Restore visibility before retry and assert the retry targets that profile. For the unexpected-error path, bind this `CreateSwipe` test double in Laravel's container and assert the same retry UI without changing production routes:

```php
$this->app->bind(CreateSwipe::class, fn (): CreateSwipe => new class extends CreateSwipe
{
    public function handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch
    {
        throw new RuntimeException('browser test failure');
    }
});
```

Replace the top suggestion before retry and assert no swipe is persisted for the replacement, proving stale decisions are not replayed. For a reciprocal like, seed the target's like, perform the actor's like, and assert `C’est un match !`, the target display name, and the `Continuer à découvrir` button; close it and assert it does not reopen on a page revisit without another match.

Required visible assertions:

```php
$page->assertPresent('[role="alert"]')
    ->assertPresent('button[aria-label="Réessayer"]')
    ->click('Réessayer');

$page->assertPresent('[data-slot="dialog-title"]')
    ->assertPresent('[data-slot="dialog-description"]')
    ->click('Continuer à découvrir')
    ->assertNotPresent('[data-slot="dialog-title"]');
```

- [ ] **Step 5: Run the discovery file through red and green**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php`

Expected: FAIL before route interception/gesture locators are correct; final run PASS with no arbitrary `sleep()` calls.

- [ ] **Step 6: Delete the two discovery specs and commit**

Delete both listed specs with `apply_patch`, rerun the file, then:

```bash
git add tests/Browser/DiscoveryTest.php resources/js/components/discovery/SwipeCard.spec.ts resources/js/pages/Discovery/Index.spec.ts
git commit -m "test: migrate discovery frontend coverage to Pest"
```

---

### Task 6: Migrate appearance behavior

**Files:**
- Create: `tests/Browser/AppearanceTest.php`
- Delete after green: `resources/js/composables/useAppearance.spec.ts`

**Interfaces:**
- Consumes: `/settings/appearance`, browser `localStorage`, color-scheme emulation, real theme controls and Inertia navigation.
- Produces: coverage for stored preference precedence, system fallback, and stable initialization without duplicate visible effects.

- [ ] **Step 1: Write stored and system appearance tests**

```php
test('stored appearance takes precedence over the system preference', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/settings/appearance')
        ->inDarkMode()
        ->script("localStorage.setItem('appearance', 'light'); location.reload()")
        ->assertScript("document.documentElement.classList.contains('dark')", false);
});

test('system appearance is used when no preference is stored', function () {
    $user = User::factory()->withProfile()->create();
    $this->actingAs($user);

    visit('/settings/appearance')
        ->inDarkMode()
        ->script("localStorage.removeItem('appearance'); location.reload()")
        ->assertScript("document.documentElement.classList.contains('dark')", true);
});
```

- [ ] **Step 2: Write stable initialization test across navigation**

Navigate three times between `/settings/appearance` and `/settings/account`, select the dark theme once, and assert the `dark` DOM class and stored value remain stable after every navigation. Then select the system theme, revisit both routes, and assert the class still matches Chromium's emulated dark preference. This verifies the user-visible effect of stable initialization without inspecting listener internals.

Concrete browser assertion:

```php
$page->assertScript("localStorage.getItem('appearance')", 'dark')
    ->assertScript("document.documentElement.classList.contains('dark')", true)
    ->assertNoJavaScriptErrors();
```

- [ ] **Step 3: Run red/green, delete the old spec, and commit**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/AppearanceTest.php`

Expected: PASS using the `appearance` storage key defined by `useAppearance.ts`. Delete `resources/js/composables/useAppearance.spec.ts`, rerun, then:

```bash
git add tests/Browser/AppearanceTest.php resources/js/composables/useAppearance.spec.ts
git commit -m "test: migrate appearance frontend coverage to Pest"
```

---

### Task 7: Remove Vitest and make Pest the single local and CI entry point

**Files:**
- Delete: `vitest.config.ts`
- Modify: `package.json`
- Modify: `bun.lock`
- Modify: `composer.json`
- Modify: `.github/workflows/ci.yml`

**Interfaces:**
- Consumes: all green `tests/Browser/*.php`, Bun 1.3.14, MySQL service, Wayfinder, Vite.
- Produces: no Vitest runtime or scripts; `composer test` and `composer ci:check` prepare assets and run the complete Pest suite; CI installs Chromium and runs Pest once for backend and browser tests.

- [ ] **Step 1: Prove that active Vitest references still fail the acceptance check**

Run:

```bash
rg -n -i 'vitest|@vue/test-utils|jsdom|test:unit' --glob '!docs/superpowers/**' --glob '!.git/**'
```

Expected: matches in `package.json`, `bun.lock`, `vitest.config.ts`, active docs, and CI; no `*.spec.ts` remains after Tasks 2–6.

- [ ] **Step 2: Remove JavaScript test dependencies and scripts**

Run:

```bash
bun remove vitest jsdom @vue/test-utils
```

Edit `package.json` so `scripts` contains no `test` or `test:unit` entry. Delete `vitest.config.ts` with `apply_patch`. Confirm `playwright` remains in `devDependencies`.

- [ ] **Step 3: Make Composer prepare frontend assets before the single Pest run**

Update `composer.json` scripts so `ci:check` no longer contains `bun run test` and the test pipeline contains these ordered steps before `php artisan test --display-warnings`:

```json
"@php artisan wayfinder:generate --with-form",
"bun run build",
"@test:database",
"@php artisan test --display-warnings"
```

Keep `lint:check`, `types:check`, and the database guard already present. `composer test` must remain the only test command that runs all three PHPUnit suites.

- [ ] **Step 4: Consolidate CI test execution and install Chromium**

In `.github/workflows/ci.yml`, add Bun setup/cache and dependency installation to the MySQL-backed test job, followed by:

```yaml
- name: Generate Wayfinder modules
  run: php artisan wayfinder:generate --with-form
- name: Build Vite assets
  run: bun run build
- name: Install Playwright Chromium
  run: bunx playwright install --with-deps chromium
- name: Run Pest
  run: php artisan test --display-warnings
```

Remove `Run Vitest` from `frontend-quality`; retain lint, formatting, TypeScript, and the independent `vite-build` job. Rename `backend-tests` and its display name to reflect that it executes the complete Pest suite.

- [ ] **Step 5: Run dependency and configuration acceptance checks**

Run:

```bash
rg --files resources/js | rg '\.spec\.ts$'
rg -n -i 'vitest|@vue/test-utils|jsdom' package.json bun.lock resources/js .github composer.json
composer validate --strict
```

Expected: the file search returns no paths, the reference search returns no matches from existing paths, and Composer validation succeeds. Run the reference search without `vitest.config.ts` after deleting that file so no missing-file diagnostic is produced.

- [ ] **Step 6: Run the single Pest entry point and commit tooling removal**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test`

Expected: Unit, Feature, and Browser tests all PASS from this one command.

Commit:

```bash
git add composer.json composer.lock package.json bun.lock vitest.config.ts .github/workflows/ci.yml
git commit -m "chore: remove Vitest test runner"
```

---

### Task 8: Update active documentation and perform full verification

**Files:**
- Modify: `AGENTS.md`
- Modify: `CONTRIBUTING.md`
- Modify: `README.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/quality-ci-cd.md`

**Interfaces:**
- Consumes: final commands and CI topology from Task 7.
- Produces: active documentation that names Pest Browser/Playwright, uses `composer test` for all tests, and contains no active Vitest guidance.

- [ ] **Step 1: Update exact active documentation references**

Apply these semantic replacements:

```text
Stack frontend tests: Vitest + Vue Test Utils -> Pest Browser + Playwright
All tests: bun run test -> composer test
Targeted frontend example: bun run test:unit -- resources/js/pages/Dashboard.spec.ts
                         -> php artisan test tests/Browser/AdminTest.php
CI description: “Frontend quality runs Vitest” -> “Pest tests installs Chromium and runs all Pest suites”
```

Document the one-time local browser install command `bunx playwright install chromium` near setup instructions. Do not change historical plans/specs.

- [ ] **Step 2: Verify no active Vitest references remain**

Run:

```bash
rg -n -i 'vitest|@vue/test-utils|jsdom|bun run test|test:unit' AGENTS.md CONTRIBUTING.md README.md docs package.json composer.json .github resources/js --glob '!docs/superpowers/**'
```

Expected: no matches.

- [ ] **Step 3: Run all targeted quality checks**

Run:

```bash
composer lint:check
composer analyse
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

Expected: every command exits 0. Fix only migration-related findings, rerun the failing command, then repeat the complete list.

- [ ] **Step 4: Run the full MySQL-backed Pest suite**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dlp_friends_test DB_USERNAME=dlp_friends DB_PASSWORD=test-only-password composer test
```

Expected: Unit, Feature, and Browser suites PASS with no warnings.

- [ ] **Step 5: Run aggregate CI and repository checks**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=dlp_friends_test DB_USERNAME=dlp_friends DB_PASSWORD=test-only-password composer ci:check
git diff --check
git status --short
```

Expected: CI aggregate exits 0, diff check is clean, and status contains only intentional migration changes.

- [ ] **Step 6: Commit documentation and final verification fixes**

```bash
git add AGENTS.md CONTRIBUTING.md README.md docs/technical-architecture.md docs/quality-ci-cd.md
git commit -m "docs: document Pest browser testing"
```

- [ ] **Step 7: Request final code review before integration**

Use `superpowers:requesting-code-review` against the complete branch diff from `main`, resolve only verified findings, rerun the affected checks, and retain a clean `git diff --check` result.
