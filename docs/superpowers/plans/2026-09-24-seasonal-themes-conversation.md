# Seasonal Themes and Conversation Atmosphere Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver administrable Halloween and Christmas themes, including seasonal palettes, decorations, match celebrations, and conversation doodles, while improving message identification and temporal structure.

**Architecture:** Laravel stores one configuration row per supported seasonal theme and resolves the active theme deterministically at request time. Inertia shares the resolved theme and next transition, while one Vue runtime keeps the `<html>` class synchronized without altering the member's light/dark/system preference. CSS semantic-token overrides and focused Vue components provide the visual variants; conversation behavior remains unchanged.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Inertia 3, Vue 3 Composition API, TypeScript 6, Tailwind CSS 4, Bun 1.3.14, Pest, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-24-seasonal-themes-conversation-design.md`

## Global Constraints

- Supported seasonal theme values are exactly `halloween` and `christmas`; no free-form theme editor is introduced.
- Manual activation overrides every schedule until explicitly disabled.
- Scheduled intervals use `starts_at <= now < ends_at` in `config('app.timezone')`; newest start wins, then `christmas` wins an exact tie.
- The member's `light`, `dark`, or `system` preference remains independent and unchanged.
- All visible and accessible copy is translated in the existing French and English Laravel catalogs.
- No Disney or Disneyland Paris character, logo, attraction, illustration, or copied interface asset is introduced.
- The UI works without horizontal scrolling at 320 px, remains keyboard accessible, and honors `prefers-reduced-motion: reduce`.
- Existing realtime messaging, read receipts, typing, blocking, match rules, and navigation behavior remain unchanged.
- Reuse semantic tokens and current UI primitives before adding a component; add no dependency.
- Follow test-first red/green/refactor for every behavior change.

## Review Focus

- A malformed interval with only one boundary or with `ends_at <= starts_at` must be rejected without changing stored configuration; covered in Task 2.
- Simultaneous scheduled intervals with identical starts must always select Christmas, independent of database order; covered in Task 1.
- A browser left open across a transition must update its class once without accumulating timers or forcing a full-page reload; covered in Task 3.
- Theme classes combined with dark mode must preserve foreground/background and focus-ring contrast; covered in Task 4.
- Message timestamps around midnight and daylight-saving boundaries must create separators according to the active locale/time zone, not UTC; covered in Task 7.

---

### Task 1: Seasonal theme schema and deterministic resolver

**Files:**
- Create: `app/Enums/SeasonalThemeName.php`
- Create: `app/Models/SeasonalTheme.php`
- Create: `app/Actions/ResolveActiveSeasonalTheme.php`
- Create: `app/Data/ActiveSeasonalThemeData.php`
- Create: `database/migrations/2026_09_24_000000_create_seasonal_themes_table.php`
- Create: `tests/Feature/SeasonalThemeSchemaTest.php`
- Create: `tests/Unit/ResolveActiveSeasonalThemeTest.php`

**Interfaces:**
- Produces: `SeasonalThemeName: string` enum with `Halloween` and `Christmas` cases and `schedulePriority(): int`.
- Produces: `ActiveSeasonalThemeData::__construct(?SeasonalThemeName $active, ?CarbonImmutable $nextTransitionAt)`.
- Produces: `ResolveActiveSeasonalTheme::handle(?CarbonImmutable $at = null): ActiveSeasonalThemeData`.

- [ ] **Step 1: Write failing schema and resolver tests**

```php
test('the migration creates the two supported seasonal themes', function () {
    expect(SeasonalTheme::query()->orderBy('theme')->pluck('theme')->all())
        ->toBe(['christmas', 'halloween']);
});

test('manual activation overrides scheduled themes', function () {
    CarbonImmutable::setTestNow('2026-12-20 12:00:00 Europe/Paris');
    SeasonalTheme::where('theme', 'halloween')->update(['is_manually_active' => true]);
    SeasonalTheme::where('theme', 'christmas')->update([
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(),
    ]);

    expect(app(ResolveActiveSeasonalTheme::class)->handle()->active)
        ->toBe(SeasonalThemeName::Halloween);
});

test('christmas wins scheduled ties independently of row order', function () {
    $start = CarbonImmutable::parse('2026-12-20 00:00:00', config('app.timezone'));
    SeasonalTheme::query()->update(['starts_at' => $start, 'ends_at' => $start->addDays(10)]);

    expect(app(ResolveActiveSeasonalTheme::class)->handle($start->addDay())->active)
        ->toBe(SeasonalThemeName::Christmas);
});
```

