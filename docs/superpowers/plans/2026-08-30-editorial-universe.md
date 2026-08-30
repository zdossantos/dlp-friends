# Editorial Universe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harmonize every MVP-facing text with DLP Friends’ approved editorial voice, centralize all visible copy by business feature, and prevent hard-coded or forbidden copy from returning.

**Architecture:** Laravel feature catalogues are the single source for Vue, controllers, validation, and transactional mail. `FrontendTranslations` publishes those nested catalogues to Inertia, and the TypeScript translator resolves arbitrary nested keys without the legacy DOM mutation layer. Pest policy tests enforce catalogue parity, editorial rules, and the absence of visible hard-coded strings.

**Tech Stack:** PHP 8.4, Laravel 13 translations, Inertia 3, Vue 3 Composition API, TypeScript, Pest 5, Pest Browser, Bun/Vite.

**Spec:** `docs/superpowers/specs/2026-08-30-editorial-universe-design.md`

## Global Constraints

- Use a consistent French `tu` voice across member, admin, account, validation, and transactional-mail copy.
- Canonical French terms are `Explorer`, `Passer`, `Découvrir`, `Univers croisés`, `Vos univers se croisent`, `Échange`, `Profil`, and `Univers favoris`.
- No visible string may remain hard-coded, including the brand name, document titles, accessible labels, and generic UI primitive copy.
- French and English must expose the same feature files and nested keys.
- Keep internal domain identifiers such as `like`, `pass`, and `match`; this change does not rename storage or business APIs.
- Do not add a frontend i18n dependency or a new language.
- Preserve all existing behavior, authorization, and data flow.

---

### Task 1: Feature catalogue loader and nested translation keys

**Files:**
- Create: `lang/fr/common.php`
- Create: `lang/en/common.php`
- Create: `lang/fr/account.php`
- Create: `lang/en/account.php`
- Create: `lang/fr/profile.php`
- Create: `lang/en/profile.php`
- Create: `lang/fr/discovery.php`
- Create: `lang/en/discovery.php`
- Create: `lang/fr/conversations.php`
- Create: `lang/en/conversations.php`
- Create: `lang/fr/administration.php`
- Create: `lang/en/administration.php`
- Modify: `lang/fr/onboarding.php`
- Modify: `lang/en/onboarding.php`
- Modify: `lang/fr/blocking.php`
- Modify: `lang/en/blocking.php`
- Modify: `app/Support/FrontendTranslations.php`
- Modify: `resources/js/types/i18n.ts`
- Modify: `resources/js/composables/useTranslations.ts`
- Test: `tests/Feature/Localization/InertiaTranslationsTest.php`
- Create: `tests/Frontend/translations.test.js`

**Interfaces:**
- Produces: `FrontendTranslations::messages(): array<string, mixed>` with top-level keys `common`, `account`, `profile`, `onboarding`, `discovery`, `conversations`, `blocking`, and `administration`.
- Produces: `TranslationKey`, a recursive union of dotted leaf paths from `TranslationMessages`.
- Produces: `translationFor(messages, key): string`, resolving any nested dotted key.

- [ ] **Step 1: Replace the localization feature assertions with failing feature-catalogue assertions**

In `tests/Feature/Localization/InertiaTranslationsTest.php`, assert the new public contract before creating the files:

```php
test('Inertia shares feature catalogues selected by the request locale', function () {
    $this->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->where('i18n.locale', 'en')
            ->where('i18n.messages.common.locale.label', 'Language')
            ->where('i18n.messages.discovery.actions.discover', 'Discover')
            ->where('i18n.messages.discovery.match.title', 'Your worlds cross paths')
            ->where('i18n.messages.profile.interests.title', 'Favorite worlds'));
});

test('French and English feature catalogues expose identical leaf keys', function () {
    expect(featureTranslationKeys('fr'))->toBe(featureTranslationKeys('en'));
});
```

Add local helpers that load the eight explicit feature names and recursively flatten leaf paths. Do not scan every PHP file because Laravel framework catalogues such as `validation.php` have separate contracts.

