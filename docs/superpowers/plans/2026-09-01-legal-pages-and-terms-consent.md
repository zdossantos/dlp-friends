# Legal Pages and Terms Consent Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Publish complete French and English legal pages and require an explicit, versioned Terms of Use acceptance when a user registers.

**Architecture:** Extend the existing server-rendered public surface with four stable localized legal URLs, one generic legal Blade view, and URL helpers shared by SEO, sitemap, landing, and Inertia layouts. Keep acceptance authoritative in Fortify's `CreateNewUser` action: validate the checkbox, create the user and role, and append an immutable `terms_acceptances` row in the same database transaction.

**Tech Stack:** Laravel 13, Fortify, Eloquent, Blade, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Pest Browser, Playwright, Bun 1.3.14.

**Spec:** `docs/superpowers/specs/2026-09-01-legal-pages-and-terms-consent-design.md`

## Global constraints

- Follow `avatar.md`, `docs/PRD.md`, `docs/legal-and-privacy.md`, `docs/technical-architecture.md`, and `docs/engineering-principles.md`.
- Keep every visible string in `lang/fr` and `lang/en`; both catalogues must expose identical leaf keys.
- Do not add existing-user reacceptance, blocking middleware, a cookie banner, a moderation console, IP storage, delayed deletion, or data export.
- Describe only behavior implemented in the repository. State that production SMTP is to be selected before activation; Mailpit is local only.
- Treat the legal copy as implementation-ready product copy that still requires professional review before production publication.
- Use `/tmp/dlp-friends-bun-1.3.14/bin/bun` for direct frontend commands so the required Bun version is exercised.

### Task 1: Add legal configuration and public URL contracts

**Files:**

- Create: `config/legal.php`
- Modify: `.env.example`
- Modify: `app/Support/PublicUrls.php`
- Modify: `routes/web.php`
- Test: `tests/Unit/Support/PublicUrlsTest.php`

**Step 1: Write failing URL and configuration tests**

Add unit cases proving these exact absolute URLs under `https://dlp-friends.example`:

```php
expect(PublicUrls::terms('fr'))->toBe('https://dlp-friends.example/fr/conditions-generales-utilisation');
expect(PublicUrls::terms('en'))->toBe('https://dlp-friends.example/en/terms-of-use');
expect(PublicUrls::privacy('fr'))->toBe('https://dlp-friends.example/fr/politique-confidentialite');
expect(PublicUrls::privacy('en'))->toBe('https://dlp-friends.example/en/privacy-policy');
```

**Step 2: Run the targeted tests and confirm RED**

Run:

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Unit/Support/PublicUrlsTest.php
```

Expected: failures because the legal URL methods and named routes do not exist.

**Step 3: Implement the configuration contract**

Create `config/legal.php` with stable operator data and document metadata:

```php
return [
    'contact_email' => env('LEGAL_CONTACT_EMAIL'),
    'operator' => [
        'name' => 'Zacharie Dos Santos',
        'legal_form' => 'Entrepreneur individuel',
        'siren' => '104 531 819',
        'siret' => '104 531 819 00019',
        'address' => '28 rue Ernest Petit, 21000 Dijon, France',
    ],
    'host' => ['name' => 'IONOS'],
    'terms' => ['version' => '2026-09-01', 'effective_at' => '2026-09-01'],
    'privacy' => ['updated_at' => '2026-09-01'],
    'retention' => ['backup_days' => 30],
];
```

Add `LEGAL_CONTACT_EMAIL=` to `.env.example`. Register the four explicit named GET routes shown in Task 2; pointing at the not-yet-invoked controller is sufficient for URL generation. Extend `PublicUrls` with `terms(string $locale)` and `privacy(string $locale)`, using those named routes and the existing `absolute()` method. Reject unsupported locales consistently with the public locale policy.

**Step 4: Run the focused URL tests**

Run the command from Step 2. Expected: URL tests pass.

**Step 5: Commit**

```sh
git add .env.example config/legal.php app/Support/PublicUrls.php routes/web.php tests/Unit/Support/PublicUrlsTest.php
git commit -m "feat: configure public legal documents"
```

### Task 2: Publish localized, indexable legal documents

**Files:**

- Create: `app/Http/Controllers/LegalDocumentController.php`
- Create: `resources/views/legal/show.blade.php`
- Create: `lang/fr/legal.php`
- Create: `lang/en/legal.php`
- Modify: `resources/css/app.css`
- Test: `tests/Feature/LegalPagesTest.php`

**Step 1: Expand failing public-page tests**

Cover all four exact URLs with datasets. Assert each response:

- is HTTP 200 and `legal.show`;
- is server-rendered without a module script;
- has the correct `<html lang>`, localized `<title>`, description, canonical, reciprocal `hreflang`, and `x-default`;
- has no `X-Robots-Tag` noindex header;
- exposes a semantic `main`, an `h1`, an accessible table of contents, stable section anchors, updated/effective date, and the configurable contact email;
- contains the localized rights, CNIL complaint route, IONOS hosting, 30-day backup retention, account-lifetime retention, immediate active-database deletion, and service-independent disclaimer;
- contains no claims of active analytics, advertising, payment, OAuth, production SMTP, or an implemented report button.

Also assert missing `LEGAL_CONTACT_EMAIL` is tolerated outside production but fails explicitly in production.
The configured-contact case must override `legal.contact_email` and prove that value is rendered, without reading or modifying the developer's `.env`.

**Step 2: Run tests and confirm RED**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/LegalPagesTest.php
```