Add these named Pest cases with direct enum/time assertions:

```php
test('returns no theme when no period contains the instant');
test('includes the schedule start and excludes its end');
test('selects the schedule with the newest start during overlap');
test('uses enum priority if corrupted data contains two manual themes');
test('returns the nearest future start or end as the next transition');
```

- [ ] **Step 2: Run the tests and verify the expected red state**

Run: `php artisan test tests/Feature/SeasonalThemeSchemaTest.php tests/Unit/ResolveActiveSeasonalThemeTest.php`

Expected: FAIL because the migration, enum, data object, model, and resolver do not exist.

- [ ] **Step 3: Implement the enum, data object, migration, and model**

```php
enum SeasonalThemeName: string
{
    case Halloween = 'halloween';
    case Christmas = 'christmas';

    public function schedulePriority(): int
    {
        return match ($this) {
            self::Halloween => 1,
            self::Christmas => 2,
        };
    }
}
```

The migration creates `theme` as a unique string, `is_manually_active` as a false boolean, nullable `starts_at`/`ends_at`, timestamps, a check that both dates are null or both non-null with end after start, then inserts both enum values.

Use `#[Fillable]` and `casts()` on `SeasonalTheme` for the enum, boolean, and immutable datetimes.

- [ ] **Step 4: Implement deterministic resolution and transition calculation**

```php
public function handle(?CarbonImmutable $at = null): ActiveSeasonalThemeData
{
    $at ??= CarbonImmutable::now(config('app.timezone'));
    $themes = SeasonalTheme::query()->get();
    $manual = $themes->where('is_manually_active', true)
        ->sortByDesc(fn (SeasonalTheme $theme) => $theme->theme->schedulePriority())
        ->first();
    $scheduled = $themes->filter(fn (SeasonalTheme $theme) =>
        $theme->starts_at !== null && $theme->ends_at !== null
        && $theme->starts_at->lte($at) && $theme->ends_at->gt($at))
        ->sortByDesc(fn (SeasonalTheme $theme) => sprintf(
            '%s-%02d', $theme->starts_at->format('U.u'), $theme->theme->schedulePriority()
        ))->first();

    return new ActiveSeasonalThemeData(
        $manual?->theme ?? $scheduled?->theme,
        $manual === null ? $this->nextTransition($themes, $at) : null,
    );
}
```

Keep `nextTransition()` private and select the minimum future `starts_at` or `ends_at`; ignore null and past boundaries.

- [ ] **Step 5: Run focused tests and static analysis**

Run: `php artisan test tests/Feature/SeasonalThemeSchemaTest.php tests/Unit/ResolveActiveSeasonalThemeTest.php`

Expected: PASS.

Run: `composer analyse`

Expected: PASS with no new PHPStan errors.

- [ ] **Step 6: Commit**

```bash
git add app/Enums/SeasonalThemeName.php app/Models/SeasonalTheme.php app/Actions/ResolveActiveSeasonalTheme.php app/Data/ActiveSeasonalThemeData.php database/migrations/2026_09_24_000000_create_seasonal_themes_table.php tests/Feature/SeasonalThemeSchemaTest.php tests/Unit/ResolveActiveSeasonalThemeTest.php
git commit -m "feat(theme): resolve seasonal themes"
```

### Task 2: Authorized administration actions and validation

