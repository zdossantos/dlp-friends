# Interaction Feedback Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver instant optimistic discovery cards, expressive magical feedback for likes and matches, and restrained accessible feedback for messaging, forms, and navigation.

**Architecture:** Shared CSS motion tokens and a small reduced-motion utility define the visual language without a new animation dependency. Existing Vue components continue to own their asynchronous state; discovery adds one explicit optimistic-card state so the server remains authoritative and failures can restore the removed card.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS 4, Bun test, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-31-interaction-feedback-design.md`

## Global Constraints

- Do not add an animation library or protected third-party visual assets.
- Use only `transform`, `opacity`, shadows, and overlaid pseudo-elements for movement.
- Functional feedback should last 100–200 ms; expressive non-blocking feedback must remain at or below about 500 ms.
- Never delay a business action until an animation finishes.
- With `prefers-reduced-motion: reduce`, remove displacement, rotation, shimmer, pulse, halo animation, and particles while preserving all state changes.
- Every asynchronous action must lock immediately, recover after failure, and preserve user input until confirmed success.
- All visible copy must come from the existing French and English translation catalogs.
- Follow red-green-refactor and observe every new behavior test fail for the intended reason before production changes.

---

### Task 1: Shared motion foundation

**Files:**
- Create: `resources/js/lib/motion.ts`
- Modify: `resources/css/app.css`
- Modify: `docs/design-system.md`
- Test: `tests/Frontend/motion.test.js`

**Interfaces:**
- Produces: `prefersReducedMotion(media?: Pick<MediaQueryList, 'matches'>): boolean`.
- Produces: CSS custom properties `--motion-duration-instant`, `--motion-duration-feedback`, `--motion-duration-expressive`, `--motion-ease-standard`, and `--motion-ease-expressive`.
- Produces: reusable classes `motion-feedback-enter`, `motion-message-enter`, and `motion-magic-particle` whose motion is disabled by the reduced-motion media query.

- [ ] **Step 1: Write the failing reduced-motion unit tests**

```js
import { describe, expect, test } from 'bun:test';
import { prefersReducedMotion } from '../../resources/js/lib/motion';