- [ ] **Step 2: Run the localization test and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization/InertiaTranslationsTest.php
```

Expected: FAIL because `common`, `account`, `profile`, `discovery`, `conversations`, and `administration` catalogues do not exist and the shared payload still uses `frontend.php`.

- [ ] **Step 3: Add a failing nested-key frontend test**

Export `translationFor` from `resources/js/composables/useTranslations.ts`, then create `tests/Frontend/translations.test.js`:

```js
import { expect, test } from 'bun:test';
import { translationFor } from '../../resources/js/composables/useTranslations';

test('nested translation keys resolve to a leaf string', () => {
    const messages = {
        discovery: { match: { title: 'Your worlds cross paths' } },
    };

    expect(translationFor(messages, 'discovery.match.title')).toBe(
        'Your worlds cross paths',
    );
});
```

- [ ] **Step 4: Run the frontend test and verify RED**

Run: `bun test tests/Frontend/translations.test.js`

Expected: FAIL because the current resolver only reads two key segments.

- [ ] **Step 5: Implement the catalogue loader and recursive TypeScript key type**

Create all feature files with the structural keys used by existing structured translations, moving values without rewriting the remaining journeys yet. Seed the approved canonical entries exactly:

```php
// lang/fr/discovery.php
return [
    'navigation' => 'Explorer',
    'actions' => ['pass' => 'Passer', 'discover' => 'Découvrir'],
    'match' => [
        'label' => 'Univers croisés',
        'title' => 'Vos univers se croisent',
        'description' => ':name souhaite aussi te découvrir. Tu peux maintenant commencer l’échange.',
        'open_conversation' => 'Commencer l’échange',
        'continue' => 'Continuer à explorer',
    ],
];
```

Make `FrontendTranslations` load the eight exact domains through `trans($domain)`, remove server-only `onboarding.demo_profiles` from the shared payload, and return them under their domain name. Use `data_get($messages, $key)` in spirit on the client by reducing all dotted segments and throwing a descriptive error when a leaf is missing.

Define recursive types in `resources/js/types/i18n.ts`:

```ts
export type TranslationTree = {
    [key: string]: string | TranslationTree;
};

export type TranslationMessages = {
    common: TranslationTree;
    account: TranslationTree;
    profile: TranslationTree;
    onboarding: TranslationTree;
    discovery: TranslationTree;
    conversations: TranslationTree;
    blocking: TranslationTree;
    administration: TranslationTree;
};
```

Use a recursive `LeafPaths<T>` utility in `useTranslations.ts` so call sites receive dotted-key checking rather than accepting arbitrary strings.

- [ ] **Step 6: Run focused tests and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization/InertiaTranslationsTest.php
bun test tests/Frontend/translations.test.js
bun run types:check
```

Expected: all commands exit 0.

- [ ] **Step 7: Commit the translation foundation**

```bash
git add lang app/Support/FrontendTranslations.php resources/js/types/i18n.ts resources/js/composables/useTranslations.ts tests/Feature/Localization/InertiaTranslationsTest.php tests/Frontend/translations.test.js
git commit -m "refactor(i18n): organize translations by feature"
```

---

### Task 2: Common, account, and generic interface copy