**Files:**
- Create: `app/Actions/SetManualSeasonalTheme.php`
- Create: `app/Http/Controllers/Admin/SeasonalThemeController.php`
- Create: `app/Http/Controllers/Admin/SeasonalThemeActivationController.php`
- Create: `app/Http/Requests/Admin/UpdateSeasonalThemeRequest.php`
- Create: `app/Policies/SeasonalThemePolicy.php`
- Create: `tests/Feature/Admin/ManageSeasonalThemesTest.php`
- Modify: `routes/web.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`

**Interfaces:**
- Consumes: `SeasonalThemeName`, `SeasonalTheme`, and `ResolveActiveSeasonalTheme::handle()` from Task 1.
- Produces: routes `admin.seasonal-themes.index`, `.update`, `.activate`, and `.deactivate`.
- Produces: `SetManualSeasonalTheme::handle(?SeasonalThemeName $theme): void`.

- [ ] **Step 1: Write failing Feature tests for authorization, validation, and exclusivity**

```php
public function test_admin_programs_a_theme_in_the_application_timezone(): void
{
    $admin = User::factory()->withProfile()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.seasonal-themes.update', 'halloween'), [
        'starts_at' => '2026-10-20T18:00',
        'ends_at' => '2026-11-02T08:00',
    ])->assertRedirect();

    $theme = SeasonalTheme::where('theme', 'halloween')->firstOrFail();
    expect($theme->starts_at->timezone(config('app.timezone'))->format('Y-m-d\TH:i'))
        ->toBe('2026-10-20T18:00');
}

public function test_activating_one_theme_deactivates_the_other(): void
{
    $admin = User::factory()->withProfile()->admin()->create();
    SeasonalTheme::where('theme', 'halloween')->update(['is_manually_active' => true]);

    $this->actingAs($admin)->post(route('admin.seasonal-themes.activate', 'christmas'))
        ->assertRedirect();

    expect(SeasonalTheme::where('theme', 'halloween')->value('is_manually_active'))->toBeFalse()
        ->and(SeasonalTheme::where('theme', 'christmas')->value('is_manually_active'))->toBeTrue();
}
```

Add these concrete cases and assert the response plus database state in each:

```php
public function test_guest_is_redirected_and_member_is_forbidden(): void;
public function test_unknown_theme_returns_not_found(): void;
public function test_schedule_requires_both_boundaries(): void;
public function test_schedule_end_must_be_after_start(): void;
public function test_invalid_schedule_does_not_change_persisted_dates(): void;
public function test_admin_can_disable_the_manual_theme(): void;
```

- [ ] **Step 2: Run the Feature test and verify it fails**

Run: `php artisan test tests/Feature/Admin/ManageSeasonalThemesTest.php`

Expected: FAIL because routes and handlers do not exist.

- [ ] **Step 3: Implement routes, Policy, and Form Request**

```php
public function rules(): array
{
    return [
        'starts_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'required_with:ends_at'],
        'ends_at' => ['nullable', 'date_format:Y-m-d\\TH:i', 'required_with:starts_at', 'after:starts_at'],
    ];
}
```

Resolve `{seasonalTheme}` by enum value/model binding, authorize with `SeasonalThemePolicy::update`, parse submitted values in `config('app.timezone')`, and store UTC instants. Register controller routes inside the existing `role:admin` group.

- [ ] **Step 4: Implement transactional manual activation**

```php
public function handle(?SeasonalThemeName $theme): void
{
    DB::transaction(function () use ($theme): void {
        SeasonalTheme::query()->lockForUpdate()->get();
        SeasonalTheme::query()->update(['is_manually_active' => false]);

        if ($theme !== null) {
            SeasonalTheme::where('theme', $theme)->update(['is_manually_active' => true]);
        }
    });
}
```

Return localized success toasts for schedule save, manual activation, and manual deactivation.

- [ ] **Step 5: Run the focused test and lint**

Run: `php artisan test tests/Feature/Admin/ManageSeasonalThemesTest.php`

Expected: PASS.