describe('prefersReducedMotion', () => {
    test('reports the supplied reduced-motion preference', () => {
        expect(prefersReducedMotion({ matches: true })).toBe(true);
        expect(prefersReducedMotion({ matches: false })).toBe(false);
    });

    test('falls back safely when matchMedia is unavailable', () => {
        expect(prefersReducedMotion()).toBe(false);
    });
});
```

- [ ] **Step 2: Run the test and verify RED**

Run: `bun test tests/Frontend/motion.test.js`

Expected: FAIL because `resources/js/lib/motion.ts` does not exist.

- [ ] **Step 3: Implement the minimal preference utility**

```ts
export function prefersReducedMotion(
    media?: Pick<MediaQueryList, 'matches'>,
): boolean {
    if (media) {
        return media.matches;
    }

    return typeof window !== 'undefined' && typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
        : false;
}
```

- [ ] **Step 4: Add semantic motion tokens and keyframes**

Add root properties for `0ms`, `160ms`, and `480ms`, plus standard and expressive easing curves. Define opacity/translate entry, message glow entry, and a lightweight particle keyframe. Under `@media (prefers-reduced-motion: reduce)`, set all duration tokens to `0ms` and force the reusable motion classes to `animation: none` and `transition-duration: 0ms`.

- [ ] **Step 5: Document the two motion levels**

Add a “Mouvement et retours” section to `docs/design-system.md` naming the discrete and expressive levels, their durations, allowed properties, and reduced-motion behavior.

- [ ] **Step 6: Run targeted checks and verify GREEN**

Run: `bun test tests/Frontend/motion.test.js && bun run types:check && git diff --check`

Expected: all commands pass with no warnings.

- [ ] **Step 7: Commit**

```bash
git add resources/js/lib/motion.ts resources/css/app.css docs/design-system.md tests/Frontend/motion.test.js
git commit -m "feat(ui): define shared motion language"
```

### Task 2: Instant optimistic discovery decisions

**Files:**
- Modify: `resources/js/pages/Discovery/Index.vue`
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Modify: `lang/fr/discovery.php`
- Modify: `lang/en/discovery.php`
- Test: `tests/Browser/DiscoveryTest.php`

**Interfaces:**
- Consumes: `prefersReducedMotion()` and the CSS motion tokens from Task 1.
- Produces: page-local `optimisticSuggestions: Ref<DiscoveryProfile[]>` and `pendingDecision: Ref<{ profile: DiscoveryProfile; decision: SwipeDecision } | null>`.
- Preserves: `submit(decision, targetUserId?)`, `retry()`, server `suggestions`, and server `match` props.

- [ ] **Step 1: Add a failing browser test for immediate removal and double-action locking**

Create a delayed XHR interceptor for `/discover/*`, click the like button, and assert before releasing the request that the first member name has disappeared, the second member name is active, and a second decision has not produced another request.

```php
$page->script("document.querySelector('[aria-label=\"Découvrir ce profil\"]').click()");
$page->assertDontSee('Basile')
    ->assertSee('Chloé')
    ->assertScript('window.__swipeRequestCount', 1);
```

- [ ] **Step 2: Run the optimistic test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php --filter='optimistically' --display-warnings`

Expected: FAIL because Basile remains active until the delayed response returns.

- [ ] **Step 3: Implement the minimal optimistic list state**

Initialize a local copy from `props.suggestions`, watch successful prop refreshes, and change `activeSuggestion` plus the card loop to use the local list. On submit, assign `pendingDecision`, remove the first card synchronously, then call `router.post`. Keep `isSubmitting` true so the newly exposed card cannot issue another decision until the first response finishes.

- [ ] **Step 4: Add a failing browser test for rollback**

Intercept the swipe response with a network failure, click like, observe the next card immediately, then assert that the original card returns with the destructive alert and retry control.

```php
$page->assertSee('Chloé')
    ->assertSee('Basile')
    ->assertPresent('[role="alert"]')
    ->assertPresent('[aria-label="Réessayer"]');
```

- [ ] **Step 5: Run the rollback test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php --filter='rolls back' --display-warnings`

Expected: FAIL because the removed profile is not restored locally.

- [ ] **Step 6: Implement rollback and retry**

On `onError`, `onHttpException`, or `onNetworkError`, prepend `pendingDecision.profile` only if its user id is absent, retain the failed decision for `retry()`, and clear the network lock in `onFinish`. On success, clear the retained decision and synchronize from the server props. Do not clear the visible error until a retry begins or the authoritative server list advances.

- [ ] **Step 7: Add expressive swipe layers without changing request timing**

Render `aria-hidden` overlay elements for a like sparkle burst and pass trail. Drive the decision through the existing `exitDirection` state immediately, but emit the business event synchronously; use animation completion only to remove decorative state. Replace the current request-delaying `SWIPE_EXIT_DURATION_MS` timeout with `emit('like')` or `emit('pass')` in the same call stack.

- [ ] **Step 8: Verify discovery including reduced motion**

Add an assertion using `page.emulateMedia({ reducedMotion: 'reduce' })` or the Pest Browser equivalent: after like, the first card disappears immediately and computed animation durations on sparkle elements are `0s` or the elements are absent.

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/DiscoveryTest.php --display-warnings`

Expected: all discovery tests pass with no JavaScript errors.

- [ ] **Step 9: Commit**

```bash
git add resources/js/pages/Discovery/Index.vue resources/js/components/discovery/SwipeCard.vue lang/fr/discovery.php lang/en/discovery.php tests/Browser/DiscoveryTest.php
git commit -m "feat(discovery): make card decisions optimistic"
```

### Task 3: Expressive accessible match moment

**Files:**
- Modify: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/css/app.css`
- Test: `tests/Browser/DiscoveryTest.php`
- Test: `tests/Browser/OnboardingTest.php`

**Interfaces:**
- Consumes: expressive CSS tokens from Task 1 and the existing `open`, `match`, `conversationHref`, `dismissible`, and `locked` props.
- Produces: decorative nodes marked `data-test="match-magic"` and `aria-hidden="true"`.
- Preserves: immediate dialog focus, dismiss behavior, and conversation navigation.

- [ ] **Step 1: Write a failing browser test for the match presentation**

Extend the reciprocal-like path to assert the accessible heading remains focused, the decorative layer exists and is hidden, and the conversation link is clickable immediately.

```php
$page->assertPresent('[data-test="match-magic"][aria-hidden="true"]')
    ->assertScript('document.activeElement.dataset.test', 'match-heading')
    ->assertPresent('[data-test="open-match-conversation"]:not([disabled])');
```

- [ ] **Step 2: Run the match test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php --filter='match' --display-warnings`

Expected: FAIL because `data-test="match-magic"` does not exist.

- [ ] **Step 3: Implement the magical match composition**

Add an overflow-hidden relative content surface, an animated gold halo, six bounded CSS sparkle elements, and two abstract avatar-gradient circles that translate toward the center. All decorations must use `aria-hidden="true"`, `pointer-events-none`, and absolute positioning. Keep the existing title, description, footer, and events unchanged.

- [ ] **Step 4: Cover reduced motion**

Add a browser assertion that the match content still opens and focuses its heading under reduced motion while the decorative nodes have no active animation.

- [ ] **Step 5: Run match and onboarding tests and verify GREEN**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php --display-warnings`

Expected: both files pass with no JavaScript errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/discovery/MatchDialog.vue resources/css/app.css tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php
git commit -m "feat(discovery): animate the match moment"
```

### Task 4: Stable message sending feedback

**Files:**
- Modify: `resources/js/components/conversations/MessageComposer.vue`
- Modify: `resources/js/components/conversations/MessageTimeline.vue`
- Modify: `resources/js/components/ui/spinner/Spinner.vue`
- Test: `tests/Browser/ConversationTest.php`

**Interfaces:**
- Consumes: `motion-message-enter` from Task 1.
- Produces: `aria-busy="true"` on the composer form while sending and `data-pending="true"` on its send button.
- Preserves: content until a successful response, focus restoration, realtime merging, scroll anchoring, and existing `aria-live` announcements.

- [ ] **Step 1: Write a failing browser test for pending state and preserved failure input**

Delay a message POST, submit `Bonsoir depuis Main Street`, and assert the form is busy, the button is disabled, and the textarea still contains the message. Release a failing response and assert the same content remains editable with `role="alert"` visible.

- [ ] **Step 2: Run the message feedback test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ConversationTest.php --filter='preserves message' --display-warnings`

Expected: FAIL because the form does not expose `aria-busy` and the button has no pending marker.

- [ ] **Step 3: Implement stable pending feedback**

Bind `:aria-busy="pending"` on the form, `:data-pending="pending"` on the button, and render `Spinner` in place of the send icon while pending. Keep button dimensions fixed at `size-11`. Do not change the existing rule that clears `content` only after `props.onSent` succeeds.

- [ ] **Step 4: Write a failing test for message entry feedback**

Send a successful message and assert its list item has `data-new-message="true"` during initial insertion and uses the message-entry class without changing the scroll container geometry.

- [ ] **Step 5: Implement bounded message entry state**

Track the latest inserted message id in `MessageTimeline.vue`, mark only that item, and remove the marker after the expressive duration or immediately under reduced motion. Keep the existing message array, deduplication, and scroll logic unchanged.

- [ ] **Step 6: Run conversation tests and verify GREEN**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/ConversationTest.php --display-warnings`

Expected: all conversation tests pass with no JavaScript errors.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/conversations/MessageComposer.vue resources/js/components/conversations/MessageTimeline.vue resources/js/components/ui/spinner/Spinner.vue tests/Browser/ConversationTest.php
git commit -m "feat(conversations): clarify message sending states"
```

### Task 5: Reusable stable form buttons

**Files:**
- Modify: `resources/js/components/ui/button/Button.vue`
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `resources/js/pages/auth/ForgotPassword.vue`
- Modify: `resources/js/pages/auth/ResetPassword.vue`
- Modify: `resources/js/pages/auth/ConfirmPassword.vue`
- Modify: `resources/js/components/profile/ProfileForm.vue`
- Modify: `resources/js/pages/settings/Account.vue`
- Modify: `resources/js/pages/settings/Security.vue`
- Test: `tests/Browser/WelcomeAndRegistrationTest.php`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Produces: optional `busy?: boolean` prop on `Button`; when true it forwards `aria-busy="true"`, disables a native button, preserves the slot’s dimensions, and overlays the translated `Spinner`.
- Consumes: existing `processing` values from Inertia `Form` slots.
- Preserves: every existing label in the DOM so the accessible name and button width remain stable.

- [ ] **Step 1: Write failing browser assertions for a busy form button**

Delay registration and profile form requests. Assert each submit button exposes `aria-busy="true"`, remains the same width before and during processing, contains a status spinner, and cannot issue a second request.

- [ ] **Step 2: Run the form tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php --filter='busy' --display-warnings`

Expected: FAIL because buttons do not have the shared busy contract.

- [ ] **Step 3: Implement the Button busy contract**

Add `busy?: boolean` to `Props`, import `Spinner`, apply `relative`, render the slot inside a span whose opacity becomes zero while busy, and absolutely center the spinner. Forward `aria-busy` and `disabled` only when `as === 'button'`; do not alter `as-child` links.

```vue
<span :class="busy ? 'opacity-0' : undefined"><slot /></span>
<Spinner v-if="busy" class="absolute" />
```

- [ ] **Step 4: Adopt busy buttons in primary member and authentication forms**

Replace local conditional spinners with `:busy="processing"` on the listed submit buttons while retaining `:disabled="processing || existingCondition"`. Add no new copy and do not change validation or success behavior.

- [ ] **Step 5: Run form tests and verify GREEN**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php --display-warnings`

Expected: both test files pass and button geometry assertions remain stable.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ui/button/Button.vue resources/js/pages/auth resources/js/components/profile/ProfileForm.vue resources/js/pages/settings/Account.vue resources/js/pages/settings/Security.vue tests/Browser/WelcomeAndRegistrationTest.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat(ui): standardize form processing feedback"
```

### Task 6: Discrete navigation feedback and final verification

**Files:**
- Modify: `resources/js/app.ts`
- Modify: `resources/css/app.css`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `docs/PRD.md`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Consumes: Inertia’s existing progress integration and motion tokens from Task 1.
- Produces: a stable, theme-aware `#nprogress` treatment and subtle active-link feedback that stops moving under reduced motion.
- Preserves: current route detection, navigation labels, focus rings, and member-shell geometry.

- [ ] **Step 1: Write failing navigation feedback assertions**

Delay an Inertia navigation from profile to discovery. Assert `#nprogress` is visible without hiding the current content, the bottom navigation retains identical geometry, and the destination link exposes pending visual state. Under reduced motion, assert its icon has no transform animation.

- [ ] **Step 2: Run the navigation test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='navigation feedback' --display-warnings`

Expected: FAIL because the destination link does not expose a pending state and the progress styling is not motion-token based.

- [ ] **Step 3: Implement navigation feedback**

Configure the Inertia progress indicator with `showSpinner: false` and a short delay. Style `#nprogress .bar` with the primary token, a fixed two-pixel overlay, and the shared feedback duration. In `MemberBottomNavigation.vue`, subscribe to Inertia `start`, `finish`, and `navigate` events, store the pending destination pathname, and bind `data-pending` plus a subtle opacity/scale class to the matching link. Dispose listeners on unmount.

- [ ] **Step 4: Update implementation documentation**

Add the interaction-feedback capability to the PRD implementation matrix as implemented, citing optimistic cards, asynchronous state feedback, and reduced-motion support as the observed evidence.

- [ ] **Step 5: Run all targeted frontend and browser checks**

Run:

```bash
bun test tests/Frontend
bun run lint:check
bun run format:check
bun run types:check
bun run build
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/DiscoveryTest.php tests/Browser/OnboardingTest.php tests/Browser/ConversationTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/WelcomeAndRegistrationTest.php --display-warnings
composer lint:check
composer analyse
```

Expected: every command passes with no unexpected warning or JavaScript error.

- [ ] **Step 6: Perform manual accessibility and performance checks**

At 390×844 and desktop width, exercise like, pass, match, message failure, profile submission, and member navigation with mouse and keyboard. Repeat with Chromium reduced motion enabled. Confirm no blocked focus, no lost input, no horizontal overflow, and no visible layout shift. Run Lighthouse mobile Performance and Accessibility audits against the production build and record the scores plus CLS in the PR description.

- [ ] **Step 7: Run final repository checks**

Run: `git diff --check && git status --short`

Expected: no whitespace errors; only intended issue-40 files are modified.

- [ ] **Step 8: Commit**

```bash
git add resources/js/app.ts resources/css/app.css resources/js/components/MemberBottomNavigation.vue docs/PRD.md tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat(ui): add navigation interaction feedback"
```