**Files:**
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Modify: `lang/fr/mail.php`
- Modify: `lang/en/mail.php`
- Modify: `lang/fr/validation.php`
- Modify: `lang/en/validation.php`
- Modify: `lang/fr/passwords.php`
- Create: `lang/en/passwords.php`
- Modify: `resources/js/app.ts`
- Delete: `resources/js/lib/translateDom.ts`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/views/mail/auth/reset-password.blade.php`
- Modify: `resources/views/mail/auth/verify-email.blade.php`
- Modify: `resources/js/layouts/AuthLayout.vue`
- Modify: `resources/js/layouts/auth/AuthCardLayout.vue`
- Modify: `resources/js/layouts/auth/AuthSimpleLayout.vue`
- Modify: `resources/js/layouts/auth/AuthSplitLayout.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/js/pages/Welcome.vue`
- Modify: `resources/js/pages/auth/ConfirmPassword.vue`
- Modify: `resources/js/pages/auth/ForgotPassword.vue`
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `resources/js/pages/auth/ResetPassword.vue`
- Modify: `resources/js/pages/auth/TwoFactorChallenge.vue`
- Modify: `resources/js/pages/auth/VerifyEmail.vue`
- Modify: `resources/js/pages/settings/Account.vue`
- Modify: `resources/js/pages/settings/Appearance.vue`
- Modify: `resources/js/pages/settings/Security.vue`
- Modify: `resources/js/components/AppHeader.vue`
- Modify: `resources/js/components/AppLogo.vue`
- Modify: `resources/js/components/AppLogoIcon.vue`
- Modify: `resources/js/components/AppearanceTabs.vue`
- Modify: `resources/js/components/DeleteUser.vue`
- Modify: `resources/js/components/LocaleSwitcher.vue`
- Modify: `resources/js/components/ManagePasskeys.vue`
- Modify: `resources/js/components/ManageTwoFactor.vue`
- Modify: `resources/js/components/PasskeyItem.vue`
- Modify: `resources/js/components/PasskeyRegister.vue`
- Modify: `resources/js/components/PasskeyVerify.vue`
- Modify: `resources/js/components/TwoFactorRecoveryCodes.vue`
- Modify: `resources/js/components/TwoFactorSetupModal.vue`
- Modify: `resources/js/components/PasswordInput.vue`
- Modify: `resources/js/components/UserMenuContent.vue`
- Modify: `resources/js/components/ui/breadcrumb/Breadcrumb.vue`
- Modify: `resources/js/components/ui/breadcrumb/BreadcrumbEllipsis.vue`
- Modify: `resources/js/components/ui/dialog/DialogContent.vue`
- Modify: `resources/js/components/ui/dialog/DialogScrollContent.vue`
- Modify: `resources/js/components/ui/sheet/SheetContent.vue`
- Modify: `resources/js/components/ui/sidebar/Sidebar.vue`
- Modify: `resources/js/components/ui/sidebar/SidebarRail.vue`
- Modify: `resources/js/components/ui/sidebar/SidebarTrigger.vue`
- Modify: `resources/js/components/ui/spinner/Spinner.vue`
- Modify: `app/Http/Controllers/Settings/AccountController.php`
- Modify: `app/Http/Controllers/Settings/SecurityController.php`
- Modify: `app/Providers/FortifyServiceProvider.php`
- Modify: `app/Mail/ResetPasswordMail.php`
- Modify: `app/Mail/VerifyEmailMail.php`
- Test: `tests/Browser/WelcomeAndRegistrationTest.php`
- Test: `tests/Browser/AppearanceTest.php`
- Test: `tests/Browser/ProfileAndNavigationTest.php`
- Test: `tests/Feature/Auth/AuthenticationTest.php`
- Test: `tests/Feature/Auth/EmailVerificationTest.php`
- Test: `tests/Feature/Auth/PasswordResetTest.php`
- Test: `tests/Feature/Mail/AuthMailTest.php`
- Test: `tests/Feature/Settings/AccountUpdateTest.php`
- Test: `tests/Feature/Settings/SecurityTest.php`

**Interfaces:**
- Consumes: nested `t()` keys and feature payload from Task 1.
- Produces: all shared, account, settings, mail, and generic primitive copy through `common.*` or `account.*` keys.

- [ ] **Step 1: Update account journey expectations to the approved voice**

Change focused feature/browser assertions before components. Include at least:

```php
$page->assertSee('Crée ton compte');
$page->assertSee('Vérifie ton adresse e-mail');
$page->assertSee('Supprimer ton compte');
```

Update `AuthMailTest` to assert tutoiement in both HTML and text parts, including an actionable expiry/error instruction.

- [ ] **Step 2: Run the focused account tests and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Auth tests/Feature/Settings tests/Feature/Mail/AuthMailTest.php
```

Expected: FAIL on the old vouvoiement and old translation keys.

- [ ] **Step 3: Centralize and rewrite common/account copy**