Run: `composer lint:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/SetManualSeasonalTheme.php app/Http/Controllers/Admin/SeasonalThemeController.php app/Http/Controllers/Admin/SeasonalThemeActivationController.php app/Http/Requests/Admin/UpdateSeasonalThemeRequest.php app/Policies/SeasonalThemePolicy.php routes/web.php lang/fr/administration.php lang/en/administration.php tests/Feature/Admin/ManageSeasonalThemesTest.php
git commit -m "feat(theme): administrer les thèmes saisonniers"
```

### Task 3: Inertia theme delivery and automatic browser transitions

**Files:**
- Create: `resources/js/types/seasonalTheme.ts`
- Create: `resources/js/lib/seasonalTheme.ts`
- Create: `resources/js/composables/useSeasonalTheme.ts`
- Create: `tests/Frontend/seasonalTheme.test.js`
- Create: `tests/Feature/SeasonalThemeInertiaTest.php`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/types/global.d.ts`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/app.ts`

**Interfaces:**
- Consumes: `ResolveActiveSeasonalTheme::handle()` from Task 1.
- Produces: `SeasonalThemeName = 'halloween' | 'christmas'` and `SeasonalThemeState`.
- Produces: `seasonalThemeClass(active): string | null`, `millisecondsUntilTransition(iso, now): number | null`, and `initializeSeasonalTheme(): void`.

- [ ] **Step 1: Write failing PHP and Bun tests**

```js
test('returns the class for each active theme', () => {
    expect(seasonalThemeClass('halloween')).toBe('seasonal-halloween');
    expect(seasonalThemeClass('christmas')).toBe('seasonal-christmas');
    expect(seasonalThemeClass(null)).toBeNull();
});

test('bounds long transition delays to the browser timeout maximum', () => {
    expect(millisecondsUntilTransition('2027-12-01T00:00:00Z', Date.parse('2026-01-01T00:00:00Z')))
        .toBe(2_147_000_000);
});
```

The Feature test freezes time, configures a period, requests an Inertia page, and asserts `seasonalTheme.active` plus `nextTransitionAt`.

- [ ] **Step 2: Run tests and verify the red state**

Run: `bun test tests/Frontend/seasonalTheme.test.js`

Run: `php artisan test tests/Feature/SeasonalThemeInertiaTest.php`

Expected: both FAIL because the shared state and frontend helpers do not exist.

- [ ] **Step 3: Implement shared prop and first-render class**

```php
'seasonalTheme' => function () use ($resolver): array {
    $theme = $resolver->handle();

    return [
        'active' => $theme->active?->value,
        'nextTransitionAt' => $theme->nextTransitionAt?->toIso8601String(),
    ];
},
```

Add the matching typed prop and apply `seasonal-{active}` in the Blade `<html>` class list from `$page['props']['seasonalTheme']['active']`.

- [ ] **Step 4: Implement the single-timer runtime**

```ts
export function syncSeasonalThemeClass(active: SeasonalThemeName | null): void {
    document.documentElement.classList.remove('seasonal-halloween', 'seasonal-christmas');
    const className = seasonalThemeClass(active);
    if (className !== null) document.documentElement.classList.add(className);
}
```

`initializeSeasonalTheme()` subscribes once to Inertia `navigate`, clears the previous timeout, syncs the class, and schedules `router.reload({ only: ['seasonalTheme'], preserveScroll: true, preserveState: true })`. Bound each timeout to `2_147_000_000` ms and reschedule rather than reload when the real transition is still future.

- [ ] **Step 5: Re-run focused tests and type checking**

Run: `bun test tests/Frontend/seasonalTheme.test.js && php artisan test tests/Feature/SeasonalThemeInertiaTest.php && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/types/seasonalTheme.ts resources/js/types/index.ts resources/js/types/global.d.ts resources/js/lib/seasonalTheme.ts resources/js/composables/useSeasonalTheme.ts resources/js/app.ts resources/views/app.blade.php app/Http/Middleware/HandleInertiaRequests.php tests/Frontend/seasonalTheme.test.js tests/Feature/SeasonalThemeInertiaTest.php
git commit -m "feat(theme): synchroniser le thème saisonnier"
```

### Task 4: Accessible seasonal palettes and original SVG decorations