Expected: route-not-found or view-not-found failures.

**Step 3: Add the controller behind the routes from Task 1**

The four explicit GET routes must appear before the localized landing route:

```php
Route::get('fr/conditions-generales-utilisation', [LegalDocumentController::class, 'terms'])
    ->defaults('locale', 'fr')->name('legal.terms.fr');
Route::get('en/terms-of-use', [LegalDocumentController::class, 'terms'])
    ->defaults('locale', 'en')->name('legal.terms.en');
Route::get('fr/politique-confidentialite', [LegalDocumentController::class, 'privacy'])
    ->defaults('locale', 'fr')->name('legal.privacy.fr');
Route::get('en/privacy-policy', [LegalDocumentController::class, 'privacy'])
    ->defaults('locale', 'en')->name('legal.privacy.en');
```

The controller must set the request locale, validate production contact configuration, select `terms` or `privacy`, build canonical/alternate SEO data through `PublicUrls`, and return one generic view. Keep legal content out of the controller.

**Step 4: Add matched French and English catalogues**

Structure each document as metadata plus ordered sections:

```php
'terms' => [
    'meta' => ['title' => '...', 'description' => '...'],
    'title' => '...',
    'effective_date' => '...',
    'sections' => [
        ['id' => 'purpose', 'title' => '...', 'paragraphs' => ['...'], 'items' => []],
    ],
],
```

Terms sections must cover publisher identity, service purpose and independence from Disney, adult eligibility, account duties, strictly friendly conduct, prohibited behavior, member-content responsibility, reciprocal discovery and messaging rules, proportionate moderation/suspension/deletion, account deletion, service availability/liability, intellectual property, changes, governing law, and contact.

Privacy sections must cover controller identity/contact, exact data categories and purposes, GDPR legal bases, recipients/processors, IONOS VPS and self-hosted MySQL/Redis/Reverb/S3-compatible storage, local-only Mailpit and undecided production SMTP, security, retention table, immediate deletion from active systems, encrypted daily backups retained 30 days, rights, CNIL complaint, international transfers, cookies strictly necessary to authentication/localization, and policy updates.

Do not claim backup deletion selectively before rotation. Explain that deleted data can remain in protected backups until automatic expiry and is not restored for ordinary use.

**Step 5: Build the shared Blade view and print styles**

Render metadata, a visible last-updated/effective date, localized language switcher, table of contents, and sections using semantic HTML. Add strong keyboard focus, readable measure, responsive spacing, and print CSS that hides navigation controls and prints URLs where useful. Use no client-side JavaScript.

**Step 6: Run focused tests and format checks**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/LegalPagesTest.php
composer lint:check
```

Expected: all legal-page tests pass and Pint reports clean code.

**Step 7: Commit**

```sh
git add app/Http/Controllers/LegalDocumentController.php resources/views/legal/show.blade.php lang/fr/legal.php lang/en/legal.php resources/css/app.css tests/Feature/LegalPagesTest.php
git commit -m "feat: publish localized legal pages"
```

### Task 3: Expose permanent legal links and update discovery metadata

**Files:**

- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/sitemap.blade.php`
- Modify: `routes/web.php`
- Modify: `resources/js/layouts/auth/AuthCardLayout.vue`
- Modify: `resources/js/layouts/MemberLayout.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Modify: `tests/Feature/PublicLandingTest.php`
- Modify: `tests/Browser/WelcomeAndRegistrationTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`

**Step 1: Write failing integration and browser tests**

Change the sitemap test to expect three localized document groups: landing, Terms, and Privacy, each with reciprocal alternates. Extend robots assertions so the legal URLs are crawlable through the existing `/fr` and `/en` allow rules.

Add browser assertions that:

- the landing footer exposes both localized legal links;
- auth pages expose both legal links without opening an Inertia document;
- an authenticated member can reach permanent legal links from the member shell;
- legal pages remain usable at narrow viewport width, the table of contents is visible, and focus styles/anchors work.

**Step 2: Run and confirm RED**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/PublicLandingTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php --filter='legal'
```