Move all visible strings from the listed layouts, pages, components, Blade templates, controllers, mailables, and provider into `common.php`, `account.php`, or Laravel’s required validation/password catalogues. Use feature keys directly in PHP (`__('account.feedback.updated')`) and `t('account.login.title')` in Vue.

Set the app name through `common.brand.name` in the Inertia payload and stop using the hard-coded Vite fallback. Remove `initializeDomTranslations`, its import, and `translateDom.ts` after every migrated call site uses explicit keys.

For generic UI primitives, accept a translated accessible label as a prop when the primitive can render outside an Inertia page; pass `t('common.accessibility.close')`, `t('common.accessibility.loading')`, and sidebar equivalents from the owning layout. Do not bury `useTranslations()` inside a low-level primitive that is also used in isolated tests.

- [ ] **Step 4: Run account and frontend verification and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Auth tests/Feature/Settings tests/Feature/Mail/AuthMailTest.php
bun run types:check
bun run build
```

Expected: all commands exit 0 and no import references `translateDom`.

- [ ] **Step 5: Commit account and common copy**

```bash
git add lang app resources/js resources/views tests/Feature/Auth tests/Feature/Settings tests/Feature/Mail tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/AppearanceTest.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat(copy): harmonize account and common language"
```

---

### Task 3: Profile and administration copy

**Files:**
- Modify: `lang/fr/profile.php`
- Modify: `lang/en/profile.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Modify: `resources/js/pages/profile/Create.vue`
- Modify: `resources/js/pages/profile/Edit.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `resources/js/pages/Members/Show.vue`
- Modify: `resources/js/components/profile/AvatarCarousel.vue`
- Modify: `resources/js/components/profile/InterestTagSelector.vue`
- Modify: `resources/js/components/profile/ProfileForm.vue`
- Modify: `resources/js/components/profile/ProfileFormStepper.vue`
- Modify: `resources/js/components/profile/ProfilePresentation.vue`
- Modify: `resources/js/pages/Dashboard.vue`
- Modify: `resources/js/pages/Admin/Avatars/Index.vue`
- Modify: `resources/js/pages/Admin/Interests/Index.vue`
- Modify: `resources/js/pages/Admin/Onboarding/Index.vue`
- Modify: `app/Http/Controllers/MemberProfileController.php`
- Modify: `app/Http/Controllers/Admin/AvatarController.php`
- Modify: `app/Http/Controllers/Admin/AvatarStatusController.php`
- Modify: `app/Http/Controllers/Admin/InterestController.php`
- Modify: `app/Http/Controllers/Admin/InterestSettingController.php`
- Modify: `app/Http/Controllers/Admin/InterestStatusController.php`
- Modify: `app/Http/Controllers/Admin/ProductOnboardingController.php`
- Modify: `app/Actions/SyncProfileInterests.php`
- Modify: `app/Http/Requests/MemberProfileRequest.php`
- Modify: `app/Http/Requests/StoreInterestRequest.php`
- Test: `tests/Feature/MemberProfileTest.php`
- Test: `tests/Feature/PublicMemberProfileTest.php`
- Test: `tests/Feature/Admin/ManageAvatarCatalogTest.php`
- Test: `tests/Feature/Admin/ManageInterestCatalogTest.php`
- Test: `tests/Feature/Admin/ManageProductOnboardingTest.php`
- Test: `tests/Browser/ProfileAndNavigationTest.php`
- Test: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: `profile.*`, `administration.*`, and `common.*` translation trees.
- Produces: the canonical visible term `Univers favoris` across member forms, presentations, validations, and administration help.

- [ ] **Step 1: Add failing canonical-profile assertions**

Update the profile browser test to assert `Choisis tes univers favoris`, `Ton profil`, and tutoiement in the visibility help. Update admin feature assertions to expect actionable translated conflicts such as `Cet avatar est encore utilisé. Choisis-en un autre avant de le supprimer.`

- [ ] **Step 2: Run profile/admin focused tests and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MemberProfileTest.php tests/Feature/PublicMemberProfileTest.php tests/Feature/Admin
```

