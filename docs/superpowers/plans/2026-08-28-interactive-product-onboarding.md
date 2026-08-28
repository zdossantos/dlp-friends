# Interactive Product Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a safe interactive onboarding that teaches Pass, Like, matching, and messaging without social writes, plus member relaunch controls and an admin configuration/reporting page.

**Architecture:** Persist tutorial status and step in a dedicated one-to-one model, and apply every transition through a locked domain action. Render static localized demonstration content on an isolated Inertia page that never calls social endpoints. Store the two demo avatar choices in singleton settings and expose aggregate/member progress only to administrators.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Pest Browser, Bun, Wayfinder.

**Spec:** `docs/superpowers/specs/2026-08-28-interactive-product-onboarding-design.md`

## Global Constraints

- DLP Friends remains strictly friendly, adults-only, and independent from Disney.
- The tutorial never creates a swipe, match, conversation, message, broadcast, or realtime subscription.
- Statuses are exactly `not_started`, `in_progress`, `completed`, and `skipped`.
- Steps are exactly `pass_demo`, `like_demo`, `match_demo`, and `conversation_demo`.
- Required order: Pass, Like, open fake match, guided conversation.
- Main and bottom member navigation stay hidden throughout the tutorial.
- Demo avatars are active and distinct; configured avatars cannot be archived or deleted.
- Visible and accessible copy is available in French and English.
- Interactive coverage uses Pest Browser, not Vitest.

---

### Task 1: Persist progress and avatar configuration

**Files:**
- Create: `app/Enums/ProductOnboardingStatus.php`
- Create: `app/Enums/ProductOnboardingStep.php`
- Create: `app/Models/ProductOnboarding.php`
- Create: `app/Models/ProductOnboardingSetting.php`
- Create: `database/factories/ProductOnboardingFactory.php`
- Create: `database/migrations/2026_08_28_100000_create_product_onboarding_tables.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Avatar.php`
- Test: `tests/Feature/ProductOnboardingSchemaTest.php`

**Interfaces:**
- Produces `User::productOnboarding(): HasOne`.
- Produces `ProductOnboardingSetting::current(): ?ProductOnboardingSetting`.
- Produces avatar inverse relations for both setting columns.

- [ ] **Step 1: Write failing schema and relation tests**

```php
test('progress is unique per user and cast to enums', function () {
    $user = User::factory()->create();
    $progress = $user->productOnboarding()->create([
        'status' => ProductOnboardingStatus::InProgress,
        'step' => ProductOnboardingStep::PassDemo,
    ]);

    expect($progress->status)->toBe(ProductOnboardingStatus::InProgress)
        ->and($progress->step)->toBe(ProductOnboardingStep::PassDemo);

    $this->expectException(QueryException::class);
    ProductOnboarding::factory()->for($user)->create();
});

test('settings reject the same avatar twice', function () {
    $avatar = Avatar::factory()->create();
    $this->expectException(QueryException::class);

    ProductOnboardingSetting::query()->create([
        'id' => ProductOnboardingSetting::SINGLETON_ID,
        'pass_avatar_id' => $avatar->id,
        'like_avatar_id' => $avatar->id,
    ]);
});
```

- [ ] **Step 2: Verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/ProductOnboardingSchemaTest.php`

Expected: FAIL because the tables, enums, models, and relations do not exist.

- [ ] **Step 3: Implement minimal persistence**

```php
enum ProductOnboardingStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
}

enum ProductOnboardingStep: string
{
    case PassDemo = 'pass_demo';
    case LikeDemo = 'like_demo';
    case MatchDemo = 'match_demo';
    case ConversationDemo = 'conversation_demo';
}
```

Create a unique cascading `user_id`, typed status, nullable typed step, timestamps, singleton ID, two avatar foreign keys, and `CHECK (pass_avatar_id <> like_avatar_id)`. Define `SINGLETON_ID = 1`; `current()` loads both avatars and never invents production configuration.

- [ ] **Step 4: Verify GREEN**

Run the schema test again; expected PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Enums app/Models database/factories database/migrations tests/Feature/ProductOnboardingSchemaTest.php
git commit -m "feat: persist product onboarding progress"
```

---

### Task 2: Implement the locked state machine

**Files:**
- Create: `app/Actions/AdvanceProductOnboarding.php`
- Create: `app/Http/Requests/UpdateProductOnboardingRequest.php`
- Test: `tests/Feature/ProductOnboardingTransitionTest.php`

**Interfaces:**
- Produces `start(User $user, bool $restart = false): ProductOnboarding`.
- Produces `advance(User $user, ProductOnboardingStep $expectedStep): ProductOnboarding`.
- Produces `skip(User $user): ProductOnboarding` and `complete(User $user): ProductOnboarding`.