**Files:**
- Create: `resources/js/components/seasonal/SeasonalDecorations.vue`
- Modify: `resources/css/app.css`
- Modify: `tests/Browser/AppearanceTest.php`

**Interfaces:**
- Consumes: root classes `seasonal-halloween`, `seasonal-christmas`, and `.dark` from Task 3.
- Produces: semantic token overrides, `SeasonalDecorations` selected from the active shared prop, and test hooks `seasonal-decoration-halloween|christmas`.

- [ ] **Step 1: Add failing browser assertions for palettes and motion reduction**

Add helpers that set a seasonal root class and assert WCAG contrast for `background/foreground`, `card/card-foreground`, `primary/primary-foreground`, `secondary/secondary-foreground`, and `ring/background` in both light and dark modes. Assert each seasonal class changes `primary` from the standard palette and that decorative SVGs are `aria-hidden`, non-focusable, and statically visible with reduced motion.

- [ ] **Step 2: Run targeted browser tests and confirm failure**

Run: `php artisan test tests/Browser/AppearanceTest.php --filter='seasonal'`

Expected: FAIL because seasonal variables and decorations do not exist.

- [ ] **Step 3: Implement theme token overrides**

```css
.seasonal-halloween {
    --background: hsl(34 55% 96%);
    --foreground: hsl(275 32% 13%);
    --primary: hsl(24 88% 43%);
    --primary-foreground: hsl(36 100% 98%);
    --ring: hsl(274 60% 42%);
}

.dark.seasonal-halloween {
    --background: hsl(276 28% 8%);
    --foreground: hsl(35 65% 94%);
    --card: hsl(276 24% 12%);
    --card-foreground: hsl(35 65% 94%);
    --primary: hsl(28 95% 64%);
    --primary-foreground: hsl(278 38% 10%);
    --ring: hsl(34 96% 68%);
}

.seasonal-christmas {
    --background: hsl(44 52% 96%);
    --foreground: hsl(151 38% 12%);
    --card: hsl(42 45% 99%);
    --card-foreground: hsl(151 38% 12%);
    --primary: hsl(351 72% 38%);
    --primary-foreground: hsl(42 100% 98%);
    --ring: hsl(148 48% 31%);
}

.dark.seasonal-christmas {
    --background: hsl(151 32% 7%);
    --foreground: hsl(42 44% 94%);
    --card: hsl(151 27% 11%);
    --card-foreground: hsl(42 44% 94%);
    --primary: hsl(352 74% 68%);
    --primary-foreground: hsl(151 38% 9%);
    --ring: hsl(44 72% 62%);
}
```

Define every token overridden as a coherent set, including card, popover, secondary, accent, muted, destructive, border, input, ring, and sidebar equivalents. Verify actual browser-computed contrast rather than trusting the HSL values.

- [ ] **Step 4: Implement original inline decorations**

Create separate inline path groups for Halloween (moon, abstract bat, leaf, geometric pumpkin) and Christmas (snowflake, star, branch, abstract bauble). Select by `usePage().props.seasonalTheme.active`; add `aria-hidden="true"`, `focusable="false"`, `pointer-events-none`, and no external `<use>` reference.

- [ ] **Step 5: Verify browser test, reduced motion, and formatting**

Run: `php artisan test tests/Browser/AppearanceTest.php --filter='seasonal'`

Run: `bun run format:check && bun run lint:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/css/app.css resources/js/components/seasonal/SeasonalDecorations.vue tests/Browser/AppearanceTest.php
git commit -m "feat(theme): ajouter les palettes saisonnières"
```

### Task 5: Seasonal theme administration page

**Files:**
- Create: `resources/js/pages/Admin/SeasonalThemes/Index.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/types/seasonalTheme.ts`
- Modify: `tests/Browser/AdminTest.php`
- Regenerate: `resources/js/routes/admin/seasonal-themes/**`

**Interfaces:**
- Consumes: Task 2 routes and Task 3 types.
- Produces: admin cards with schedule forms, previews, activation controls, and `data-test="seasonal-theme-{theme}"`.