Expected: FAIL on old `Intérêts`, old vouvoiement, and sentence-key translations.

- [ ] **Step 3: Migrate profile and administration call sites**

Replace every visible literal in the listed Vue files with feature keys. Move controller, request, and action messages away from JSON/sentence keys to `profile.*` or `administration.*`. Keep catalogue entity names entered by administrators dynamic and use replacements for accessible labels such as `Monter :name`.

- [ ] **Step 4: Run focused tests, types, and formatting and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MemberProfileTest.php tests/Feature/PublicMemberProfileTest.php tests/Feature/Admin
bun run types:check
bun run format:check
```

Expected: all commands exit 0.

- [ ] **Step 5: Commit profile and administration copy**

```bash
git add lang/fr/profile.php lang/en/profile.php lang/fr/administration.php lang/en/administration.php resources/js/pages/profile resources/js/pages/Members resources/js/pages/Admin resources/js/pages/Dashboard.vue resources/js/components/profile app tests/Feature/MemberProfileTest.php tests/Feature/PublicMemberProfileTest.php tests/Feature/Admin tests/Browser/ProfileAndNavigationTest.php tests/Browser/AdminTest.php
git commit -m "feat(copy): harmonize profile and administration language"
```

---

### Task 4: Discovery and onboarding canonical journey

**Files:**
- Modify: `lang/fr/discovery.php`
- Modify: `lang/en/discovery.php`
- Modify: `lang/fr/onboarding.php`
- Modify: `lang/en/onboarding.php`
- Modify: `resources/js/pages/Discovery/Index.vue`
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Modify: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `resources/js/components/NavMain.vue`
- Modify: `resources/js/components/NavUser.vue`
- Modify: `app/Actions/CreateSwipe.php`
- Modify: `app/Actions/AdvanceProductOnboarding.php`
- Modify: `app/Http/Controllers/ProductOnboardingController.php`
- Modify: `app/Http/Controllers/SwipeController.php`
- Test: `tests/Feature/CreateSwipeTest.php`
- Test: `tests/Feature/ProductOnboardingTest.php`
- Test: `tests/Feature/ProductOnboardingTransitionTest.php`
- Test: `tests/Feature/DiscoveryPageTest.php`
- Test: `tests/Browser/DiscoveryTest.php`
- Test: `tests/Browser/OnboardingTest.php`

**Interfaces:**
- Consumes: feature translator from Task 1.
- Produces: one consistent `Explorer` / `Passer` / `Découvrir` / `Univers croisés` journey.

- [ ] **Step 1: Write failing canonical discovery assertions**

In browser tests assert these exact observable strings:

```php
$page->assertSee('Explorer');
$page->assertAttribute('@pass-profile', 'aria-label', 'Passer le profil de Alex');
$page->assertAttribute('@discover-profile', 'aria-label', 'Découvrir le profil de Alex');
$page->assertSee('Vos univers se croisent');
$page->assertSee('Alex souhaite aussi te découvrir.');
```

Update onboarding assertions so step labels use `Passer`, `Découvrir`, `Univers croisés`, and `Échange`.

- [ ] **Step 2: Run discovery/onboarding tests and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/CreateSwipeTest.php tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Feature/DiscoveryPageTest.php
```

Expected: FAIL because the UI and responses still expose `J’aime`, `Match`, and vouvoiement.

- [ ] **Step 3: Implement the canonical journey and actionable errors**

Use `discovery.actions.pass`, `discovery.actions.discover`, and the `discovery.match.*` keys from Task 1 everywhere, including icon labels and titles. Rewrite duplicate-swipe and unavailable-profile errors to explain that the profile is no longer available and tell the member to return to exploration. Update onboarding demo copy and instructions without changing its internal `pass`, `like`, `match`, and `conversation` transition values.