- [ ] **Step 1: Write failing ordered-transition and isolation tests**

```php
test('the tutorial advances only pass then like then match then conversation', function () {
    $user = memberWithCompleteProfile();
    $action = app(AdvanceProductOnboarding::class);

    expect($action->start($user)->step)->toBe(ProductOnboardingStep::PassDemo)
        ->and($action->advance($user, ProductOnboardingStep::PassDemo)->step)->toBe(ProductOnboardingStep::LikeDemo)
        ->and($action->advance($user, ProductOnboardingStep::LikeDemo)->step)->toBe(ProductOnboardingStep::MatchDemo)
        ->and($action->advance($user, ProductOnboardingStep::MatchDemo)->step)->toBe(ProductOnboardingStep::ConversationDemo)
        ->and($action->complete($user)->status)->toBe(ProductOnboardingStatus::Completed);

    $this->assertDatabaseCount('swipes', 0);
    $this->assertDatabaseCount('matches', 0);
    $this->assertDatabaseCount('conversations', 0);
    $this->assertDatabaseCount('messages', 0);
});
```

Also assert an out-of-order action throws `ValidationException` without changing the row, repeating the immediately preceding transition is idempotent, restart resets to Pass, and skip is terminal for automatic launch.

- [ ] **Step 2: Verify RED**

Run the transition test; expected FAIL because the action is absent.

- [ ] **Step 3: Implement with `DB::transaction` and row locks**

```php
private const NEXT_STEP = [
    'pass_demo' => ProductOnboardingStep::LikeDemo,
    'like_demo' => ProductOnboardingStep::MatchDemo,
    'match_demo' => ProductOnboardingStep::ConversationDemo,
];
```

Lock the user then progress row. Reject mismatches with a localized validation error. `complete()` only accepts `conversation_demo`; `skip()` accepts non-terminal states; restart always writes `in_progress/pass_demo`.

- [ ] **Step 4: Verify GREEN and commit**

Run schema plus transition tests, then:

```bash
git add app/Actions app/Http/Requests tests/Feature/ProductOnboardingTransitionTest.php
git commit -m "feat: add product onboarding state machine"
```

---

### Task 3: Trigger and expose the member tutorial

**Files:**
- Create: `app/Http/Controllers/ProductOnboardingController.php`
- Modify: `app/Http/Controllers/MemberProfileController.php`
- Modify: `app/Http/Controllers/LandingController.php`
- Modify: `routes/web.php`
- Modify: `app/Support/FrontendTranslations.php`
- Modify: `lang/fr/frontend.php`
- Modify: `lang/en/frontend.php`
- Test: `tests/Feature/ProductOnboardingTest.php`

**Interfaces:**
- Produces routes `onboarding.show`, `onboarding.advance`, `onboarding.skip`, `onboarding.restart`, `onboarding.complete`.
- Produces props `status`, `step`, `resumable`, and `demoProfiles`; no real member ID appears.

- [ ] **Step 1: Write failing tests**

```php
test('first profile completion redirects to product onboarding', function () {
    $user = verifiedAdultMember();
    configureDemoAvatars();

    $this->actingAs($user)
        ->post(route('member-profile.store'), validProfilePayload())
        ->assertRedirect(route('onboarding.show'));
});

test('completed and skipped tutorials do not auto launch', function (ProductOnboardingStatus $status) {
    $user = memberWithCompleteProfile();
    ProductOnboarding::factory()->for($user)->create(['status' => $status, 'step' => null]);

    $this->actingAs($user)->get(route('app'))
        ->assertRedirect(route('discovery.index'));
})->with([ProductOnboardingStatus::Completed, ProductOnboardingStatus::Skipped]);
```

Assert the Inertia payload contains only configured avatar presentation and static localized demo fields, without `userId`, match ID, or conversation ID.

- [ ] **Step 2: Verify RED**

Run `tests/Feature/ProductOnboardingTest.php`; expected missing routes/controller failures.

- [ ] **Step 3: Implement routes and controllers**

Capture `$wasComplete` before profile storage and redirect only for the incomplete-to-complete transition. In `LandingController`, route `not_started` and `in_progress` to the tutorial before normal member/admin destinations. Return HTTP 503 with localized copy when valid avatar settings are unavailable. All mutation routes derive the user from authentication and accept no social identifier.

- [ ] **Step 4: Verify GREEN, generate Wayfinder, commit**

Run ProductOnboarding, MemberProfile, and Landing feature tests, then generate routes and commit:

```bash
php artisan wayfinder:generate --with-form
git add app/Http/Controllers app/Support lang routes resources/js/actions resources/js/routes tests/Feature/ProductOnboardingTest.php
git commit -m "feat: trigger onboarding after profile completion"
```

---

