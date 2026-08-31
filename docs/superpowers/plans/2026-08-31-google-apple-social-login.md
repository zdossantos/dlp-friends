# Google and Apple Social Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add secure account creation and reconnection through Google and Apple while preserving verified-email, adulthood, and active-account requirements.

**Architecture:** Laravel Socialite owns OAuth state and provider communication; the native Google driver and SocialiteProviders Apple driver feed a small provider enum and two focused controllers. Existing identities log in immediately, while new identities store only provider ID and verified e-mail in the session until an adult birth date is submitted to a transactional creation action.

**Tech Stack:** PHP 8.4, Laravel 13, Fortify, Laravel Socialite, SocialiteProviders Apple, Inertia 3, Vue 3, TypeScript, Pest 5, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-08-31-google-apple-social-login-design.md`

## Global Constraints

- Support exactly `google` and `apple`; reject every other provider.
- Display only each provider logo and the text `Google` or `Apple` on both login and registration pages.
- Never persist, expose, or intentionally log an OAuth access token, refresh token, raw callback payload, or Socialite user object.
- A provider-verified e-mail sets `email_verified_at` immediately; no DLP Friends verification e-mail is sent.
- Never auto-link a new provider identity to an existing user with the same normalized e-mail.
- Require a date of birth and an age of at least 18 before creating a social user.
- Refuse reconnection when the linked user status is not `active`.
- Keep all user-visible and accessibility copy in the French and English Laravel translation catalogs.
- Keep the existing `/app` landing flow, `verified` middleware, `social` eligibility middleware, profile onboarding, and product onboarding authoritative after login.

---

## File Structure

- `app/Enums/SocialProvider.php`: closed provider list and provider-specific verified-email rule.
- `app/Models/SocialAccount.php`: social identity record and `user()` relation.
- `app/Data/PendingSocialIdentity.php`: validated, token-free session payload.
- `app/Actions/CreateSocialUser.php`: transactional adulthood recheck, conflict detection, user/role/link creation.
- `app/Exceptions/SocialAuthenticationException.php`: safe error key for expected callback and conflict failures.
- `app/Http/Controllers/Auth/SocialAuthController.php`: OAuth redirect, callback, existing-link login, and pending-session setup.
- `app/Http/Controllers/Auth/SocialRegistrationController.php`: birth-date page and completion submission.
- `app/Http/Requests/CompleteSocialRegistrationRequest.php`: birth-date validation and localized majority error.
- `resources/js/components/auth/SocialLoginButtons.vue`: shared accessible Google and Apple buttons.
- `resources/js/pages/auth/CompleteSocialRegistration.vue`: birth-date-only completion form.
- `tests/Feature/Auth/SocialAuthenticationTest.php`: observable OAuth, conflict, eligibility, and completion behavior.
- `tests/Feature/SocialAccountSchemaTest.php`: storage constraints and cascade behavior.

---

### Task 1: Install and configure the Socialite providers

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `config/services.php`
- Modify: `.env.example`
- Modify: `phpunit.xml`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Auth/SocialProviderConfigurationTest.php`

**Interfaces:**
- Produces: Socialite drivers named `google` and `apple` and configuration keys under `services.google` and `services.apple`.
- Produces: environment variables `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`, `APPLE_CLIENT_ID`, `APPLE_CLIENT_SECRET`, `APPLE_KEY_ID`, `APPLE_TEAM_ID`, `APPLE_PRIVATE_KEY`, and `APPLE_REDIRECT_URI`.

- [ ] **Step 1: Write the failing provider-configuration test**

Create `tests/Feature/Auth/SocialProviderConfigurationTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use Laravel\Socialite\Contracts\Factory;
use Tests\TestCase;

class SocialProviderConfigurationTest extends TestCase
{
    public function test_google_and_apple_socialite_drivers_are_registered(): void
    {
        $factory = app(Factory::class);

        $this->assertNotNull($factory->driver('google'));
        $this->assertNotNull($factory->driver('apple'));
    }

    public function test_social_provider_configuration_comes_from_the_environment_contract(): void
    {
        $this->assertSame('google-client', config('services.google.client_id'));
        $this->assertSame('https://example.test/auth/google/callback', config('services.google.redirect'));
        $this->assertSame('apple-client', config('services.apple.client_id'));
        $this->assertSame('https://example.test/auth/apple/callback', config('services.apple.redirect'));
    }
}
```

