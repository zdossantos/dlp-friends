# Event Overlay UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver an events experience with only Discover and My Events as full screens, while every detail and management flow uses the same responsive drawer/modal interface regardless of entry point.

**Architecture:** Laravel keeps every secondary state addressable and renders the same `EventWorkspace` with a typed optional panel payload. `Events/Index.vue` and `Events/Mine.vue` are thin entry pages around that shared workspace; mobile and desktop share panel content through one adaptive `Sheet`/`Dialog` host. Server queries enforce discovery capacity and existing policies protect private participants and embedded profiles.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, VueUse, Pest, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-09-event-overlay-ux-design.md`

## Global Constraints

- The only full-screen event views are `/events` and `/events/mine`.
- Details, create, edit, participants, participant profiles, registrations, and confirmations use one responsive panel system.
- Mobile uses `Sheet`; screens at `sm` and above use `Dialog`, with shared content and state.
- Direct URLs, notification links, and in-app clicks reconstruct the same interface.
- Full events are excluded server-side from Discover but remain in My Events for the organizer and accepted participants.
- Participant identities remain visible only to the organizer and accepted members.
- All visible copy is localized in French and English; no user-visible string is hard-coded.
- UI colors use semantic design-system tokens and must remain readable in light and dark themes.
- Existing Laravel actions, policies, notifications, and capacity locking remain the source of truth.
- No new frontend or backend dependency is introduced.

---

## File Structure

### Backend

- Create `app/Data/EventWorkspaceData.php`: build Discover/My Events lists and the shared workspace response props.
- Create `app/Data/PublicMemberData.php`: serialize the reusable public profile payload and profile actions.
- Create `app/Http/Controllers/EventParticipantController.php`: render participant-list and embedded-profile panel routes through the workspace.
- Create `app/Http/Controllers/EventRegistrationIndexController.php`: render organizer registration management through the workspace.
- Modify `app/Models/Event.php`: reusable server-side available-capacity scope and accepted count.
- Modify `app/Data/EventSummaryData.php`: consume eager-loaded accepted counts.
- Modify `app/Data/EventDetailData.php`: add avatar summaries without exposing extra profile data.
- Modify `app/Http/Controllers/EventController.php`: render create/show/edit in the shared workspace and preserve trusted origin.
- Modify `app/Http/Controllers/MyEventController.php`: use the shared list builder.
- Modify `app/Http/Controllers/PublicMemberProfileController.php`: reuse `PublicMemberData`.
- Modify mutation controllers under `app/Http/Controllers/Event*Controller.php`: preserve the trusted workspace origin when redirecting.
- Modify `routes/web.php`: add participants and registrations panel routes.

### Frontend

- Create `resources/js/components/events/AdaptiveEventPanel.vue`: one responsive modal/drawer host.
- Create `resources/js/components/events/EventWorkspace.vue`: the two-screen shell and route-driven panel coordinator.
- Create `resources/js/components/events/EventPanelContent.vue`: switch between the typed secondary views.
- Create `resources/js/components/events/EventDetail.vue`: reusable event detail content.
- Create `resources/js/components/events/EventParticipantStack.vue`: overlapping avatar trigger and `+N` counter.
- Create `resources/js/components/events/EventParticipantList.vue`: accessible participant rows.
- Create `resources/js/components/events/EventParticipantProfile.vue`: embedded `ProfilePresentation` and panel back action.
- Create `resources/js/components/events/EventConfirmationDialog.vue`: reusable explicit confirmation.
- Modify `resources/js/components/events/EventCard.vue`: role variants and panel-aware links.
- Modify `resources/js/components/events/EventForm.vue`: panel completion callbacks and origin-aware actions.
- Modify `resources/js/components/events/EventRegistrationActions.vue`: explicit confirmation and panel refresh.
- Modify `resources/js/components/events/OrganizerRegistrations.vue`: avatar rows and explicit confirmations.
- Modify `resources/js/pages/Events/Index.vue` and `resources/js/pages/Events/Mine.vue`: thin workspace entry pages.
- Remove `resources/js/pages/Events/Create.vue`, `resources/js/pages/Events/Edit.vue`, and `resources/js/pages/Events/Show.vue` after their content is moved.
- Modify `resources/js/types/event.ts`: workspace, panel, avatar, and embedded-profile types.
- Modify `lang/fr/events.php` and `lang/en/events.php`: all new labels, descriptions, empty states, and confirmation consequences.
- Regenerate `resources/js/routes/**` with Wayfinder.

### Tests and docs

- Modify `tests/Feature/EventVisibilityTest.php`: full-event filtering and My Events retention/grouping.
- Create `tests/Feature/EventWorkspaceTest.php`: route-to-workspace equivalence and trusted origin.
- Create `tests/Feature/EventParticipantPanelTest.php`: participant/profile privacy and payloads.
- Modify `tests/Feature/PublicMemberProfileTest.php`: serializer parity.
- Modify `tests/Feature/NotificationCenterTest.php`: event links open the canonical detail panel route.
- Rewrite `tests/Browser/EventTest.php`: responsive panel flows, roles, confirmations, history, dark mode, and multi-user capacity.
- Modify `docs/PRD.md` and `docs/design-system.md`: document delivered navigation and responsive panel behavior.

---

### Task 1: Enforce Available Capacity in Discovery

**Files:**
- Modify: `app/Models/Event.php`
- Modify: `app/Data/EventSummaryData.php`
- Create: `app/Data/EventWorkspaceData.php`
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `app/Http/Controllers/MyEventController.php`
- Test: `tests/Feature/EventVisibilityTest.php`

**Interfaces:**
- Produces: `Event::scopeWithAcceptedRegistrationCount(Builder $query): void`.
- Produces: `Event::scopeWithAvailableCapacity(Builder $query): void`.
- Produces: `EventWorkspaceData::discovery(User $viewer): array` and `EventWorkspaceData::mine(User $viewer): array{organized: array, participating: array}`.
- Consumes: existing `EventSummaryData::from(Event $event, User $viewer): array`.

- [ ] **Step 1: Write failing visibility tests**

Add tests that create capacity-two events with one accepted registration and assert that the full event is missing from `events`, while an event with capacity remaining is present. Extend the My Events test to assert that the same full event appears under `organized` for its organizer and under `participating` for its accepted member.

```php
$full = Event::factory()->for($organizer, 'organizer')->create(['capacity' => 2]);
EventRegistration::factory()->for($full)->for($accepted)->accepted()->create();

$this->actingAs($outsider)->get(route('events.index'))
    ->assertInertia(fn (Assert $page) => $page
        ->where('events', fn (array $events) => collect($events)->doesntContain('id', $full->id)));

$this->actingAs($organizer)->get(route('events.mine'))
    ->assertInertia(fn (Assert $page) => $page
        ->where('organized.0.id', $full->id));
```

- [ ] **Step 2: Run the tests and confirm the capacity case fails**

Run: `php artisan test tests/Feature/EventVisibilityTest.php`

Expected: FAIL because discovery still includes the full event and My Events still exposes a single `events` collection.

- [ ] **Step 3: Add reusable capacity scopes and workspace list queries**

Use a constrained `withCount` for accepted registrations and a correlated capacity predicate. `EventSummaryData` must use `accepted_registrations_count` when loaded and fall back to the existing count only outside list queries.

```php
public function scopeWithAcceptedRegistrationCount(Builder $query): void
{
    $query->withCount(['registrations as accepted_registrations_count' =>
        fn (Builder $registrations) => $registrations
            ->where('status', EventRegistrationStatus::Accepted),
    ]);
}

public function scopeWithAvailableCapacity(Builder $query): void
{
    $query->whereRaw('events.capacity > 1 + (select count(*) from event_registrations where event_registrations.event_id = events.id and event_registrations.status = ?)', [EventRegistrationStatus::Accepted->value]);
}
```

Build `EventWorkspaceData::discovery()` with active/upcoming/unblocked/available events and `mine()` with two explicit collections. The participating collection includes only `pending` and `accepted` registrations so terminal history does not masquerade as current participation; organized events keep past and cancelled records.

- [ ] **Step 4: Point both list controllers at `EventWorkspaceData`**

`EventController::index()` returns `Events/Index` with `events`, `panel => null`, and `context => 'discover'`. `MyEventController` returns `Events/Mine` with `organized`, `participating`, `panel => null`, and `context => 'mine'`.

- [ ] **Step 5: Run targeted tests**

Run: `php artisan test tests/Feature/EventVisibilityTest.php tests/Unit/Models/EventTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Event.php app/Data/EventSummaryData.php app/Data/EventWorkspaceData.php app/Http/Controllers/EventController.php app/Http/Controllers/MyEventController.php tests/Feature/EventVisibilityTest.php tests/Unit/Models/EventTest.php
git commit -m "feat(events): hide full events from discovery"
```

### Task 2: Define the Shared Workspace and Panel Contract

**Files:**
- Modify: `app/Data/EventWorkspaceData.php`
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `app/Http/Controllers/EventRegistrationController.php`
- Modify: `app/Http/Controllers/EventCancellationController.php`
- Modify: `app/Http/Controllers/EventRegistrationDecisionController.php`
- Modify: `app/Http/Controllers/EventRegistrationRemovalController.php`
- Modify: `resources/js/types/event.ts`
- Create: `tests/Feature/EventWorkspaceTest.php`

**Interfaces:**
- Produces: `EventWorkspaceData::render(User $viewer, string $context, ?array $panel): Response`.
- Produces: `EventWorkspaceData::context(Request $request): 'discover'|'mine'`, accepting only query value `mine`; every other value maps to `discover`.
- Produces frontend union `EventPanel = { kind: 'detail'; event: EventDetail } | { kind: 'create' } | { kind: 'edit'; event: EventDetail } | { kind: 'participants'; event: EventDetail } | { kind: 'participant-profile'; event: EventDetail; profile: EmbeddedMemberProfile } | { kind: 'registrations'; event: EventDetail }`.
- Produces: `EventWorkspaceProps` with `context`, list props, `panel`, and `closeHref`.

- [ ] **Step 1: Write failing route-equivalence tests**

Assert direct show renders `Events/Index` with a detail panel, `?origin=mine` renders `Events/Mine`, create renders a create panel, and edit renders an edit panel. Also assert `origin=https://example.com` falls back to Discover.

```php
$this->actingAs($organizer)->get(route('events.show', $event))
    ->assertInertia(fn (Assert $page) => $page
        ->component('Events/Index')
        ->where('context', 'discover')
        ->where('panel.kind', 'detail')
        ->where('panel.event.id', $event->id)
        ->where('closeHref', route('events.index', absolute: false)));

$this->actingAs($organizer)->get(route('events.show', ['event' => $event, 'origin' => 'mine']))
    ->assertInertia(fn (Assert $page) => $page
        ->component('Events/Mine')
        ->where('context', 'mine')
        ->where('panel.kind', 'detail'));
```

- [ ] **Step 2: Run the new test and verify it fails on the old page components**

Run: `php artisan test tests/Feature/EventWorkspaceTest.php`

Expected: FAIL because show/create/edit render `Events/Show`, `Events/Create`, and `Events/Edit`.

- [ ] **Step 3: Implement trusted workspace rendering**

Add `context()` and `render()` to `EventWorkspaceData`. `render()` chooses only `Events/Index` or `Events/Mine`, attaches the correct list, and uses a route-generated internal `closeHref`. Update `EventController::create/show/edit` to pass typed panel arrays.

```php
public function context(Request $request): string
{
    return $request->query('origin') === 'mine' ? 'mine' : 'discover';
}

public function show(Request $request, Event $event): Response
{
    Gate::authorize('view', $event);
    $event->load('organizer.profile.avatar');

    return $this->workspace->render($this->user($request), $this->workspace->context($request), [
        'kind' => 'detail',
        'event' => EventDetailData::from($event, $this->user($request)),
    ]);
}
```

- [ ] **Step 4: Preserve origin across successful mutations**

Add a private route builder or shared helper that appends `origin=mine` only when the incoming query equals `mine`. Creation redirects to the detail panel, update returns to detail, and registration/cancellation decisions redirect back to the same secondary route. Do not accept arbitrary return URLs.

- [ ] **Step 5: Define exact TypeScript discriminated unions**

```ts
export type EventWorkspaceContext = 'discover' | 'mine';

export type EventPanel =
    | { kind: 'detail'; event: EventDetail }
    | { kind: 'create' }
    | { kind: 'edit'; event: EventDetail }
    | { kind: 'participants'; event: EventDetail }
    | { kind: 'participant-profile'; event: EventDetail; profile: EmbeddedMemberProfile }
    | { kind: 'registrations'; event: EventDetail };
```

- [ ] **Step 6: Run targeted backend tests and frontend type checking**

Run: `php artisan test tests/Feature/EventWorkspaceTest.php tests/Feature/CreateEventTest.php tests/Feature/UpdateEventTest.php`

Run: `bun run types:check`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Data/EventWorkspaceData.php app/Http/Controllers/EventController.php app/Http/Controllers/EventRegistrationController.php app/Http/Controllers/EventCancellationController.php app/Http/Controllers/EventRegistrationDecisionController.php app/Http/Controllers/EventRegistrationRemovalController.php resources/js/types/event.ts tests/Feature/EventWorkspaceTest.php
git commit -m "refactor(events): render secondary routes in workspace"
```

### Task 3: Reuse Public Profile Data and Secure Participant Routes

**Files:**
- Create: `app/Data/PublicMemberData.php`
- Modify: `app/Data/EventDetailData.php`
- Modify: `app/Http/Controllers/PublicMemberProfileController.php`
- Create: `app/Http/Controllers/EventParticipantController.php`
- Create: `app/Http/Controllers/EventRegistrationIndexController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/types/event.ts`
- Test: `tests/Feature/EventParticipantPanelTest.php`
- Test: `tests/Feature/PublicMemberProfileTest.php`

**Interfaces:**
- Produces: `PublicMemberData::from(User $viewer, User $member): array{member: array, canBlock: bool, canLike: bool, canUnblock: bool}`.
- Produces routes: `events.participants.index`, `events.participants.show`, and `events.registrations.index`.
- Extends `EventParticipant` to `{ id: number; displayName: string; avatar: MemberAvatar }`.
- Produces `EmbeddedMemberProfile` matching the existing `PublicMember` plus `canBlock`, `canLike`, and `canUnblock`.

- [ ] **Step 1: Write failing participant privacy and serializer-parity tests**

Cover organizer/accepted access, pending/outsider denial, a member not belonging to the event returning 404, and equality of the embedded member payload with the existing public profile payload.

```php
$this->actingAs($accepted)
    ->get(route('events.participants.show', [$event, $organizer]))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->where('panel.kind', 'participant-profile')
        ->where('panel.profile.member.id', $organizer->id)
        ->has('panel.profile.member.avatar'));

$this->actingAs($pending)
    ->get(route('events.participants.index', $event))
    ->assertNotFound();
```

- [ ] **Step 2: Run tests and confirm routes/data are missing**

Run: `php artisan test tests/Feature/EventParticipantPanelTest.php tests/Feature/PublicMemberProfileTest.php`

Expected: FAIL because the panel routes and reusable serializer do not exist.

- [ ] **Step 3: Extract `PublicMemberData` from the existing controller**

Move the public member shape and action booleans without changing field names. Keep `backHref` in `PublicMemberProfileController`; the embedded panel uses panel routes rather than a free-form back URL. Rerun existing public profile tests after extraction.

- [ ] **Step 4: Add avatar summaries to private event participants**

Eager-load `profile.avatar` and serialize only `id`, `displayName`, and the avatar object with `name`, image route, primary color, and secondary color. Preserve the existing `EventPolicy::viewPrivateDetails` gate before building the array.

- [ ] **Step 5: Implement participant and registration panel controllers**

`EventParticipantController::index()` authorizes private details and renders `kind=participants`. `show()` additionally verifies the selected member is the organizer or has an accepted registration before calling `PublicMemberData`. `EventRegistrationIndexController` authorizes the organizer and renders `kind=registrations`.

- [ ] **Step 6: Add explicit routes and regenerate Wayfinder**

```php
Route::get('events/{event}/participants', [EventParticipantController::class, 'index'])
    ->name('events.participants.index');
Route::get('events/{event}/participants/{member}', [EventParticipantController::class, 'show'])
    ->name('events.participants.show');
Route::get('events/{event}/requests', EventRegistrationIndexController::class)
    ->name('events.registrations.index');
```

Run: `php artisan wayfinder:generate --with-form`

- [ ] **Step 7: Run privacy and compatibility tests**

Run: `php artisan test tests/Feature/EventParticipantPanelTest.php tests/Feature/PublicMemberProfileTest.php tests/Feature/EventVisibilityTest.php`

Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Data/PublicMemberData.php app/Data/EventDetailData.php app/Http/Controllers/PublicMemberProfileController.php app/Http/Controllers/EventParticipantController.php app/Http/Controllers/EventRegistrationIndexController.php routes/web.php resources/js/routes resources/js/types/event.ts tests/Feature/EventParticipantPanelTest.php tests/Feature/PublicMemberProfileTest.php
git commit -m "feat(events): add secure participant panel data"
```

### Task 4: Build the Adaptive Panel and Shared Workspace Shell

**Files:**
- Create: `resources/js/components/events/AdaptiveEventPanel.vue`
- Create: `resources/js/components/events/EventWorkspace.vue`
- Create: `resources/js/components/events/EventPanelContent.vue`
- Modify: `resources/js/pages/Events/Index.vue`
- Modify: `resources/js/pages/Events/Mine.vue`
- Remove: `resources/js/pages/Events/Create.vue`
- Remove: `resources/js/pages/Events/Edit.vue`
- Remove: `resources/js/pages/Events/Show.vue`
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- Produces `AdaptiveEventPanel` props `{ open: boolean; title: string; description: string }` and emits `update:open`.
- Produces `EventWorkspace` props matching `EventWorkspaceProps` and a single `EventPanelContent` slot tree.
- Consumes `EventPanel` from Task 2.

- [ ] **Step 1: Add a failing browser test for identical entry points**

Visit Discover, click an event card, and assert `[data-test=event-panel]` and `[data-test=event-detail]`. Then visit the event URL directly and assert the same selectors and a Discover list behind it. Add the same assertion for a link carrying `origin=mine` with My Events behind it.

- [ ] **Step 2: Run the focused browser test and confirm it fails**

Run: `php artisan test tests/Browser/EventTest.php --filter='same event panel'`

Expected: FAIL because the current secondary routes render standalone pages.

- [ ] **Step 3: Implement `AdaptiveEventPanel` with one content instance**

Use `useMediaQuery('(max-width: 639px)')`. Render the slot inside the active branch only: bottom `SheetContent` on mobile and `DialogContent` on desktop. Add `data-test="event-panel"`, constrained height, internal scrolling, safe-area padding, accessible title/description, and a close event.

```ts
const isMobile = useMediaQuery('(max-width: 639px)');
const emit = defineEmits<{ 'update:open': [open: boolean] }>();
```

- [ ] **Step 4: Implement the shared workspace and thin pages**

`EventWorkspace` owns headers, list sections, panel closing via `router.visit(closeHref, { preserveScroll: true })`, and renders one `EventPanelContent`. `Index.vue` and `Mine.vue` only set `<Head>` and pass their props through. Delete the three obsolete page components only after no controller references them.

- [ ] **Step 5: Add localized panel titles and descriptions**

Add keys for every panel kind in both language files, including close and back labels. Use neutral friendly language and keep all confirmation consequences explicit.

- [ ] **Step 6: Run the browser test, lint, and type checks**

Run: `php artisan test tests/Browser/EventTest.php --filter='same event panel'`

Run: `bun run lint:check && bun run types:check`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/events/AdaptiveEventPanel.vue resources/js/components/events/EventWorkspace.vue resources/js/components/events/EventPanelContent.vue resources/js/pages/Events lang/fr/events.php lang/en/events.php tests/Browser/EventTest.php
git commit -m "feat(events): add responsive event workspace panel"
```

### Task 5: Redesign Lists, Roles, and Dark-Theme Surfaces

**Files:**
- Modify: `resources/js/components/events/EventWorkspace.vue`
- Modify: `resources/js/components/events/EventCard.vue`
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- Extends `EventCard` with `context: EventWorkspaceContext` and `role?: 'organizer'|'participant'`.
- Consumes `organized` and `participating` lists from Task 1.

- [ ] **Step 1: Write failing role and dark-theme browser assertions**

As a member who organizes one event and has an accepted registration in another, open My Events and assert separate `[data-test=organized-events]` and `[data-test=participating-events]` sections plus explicit role labels. Switch to dark mode, open each card and assert the critical title, description, status, and private-location elements are visible and do not use hard-coded light text classes.

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `php artisan test tests/Browser/EventTest.php --filter='roles|dark theme'`

Expected: FAIL because lists are merged and the current presentation lacks role landmarks.

- [ ] **Step 3: Implement list hierarchy and card variants**

Render section headers with icon plus text: `J’organise`/`I organize` and `Je participe`/`I’m joining`. The card adds an explicit role badge and status text, while sharing date/location/capacity markup. Past/cancelled cards use `opacity-70` plus text labels, not opacity alone.

- [ ] **Step 4: Replace fragile colors with semantic tokens**

Use `bg-card text-card-foreground`, `bg-secondary text-secondary-foreground`, `bg-muted text-muted-foreground`, `border-border`, and `text-foreground`. Preserve visible focus rings and avoid raw pink/white/black text combinations in event components.

- [ ] **Step 5: Run browser and frontend checks**

Run: `php artisan test tests/Browser/EventTest.php --filter='roles|dark theme'`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/events/EventWorkspace.vue resources/js/components/events/EventCard.vue lang/fr/events.php lang/en/events.php tests/Browser/EventTest.php
git commit -m "feat(events): clarify event roles and dark theme"
```

### Task 6: Add the Avatar Stack, Participant List, and Embedded Profile

**Files:**
- Create: `resources/js/components/events/EventDetail.vue`
- Create: `resources/js/components/events/EventParticipantStack.vue`
- Create: `resources/js/components/events/EventParticipantList.vue`
- Create: `resources/js/components/events/EventParticipantProfile.vue`
- Modify: `resources/js/components/events/EventPanelContent.vue`
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- `EventParticipantStack` consumes `EventParticipant[]` and `href`, displaying `participants.slice(0, 3)` plus `participants.length - 3`.
- `EventParticipantList` consumes `eventId`, `origin`, and `EventParticipant[]`.
- `EventParticipantProfile` consumes `EmbeddedMemberProfile`, emits no navigation, and receives a route-generated `backHref`.
- Consumes `ProfilePresentation` without duplicating its profile layout.

- [ ] **Step 1: Write a failing browser scenario for four participants**

Create an organizer plus three accepted members. Open the detail as an accepted member, assert three avatar images and `+1`, click the stack, click a participant, assert the embedded profile remains in `[data-test=event-panel]`, then click the top-right back action and assert the participant list returns.

- [ ] **Step 2: Run the scenario and confirm it fails**

Run: `php artisan test tests/Browser/EventTest.php --filter='participant avatar stack'`

Expected: FAIL because participants are text badges and profiles navigate away.

- [ ] **Step 3: Move standalone detail markup into `EventDetail`**

Keep date, general location, capacity, private location, registration actions, and organizer actions in this reusable component. Replace participant badges with `EventParticipantStack`; use the generated participants route with `origin` preserved.

- [ ] **Step 4: Implement the overlapping avatar stack**

Use the existing `Avatar`, `AvatarImage`, and `AvatarFallback` primitives. Apply negative inline margins after the first avatar, `ring-2 ring-card`, and a semantic `+N` circle. The trigger must have a localized `aria-label` with the total count and a 44 px minimum target.

- [ ] **Step 5: Implement participant list and embedded profile**

Rows show avatar, display name, and a full-row Inertia link to the participant profile panel route. `EventParticipantProfile` places an icon-only back action at the top right, then passes the exact payload to `ProfilePresentation`, including like/block controls under the same authorization booleans used on the public profile page.

- [ ] **Step 6: Run participant, profile-like, and frontend checks**

Run: `php artisan test tests/Browser/EventTest.php --filter='participant avatar stack' tests/Feature/EventParticipantPanelTest.php tests/Feature/PublicMemberProfileTest.php`

Run: `bun run lint:check && bun run types:check`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/events/EventDetail.vue resources/js/components/events/EventParticipantStack.vue resources/js/components/events/EventParticipantList.vue resources/js/components/events/EventParticipantProfile.vue resources/js/components/events/EventPanelContent.vue lang/fr/events.php lang/en/events.php tests/Browser/EventTest.php
git commit -m "feat(events): add participant profiles inside event panel"
```

### Task 7: Move Create and Edit into the Shared Panel

**Files:**
- Modify: `resources/js/components/events/EventForm.vue`
- Modify: `resources/js/components/events/EventPanelContent.vue`
- Modify: `resources/js/components/events/EventWorkspace.vue`
- Modify: `resources/js/components/events/EventDetail.vue`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- `EventForm` consumes existing `event`, `action`, `method`, and `submitLabel` plus `origin: EventWorkspaceContext`.
- Successful POST/PATCH responses land on the detail panel route with the same trusted origin.
- Validation responses retain the same create/edit panel kind and field values.

- [ ] **Step 1: Rewrite creation/edit browser tests around the panel**

Start from Discover, click Create, assert the background list and create panel, submit, and assert the same panel changes to event detail. Start from My Events, edit as organizer, submit, and assert My Events remains behind the refreshed detail. Keep the existing invalid-date description preservation assertion.

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `php artisan test tests/Browser/EventTest.php --filter='creates|edits|preserves its description'`

Expected: FAIL until buttons and forms use the workspace routes.

- [ ] **Step 3: Render the one existing form for both panel kinds**

In `EventPanelContent`, map `create` to the store route and `edit` to the update route. Append only `origin=mine` when appropriate. Keep server validation, `v-model` description preservation, input errors, processing spinner, and disabled submission.

- [ ] **Step 4: Keep panel state after validation and success**

Use Inertia navigation without replacing the workspace component. Preserve scroll on error. On success, let the server redirect to canonical `events.show` with the trusted origin so the detail panel is reconstructed from server data.

- [ ] **Step 5: Run create/update feature and browser tests**

Run: `php artisan test tests/Feature/CreateEventTest.php tests/Feature/UpdateEventTest.php tests/Browser/EventTest.php --filter='creates|edits|preserves its description'`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/events/EventForm.vue resources/js/components/events/EventPanelContent.vue resources/js/components/events/EventWorkspace.vue resources/js/components/events/EventDetail.vue tests/Browser/EventTest.php
git commit -m "feat(events): move event forms into adaptive panel"
```

### Task 8: Replace Browser Confirmations and Move Registration Management into the Panel

**Files:**
- Create: `resources/js/components/events/EventConfirmationDialog.vue`
- Modify: `resources/js/components/events/EventRegistrationActions.vue`
- Modify: `resources/js/components/events/OrganizerRegistrations.vue`
- Modify: `resources/js/components/events/EventDetail.vue`
- Modify: `resources/js/components/events/EventPanelContent.vue`
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- `EventConfirmationDialog` consumes `{ title: string; description: string; confirmLabel: string; destructive?: boolean; busy?: boolean }`, exposes a `trigger` slot, and emits `confirm`.
- Registration management is addressed through `events.registrations.index` and rendered as panel kind `registrations`.

- [ ] **Step 1: Write failing explicit-confirmation tests**

For withdraw, cancel, refuse, and remove: click the trigger; assert a visible localized dialog title and consequence; click Cancel and assert no mutation; reopen, Confirm, and assert the expected database/UI change. Do not stub `window.confirm`.

- [ ] **Step 2: Run the focused tests and confirm they fail**

Run: `php artisan test tests/Browser/EventTest.php --filter='confirmation'`

Expected: FAIL because current actions use native browser confirmation.

- [ ] **Step 3: Implement the reusable accessible confirmation**

Compose `Dialog`, `DialogContent`, `DialogHeader`, `DialogTitle`, `DialogDescription`, and `DialogFooter`. Keep the dialog open during processing, disable both buttons, emit once, and close only after the caller reports completion. Use `variant="destructive"` for destructive confirms.

- [ ] **Step 4: Replace all `window.confirm` event usages**

Use explicit titles and descriptions for withdrawal, cancellation, removal, and definitive refusal. Preserve existing routes and server actions. On success, refresh the relevant panel props with `router.reload({ only: [...] })` or follow the canonical redirect returned by Laravel.

- [ ] **Step 5: Put registration requests in their routed sub-view**

The organizer’s detail shows a summary button with the pending count. Clicking opens `kind=registrations`; rows include avatar/name/status and use the shared confirmation for refusal/removal. A top-right back action returns to event detail with the same origin.

- [ ] **Step 6: Run lifecycle and browser tests**

Run: `php artisan test tests/Feature/EventRegistrationTest.php tests/Feature/CancelEventTest.php tests/Browser/EventTest.php --filter='confirmation|manual registration'`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/events/EventConfirmationDialog.vue resources/js/components/events/EventRegistrationActions.vue resources/js/components/events/OrganizerRegistrations.vue resources/js/components/events/EventDetail.vue resources/js/components/events/EventPanelContent.vue lang/fr/events.php lang/en/events.php tests/Browser/EventTest.php
git commit -m "feat(events): add accessible event confirmations"
```

### Task 9: Verify Notifications, History, and Multi-User Behavior

**Files:**
- Modify: `app/Http/Controllers/NotificationReadController.php`
- Modify: `tests/Feature/NotificationCenterTest.php`
- Modify: `tests/Browser/NotificationCenterTest.php`
- Modify: `tests/Browser/EventTest.php`

**Interfaces:**
- Event notification destinations remain canonical `events.show` URLs.
- `events.show` now reconstructs Discover plus `kind=detail`; no notification-specific UI path is introduced.

- [ ] **Step 1: Add failing notification-interface assertions**

Create an event notification, mark it read, follow its redirect, and assert the destination is still `/events/{id}`. In the browser, click it and assert both `[data-test=discover-events]` and `[data-test=event-panel]` are present.

- [ ] **Step 2: Run notification tests**

Run: `php artisan test tests/Feature/NotificationCenterTest.php tests/Browser/NotificationCenterTest.php --filter='event'`

Expected: the feature redirect should remain valid; the browser interface assertion fails until all workspace pieces are wired.

- [ ] **Step 3: Preserve canonical event notification targets**

Keep event notifications pointed at `events.show`; change only code that assumes show is a standalone page. Missing or unauthorized events continue to fall back to the notification center.

- [ ] **Step 4: Add browser history coverage**

From Discover, open detail → participants → profile. Use browser back to return profile → participants → detail → closed panel, asserting the Discover scroll marker is unchanged. Repeat detail → edit from My Events and assert My Events remains the background.

- [ ] **Step 5: Add the full-capacity multi-user scenario**

Use organizer, accepted participant, pending member, and outsider. Fill the last place, verify the outsider no longer sees the event in Discover, and verify organizer/accepted member still see and open it in their My Events sections. Verify the pending member cannot see private participants.

- [ ] **Step 6: Run the complete event and notification suites**

Run: `php artisan test tests/Feature/EventVisibilityTest.php tests/Feature/EventWorkspaceTest.php tests/Feature/EventParticipantPanelTest.php tests/Feature/EventRegistrationTest.php tests/Feature/EventNotificationTest.php tests/Feature/NotificationCenterTest.php tests/Browser/EventTest.php tests/Browser/NotificationCenterTest.php`

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/NotificationReadController.php tests/Feature/NotificationCenterTest.php tests/Browser/NotificationCenterTest.php tests/Browser/EventTest.php
git commit -m "test(events): cover routed panel user journeys"
```

### Task 10: Documentation, Full Verification, and Docker Validation

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/design-system.md`
- Modify when generated: `resources/js/routes/**`

**Interfaces:**
- Documents the delivered two-screen navigation and adaptive panel rule.
- Produces a Docker runtime image containing the same verified assets and routes.

- [ ] **Step 1: Update product and design-system documentation**

Mark the two-screen events experience as implemented in `docs/PRD.md`. Add the reusable responsive-panel behavior, semantic dark-theme surface rule, avatar-stack accessibility, and history behavior to `docs/design-system.md`.

- [ ] **Step 2: Regenerate routes and run formatting checks**

Run: `php artisan wayfinder:generate --with-form`

Run: `git diff --check && composer lint:check && bun run lint:check && bun run format:check && bun run types:check`

Expected: all commands exit 0.

- [ ] **Step 3: Run static analysis, production build, and all tests**

Run: `composer analyse`

Run: `bun run build`

Run: `composer test`

Expected: all commands exit 0 with no new warnings or failures.

- [ ] **Step 4: Build and start the updated Docker stack**

Run: `docker compose up --build -d`

Run: `docker compose ps`

Expected: web, worker, reverb, MySQL, Redis, MinIO, and Mailpit services are running or healthy as defined by Compose.

- [ ] **Step 5: Apply explicit migrations only if the implementation added one**

This design should not require a migration. Verify with `git diff --name-only main...HEAD -- database/migrations`. If the command prints a migration created by this implementation, run `docker compose exec web php artisan migrate --force`; otherwise do not run a migration.

- [ ] **Step 6: Run Docker smoke journeys**

Against the rebuilt app, verify Discover, My Events, direct event URLs, notification entry, mobile drawer, desktop modal, dark mode, avatar stack, embedded profile/back, creation/editing, and all four confirmation paths without JavaScript errors.

- [ ] **Step 7: Request code review and fix verified findings**

Invoke `superpowers:requesting-code-review`, provide the design and plan paths, inspect every reported issue, and apply only findings confirmed against current code and tests. Rerun the affected checks after fixes.

- [ ] **Step 8: Commit documentation and final integration changes**

```bash
git add docs/PRD.md docs/design-system.md resources/js/routes
git commit -m "docs(events): document adaptive event workspace"
```

- [ ] **Step 9: Perform completion verification**

Invoke `superpowers:verification-before-completion` and use fresh outputs from the full checks and Docker smoke journeys before claiming success or updating the pull request.

---

## Self-Review

- Spec coverage: every design section maps to Tasks 1–10, including capacity, two-screen routing, responsive panels, roles, dark theme, participants, embedded profiles, forms, confirmations, notifications, history, accessibility, multi-user behavior, documentation, and Docker.
- Placeholder scan: the plan contains no deferred implementation markers; each task names exact files, interfaces, test commands, expected results, and commit boundaries.
- Type consistency: `EventWorkspaceContext`, `EventPanel`, `EventWorkspaceProps`, `EventParticipant`, and `EmbeddedMemberProfile` are introduced before their frontend consumers; route names are consistent across backend and frontend tasks.