- [ ] **Step 4: Run focused tests and browser journeys and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/CreateSwipeTest.php tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Feature/DiscoveryPageTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php
```

Expected: all commands exit 0.

- [ ] **Step 5: Commit discovery and onboarding copy**

```bash
git add lang/fr/discovery.php lang/en/discovery.php lang/fr/onboarding.php lang/en/onboarding.php resources/js/pages/Discovery resources/js/pages/Onboarding resources/js/components/discovery resources/js/components/MemberBottomNavigation.vue resources/js/components/NavMain.vue resources/js/components/NavUser.vue app tests/Feature/CreateSwipeTest.php tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Feature/DiscoveryPageTest.php tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php
git commit -m "feat(copy): define discovery editorial journey"
```

---

### Task 5: Conversations and blocking copy

**Files:**
- Modify: `lang/fr/conversations.php`
- Modify: `lang/en/conversations.php`
- Modify: `lang/fr/blocking.php`
- Modify: `lang/en/blocking.php`
- Modify: `resources/js/pages/Conversations/Index.vue`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `resources/js/components/conversations/ConversationHeader.vue`
- Modify: `resources/js/components/conversations/MessageComposer.vue`
- Modify: `resources/js/components/conversations/MessageItems.vue`
- Modify: `resources/js/components/conversations/MessageTimeline.vue`
- Modify: `resources/js/components/conversations/RealtimeStatus.vue`
- Modify: `resources/js/components/members/BlockMemberDialog.vue`
- Modify: `resources/js/components/members/UnblockMemberButton.vue`
- Modify: `app/Actions/BlockUser.php`
- Modify: `app/Actions/SendMessage.php`
- Modify: `app/Http/Controllers/BlockMemberController.php`
- Modify: `app/Http/Controllers/UnblockMemberController.php`
- Test: `tests/Feature/BlockMemberControllerTest.php`
- Test: `tests/Feature/BlockUserTest.php`
- Test: `tests/Feature/SendMessageTest.php`
- Test: `tests/Feature/StoreMessageTest.php`
- Test: `tests/Browser/ConversationTest.php`
- Test: `tests/Browser/MemberBlockingTest.php`

**Interfaces:**
- Consumes: `conversations.*`, `blocking.*`, and `common.*` keys.
- Produces: consistent `Échange` terminology and actionable realtime/send/block errors.

- [ ] **Step 1: Write failing conversation and blocking copy assertions**

Assert `Mes échanges`, `Commencer l’échange`, and the tutoiement form of empty/realtime/error states. For blocking, assert the actual product rule: blocking makes the exchange inaccessible, rather than claiming it stays consultable.

- [ ] **Step 2: Run focused tests and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockMemberControllerTest.php tests/Feature/BlockUserTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php
```

Expected: FAIL on old copy and old translation locations.

- [ ] **Step 3: Migrate conversation and blocking text**

Move all visible strings and dynamic plural labels into their domain catalogues. Use explicit singular/plural keys selected from counts; do not build French or English plurals with string concatenation in Vue. Align blocking confirmation with the PRD: the exchange becomes inaccessible to both members, and the blocked member is not explicitly notified.