Add non-secret test values for these four assertions to `phpunit.xml` beside the existing environment entries.

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Auth/SocialProviderConfigurationTest.php`

Expected: FAIL because `Laravel\Socialite\Contracts\Factory` is unavailable.

- [ ] **Step 3: Install the two maintained packages**

Run:

```bash
composer require laravel/socialite socialiteproviders/apple
```

Do not add an Apple helper package; the selected provider already supports either `APPLE_CLIENT_SECRET` or private-key generation.

- [ ] **Step 4: Add provider configuration and Apple registration**

Append to `config/services.php`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],

'apple' => [
    'client_id' => env('APPLE_CLIENT_ID'),
    'client_secret' => env('APPLE_CLIENT_SECRET'),
    'key_id' => env('APPLE_KEY_ID'),
    'team_id' => env('APPLE_TEAM_ID'),
    'private_key' => env('APPLE_PRIVATE_KEY'),
    'redirect' => env('APPLE_REDIRECT_URI'),
],
```

In `AppServiceProvider::boot()`, register only the Apple extension:

```php
Event::listen(function (SocialiteWasCalled $event): void {
    $event->extendSocialite('apple', AppleProvider::class);
});
```

Import `Illuminate\Support\Facades\Event`, `SocialiteProviders\Apple\Provider as AppleProvider`, and `SocialiteProviders\Manager\SocialiteWasCalled`.

Document every interface variable in `.env.example` with empty values. Use relative defaults `/auth/google/callback` and `/auth/apple/callback` only if Socialite resolves them correctly in the installed version; otherwise leave the URI values empty and document the exact paths in an adjacent comment.

- [ ] **Step 5: Run the provider test and static checks**

Run:

```bash
php artisan test tests/Feature/Auth/SocialProviderConfigurationTest.php
composer analyse
```

Expected: PASS with both drivers resolvable and no PHPStan errors.

- [ ] **Step 6: Commit the provider integration**

```bash
git add composer.json composer.lock config/services.php .env.example phpunit.xml app/Providers/AppServiceProvider.php tests/Feature/Auth/SocialProviderConfigurationTest.php
git commit -m "build: configure Google and Apple Socialite providers"
```

---

### Task 2: Add the unique social identity model

**Files:**
- Create: `database/migrations/2026_08_31_000000_create_social_accounts_table.php`
- Create: `app/Models/SocialAccount.php`
- Create: `database/factories/SocialAccountFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/SocialAccountSchemaTest.php`
- Test: `tests/Unit/Models/UserTest.php`

**Interfaces:**
- Produces: `SocialAccount::user(): BelongsTo`.
- Produces: `User::socialAccounts(): HasMany`.
- Produces: unique database identity `(provider, provider_user_id)`.

- [ ] **Step 1: Write failing schema and relationship tests**

Create tests asserting:

```php
Schema::hasColumns('social_accounts', [
    'id', 'user_id', 'provider', 'provider_user_id', 'created_at', 'updated_at',
]);

$user = User::factory()->create();
$account = SocialAccount::factory()->for($user)->create([
    'provider' => 'google',
    'provider_user_id' => 'google-123',
]);

expect($account->user->is($user))->toBeTrue();
expect($user->socialAccounts()->sole()->is($account))->toBeTrue();
```

Also assert a duplicate `(google, google-123)` throws `UniqueConstraintViolationException`, the same ID under `apple` succeeds, and deleting the user removes the link.

- [ ] **Step 2: Run the tests to verify they fail**

Run:

```bash
php artisan test tests/Feature/SocialAccountSchemaTest.php
php artisan test tests/Unit/Models/UserTest.php
```

Expected: FAIL because the table, model, factory, and relation do not exist.

- [ ] **Step 3: Implement the migration and model**

Use this migration body:

```php
Schema::create('social_accounts', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('provider', 16);
    $table->string('provider_user_id', 255);
    $table->timestamps();
    $table->unique(['provider', 'provider_user_id']);
});
```

Create `SocialAccount` with `HasFactory`, `#[Fillable(['provider', 'provider_user_id'])]`, a typed `BelongsTo` relation, and no token fields. Add the inverse typed `HasMany` relation and PHPDoc collection to `User`. The factory defaults to `google` and a UUID-like provider ID and does not invent token attributes.

