# Mandatory Registration Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the product tutorial a mandatory continuation of registration, using the real discovery, match, and conversation components while strictly allowing only the expected decision at each swipe step.

**Architecture:** Persist one resumable onboarding state on the user, enforce it with Laravel middleware after profile completion, and keep tutorial interactions local so no social records are created. Refactor the existing production UI into reusable components, then compose those components on the onboarding page with a shared eight-step registration stepper.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest Browser, Pest, Bun.

**Spec:** `docs/superpowers/specs/2026-08-28-mandatory-registration-onboarding-design.md`

## Global Constraints

- Follow red-green-refactor for every behavior change: write one focused failing test, run it and confirm the intended failure, implement the minimum, then rerun it.
- Never create likes, passes, matches, conversations, or messages in the database during tutorial steps.
- Keep all authorization and mandatory-flow enforcement on the server; disabled Vue controls are additional UX protection.
- Preserve the production discovery and conversation behavior when extracting shared components.
- Preserve reduced-motion support and use transform-based animations only.
- Do not add skip, restart, postpone, or settings relaunch paths under another name.
- Use neutral friendship vocabulary and remove every visible occurrence of “fictif”, “fictive” and “démonstration” from onboarding.

---

## Task 1: Normalize persisted onboarding state

**Files:**

- Create: `database/migrations/2026_08_28_000000_normalize_mandatory_product_onboarding_status.php`
- Modify: `app/Enums/ProductOnboardingStatus.php`
- Modify: `app/Actions/AdvanceProductOnboarding.php`
- Modify: `tests/Feature/ProductOnboardingSchemaTest.php`
- Modify: `tests/Feature/ProductOnboardingTransitionTest.php`

- [ ] Add a failing schema test proving legacy `skipped` users become resumable at `in_progress/pass_demo` after the migration, while completed users remain completed.

```php
it('normalizes skipped onboarding records to the mandatory first tutorial step', function () {
    $user = User::factory()->create();
    DB::table('product_onboardings')->insert([
        'user_id' => $user->id,
        'status' => 'skipped',
        'step' => 'completed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $migration = require database_path(
        'migrations/2026_08_28_000000_normalize_mandatory_product_onboarding_status.php',
    );
    $migration->up();

    expect(DB::table('product_onboardings')->where('user_id', $user->id)->first())
        ->status->toBe('in_progress')
        ->step->toBe('pass_demo');
});
```

- [ ] Run `php artisan test tests/Feature/ProductOnboardingSchemaTest.php` and confirm it fails because the normalization migration does not exist.
- [ ] Create an idempotent data migration that updates `skipped` rows to `in_progress/pass_demo` before the enum value is removed. Keep `down()` intentionally empty because normalized rows cannot be distinguished safely from genuine in-progress rows.
- [ ] Remove `Skipped` from `ProductOnboardingStatus` and remove `skip()` and restart-specific behavior from `AdvanceProductOnboarding`; keep `start()`, `advance()` and `complete()` idempotent and transaction-safe.
- [ ] Update transition tests to prove the only valid sequence is `pass_demo → like_demo → match_explanation → conversation_demo → completed`, and that resuming never rewinds progress.
- [ ] Run `php artisan test tests/Feature/ProductOnboardingSchemaTest.php tests/Feature/ProductOnboardingTransitionTest.php` and confirm green.
- [ ] Commit with `git add database/migrations app/Enums/ProductOnboardingStatus.php app/Actions/AdvanceProductOnboarding.php tests/Feature/ProductOnboardingSchemaTest.php tests/Feature/ProductOnboardingTransitionTest.php && git commit -m "refactor: make product onboarding mandatory"`.

## Task 2: Enforce completion with middleware and remove escape routes

**Files:**

- Create: `app/Http/Middleware/EnsureProductOnboardingIsComplete.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `routes/settings.php`
- Modify: `app/Http/Controllers/ProductOnboardingController.php`
- Delete: `app/Http/Controllers/Settings/ProductOnboardingController.php`
- Delete: `resources/js/pages/settings/Onboarding.vue`
- Modify: `tests/Feature/ProductOnboardingTest.php`
- Delete: `tests/Feature/ProductOnboardingSettingsTest.php`

- [ ] Add failing feature tests for these routing rules:

```php
it('redirects a profile-complete member to the current onboarding step', function () {
    $user = User::factory()->profileComplete()->create([
        'product_onboarding_status' => ProductOnboardingStatus::InProgress,
        'product_onboarding_step' => ProductOnboardingStep::LikeDemo,
    ]);

    $this->actingAs($user)->get(route('discover.index'))
        ->assertRedirect(route('onboarding.show'));
});