- [ ] **Step 4: Run focused feature and browser tests and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockMemberControllerTest.php tests/Feature/BlockUserTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ConversationTest.php tests/Browser/MemberBlockingTest.php
```

Expected: all commands exit 0.

- [ ] **Step 5: Commit conversation and blocking copy**

```bash
git add lang/fr/conversations.php lang/en/conversations.php lang/fr/blocking.php lang/en/blocking.php resources/js/pages/Conversations resources/js/components/conversations resources/js/components/members app/Actions/BlockUser.php app/Actions/SendMessage.php app/Http/Controllers/BlockMemberController.php app/Http/Controllers/UnblockMemberController.php tests/Feature/BlockMemberControllerTest.php tests/Feature/BlockUserTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php tests/Browser/ConversationTest.php tests/Browser/MemberBlockingTest.php
git commit -m "feat(copy): harmonize exchanges and blocking language"
```

---

### Task 6: Editorial guide and automated policy gates

**Files:**
- Create: `docs/editorial-guidelines.md`
- Modify: `docs/PRD.md`
- Delete: `lang/fr/frontend.php`
- Delete: `lang/en/frontend.php`
- Delete: `lang/fr.json`
- Delete: `lang/en.json`
- Create: `tests/Feature/Localization/EditorialCopyTest.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`
- Modify: `tests/Feature/Localization/BackendTranslationsTest.php`
- Modify: `tests/Browser/LocalizationTest.php`

**Interfaces:**
- Consumes: all migrated feature catalogues and explicit-key call sites.
- Produces: a documented editorial contract and CI gates that fail on hard-coded visible text, forbidden terms, empty translations, missing keys, or legacy catalogues.

- [ ] **Step 1: Write failing catalogue and policy tests**

Create `EditorialCopyTest.php` with three focused tests:

```php
test('feature catalogues contain no forbidden romantic language', function () {
    $forbidden = [
        '/âme[ -]sœur/iu', '/coups? de cœur/iu', '/alchimie/iu',
        '/sédui(?:re|sant|sante)|séduction/iu', '/craquer pour/iu',
        '/partenaire idéal/iu', '/relation amoureuse/iu',
        '/rendez-vous amoureux/iu', '/compatibilité amoureuse/iu',
        '/flirt(?:er)?/iu', '/dating/iu',
    ];

    foreach (featureTranslationValues() as $key => $value) {
        foreach ($forbidden as $pattern) {
            expect($value, "Forbidden editorial term at {$key}")
                ->not->toMatch($pattern);
        }
    }
});
```

Define `featureTranslationValues()` in the test file by loading the eight exact
feature catalogues for both locales and recursively yielding scalar leaves as
`locale.domain.path => value`. Use the same flattener to compare keys and reject
empty values.

Add a source policy test with these explicit detectors:

```php
$visiblePatterns = [
    // Literal text nodes in Vue and Blade templates.
    '/>([^<>{}\n]*\p{L}[^<>{}\n]*)</u',
    // Literal accessibility, help, and image attributes.
    '/(?:aria-label|placeholder|title|alt)="([^"@:]*\p{L}[^"@:]*)"/u',
    // Literal user feedback returned by PHP.
    "/['\"](?:message|error|title|description)['\"]\s*=>\s*['\"]([^'\"]*\\p{L}[^'\"]*)['\"]/u",
];

$templateSources = collect([
    ...File::allFiles(resource_path('js')),
    ...File::allFiles(resource_path('views')),
])->filter(fn (SplFileInfo $file): bool =>
    str_ends_with($file->getFilename(), '.vue')
    || str_ends_with($file->getFilename(), '.blade.php'));

$phpSources = collect(File::allFiles(app_path()))
    ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

$violations = [];

foreach ($templateSources as $source) {
    $contents = $source->getContents();
    $contents = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/su', '', $contents);
    $contents = preg_replace('/<!--.*?-->/su', '', $contents);

    foreach (array_slice($visiblePatterns, 0, 2) as $pattern) {
        preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as [$literal, $offset]) {
            $trimmed = trim(strip_tags($literal));

            if ($trimmed !== '' && preg_match('/\p{L}/u', $trimmed) === 1) {
                $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
                $violations[] = str_replace(base_path().'/', '', $source->getPathname()).":{$line} {$trimmed}";
            }
        }
    }
}

foreach ($phpSources as $source) {
    $contents = $source->getContents();
    preg_match_all($visiblePatterns[2], $contents, $matches, PREG_OFFSET_CAPTURE);

    foreach ($matches[1] as [$literal, $offset]) {
        $line = substr_count(substr($contents, 0, $offset), "\n") + 1;
        $violations[] = str_replace(base_path().'/', '', $source->getPathname()).":{$line} {$literal}";
    }
}

expect($violations)->toBe([]);
```

Do not suppress a detected word through an allow-list: convert the owning call
site to `t()` or `__()`.

- [ ] **Step 2: Run policy tests and verify RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization/EditorialCopyTest.php tests/Feature/Localization/InertiaTranslationsTest.php tests/Feature/Localization/BackendTranslationsTest.php
```

Expected: FAIL with any remaining hard-coded copy, legacy `frontend.php`/JSON catalogues, or key mismatches.