Expected: sitemap and link assertions fail.

**Step 3: Share legal URLs with Inertia and add permanent links**

In `HandleInertiaRequests`, share localized `legal.terms_url` and `legal.privacy_url` generated by `PublicUrls`. Add matching `common.legal.*` labels to both frontend catalogues.

Add a compact footer to:

- the server-rendered landing;
- `AuthCardLayout`, below the card;
- `MemberLayout`, within the scrollable member content after the page slot so it does not crowd the bottom navigation.

Use normal `<a>` elements for legal documents so navigation loads a full SSR document. Do not use `_blank` by default. Preserve touch targets and safe-area spacing.

**Step 4: Refactor sitemap input by document group**

Have `/sitemap.xml` pass ordered groups such as:

```php
[
    ['fr' => PublicUrls::landing('fr'), 'en' => PublicUrls::landing('en')],
    ['fr' => PublicUrls::terms('fr'), 'en' => PublicUrls::terms('en')],
    ['fr' => PublicUrls::privacy('fr'), 'en' => PublicUrls::privacy('en')],
]
```

Update the Blade sitemap to render one `<url>` per locale per group with correct alternate links and no private routes.

**Step 5: Run focused feature and browser tests**

Run the commands from Step 2. Expected: all pass.

**Step 6: Commit**

```sh
git add resources/views/welcome.blade.php resources/views/sitemap.blade.php routes/web.php resources/js/layouts/auth/AuthCardLayout.vue resources/js/layouts/MemberLayout.vue app/Http/Middleware/HandleInertiaRequests.php lang/fr/common.php lang/en/common.php tests/Feature/PublicLandingTest.php tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat: expose permanent legal links"
```

### Task 4: Persist immutable, versioned Terms acceptance

**Files:**

- Create: `database/migrations/2026_09_01_000000_create_terms_acceptances_table.php`
- Create: `app/Models/TermsAcceptance.php`
- Modify: `app/Models/User.php`
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`

**Step 1: Write failing registration behavior tests**

Update successful registration payloads with `'terms_accepted' => true`. In `RegistrationTest`, add cases proving:

- missing, false, `0`, and `'0'` acceptance are rejected with the localized validation error and no user/acceptance row;
- accepted registration stores exactly one record linked to the created user;
- `terms_version` equals `config('legal.terms.version')` and `accepted_at` equals the frozen server time, regardless of unexpected client-supplied version/timestamp fields;
- user, role attachment, and acceptance are atomic by forcing acceptance persistence to fail and asserting no user remains;
- deleting the user cascades the proof row according to the approved account-deletion behavior;
- the model exposes no update timestamp and the schema has a unique `(user_id, terms_version)` constraint.

**Step 2: Run tests and confirm RED**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Auth/RegistrationTest.php
```

Expected: rejection and persistence assertions fail because acceptance is not validated or stored.

**Step 3: Add the acceptance schema and model relation**

The migration must create:

```php
$table->id();
$table->foreignId('user_id')->constrained()->cascadeOnDelete();
$table->string('terms_version');
$table->timestamp('accepted_at');
$table->unique(['user_id', 'terms_version']);
```

Do not add IP, user-agent, mutable metadata, or `updated_at`. The model should set `public $timestamps = false`, cast `accepted_at` to `immutable_datetime`, and expose only the required fillable attributes. Add `User::termsAcceptances(): HasMany` and its PHPDoc relationship type.

**Step 4: Validate and store in the existing transaction**

Add the authoritative validation rule:

```php
'terms_accepted' => ['required', 'accepted'],
```

Provide a localized field-specific error. After role attachment and before returning, create the relation row using only server-owned values:

```php
$user->termsAcceptances()->create([
    'terms_version' => config('legal.terms.version'),
    'accepted_at' => now(),
]);
```

Keep all three writes in the existing `DB::transaction`.

**Step 5: Run focused tests and migration checks**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Auth/RegistrationTest.php
composer analyse
```

Expected: all registration tests and PHPStan pass.

**Step 6: Commit**

```sh
git add database/migrations/2026_09_01_000000_create_terms_acceptances_table.php app/Models/TermsAcceptance.php app/Models/User.php app/Actions/Fortify/CreateNewUser.php tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: record terms acceptance at registration"
```

### Task 5: Add the unchecked registration checkbox and localized links

**Files:**

- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Modify: `app/Support/FrontendTranslations.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`
- Modify: `tests/Browser/WelcomeAndRegistrationTest.php`

**Step 1: Write failing frontend catalogue and browser tests**

Add `legal` to the frontend-shared catalogue only if registration link labels live there; otherwise keep registration consent copy under `account.registration.terms` and share legal URLs separately. Update the catalogue-key parity and exact-domain tests accordingly.

Extend the registration browser path to prove:

- the checkbox exists, has an associated label, is initially unchecked, and is keyboard focusable;
- the label contains working localized links to Terms and Privacy;
- submitting unchecked produces an inline validation error and does not authenticate;
- checking it and submitting valid details succeeds.

**Step 2: Run and confirm RED**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization/InertiaTranslationsTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --filter='terms'
```