### Task 4: Build the isolated accessible tutorial UI

**Files:**
- Create: `resources/js/components/onboarding/DemoSwipeCard.vue`
- Create: `resources/js/components/onboarding/DemoMatch.vue`
- Create: `resources/js/components/onboarding/DemoConversation.vue`
- Create: `resources/js/pages/Onboarding/Show.vue`
- Modify: `resources/js/composables/useMemberNavigationVisibility.ts`
- Test: `tests/Browser/OnboardingTest.php`

**Interfaces:**
- `DemoSwipeCard` accepts `requiredDecision: 'pass' | 'like'` and emits `pass | like`.
- `DemoMatch` emits `open-conversation`.
- `DemoConversation` keeps the fake message in memory and emits `complete`.

- [ ] **Step 1: Write the failing browser journey**

```php
$page->assertSee('Démonstration')
    ->assertMissing('[data-test="member-bottom-navigation"]')
    ->click('[data-test="demo-like"]')
    ->assertSee('Pour commencer, passez cette carte.')
    ->click('[data-test="demo-pass"]')
    ->assertSee('Indiquez maintenant votre intérêt.')
    ->click('[data-test="demo-like"]')
    ->assertSee('Match de démonstration')
    ->click('[data-test="open-demo-conversation"]')
    ->assertSee('Conversation de démonstration');
```

- [ ] **Step 2: Verify RED**

Run the filtered browser test; expected component-not-found failure.

- [ ] **Step 3: Implement minimal components and orchestration**

Reuse the pointer threshold principles from `SwipeCard.vue` without importing social routes. Map ArrowLeft to Pass and ArrowRight to Like. Wrong decisions remain possible but only update an `aria-live` instruction. Watch server step changes and move focus to a `tabindex="-1"` step heading after `nextTick()`. The fake composer appends one local bubble and never imports conversation/message routes.

Update `useMemberNavigationVisibility()` to return false on `/onboarding`, removing both the dock and reserved bottom padding.

- [ ] **Step 4: Add keyboard, pointer, focus, resume, restart, skip, and isolation cases**

After each complete browser journey, assert `swipes`, `matches`, `conversations`, and `messages` remain empty.

- [ ] **Step 5: Verify GREEN and commit**

Run the browser file plus frontend lint, formatting, type checks, and build.

```bash
git add resources/js/components/onboarding resources/js/pages/Onboarding resources/js/composables tests/Browser/OnboardingTest.php
git commit -m "feat: add accessible onboarding journey"
```

---

### Task 5: Add member relaunch settings

**Files:**
- Create: `app/Http/Controllers/Settings/ProductOnboardingController.php`
- Create: `resources/js/pages/settings/Onboarding.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `routes/settings.php`
- Modify: translation catalogs
- Test: `tests/Feature/ProductOnboardingSettingsTest.php`
- Test: `tests/Browser/OnboardingTest.php`

- [ ] **Step 1: Write the failing restart test**

```php
$this->actingAs($user)->post(route('onboarding-settings.restart'))
    ->assertRedirect(route('onboarding.show'));

$this->assertDatabaseHas('product_onboardings', [
    'user_id' => $user->id,
    'status' => 'in_progress',
    'step' => 'pass_demo',
]);
```

- [ ] **Step 2: Verify RED**

Run the settings feature test; expected missing route failure.

- [ ] **Step 3: Implement settings page and routes**

Expose `{ status, step, updatedAt }`, add a translated “Tutoriel” sidebar entry, and restart through `AdvanceProductOnboarding::start($user, restart: true)`.

- [ ] **Step 4: Verify GREEN, generate routes, and commit**

Run settings feature and onboarding browser tests, generate Wayfinder, and commit as `feat: let members relaunch onboarding`.

---

### Task 6: Add admin configuration, protection, stats, and table

**Files:**
- Create: `app/Http/Controllers/Admin/ProductOnboardingController.php`
- Create: `app/Http/Requests/UpdateProductOnboardingSettingRequest.php`
- Create: `resources/js/pages/Admin/Onboarding/Index.vue`
- Modify: `app/Http/Controllers/Admin/AvatarStatusController.php`
- Modify: `app/Http/Controllers/Admin/AvatarController.php`
- Modify: `resources/js/pages/Admin/Avatars/Index.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `routes/web.php`
- Modify: `lang/fr/frontend.php`
- Modify: `lang/en/frontend.php`
- Test: `tests/Feature/Admin/ManageProductOnboardingTest.php`
- Test: `tests/Browser/AdminTest.php`

**Interfaces:**
- Produces routes `admin.onboarding.index` and `admin.onboarding.update`.
- Produces `stats: { not_started, in_progress, completed, skipped, completion_rate }`.
- Produces paginated `members.data[]: { id, display_name, email, status, step, updated_at }`.