- [ ] **Step 1: Add a failing browser journey**

```php
test('admin schedules activates and disables seasonal themes', function () {
    $admin = User::factory()->withProfile()->admin()->create();
    $this->actingAs($admin);

    visit('/admin/seasonal-themes')
        ->assertSee('Thèmes saisonniers')
        ->assertPresent('[data-test="seasonal-theme-halloween"]')
        ->assertPresent('[data-test="seasonal-theme-christmas"]')
        ->type('#halloween-starts-at', '2026-10-20T18:00')
        ->type('#halloween-ends-at', '2026-11-02T08:00')
        ->press('Enregistrer la période Halloween')
        ->assertSee('Période enregistrée.')
        ->press('Activer Halloween maintenant')
        ->assertSee('Halloween est actif manuellement.')
        ->press('Désactiver le thème manuel')
        ->assertNoJavaScriptErrors();
});
```

Also visit at mobile size, tab through controls, and assert no horizontal overflow.

- [ ] **Step 2: Run the browser test and verify failure**

Run: `php artisan test tests/Browser/AdminTest.php --filter='seasonal themes'`

Expected: FAIL because the page and navigation item do not exist.

- [ ] **Step 3: Build the page with existing Card, Input, Button, Badge, and Alert primitives**

Use one form per schedule and separate generated route forms for activation/deactivation. Display the configured application timezone, explicit manual-priority explanation, current source, local dates, and light/dark previews. Keep all copy in `administration.php` and use `useTranslations()`.

- [ ] **Step 4: Add navigation and regenerate Wayfinder**

Run: `php artisan wayfinder:generate --with-form`

Import the generated seasonal route helpers into `AppSidebar.vue` and add a translated `Sparkles` navigation entry for admins.

- [ ] **Step 5: Verify the journey and frontend checks**

Run: `php artisan test tests/Browser/AdminTest.php --filter='seasonal themes'`

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Admin/SeasonalThemes/Index.vue resources/js/components/AppSidebar.vue resources/js/types/seasonalTheme.ts resources/js/routes/admin/seasonal-themes tests/Browser/AdminTest.php
git commit -m "feat(theme): ajouter la gestion saisonnière admin"
```

### Task 6: Theme-specific match celebrations

**Files:**
- Create: `resources/js/components/discovery/MatchCelebration.vue`
- Modify: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/css/app.css`
- Modify: `tests/Browser/DiscoveryTest.php`

**Interfaces:**
- Consumes: active theme prop and `SeasonalDecorations` from Tasks 3–4.
- Produces: `MatchCelebration` with `data-test="match-celebration-{standard|halloween|christmas}"`.

- [ ] **Step 1: Add failing browser assertions for all three variants**

For each active value (`null`, `halloween`, `christmas`), create a reciprocal match and assert the corresponding hook exists, the other two do not, the layer equals the viewport, the dialog buttons remain usable, and reduced motion yields `animationName === 'none'` on seasonal particles.

- [ ] **Step 2: Run the targeted tests and verify failure**

Run: `php artisan test tests/Browser/DiscoveryTest.php --filter='match celebration'`

Expected: FAIL because the celebration does not expose seasonal variants.

- [ ] **Step 3: Extract and implement the celebration component**

Move the existing standard halo/firework/jewel layer unchanged into `MatchCelebration.vue`. Add Halloween moon, abstract bats, and leaf particles; add Christmas central star, snowflakes, and red/green/gold sparkles. Render only the active variant and keep the whole layer `aria-hidden` and pointer-events disabled.

- [ ] **Step 4: Add transform/opacity-only animation utilities and reduction rules**

Define named seasonal keyframes and utilities in `app.css`; include every new utility in the existing reduced-motion selector with `animation: none` and `transition-duration: 0ms`. Keep the dialog content outside the decorative layer.

- [ ] **Step 5: Verify targeted browser tests and frontend checks**