it('does not expose tutorial escape routes', function (string $method, string $uri) {
    $this->actingAs(User::factory()->profileComplete()->create())
        ->call($method, $uri)
        ->assertNotFound();
})->with([
    ['POST', '/onboarding/skip'],
    ['POST', '/onboarding/restart'],
    ['GET', '/settings/onboarding'],
]);
```

- [ ] Also test that completed users reach discovery, incomplete-profile users still reach profile completion first, onboarding GET/PATCH/complete endpoints do not loop, and login/logout/public/avatar-image routes remain reachable.
- [ ] Test that `/admin/onboarding` and its tutorial-avatar catalog routes remain accessible to authorized admins so invalid tutorial configuration can be repaired; all unrelated member/admin pages remain protected.
- [ ] Run `php artisan test tests/Feature/ProductOnboardingTest.php` and confirm the new redirect and 404 assertions fail.
- [ ] Implement `EnsureProductOnboardingIsComplete` with an explicit completed-state check and redirect to `onboarding.show`; register alias `onboarding.complete` in `bootstrap/app.php`.
- [ ] Restructure route groups so profile completion precedes onboarding enforcement. Keep onboarding transition routes and the admin onboarding configuration routes outside `onboarding.complete`; place discovery, conversations, settings, and unrelated admin routes inside it.
- [ ] Remove skip/restart endpoints, controller methods, settings relaunch controller/page/route, and any navigation link to that settings page.
- [ ] Run `php artisan test tests/Feature/ProductOnboardingTest.php tests/Feature/Admin/ManageProductOnboardingTest.php` and confirm green.
- [ ] Commit with `git add app/Http bootstrap/app.php routes resources/js/pages/settings tests/Feature && git commit -m "feat: require onboarding before application access"`.

## Task 3: Extend the registration stepper from four to eight steps

**Files:**

- Modify: `resources/js/components/profile/ProfileFormStepper.vue`
- Modify: `resources/js/components/profile/ProfileForm.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `tests/Browser/OnboardingTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`

- [ ] Add a failing browser assertion that onboarding displays the eight registration labels and highlights the persisted tutorial step: `Avatar`, `Identité`, `Affinités`, `Aperçu`, `Passer`, `J’aime`, `Match`, `Discussion`.
- [ ] Add a regression assertion that profile creation still shows only its four editable steps.
- [ ] Run `php artisan test tests/Browser/OnboardingTest.php --filter='stepper'` and confirm it fails because the onboarding page has no shared stepper.
- [ ] Change `ProfileFormStepper` to accept typed props instead of hardcoded labels:

```ts
const props = withDefaults(defineProps<{
    labels: readonly string[];
    currentStep: number;
    furthestStep: number;
    selectable?: boolean;
}>(), { selectable: true });
```

  Emit `select` only when `selectable` is true and the requested step is not beyond `furthestStep`. Preserve the current compact/mobile styling.
- [ ] Pass the original four labels from `ProfileForm`. In onboarding, pass all eight labels, map the persisted enum to indices 5–8, mark earlier steps complete, and set `selectable="false"` so users cannot jump ahead or back.
- [ ] Run the two targeted browser tests and confirm green.
- [ ] Commit with `git add resources/js/components/profile resources/js/pages/Onboarding/Show.vue tests/Browser && git commit -m "feat: continue registration stepper through onboarding"`.

## Task 4: Constrain real swipe interactions to the required decision

**Files:**

- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Delete: `resources/js/components/onboarding/DemoSwipeCard.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `tests/Browser/OnboardingTest.php`
- Modify: `tests/Browser/DiscoveryTest.php`

- [ ] Add failing browser tests for both tutorial cards:

  - On `pass_demo`, the Like button is disabled, Passer is enabled, right drag returns to center, right keyboard decision emits nothing, and left drag/Passer advances.
  - On `like_demo`, Passer is disabled, Like is enabled, left drag returns to center, left keyboard decision emits nothing, and right drag/Like advances.
  - In normal discovery, both decisions still work.

- [ ] Run `php artisan test tests/Browser/OnboardingTest.php --filter='decision'` and confirm forbidden buttons/gestures currently trigger actions.
- [ ] Add the backward-compatible prop to the production card:

```ts
type AllowedDecision = 'pass' | 'like' | 'both';

