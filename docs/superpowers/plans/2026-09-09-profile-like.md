# Profile Like Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an eligible member create an irreversible like from another member’s profile using the existing swipe and match flow.

**Architecture:** A focused invokable controller delegates to `CreateSwipe` with `SwipeDecision::Like`. The profile presenter exposes `canLike` only when no prior outgoing decision exists; the server Action remains authoritative for eligibility, blocking, uniqueness, match creation, and notifications.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Pest, Pest Browser.

**Spec:** `docs/superpowers/specs/2026-09-09-friendly-events-notifications-profile-like-design.md`

## Global Constraints

- Do not permit self-like, blocked pairs, unavailable profiles, or replacement of any existing `like` or `pass`.
- Reuse `CreateSwipe`; do not duplicate matching or concurrency logic.
- Use strictly friendly vocabulary in French and English.
- This plan depends on `2026-09-09-persistent-notifications.md` for durable match notifications.

---

### Task 1: Profile-like authorization and endpoint

**Files:**
- Create: `app/Http/Controllers/LikeMemberController.php`
- Create: `app/Support/DiscoveryMatchFlash.php`
- Modify: `app/Http/Controllers/PublicMemberProfileController.php`
- Modify: `app/Http/Controllers/SwipeController.php`
- Modify: `routes/web.php`
- Modify: `lang/fr/discovery.php`
- Modify: `lang/en/discovery.php`
- Test: `tests/Feature/PublicMemberProfileTest.php`
- Test: `tests/Feature/LikeMemberControllerTest.php`

**Interfaces:**
- Produces: `POST /members/{member}/like`, named `members.like`.
- Produces: Inertia prop `canLike: bool`.
- Consumes: `CreateSwipe::handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch`.

- [ ] **Step 1: Add failing profile visibility and endpoint tests**

```php
$this->actingAs($viewer)->get(route('members.show', $target))
    ->assertInertia(fn (Assert $page) => $page->where('canLike', true));

Swipe::factory()->create(['actor_user_id' => $viewer->id, 'target_user_id' => $target->id]);
$this->actingAs($viewer)->get(route('members.show', $target))
    ->assertInertia(fn (Assert $page) => $page->where('canLike', false));

$this->actingAs($viewer)->post(route('members.like', $target))
    ->assertRedirect();
expect(Swipe::whereBelongsTo($viewer, 'actor')->whereBelongsTo($target, 'target')->value('decision'))
    ->toBe(SwipeDecision::Like);
```

Cover self, blocked, hidden/inactive/incomplete, prior pass, prior like, and a
reciprocal like creating the existing match/conversation once.

- [ ] **Step 2: Run and verify failures**

Run: `php artisan test tests/Feature/PublicMemberProfileTest.php tests/Feature/LikeMemberControllerTest.php`

Expected: FAIL with missing route/controller/prop.

- [ ] **Step 3: Implement the minimal controller and prop**

```php
public function __invoke(
    Request $request,
    User $member,
    CreateSwipe $createSwipe,
    DiscoveryMatchFlash $matchFlash,
): RedirectResponse
{
    $match = $createSwipe->handle($request->user(), $member, SwipeDecision::Like);

    if ($match !== null) {
        $matchFlash->put($request->session(), $match, $member);

        return to_route('discovery.index');
    }

    return back()->with('success', __('discovery.profile_like.success'));
}
```

Compute `canLike` with an existence query for the viewer’s outgoing swipe and
the same public-profile Policy already used to render the page. Never trust the
prop for authorization. Extract the existing discovery match flash payload from
`SwipeController` into `DiscoveryMatchFlash`, and call it from both controllers,
so a reciprocal profile like redirects to discovery and opens the existing match
dialog without duplicating its DTO.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/PublicMemberProfileTest.php tests/Feature/LikeMemberControllerTest.php tests/Feature/CreateSwipeTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the backend flow**

```bash
git add app/Http/Controllers/LikeMemberController.php app/Http/Controllers/PublicMemberProfileController.php app/Http/Controllers/SwipeController.php app/Support/DiscoveryMatchFlash.php routes/web.php lang/fr/discovery.php lang/en/discovery.php tests/Feature/PublicMemberProfileTest.php tests/Feature/LikeMemberControllerTest.php
git commit -m "feat(discovery): allow likes from member profiles"
```

### Task 2: Profile-like UI and browser verification

**Files:**
- Create: `resources/js/components/members/LikeMemberButton.vue`
- Modify: `resources/js/pages/Members/Show.vue`
- Modify: `tests/Browser/DiscoveryTest.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`

**Interfaces:**
- Consumes: `canLike` and generated `members.like` route.
- Produces: accessible, busy-safe « Ajouter à mes amis » / “Add as a friend” action.

- [ ] **Step 1: Add a failing browser journey**

Create two eligible members, open the target profile, press the localized like
button, assert the success feedback, then reload and assert the button is no
longer offered. Add the reciprocal swipe and assert the match dialog and
conversation link appear.

- [ ] **Step 2: Run the browser test and verify the button is missing**

Run: `php artisan test tests/Browser/DiscoveryTest.php --filter='like from profile'`

Expected: FAIL because the action is absent.

- [ ] **Step 3: Implement `LikeMemberButton` and mount it in `summary-actions`**

Use an Inertia POST with `preserveScroll`, disable during processing, expose a
localized busy label, and render it before block/unblock controls. Do not render
the button when `canLike` is false.

- [ ] **Step 4: Run targeted checks**

Run: `php artisan test tests/Feature/LikeMemberControllerTest.php tests/Browser/DiscoveryTest.php --filter='like from profile' && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 5: Commit the UI**

```bash
git add resources/js/components/members/LikeMemberButton.vue resources/js/pages/Members/Show.vue tests/Browser/DiscoveryTest.php tests/Feature/Localization/InertiaTranslationsTest.php
git commit -m "feat(discovery): add friendly profile like action"
```