- [ ] **Step 4: Run the model tests**

Run the two commands from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit the social account model**

```bash
git add database/migrations/2026_08_31_000000_create_social_accounts_table.php app/Models/SocialAccount.php database/factories/SocialAccountFactory.php app/Models/User.php tests/Feature/SocialAccountSchemaTest.php tests/Unit/Models/UserTest.php
git commit -m "feat(auth): store unique social identities"
```

---

### Task 3: Implement OAuth callbacks and safe reconnection

**Files:**
- Create: `app/Enums/SocialProvider.php`
- Create: `app/Data/PendingSocialIdentity.php`
- Create: `app/Exceptions/SocialAuthenticationException.php`
- Create: `app/Http/Controllers/Auth/SocialAuthController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/SocialAuthenticationTest.php`

**Interfaces:**
- Produces: `SocialProvider::Google` and `SocialProvider::Apple` string-backed cases.
- Produces: `SocialProvider::hasVerifiedEmail(SocialiteUser $user): bool`.
- Produces: `PendingSocialIdentity::SESSION_KEY = 'social_auth.pending'`, `toSession(): array{provider: string, provider_user_id: string, email: string}`, and `fromSession(array $payload): self`.
- Produces routes `social.redirect`, `social.callback`, and redirect target `social.registration.create` used by Task 4.

- [ ] **Step 1: Write failing redirect and callback tests**

In `SocialAuthenticationTest`, use `RefreshDatabase`, `Socialite::fake()`, and `Laravel\Socialite\Two\User::fake()` to cover both providers through a dataset:

```php
Socialite::fake($provider, SocialiteUser::fake([
    'id' => "{$provider}-123",
    'email' => 'new@example.com',
])->setRaw($provider === 'google'
    ? ['verified_email' => true]
    : ['email_verified' => 'true']));
```

Assert redirect routes invoke the matching fake driver. Assert callbacks for unknown identities redirect to `social.registration.create`, store exactly provider/provider ID/lowercase e-mail under `PendingSocialIdentity::SESSION_KEY`, and do not put `token`, `refreshToken`, or the raw user in the session.

Create a linked active user and assert its callback authenticates that same user, regenerates the session ID, and redirects to `route('app')`. Create a linked `pending_deletion` user and assert the callback leaves the visitor unauthenticated with localized `social_auth.inactive` feedback.

Add failures for an invalid OAuth state/exception, absent ID, absent e-mail on a new identity, and provider-specific unverified e-mail. Assert each response returns to `route('login')`, remains a guest, and does not create a user or social account.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Auth/SocialAuthenticationTest.php`

Expected: FAIL because the enum, controller, data object, routes, and exception do not exist.

- [ ] **Step 3: Implement the closed provider and token-free DTO**

Implement:

```php
enum SocialProvider: string
{
    case Google = 'google';
    case Apple = 'apple';