const props = withDefaults(defineProps<{
    profile: DiscoveryProfile;
    locked: boolean;
    preview?: boolean;
    compact?: boolean;
    allowedDecision?: AllowedDecision;
}>(), { allowedDecision: 'both' });
```

- [ ] Centralize `canDecide('pass' | 'like')`. Apply it before button actions, keyboard actions, threshold acceptance, and emitted decisions. Bind native `disabled` plus existing disabled styling to the forbidden button.
- [ ] During pointer movement, clamp translation at zero in the forbidden direction. On release in a forbidden direction, animate back to center without emitting. Keep `translate3d`, pointer capture, the existing exit duration, and reduced-motion behavior to eliminate the jerky duplicate tutorial animation.
- [ ] Render the two configured tutorial profiles with `SwipeCard`, setting `allowedDecision="pass"` then `allowedDecision="like"`; delete `DemoSwipeCard`.
- [ ] Run targeted onboarding and discovery browser tests and confirm green.
- [ ] Commit with `git add resources/js/components/discovery/SwipeCard.vue resources/js/components/onboarding/DemoSwipeCard.vue resources/js/pages/Onboarding/Show.vue tests/Browser && git commit -m "feat: constrain onboarding swipe decisions"`.

## Task 5: Extract and reuse the production match dialog

**Files:**

- Create: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/js/pages/Discovery/Index.vue`
- Delete: `resources/js/components/onboarding/DemoMatch.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `tests/Browser/DiscoveryTest.php`
- Modify: `tests/Browser/OnboardingTest.php`

- [ ] Add failing browser assertions that discovery and onboarding share the same match title, participant presentation, and primary “Ouvrir la conversation” control, while onboarding has no “Continuer à découvrir” action.
- [ ] Run the two focused browser tests and confirm the onboarding assertion fails against `DemoMatch`.
- [ ] Extract the current dialog from `Discovery/Index.vue` into `MatchDialog.vue` with this contract:

```ts
defineProps<{
    open: boolean;
    match: DiscoveryMatch;
    conversationHref?: string;
    showContinue?: boolean;
}>();

defineEmits<{
    'update:open': [value: boolean];
    openConversation: [];
}>();
```

  Default `showContinue` to true. Use a real link when `conversationHref` exists and emit `openConversation` for the local onboarding transition otherwise.
- [ ] Replace the inline production dialog and onboarding demo card with the shared component. In onboarding keep the dialog non-dismissible until its primary button is used.
- [ ] Delete `DemoMatch`, run targeted tests, and confirm green.
- [ ] Commit with `git add resources/js/components/discovery resources/js/components/onboarding/DemoMatch.vue resources/js/pages/Discovery resources/js/pages/Onboarding tests/Browser && git commit -m "refactor: share the production match dialog"`.

## Task 6: Reuse the production conversation interface with a local sender

**Files:**

- Create: `resources/js/components/conversations/ConversationHeader.vue`
- Create: `resources/js/lib/sendConversationMessage.ts`
- Modify: `resources/js/components/conversations/MessageComposer.vue`
- Modify: `resources/js/components/conversations/MessageTimeline.vue`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Delete: `resources/js/components/onboarding/DemoConversation.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `tests/Browser/ConversationTest.php`
- Modify: `tests/Browser/OnboardingTest.php`

- [ ] Add failing tests that onboarding shows the production conversation header, timeline, composer, placeholder and send control; sending a non-empty message completes onboarding without creating any `conversations` or `messages` rows.
- [ ] Add conversation regression assertions for real message submission, archived composer state, pagination trigger, and back navigation.
- [ ] Run focused onboarding and conversation tests and confirm the shared-interface assertions fail.
- [ ] Extract `ConversationHeader` from the production page with participant/avatar props and optional `backHref`; omit the back control when no href is provided.
- [ ] Move the real endpoint request into `sendConversationMessage.ts`:

```ts
export async function sendConversationMessage(
    conversationId: number,
    content: string,
): Promise<ConversationMessage>;
```

- [ ] Refactor `MessageComposer` to accept `submitMessage: (content: string) => Promise<ConversationMessage>` instead of owning the endpoint. Keep validation, pending state, focus restoration, error handling, and `onSent` behavior unchanged. The production page passes a closure around `sendConversationMessage`; onboarding passes a local async closure that returns an in-memory message object.
- [ ] Add `infinite?: boolean` (default true) to `MessageTimeline`. When false, render the same semantic message list without Inertia `InfiniteScroll`; do not duplicate message bubble styling.
- [ ] Compose `ConversationHeader`, `MessageTimeline(infinite=false)`, and `MessageComposer` in onboarding; delete `DemoConversation`. Use ordinary copy such as “Écrivez votre message” and never “message fictif”.
- [ ] Run focused tests and confirm green.
- [ ] Commit with `git add resources/js/components/conversations resources/js/lib/sendConversationMessage.ts resources/js/pages/Conversations resources/js/pages/Onboarding resources/js/components/onboarding/DemoConversation.vue tests/Browser && git commit -m "refactor: reuse conversation UI in onboarding"`.

## Task 7: Simplify onboarding page, use toast feedback, and preserve progress

**Files:**

- Modify: `app/Http/Controllers/ProductOnboardingController.php`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `tests/Feature/ProductOnboardingTest.php`
- Modify: `tests/Browser/OnboardingTest.php`