Run: `php artisan test tests/Browser/DiscoveryTest.php --filter='match celebration'`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/discovery/MatchCelebration.vue resources/js/components/discovery/MatchDialog.vue resources/css/app.css tests/Browser/DiscoveryTest.php
git commit -m "feat(theme): décliner la célébration de match"
```

### Task 7: Conversation Lucide doodles, sender labels, and day separators

**Files:**
- Create: `resources/js/lib/conversationTimeline.ts`
- Create: `tests/Frontend/conversationTimeline.test.js`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `resources/js/components/conversations/MessageTimeline.vue`
- Modify: `resources/js/components/conversations/MessageComposer.vue`
- Modify: `resources/css/app.css`
- Modify: `lang/fr/conversations.php`
- Modify: `lang/en/conversations.php`
- Modify: `tests/Browser/ConversationTest.php`

**Interfaces:**
- Produces: `conversationDayKey(iso, locale, timeZone): string`, `shouldShowDaySeparator(previous, current, locale, timeZone): boolean`, and `conversationDayLabel(iso, now, locale, timeZone, labels): string`.
- `MessageTimeline` adds required prop `participantName: string` and continues to consume existing message/current-user props.

- [ ] **Step 1: Write failing timeline utility tests**

```js
test('groups timestamps by the configured timezone rather than UTC', () => {
    expect(conversationDayKey('2026-10-25T23:30:00Z', 'fr', 'Europe/Paris'))
        .toBe('2026-10-26');
});

test('labels today yesterday and older dates in the active locale', () => {
    const now = new Date('2026-09-24T12:00:00+02:00');
    expect(conversationDayLabel('2026-09-24T08:00:00Z', now, 'fr', 'Europe/Paris', labels))
        .toBe("Aujourd’hui");
    expect(conversationDayLabel('2026-09-23T08:00:00Z', now, 'fr', 'Europe/Paris', labels))
        .toBe('Hier');
});
```

Cover DST fall-back, two messages on the same local day, and an older localized date.

- [ ] **Step 2: Run the utility test and verify failure**

Run: `bun test tests/Frontend/conversationTimeline.test.js`

Expected: FAIL because the utility does not exist.

- [ ] **Step 3: Implement pure localized timeline helpers**

Use `Intl.DateTimeFormat(...).formatToParts()` to build stable local `YYYY-MM-DD` keys; do not compare UTC date substrings. Compute today/yesterday keys through the same formatter, and use a locale-aware short date for older messages.

- [ ] **Step 4: Add failing browser coverage for the conversation composition**

Add messages on two days, including a 2,000-character unbroken string. Assert visible `Vous`, participant display name, day labels, `data-test="conversation-pattern"`, the correct seasonal pattern value for standard/Halloween/Christmas, readable error/loading/empty states, no horizontal overflow at 320 px, keyboard-send behavior, and no JavaScript errors in light and dark modes.

- [ ] **Step 5: Run targeted browser tests and verify failure**

Run: `php artisan test tests/Browser/ConversationTest.php --filter='conversation atmosphere'`

Expected: FAIL because sender labels, day separators, and pattern tokens do not exist.

- [ ] **Step 6: Implement timeline markup and composer harmony**

Pass `participant.display_name` from `Show.vue`. Render a separator before the first message of each local day. Render a visible sender label above the first message in each consecutive sender group. Preserve `role="log"`, InfiniteScroll, read receipt, live announcement, current animation, and scroll behavior. Add `min-w-0`, `overflow-wrap:anywhere`, and mobile max widths so 2,000-character content cannot overflow.

- [ ] **Step 7: Implement theme-aware Lucide conversation patterns**

Compose the standard, Halloween, and Christmas patterns with decorative components from the existing Lucide library. Adapt their semantic color and opacity in light and dark modes, keep message content above the pattern, and ensure pattern opacity is independent from bubble opacity. Do not add inline or encoded custom SVGs.

- [ ] **Step 8: Verify utilities, browser behavior, and frontend checks**

Run: `bun test tests/Frontend/conversationTimeline.test.js`

Run: `php artisan test tests/Browser/ConversationTest.php --filter='conversation atmosphere'`

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add resources/js/lib/conversationTimeline.ts tests/Frontend/conversationTimeline.test.js resources/js/pages/Conversations/Show.vue resources/js/components/conversations/MessageTimeline.vue resources/js/components/conversations/MessageComposer.vue resources/css/app.css lang/fr/conversations.php lang/en/conversations.php tests/Browser/ConversationTest.php
git commit -m "feat(messaging): enrichir l’ambiance des échanges"
```