    public function hasVerifiedEmail(SocialiteUser $user): bool
    {
        $key = $this === self::Google ? 'verified_email' : 'email_verified';

        return filter_var(data_get($user->getRaw(), $key), FILTER_VALIDATE_BOOL);
    }
}
```

If the installed Socialite user exposes raw attributes through `user` rather than `getRaw()`, use the documented public accessor confirmed by its source and keep this provider-specific logic isolated here.

`PendingSocialIdentity` must normalize e-mail with `Str::lower(trim($email))`, reject missing/extra or incorrectly typed session fields with `InvalidArgumentException`, and serialize only the three declared fields.

`SocialAuthenticationException` accepts a translation key such as `social_auth.cancelled`, `social_auth.invalid_identity`, `social_auth.email_required`, `social_auth.email_unverified`, `social_auth.email_conflict`, or `social_auth.inactive` and exposes it through `translationKey()`.

- [ ] **Step 4: Implement redirect, callback, and reconnection**

Add routes outside authenticated groups:

```php
Route::middleware('guest')->prefix('auth/{provider}')->group(function (): void {
    Route::get('redirect', [SocialAuthController::class, 'redirect'])
        ->middleware('throttle:10,1')->name('social.redirect');
    Route::get('callback', [SocialAuthController::class, 'callback'])
        ->middleware('throttle:10,1')->name('social.callback');
});
```

Constrain `{provider}` with `whereIn('provider', array_column(SocialProvider::cases(), 'value'))` and type the controller argument as `SocialProvider` if Laravel enum binding works with this route shape; otherwise resolve it with `SocialProvider::from()` after the route constraint.

`redirect()` returns `Socialite::driver($provider->value)->redirect()`.

`callback()` must:

1. retrieve the Socialite user with stateful `user()`, never `stateless()`;
2. look up `SocialAccount` by provider and provider ID before requiring an e-mail;
3. for an existing link, reject non-active users, call `Auth::login($user)`, regenerate the session, and redirect to `route('app')`;
4. for a new link, require normalized e-mail, provider verification, and no existing `users.email` match;
5. store `PendingSocialIdentity::toSession()` and redirect to `social.registration.create`;
6. catch only expected provider/cancellation exceptions and translate them to safe feedback; let unexpected exceptions follow Laravel's normal sanitized error handling without adding sensitive log context.

Do not include any token property in a query, model, DTO, session, response, log call, or exception message.

- [ ] **Step 5: Run the callback tests and static analysis**

Run:

```bash
php artisan test tests/Feature/Auth/SocialAuthenticationTest.php
composer analyse
```

Expected: PASS.

- [ ] **Step 6: Commit OAuth callback behavior**

```bash
git add app/Enums/SocialProvider.php app/Data/PendingSocialIdentity.php app/Exceptions/SocialAuthenticationException.php app/Http/Controllers/Auth/SocialAuthController.php routes/web.php tests/Feature/Auth/SocialAuthenticationTest.php
git commit -m "feat(auth): handle social login callbacks"
```

---

### Task 4: Complete adult social registration transactionally

**Files:**
- Create: `app/Actions/CreateSocialUser.php`
- Create: `app/Http/Requests/CompleteSocialRegistrationRequest.php`
- Create: `app/Http/Controllers/Auth/SocialRegistrationController.php`
- Create: `resources/js/pages/auth/CompleteSocialRegistration.vue`
- Modify: `routes/web.php`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Test: `tests/Feature/Auth/SocialAuthenticationTest.php`

**Interfaces:**
- Consumes: `PendingSocialIdentity` and `SocialProvider` from Task 3.
- Produces: `CreateSocialUser::execute(PendingSocialIdentity $identity, string $birthDate): User`.
- Produces: routes `social.registration.create` and `social.registration.store`.

- [ ] **Step 1: Add failing completion tests**

Extend `SocialAuthenticationTest` to assert:

- the create route redirects to login without a valid pending identity;
- the create route renders `auth/CompleteSocialRegistration` with no provider ID, e-mail, or token prop;
- a missing birth date and a date one day younger than 18 return localized validation errors and create nothing;
- an exactly-18 birth date creates one active user with normalized e-mail, non-empty hashed password, `email_verified_at`, the `user` role, and one matching social account;
- successful completion authenticates the new user, removes `social_auth.pending`, regenerates the session, and redirects to `route('app')` even if an admin URL was previously intended;
- an existing user e-mail conflict and existing social identity conflict roll back the full transaction, keep the visitor unauthenticated, clear the pending identity, and return safe localized feedback;
- submitting the same pending identity twice creates no duplicate or orphan user.

- [ ] **Step 2: Run completion tests to verify they fail**

Run: `php artisan test tests/Feature/Auth/SocialAuthenticationTest.php`

Expected: FAIL because completion routes and action are missing.

- [ ] **Step 3: Implement validation and transactional creation**

`CompleteSocialRegistrationRequest::rules()` returns:

```php
return [
    'birth_date' => ['required', Rule::date()->beforeOrEqual(today()->subYears(18))],
];
```

Map `birth_date.before_or_equal` to `account.registration.adult_only`.

`CreateSocialUser::execute()` must revalidate the DTO values, then use `DB::transaction()` to:

1. reject any existing social identity;
2. reject any existing normalized e-mail;
3. create `User` with `UserStatus::Active`, `email_verified_at => now()`, the submitted birth date, and `password => Str::password(64)` so the model hash cast stores only a hash;
4. attach the existing `RoleName::User` role;
5. create the social account through `$user->socialAccounts()`.

Catch `UniqueConstraintViolationException` outside the transaction and convert it to `SocialAuthenticationException('social_auth.conflict')`, ensuring rollback removes a user created before the link collision.

- [ ] **Step 4: Implement completion routes, controller, and page**

Add guest routes:

```php
Route::get('auth/social/complete', [SocialRegistrationController::class, 'create'])
    ->name('social.registration.create');