Expected: translation/link/checkbox assertions fail.

**Step 3: Implement the Reka checkbox**

Import the existing `Checkbox` component. Add it after password confirmation with:

```vue
<Checkbox
    id="terms_accepted"
    name="terms_accepted"
    value="1"
    :tabindex="5"
    required
/>
```

Associate a visible `Label for="terms_accepted"`; compose its text with normal anchors to the shared localized legal URLs. Render `<InputError :message="errors.terms_accepted" />`. Do not initialize it checked and do not disable the submit button based on client state. Shift later tab indices so keyboard order remains linear.

If Reka serializes the value differently under Inertia's native `Form`, verify the actual request in the red test and use the smallest supported binding that posts an accepted Laravel value; keep server validation unchanged.

**Step 4: Run focused tests and frontend checks**

```sh
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization/InertiaTranslationsTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php --filter='terms'
/tmp/dlp-friends-bun-1.3.14/bin/bun run lint:check
/tmp/dlp-friends-bun-1.3.14/bin/bun run types:check
```

Expected: browser behavior, catalogue parity, lint, and TypeScript pass.

**Step 5: Commit**

```sh
git add resources/js/pages/auth/Register.vue lang/fr/account.php lang/en/account.php app/Support/FrontendTranslations.php tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/WelcomeAndRegistrationTest.php
git commit -m "feat: require terms checkbox on registration"
```

### Task 6: Reconcile documentation with delivered behavior

**Files:**

- Modify: `docs/PRD.md`
- Modify: `docs/legal-and-privacy.md`
- Modify: `docs/operations.md`
- Modify: `docs/technical-architecture.md`
- Modify: `README.md`

**Step 1: Update sources of truth**

Document:

- the four stable public legal routes and sitemap coverage;
- operator/controller identity and `LEGAL_CONTACT_EMAIL` deployment requirement;
- Terms version `2026-09-01` and registration-only acceptance proof;
- no existing-user reacceptance workflow in this scope;
- immediate active-store deletion and encrypted backup rotation after 30 days;
- IONOS VPS hosting and self-hosted services;
- Mailpit local-only and the production SMTP decision gate;
- professional legal review as a production-release prerequisite.

Mark issues 42 and 43 implemented in the PRD only after tests prove the behavior. Do not present future reporting, export, delayed purge, or reacceptance as implemented.

**Step 2: Verify documentation consistency**

```sh
rg -n "CGU|Terms of Use|privacy|confidentialit|30 jours|30 days|LEGAL_CONTACT_EMAIL|Mailpit|SMTP|104 531 819" README.md docs config .env.example
git diff --check
```

Expected: terminology and values agree; no whitespace errors.

**Step 3: Commit**

```sh
git add README.md docs/PRD.md docs/legal-and-privacy.md docs/operations.md docs/technical-architecture.md
git commit -m "docs: document legal publication controls"
```

### Task 7: Full verification and review handoff

**Files:**

- Review: all files changed since `main`

**Step 1: Run the complete relevant checks**

```sh
PATH='/tmp/dlp-friends-bun-1.3.14/bin:/opt/homebrew/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin' APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test
git diff --check
git status --short
```

Expected: PHP lint and analysis pass, frontend tests/lint/format/types/build pass, all Pest tests pass, diff check is clean, and only intended work remains.

**Step 2: Run production-config smoke checks**

With a temporary application environment, verify that a configured `LEGAL_CONTACT_EMAIL` renders all four pages and that the missing-variable guard fires in production. Do not edit the user's `.env`.

**Step 3: Inspect the final diff against the validated spec**

```sh
git diff --stat main...HEAD
git diff main...HEAD -- config/legal.php routes/web.php app/Actions/Fortify/CreateNewUser.php resources/js/pages/auth/Register.vue
```

Confirm there is no existing-user gate, no prechecked value, no client-owned version/timestamp, no unsupported legal claim, and no unrelated refactor.

**Step 4: Request code review**

Use the `superpowers:requesting-code-review` skill. Address verified findings with the `superpowers:receiving-code-review` skill, rerun affected checks, then use `superpowers:verification-before-completion` before claiming completion.