### Task 8: Product and technical documentation

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/design-system.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/data-model.md`
- Modify: `docs/documentation-inventory.md`

**Interfaces:**
- Consumes: actual implementation and verification evidence from Tasks 1–7.
- Produces: current-state documentation matching shipped behavior.

- [ ] **Step 1: Update the design-system contract**

Document exact theme names, manual/schedule priority, light/dark palette intent, seasonal semantic-token rule, Lucide/IP constraints, six conversation pattern appearances, match celebration variants, and reduced-motion behavior.

- [ ] **Step 2: Update product and implementation status**

Mark seasonal themes and enriched conversation atmosphere implemented only after their tests pass. Describe that seasonal themes augment rather than replace appearance preferences.

- [ ] **Step 3: Update architecture, data model, and inventory**

Document `seasonal_themes`, the resolver interface, request-time resolution, Inertia `seasonalTheme` prop, next-transition timer, admin routes, and test evidence. Explicitly state that no scheduler is involved.

- [ ] **Step 4: Check documentation consistency**

Run: `rg -n "Planifié|seasonal|saisonnier|Halloween|Noël|conversation" docs/PRD.md docs/design-system.md docs/technical-architecture.md docs/data-model.md docs/documentation-inventory.md`

Expected: descriptions agree on priority, supported values, automatic transition mechanism, and implementation status.

Run: `git diff --check`

Expected: no whitespace errors.

- [ ] **Step 5: Commit**

```bash
git add docs/PRD.md docs/design-system.md docs/technical-architecture.md docs/data-model.md docs/documentation-inventory.md
git commit -m "docs: documenter les thèmes saisonniers"
```

### Task 9: Full verification and issue acceptance audit

**Files:**
- Modify only files required to fix failures found by the commands below.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: fresh evidence for every acceptance criterion in issues #185 and #189.

- [ ] **Step 1: Run complete frontend checks**

Run: `php artisan wayfinder:generate --with-form && bun run test:unit && bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: every command exits 0 with no lint, format, type, test, or build errors.

- [ ] **Step 2: Run complete backend checks**

Run: `composer lint:check && composer analyse && composer test`

Expected: every command exits 0 with zero failed tests.

- [ ] **Step 3: Run focused browser suites with fresh output**

Run: `php artisan test tests/Browser/AppearanceTest.php tests/Browser/AdminTest.php tests/Browser/ConversationTest.php tests/Browser/DiscoveryTest.php`

Expected: zero failures and no reported JavaScript errors.

- [ ] **Step 4: Perform visual review matrix**

Start the documented local stack and inspect the admin page, match dialog, and a populated/empty/error conversation at 320 px and desktop width for:

```text
standard light | standard dark
Halloween light | Halloween dark
Christmas light | Christmas dark
reduced motion for each match celebration
```

Capture screenshots under `artifacts/issue-185-189/` and confirm no horizontal overflow, obscured focus, unreadable text, distracting pattern, copied/protected imagery, or unusable control.

- [ ] **Step 5: Audit acceptance criteria and repository state**

Run: `git diff --check && git status --short && git log --oneline origin/main..HEAD`

Check each checkbox from issues #185 and #189 against a named test, screenshot, or documented implementation. Preserve pre-existing untracked `.superpowers/` and `artifacts/` content not created by this work.

- [ ] **Step 6: Re-run the owning check after every verification correction**

```bash
git status --short
git diff --check
```

Stage only paths named by `git status --short` that belong to issues #185 and
#189, commit them with `fix: finaliser les thèmes saisonniers`, and do not
create an empty commit when no correction was needed.