Route::post('auth/social/complete', [SocialRegistrationController::class, 'store'])
    ->middleware('throttle:6,1')->name('social.registration.store');
```

`create()` validates the session payload with `PendingSocialIdentity::fromSession()` and redirects to login with `social_auth.expired` when absent or invalid. Otherwise render `auth/CompleteSocialRegistration` without props beyond shared translations.

`store()` reads but does not pull the pending payload until validation passes. On success, forget it, authenticate, regenerate the session, and redirect explicitly to `route('app')`. On a domain conflict, forget it and return to login with only the localized error.

The Vue page reuses `AuthLayout`, `Head`, `Form`, `Input`, `InputError`, `Label`, `Button`, and `Spinner`. It contains one required `birth_date` input with `autocomplete="bday"` and one submit button. Add exact French and English keys for page title, description, field label, submit text, expired flow, inactive account, e-mail conflict, identity conflict, cancellation, invalid callback, missing e-mail, and unverified e-mail.

- [ ] **Step 5: Run completion, translation, and type checks**

Run:

```bash
php artisan test tests/Feature/Auth/SocialAuthenticationTest.php
php artisan test tests/Feature/Localization/BackendTranslationsTest.php tests/Feature/Localization/EditorialCopyTest.php
php artisan wayfinder:generate --with-form
bun run types:check
```

Expected: PASS.

- [ ] **Step 6: Commit adult social registration**

```bash
git add app/Actions/CreateSocialUser.php app/Http/Requests/CompleteSocialRegistrationRequest.php app/Http/Controllers/Auth/SocialRegistrationController.php resources/js/pages/auth/CompleteSocialRegistration.vue routes/web.php lang/fr/account.php lang/en/account.php tests/Feature/Auth/SocialAuthenticationTest.php resources/js/routes resources/js/actions
git commit -m "feat(auth): complete adult social registration"
```

Only add generated Wayfinder paths that actually changed; inspect `git status` before staging.

---

### Task 5: Add the shared Google and Apple buttons

**Files:**
- Create: `resources/js/components/auth/SocialLoginButtons.vue`
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Test: `tests/Browser/WelcomeAndRegistrationTest.php`
- Test: `tests/Frontend/translations.test.js`

**Interfaces:**
- Consumes: Wayfinder route `social.redirect({ provider: 'google'|'apple' })`.
- Produces: `<SocialLoginButtons />` with no props and two accessible links.

- [ ] **Step 1: Add failing browser and translation tests**

Extend the existing authentication browser test to visit `/login` and `/register` and assert each page has exactly one visible `Google` link and one visible `Apple` link with hrefs ending in `/auth/google/redirect` and `/auth/apple/redirect`. Assert each link contains an SVG with `aria-hidden="true"` while its provider text remains accessible.

Extend the translation unit test to require the new `account.social` keys in both locales.

- [ ] **Step 2: Run tests to verify they fail**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --filter='social'
bun run test:unit
```

Expected: FAIL because the buttons and keys are absent.

- [ ] **Step 3: Implement the shared component**

Build `SocialLoginButtons.vue` with two outline-styled `Button` components rendered `as-child`, each containing an Inertia `Link` to the generated provider redirect route. Use compact inline SVG path data for the official Google G and Apple silhouette, set SVGs to `aria-hidden="true"` and `focusable="false"`, and render only `t('account.social.google')` / `t('account.social.apple')` as visible text. Do not add explanatory marketing copy inside the buttons.

Add the component before the e-mail/password forms in both `Login.vue` and `Register.vue`. Use one localized visually accessible separator only if needed to distinguish social buttons from the existing form; keep it outside the provider buttons.

- [ ] **Step 4: Run browser, frontend, formatting, and build checks**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --filter='social'
bun run test:unit
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

Expected: PASS.

- [ ] **Step 5: Commit the social login interface**