- [ ] **Step 3: Remove every reported visible literal and legacy catalogue**

For each `path:line` failure, move the text to the owning feature catalogue and use `__()` or `t()`. Do not add visible words to an allow-list. Delete `frontend.php`, both JSON catalogues, and all assertions for `copy` once no caller depends on them.

- [ ] **Step 4: Publish the short editorial guide and link it from the PRD**

Condense the approved spec into `docs/editorial-guidelines.md` with these exact sections: `Voix`, `Vocabulaire canonique`, `Lexique recommandé`, `Formulations interdites`, `Erreurs et confirmations`, and `Checklist mobile`. Link it from the PRD specialized references and update the implementation matrix or documentation inventory to identify the editorial system as implemented only after the gates pass.

- [ ] **Step 5: Run policy and localization verification and verify GREEN**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/Localization tests/Browser/LocalizationTest.php
bun run lint:check
bun run format:check
bun run types:check
```

Expected: all commands exit 0, the hard-coded scanner reports zero visible strings, and both locale catalogues have identical keys.

- [ ] **Step 6: Commit editorial policy gates**

```bash
git add docs lang tests/Feature/Localization tests/Browser/LocalizationTest.php resources app
git commit -m "test(copy): enforce editorial guidelines"
```

---

### Task 7: Mobile journey review and full verification

**Files:**
- Modify: `tests/Browser/WelcomeAndRegistrationTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Modify: `tests/Browser/DiscoveryTest.php`
- Modify: `tests/Browser/OnboardingTest.php`
- Modify: `tests/Browser/ConversationTest.php`
- Modify: `tests/Browser/MemberBlockingTest.php`
- Modify: `tests/Browser/AdminTest.php`
- Modify: any owning feature catalogue or Vue component only when the mobile review exposes a concrete copy or layout defect.

**Interfaces:**
- Consumes: completed editorial migration and policy gates.
- Produces: evidence that every MVP journey is natural and usable at 390 px in French, with English localization coverage retained.

- [ ] **Step 1: Add mobile assertions for the approved high-risk copy**

At the start of each relevant browser journey set the viewport to `390x844`. Assert the canonical action labels, no horizontal overflow, and visible/non-truncated primary controls. Retain the existing English localization journey and assert at least the English equivalents of navigation, profile interests, discovery decisions, match title, and exchange navigation.

- [ ] **Step 2: Run the affected browser suite and verify RED or existing coverage**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php tests/Browser/ConversationTest.php tests/Browser/MemberBlockingTest.php tests/Browser/AdminTest.php tests/Browser/LocalizationTest.php
```

Expected: new assertions either expose a concrete mobile/copy defect (RED) or demonstrate the migrated behavior already satisfies the requirement. If they pass immediately, temporarily restore one old canonical label in the owning catalogue, verify the relevant assertion fails, then restore the new label and rerun GREEN.

- [ ] **Step 3: Fix only defects demonstrated by the mobile review**

Adjust the owning translation for excessive length or the owning component’s existing responsive classes. Preserve meaning between languages and do not introduce abbreviations that obscure `Passer`, `Découvrir`, or destructive actions.

- [ ] **Step 4: Run complete project verification**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer ci:check
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test
git diff --check
```

Expected: lint, format, static analysis, TypeScript, frontend unit tests, Vite build, all Pest tests, and whitespace verification exit 0.

- [ ] **Step 5: Review the issue acceptance criteria against the diff**

Confirm one-by-one: guide present; lexicon and forbidden phrases documented; canonical terms consistent; friendship clear at orientation moments; no unauthorized Disney phrase; zero visible literals; actionable errors; mobile copy reviewed; French/English key parity.

- [ ] **Step 6: Commit final mobile verification adjustments**

```bash
git add tests/Browser lang resources docs
git commit -m "test(copy): verify editorial journeys on mobile"
```

- [ ] **Step 7: Request code review before integration**

Invoke `superpowers:requesting-code-review`, address any findings with the TDD cycle, rerun `composer ci:check`, and only then prepare the pull request for issue 41.