- [ ] **Step 1: Write failing config and avatar-protection tests**

```php
$this->actingAs($admin)->patch(route('admin.onboarding.update'), [
    'pass_avatar_id' => $passAvatar->id,
    'like_avatar_id' => $likeAvatar->id,
])->assertRedirect();

$this->actingAs($admin)
    ->patch(route('admin.avatars.status', $passAvatar), ['is_active' => false])
    ->assertSessionHasErrors('avatar');

$this->actingAs($admin)
    ->delete(route('admin.avatars.destroy', $passAvatar))
    ->assertSessionHasErrors('avatar');
```

Assert non-admin denial, active and distinct validation, and unchanged avatar rows after both rejected actions.

- [ ] **Step 2: Write failing stats/pagination tests**

Create eligible users in all statuses plus one with no progress row. Add inactive, unverified, underage, and incomplete users. Assert only eligible members count; missing progress is `not_started`; rate is `completed / eligible * 100`; results are ordered by `COALESCE(product_onboardings.updated_at, users.created_at) DESC, users.id DESC` and paginated by 20.

- [ ] **Step 3: Verify RED**

Run `tests/Feature/Admin/ManageProductOnboardingTest.php`; expected missing controller/routes.

- [ ] **Step 4: Implement backend**

Use these request rules:

```php
'pass_avatar_id' => ['required', 'integer', Rule::exists('avatars', 'id')->where('is_active', true)],
'like_avatar_id' => ['required', 'integer', 'different:pass_avatar_id', Rule::exists('avatars', 'id')->where('is_active', true)],
```

Persist singleton ID 1. Build one eligible-member query (active, adult, verified, complete profile) and clone it for aggregates/table. Left join progress and coalesce absent status. Before archive/delete, lock and reject either configured avatar with:

```php
throw ValidationException::withMessages([
    'avatar' => __('Cet avatar est utilisé par le tutoriel. Remplacez-le dans la configuration avant de continuer.'),
]);
```

- [ ] **Step 5: Implement admin UI**

Render two active-avatar selectors with portraits, five stat cards, and responsive pagination table. Add `used_by_onboarding` to avatar catalog props; visually disable archive/delete for configured avatars while retaining server protection.

- [ ] **Step 6: Verify GREEN**

Run ManageProductOnboarding, ManageAvatarCatalog, and filtered Admin browser tests; generate Wayfinder; run frontend checks.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin app/Http/Requests resources/js/pages/Admin routes lang resources/js/actions resources/js/routes tests
git commit -m "feat: manage and monitor onboarding"
```

---

### Task 7: Seed, document, and verify the complete feature

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `docs/mvp-v1.md`
- Modify: `docs/ux-design.md`
- Modify: `docs/technical-architecture.md`
- Test: `tests/Feature/DatabaseSeederTest.php`

- [ ] **Step 1: Write the failing seed test**

```php
$this->seed();
$settings = ProductOnboardingSetting::current();

expect($settings)->not->toBeNull()
    ->and($settings->pass_avatar_id)->not->toBe($settings->like_avatar_id)
    ->and($settings->passAvatar->is_active)->toBeTrue()
    ->and($settings->likeAvatar->is_active)->toBeTrue();
```

- [ ] **Step 2: Verify RED**

Run the filtered seeder test; expected missing settings row.

- [ ] **Step 3: Seed and document**

After avatar seeding, select the first two active avatars by `sort_order, id` and update singleton settings only when two exist. Document trigger/status behavior, strict fake-data isolation, relaunch, admin protection/stats, and hidden navigation.

- [ ] **Step 4: Run focused verification**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/ProductOnboardingSchemaTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingSettingsTest.php tests/Feature/Admin/ManageProductOnboardingTest.php tests/Feature/Admin/ManageAvatarCatalogTest.php tests/Feature/DatabaseSeederTest.php
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/OnboardingTest.php tests/Browser/AdminTest.php
```

- [ ] **Step 5: Run complete verification**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
git diff --check
```

Expected: fresh passing output for Pint, PHPStan, frontend unit tests, Pest, browser tests, Wayfinder, formatting, TypeScript, Vite, and whitespace validation.

- [ ] **Step 6: Commit**

```bash
git add database/seeders docs tests/Feature/DatabaseSeederTest.php
git commit -m "docs: document interactive onboarding"
```

## Plan Self-Review

- Every validated spec requirement maps to Tasks 1–7.
- Tutorial transitions are structurally isolated from social actions and routes.
- Avatar field names remain `pass_avatar_id` and `like_avatar_id` throughout.
- Missing progress means `not_started`; missing avatar configuration yields explicit unavailability.
- Eligibility is shared between admin statistics and table rows.
- No placeholder or deferred implementation decision remains.