```bash
git add resources/js/components/auth/SocialLoginButtons.vue resources/js/pages/auth/Login.vue resources/js/pages/auth/Register.vue lang/fr/account.php lang/en/account.php tests/Browser/WelcomeAndRegistrationTest.php tests/Frontend/translations.test.js resources/js/routes resources/js/actions
git commit -m "feat(auth): add Google and Apple login buttons"
```

---

### Task 6: Update product and operational documentation

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/documentation-inventory.md`
- Modify: `docs/data-model.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/operations.md`
- Modify: `.env.example`

**Interfaces:**
- Consumes: completed routes, schema, security behavior, and environment variable names from Tasks 1–5.
- Produces: implementation evidence and operator setup instructions matching the delivered code.

- [ ] **Step 1: Write a failing documentation consistency check**

Run:

```bash
rg -n "Connexion Google et Apple \| \*\*Planifié\*\*|aucun flux OAuth|aucune migration `social_accounts`" docs
```

Expected: matches in the PRD/inventory that are now stale.

- [ ] **Step 2: Update the documentation from observed code**

Change the PRD row to `Implémenté` and cite Socialite, the two providers, the birth-date completion, and automated tests. Update the inventory evidence with the exact migration, controllers, action, routes, Vue component, and test paths.

In `data-model.md`, document the implemented `social_accounts` columns and uniqueness. In `technical-architecture.md`, document the callback/pending-session/completion flow and the native Google plus community Apple providers. In `security-privacy.md`, state that provider-verified e-mail replaces the mail link only for a new social account, no token is stored, existing e-mail collisions are never auto-linked, and adulthood remains mandatory. In `operations.md`, list the required provider variables, exact callback paths, and Apple key/secret renewal responsibility without real credentials.

- [ ] **Step 3: Verify documentation and repository formatting**

Run:

```bash
rg -n "Connexion Google et Apple \| \*\*Planifié\*\*|aucun flux OAuth|aucune migration `social_accounts`" docs/PRD.md docs/documentation-inventory.md
git diff --check
```

Expected: the first command returns no matches; `git diff --check` returns no output.

- [ ] **Step 4: Commit documentation**

```bash
git add docs/PRD.md docs/documentation-inventory.md docs/data-model.md docs/technical-architecture.md docs/security-privacy.md docs/operations.md .env.example
git commit -m "docs: document social login operations"
```

---

### Task 7: Run complete verification and review

**Files:**
- Review: all branch changes from `main`
- Modify: only files needed to fix verified failures or review findings

**Interfaces:**
- Consumes: all prior tasks.
- Produces: a fully verified issue-12 branch with no unrelated changes.

- [ ] **Step 1: Run focused authentication tests**

```bash
php artisan test tests/Feature/Auth/SocialProviderConfigurationTest.php tests/Feature/SocialAccountSchemaTest.php tests/Feature/Auth/SocialAuthenticationTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --filter='social'
```

Expected: PASS.

- [ ] **Step 2: Run complete relevant quality gates**

```bash
composer lint:check
composer analyse
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
composer test
git diff --check
```

Expected: every command exits zero and `git diff --check` prints nothing.

- [ ] **Step 3: Inspect security-sensitive branch diff**

```bash
git diff main...HEAD -- app config routes resources/js/pages/auth resources/js/components/auth database tests/Feature/Auth .env.example
rg -n "access_token|refresh_token|->token|refreshToken|Log::|logger\(" app database resources tests/Feature/Auth
```

Confirm the diff contains no token column, token serialization, raw provider logging, auto-link by e-mail, `stateless()` callback, or bypass of `/app` and the existing eligibility middleware. Existing unrelated token usage elsewhere is not a finding unless introduced by this branch.

- [ ] **Step 4: Request code review and fix only verified findings**

Use `superpowers:requesting-code-review` on `git diff main...HEAD`. Apply necessary fixes through a fresh red/green cycle and rerun the affected commands from Steps 1–2.

- [ ] **Step 5: Record final verification state**

```bash
git status --short --branch
git log --oneline main..HEAD
```

Expected: only intentional changes remain, generated files are accounted for, and all implementation commits are visible.

- [ ] **Step 6: Commit any final verified correction**

If review required a correction, inspect `git status --short`, stage each
corrected path explicitly with `git add path/to/corrected-file`, then run
`git commit -m "fix(auth): address social login review"`.

If no correction was required, do not create an empty commit.