- [ ] Rewrite the end-to-end browser test first to cover the complete mandatory path from profile preview through Passer, J’aime, Match, Discussion, and final redirect to discovery.
- [ ] Assert absence of “Continuer plus tard”, “Ignorer le tutoriel”, “Recommencer”, “Démonstration”, “Découvrez le fonctionnement”, “fictif” and “fictive”. Assert the bottom application navigation is absent throughout onboarding.
- [ ] Add a failure-path assertion: force the transition endpoint to return an error, verify a toast appears without changing layout/card position, then retry successfully.
- [ ] Run the focused browser test and confirm it fails against the current header/footer/inline error UI.
- [ ] Reduce `Onboarding/Show.vue` to stepper, current instruction, real shared component, and toast host. Remove the demo badge/title/disclaimer, postpone/skip/restart footer, inline wrong-action/error box, and any bottom navigation layout.
- [ ] Do not send forbidden decisions to the server. For unexpected server/network failures, show a concise toast and keep the current persisted step/component interactive. Avoid optimistic step advancement until the PATCH succeeds.
- [ ] Ensure controller props contain only anonymous tutorial presentation data and state. Never include real target user IDs. Keep completion idempotent and redirect to discovery only after the final server transition succeeds.
- [ ] Run `php artisan test tests/Feature/ProductOnboardingTest.php tests/Browser/OnboardingTest.php` and confirm green.
- [ ] Commit with `git add app/Http/Controllers/ProductOnboardingController.php resources/js/pages/Onboarding/Show.vue tests/Feature/ProductOnboardingTest.php tests/Browser/OnboardingTest.php && git commit -m "feat: simplify mandatory onboarding flow"`.

## Task 8: Align admin reporting and product documentation

**Files:**

- Modify: `app/Http/Controllers/Admin/ProductOnboardingController.php`
- Modify: `resources/js/pages/Admin/Onboarding/Index.vue`
- Modify: `tests/Feature/Admin/ManageProductOnboardingTest.php`
- Modify: `tests/Browser/AdminTest.php`
- Modify: `docs/mvp-v1.md`

- [ ] Add failing admin tests proving no skipped status/stat/filter is rendered and that counts are grouped into not started, in progress by current step, and completed. Preserve tutorial-avatar selection and archive protection tests.
- [ ] Run `php artisan test tests/Feature/Admin/ManageProductOnboardingTest.php tests/Browser/AdminTest.php --filter='onboarding'` and confirm skipped output still exists.
- [ ] Remove skipped branches from admin queries, DTOs, filters, cards, and labels. Keep aggregate cards for started/in-progress/completed and the per-user current-step table.
- [ ] Update `docs/mvp-v1.md` to state that tutorial steps 5–8 are mandatory after the four profile-registration steps, resumable, and use local tutorial data without social persistence. Remove documented skip/relaunch behavior.
- [ ] Run targeted admin tests and confirm green.
- [ ] Commit with `git add app/Http/Controllers/Admin resources/js/pages/Admin tests/Feature/Admin tests/Browser/AdminTest.php docs/mvp-v1.md && git commit -m "docs: align onboarding administration and product flow"`.

## Task 9: Full verification and Docker handoff

**Files:**

- Verify only; modify files solely to fix failures directly caused by this feature.

- [ ] Run backend static and formatting checks:

```sh
composer lint:check
composer analyse
```

- [ ] Run frontend checks and production build:

```sh
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

- [ ] Run all directly affected tests:

```sh
php artisan test tests/Feature/ProductOnboardingSchemaTest.php \
  tests/Feature/ProductOnboardingTransitionTest.php \
  tests/Feature/ProductOnboardingTest.php \
  tests/Feature/Admin/ManageProductOnboardingTest.php \
  tests/Browser/OnboardingTest.php \
  tests/Browser/DiscoveryTest.php \
  tests/Browser/ConversationTest.php \
  tests/Browser/ProfileAndNavigationTest.php \
  tests/Browser/AdminTest.php
```

- [ ] Run `composer test`. If any CI-flaky browser test fails, rerun the exact failing test to diagnose; do not accept a retry as the fix without identifying and removing the race.
- [ ] Run `git diff --check` and `git status --short`; inspect the final diff for removed escape controls, forbidden gesture guards, and accidental social writes.
- [ ] Rebuild and replace the currently running worktree Docker environment, then migrate/seed explicitly:

```sh
docker compose up --build -d
docker compose exec -T web php artisan migrate --seed --force
docker compose ps
```

- [ ] Manually exercise at `http://localhost:8000`: registration steps 1–4, forbidden controls/gestures on steps 5–6, match CTA, local conversation send, completion redirect, reload/resume, and admin stats/avatar configuration.
- [ ] Commit any verification-only fixes with a focused Conventional Commit; otherwise leave no uncommitted generated artifacts.
