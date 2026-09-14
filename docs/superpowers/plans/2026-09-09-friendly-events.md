# Friendly Events Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver private member-only friendly events with capacity-safe automatic/manual registration, organizer controls, privacy boundaries, lifecycle notifications, and bilingual accessible UI.

**Architecture:** `Event` and `EventRegistration` models encode persisted state; focused Actions own every transition and lock the event row inside MySQL transactions. An `EventPolicy` controls organizer and private-detail access. Inertia controllers provide filtered representations, and event notifications reuse the durable notification center from the prerequisite plan.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4 row locks, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Pest Browser, Playwright Chromium.

**Spec:** `docs/superpowers/specs/2026-09-09-friendly-events-notifications-profile-like-design.md`

## Global Constraints

- Events are visible only to authenticated, verified, active adult members with complete profile and onboarding.
- Input and display timezone is `Europe/Paris`; persistence timezone is UTC.
- Organizer is included in capacity and has no registration row.
- Pending registrations consume no capacity; only accepted registrations do.
- No waitlist, recurrence, geolocation, group chat, e-mail notification, or public indexing.
- Detailed location and participant identities are sent only to organizer and accepted participants.
- Major changes use the currently persisted start as the 24-hour cutoff.
- All visible copy, validation, notification, toast, confirmation, and accessibility text is translated in French and English.
- This plan depends on `2026-09-09-persistent-notifications.md`.

---

### Task 1: Event schema, enums, models, and factories

**Files:**
- Create: `database/migrations/2026_09_09_200000_create_events_and_registrations_tables.php`
- Create: `app/Enums/EventRegistrationMode.php`
- Create: `app/Enums/EventRegistrationStatus.php`
- Create: `app/Models/Event.php`
- Create: `app/Models/EventRegistration.php`
- Create: `database/factories/EventFactory.php`
- Create: `database/factories/EventRegistrationFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/EventSchemaTest.php`
- Test: `tests/Unit/Models/EventTest.php`

**Interfaces:**
- Produces: `EventRegistrationMode::{Automatic,Manual}`.
- Produces: `EventRegistrationStatus::{Pending,Accepted,Refused,Withdrawn,Removed,Blocked}`.
- Produces: `Event::occupiedPlaces(): int`, `Event::hasStarted(): bool`, `Event::majorChangesAllowed(): bool`.
- Produces User relations `organizedEvents()`, `eventRegistrations()`.

- [ ] **Step 1: Write failing schema and model tests**

```php
expect(Schema::hasColumns('events', [
    'organizer_user_id', 'title', 'description', 'general_location',
    'detailed_location', 'starts_at', 'capacity', 'registration_mode', 'cancelled_at',
]))->toBeTrue();

$event = Event::factory()->has(EventRegistration::factory()->accepted()->count(2), 'registrations')->create();
expect($event->occupiedPlaces())->toBe(3);

Carbon::setTestNow('2026-09-09 10:00:00 Europe/Paris');
expect(Event::factory()->make(['starts_at' => now()->addHours(24)])->majorChangesAllowed())->toBeTrue();
```

Assert the unique `(event_id,user_id)` key, cascades, UTC datetime casts, enum
casts, terminal statuses, and organizer-not-registration constraint at the
Action layer.

- [ ] **Step 2: Run and verify missing schema/models fail**

Run: `php artisan test tests/Feature/EventSchemaTest.php tests/Unit/Models/EventTest.php`

Expected: FAIL because event types and tables do not exist.

- [ ] **Step 3: Implement migration, enums, models, scopes, and factories**

Use indexed foreign keys for organizer/user/event, a unique registration pair,
an index on `(cancelled_at, starts_at)`, and an index on
`(event_id, status)`. `majorChangesAllowed()` compares `now()` to the persisted
`starts_at->subDay()` without applying proposed request data.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/EventSchemaTest.php tests/Unit/Models/EventTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the domain schema**

```bash
git add database/migrations/2026_09_09_200000_create_events_and_registrations_tables.php app/Enums/EventRegistrationMode.php app/Enums/EventRegistrationStatus.php app/Models/Event.php app/Models/EventRegistration.php database/factories/EventFactory.php database/factories/EventRegistrationFactory.php app/Models/User.php tests/Feature/EventSchemaTest.php tests/Unit/Models/EventTest.php
git commit -m "feat(events): add event and registration domain model"
```

### Task 2: Event creation, listing, and privacy policy

**Files:**
- Create: `app/Actions/CreateEvent.php`
- Create: `app/Policies/EventPolicy.php`
- Create: `app/Http/Requests/StoreEventRequest.php`
- Create: `app/Http/Controllers/EventController.php`
- Create: `app/Http/Controllers/MyEventController.php`
- Create: `app/Data/EventSummaryData.php`
- Create: `app/Data/EventDetailData.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CreateEventTest.php`
- Test: `tests/Feature/EventVisibilityTest.php`

**Interfaces:**
- Produces: `CreateEvent::handle(User $organizer, array $validated): Event`.
- Produces: routes `events.index|create|store|show` and `events.mine`.
- Produces: `EventSummaryData::from(Event $event, User $viewer): array` without private fields.
- Produces: `EventDetailData::from(Event $event, User $viewer): array` conditionally including `detailed_location` and accepted participant identities.

- [ ] **Step 1: Write failing creation and privacy tests**

```php
$response = $this->actingAs($organizer)->post(route('events.store'), [
    'title' => 'Une journée entre amis',
    'description' => 'Retrouvons-nous pour profiter du parc.',
    'general_location' => 'Disneyland Park',
    'detailed_location' => 'Sous l’horloge de Main Street Station',
    'starts_at' => '2026-10-10T10:30',
    'capacity' => 4,
    'registration_mode' => 'automatic',
]);
$response->assertRedirect();
$event = Event::firstOrFail();
expect($event->organizer->is($organizer))->toBeTrue()
    ->and($event->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-10 08:30');
```

Assert guests and incomplete users cannot access routes; ordinary/pending
viewers never receive `detailed_location` or `participants`; organizer and an
accepted member do.

- [ ] **Step 2: Run and verify missing routes/actions fail**

Run: `php artisan test tests/Feature/CreateEventTest.php tests/Feature/EventVisibilityTest.php`

Expected: FAIL with missing routes.

- [ ] **Step 3: Implement validation, Action, policy, presenters, controllers, and routes**

Validate bounded strings, `capacity >= 1`, future Paris-local datetime, and
enum mode. Parse with `CarbonImmutable::createFromFormat(..., 'Europe/Paris')`
and persist UTC. The list query excludes cancelled and started events plus any
event whose organizer has a block relationship with the viewer.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/CreateEventTest.php tests/Feature/EventVisibilityTest.php`

Expected: PASS.

- [ ] **Step 5: Commit creation and privacy boundary**

```bash
git add app/Actions/CreateEvent.php app/Policies/EventPolicy.php app/Http/Requests/StoreEventRequest.php app/Http/Controllers/EventController.php app/Http/Controllers/MyEventController.php app/Data/EventSummaryData.php app/Data/EventDetailData.php routes/web.php tests/Feature/CreateEventTest.php tests/Feature/EventVisibilityTest.php
git commit -m "feat(events): add private event creation and discovery"
```

### Task 3: Capacity-safe registration transitions

**Files:**
- Create: `app/Actions/RegisterForEvent.php`
- Create: `app/Actions/DecideEventRegistration.php`
- Create: `app/Actions/WithdrawFromEvent.php`
- Create: `app/Actions/RemoveEventParticipant.php`
- Create: `app/Http/Controllers/EventRegistrationController.php`
- Create: `app/Http/Controllers/EventRegistrationDecisionController.php`
- Create: `app/Http/Controllers/EventRegistrationRemovalController.php`
- Modify: `routes/web.php`
- Create: `lang/fr/events.php`
- Create: `lang/en/events.php`
- Modify: `app/Support/FrontendTranslations.php`
- Test: `tests/Feature/EventRegistrationTest.php`
- Test: `tests/Feature/EventRegistrationConcurrencyTest.php`

**Interfaces:**
- Produces: `RegisterForEvent::handle(User $member, Event $event): EventRegistration`.
- Produces: `DecideEventRegistration::handle(User $organizer, EventRegistration $registration, bool $accept): EventRegistration`.
- Produces: `WithdrawFromEvent::handle(User $member, Event $event): EventRegistration`.
- Produces: `RemoveEventParticipant::handle(User $organizer, EventRegistration $registration): EventRegistration`.

- [ ] **Step 1: Write failing state transition tests**

Cover automatic acceptance/full failure, manual pending/accept/refuse,
organizer-only decisions, withdrawal before start, withdrawn re-registration,
terminal refused/removed states, duplicate requests, no waitlist promotion, and
block checks in both directions.

```php
$registration = app(RegisterForEvent::class)->handle($member, $automaticEvent);
expect($registration->status)->toBe(EventRegistrationStatus::Accepted);

app(DecideEventRegistration::class)->handle($organizer, $pending, false);
expect(fn () => app(RegisterForEvent::class)->handle($member, $event))
    ->toThrow(ValidationException::class);
```

- [ ] **Step 2: Write and run the concurrency harness**

Use two independent database connections and synchronized transactions, as in
`tests/Feature/CreateSwipeConcurrencyTest.php`, to race two automatic joins for
one remaining place and two organizer acceptances for one remaining place.

Run: `php artisan test tests/Feature/EventRegistrationTest.php tests/Feature/EventRegistrationConcurrencyTest.php`

Expected: FAIL because Actions are absent.

- [ ] **Step 3: Implement every transition with an event row lock**

Inside `DB::transaction`, reload `Event::lockForUpdate()`, re-check cancellation,
start, blocks, terminal status, and `occupiedPlaces()`. Use `updateOrCreate` only
for absent/withdrawn registrations; never overwrite terminal states. Translate
all ValidationException messages through `events.php`.

- [ ] **Step 4: Run transition and concurrency tests**

Run: `php artisan test tests/Feature/EventRegistrationTest.php tests/Feature/EventRegistrationConcurrencyTest.php`

Expected: PASS with accepted count never above capacity.

- [ ] **Step 5: Commit registration workflows**

```bash
git add app/Actions/RegisterForEvent.php app/Actions/DecideEventRegistration.php app/Actions/WithdrawFromEvent.php app/Actions/RemoveEventParticipant.php app/Http/Controllers/EventRegistrationController.php app/Http/Controllers/EventRegistrationDecisionController.php app/Http/Controllers/EventRegistrationRemovalController.php routes/web.php lang/fr/events.php lang/en/events.php app/Support/FrontendTranslations.php tests/Feature/EventRegistrationTest.php tests/Feature/EventRegistrationConcurrencyTest.php
git commit -m "feat(events): add capacity-safe registrations"
```

### Task 4: Editing, cancellation, and event notifications

**Files:**
- Create: `app/Actions/UpdateEvent.php`
- Create: `app/Actions/CancelEvent.php`
- Create: `app/Notifications/EventLifecycleNotification.php`
- Create: `app/Enums/EventNotificationType.php`
- Create: `app/Http/Requests/UpdateEventRequest.php`
- Create: `app/Http/Controllers/EventCancellationController.php`
- Modify: `app/Http/Controllers/EventController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/UpdateEventTest.php`
- Test: `tests/Feature/CancelEventTest.php`
- Test: `tests/Feature/EventNotificationTest.php`

**Interfaces:**
- Produces: `UpdateEvent::handle(User $organizer, Event $event, array $validated): Event`.
- Produces: `CancelEvent::handle(User $organizer, Event $event): Event`.
- Produces: `EventLifecycleNotification(Event $event, EventNotificationType $type)` targeting the event without embedding its private location.
- Produces: enum cases `Accepted`, `Refused`, `Removed`, `Changed`, `Cancelled`.

- [ ] **Step 1: Write failing boundary and notification tests**

Freeze time and cover exactly 24 hours (allowed), 23:59:59 (major fields
rejected), attempted late postponement (rejected using old start), title and
description before start, mode locked after any registration, capacity
increase, and reduction below occupied count. Assert changed/cancelled
notifications reach accepted and pending members only and contain no detailed
location.

- [ ] **Step 2: Run and verify failures**

Run: `php artisan test tests/Feature/UpdateEventTest.php tests/Feature/CancelEventTest.php tests/Feature/EventNotificationTest.php`

Expected: FAIL on missing update/cancel contracts.

- [ ] **Step 3: Implement locked update/cancel and after-commit notifications**

Capture original fields before update. Determine `Changed` only when date,
time, general location, or detailed location differs. After the transaction
commits, notify current pending/accepted members. Cancellation sets
`cancelled_at` idempotently and never changes registration statuses.

Wire acceptance, refusal, and removal Actions from Task 3 to
`EventLifecycleNotification` after their transactions commit.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/UpdateEventTest.php tests/Feature/CancelEventTest.php tests/Feature/EventNotificationTest.php`

Expected: PASS.

- [ ] **Step 5: Commit lifecycle management**

```bash
git add app/Actions/UpdateEvent.php app/Actions/CancelEvent.php app/Actions/DecideEventRegistration.php app/Actions/RemoveEventParticipant.php app/Notifications/EventLifecycleNotification.php app/Enums/EventNotificationType.php app/Http/Requests/UpdateEventRequest.php app/Http/Controllers/EventCancellationController.php app/Http/Controllers/EventController.php routes/web.php tests/Feature/UpdateEventTest.php tests/Feature/CancelEventTest.php tests/Feature/EventNotificationTest.php
git commit -m "feat(events): add event lifecycle and notifications"
```

### Task 5: Integrate blocking, export, and account deletion

**Files:**
- Create: `app/Actions/BlockEventRegistrations.php`
- Modify: `app/Actions/BlockUser.php`
- Modify: `app/Actions/BuildUserDataExport.php`
- Modify: `app/Actions/RequestAccountDeletion.php`
- Modify: `app/Actions/DeleteMember.php`
- Modify: `app/Jobs/PurgeDeletedUser.php`
- Test: `tests/Feature/BlockUserTest.php`
- Modify: `tests/Feature/Settings/UserDataExportTest.php`
- Modify: `tests/Feature/Settings/DirectUserDataExportTest.php`
- Modify: `tests/Feature/Settings/AccountDeletionTest.php`

**Interfaces:**
- Produces: `BlockEventRegistrations::handle(User $first, User $second): void` called inside the existing block transaction.
- Extends export keys with `organized_events`, `event_registrations`, and `notifications`.

- [ ] **Step 1: Add failing privacy lifecycle tests**

Assert blocking the organizer/member in either direction changes pending or
accepted registration to `blocked`, releases capacity, sends no notification,
and hides private event fields. Assert user export includes only their event
data and no other participant identity/message body. Assert account deletion
cancels organized future events before access revocation and purge removes
registrations/notifications safely.

- [ ] **Step 2: Run and verify failures**

Run: `php artisan test tests/Feature/BlockUserTest.php tests/Feature/Settings/UserDataExportTest.php tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Settings/AccountDeletionTest.php`

Expected: FAIL on missing event integration.

- [ ] **Step 3: Implement transactional integration**

Lock relevant event rows in ascending ID order in `BlockEventRegistrations`,
then update only `pending`/`accepted` rows to `blocked`. Extend export using
explicit selected fields and presenter-safe values. Cancel organized future
events through the same domain rule before deferred deletion; do not notify for
the block itself.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/BlockUserTest.php tests/Feature/Settings/UserDataExportTest.php tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Settings/AccountDeletionTest.php`

Expected: PASS.

- [ ] **Step 5: Commit privacy lifecycle integration**

```bash
git add app/Actions/BlockEventRegistrations.php app/Actions/BlockUser.php app/Actions/BuildUserDataExport.php app/Actions/RequestAccountDeletion.php app/Actions/DeleteMember.php app/Jobs/PurgeDeletedUser.php tests/Feature/BlockUserTest.php tests/Feature/Settings
git commit -m "feat(events): integrate blocking and data controls"
```

### Task 6: Event Inertia pages and five-item navigation

**Files:**
- Create: `resources/js/pages/Events/Index.vue`
- Create: `resources/js/pages/Events/Mine.vue`
- Create: `resources/js/pages/Events/Create.vue`
- Create: `resources/js/pages/Events/Edit.vue`
- Create: `resources/js/pages/Events/Show.vue`
- Create: `resources/js/components/events/EventCard.vue`
- Create: `resources/js/components/events/EventForm.vue`
- Create: `resources/js/components/events/EventRegistrationActions.vue`
- Create: `resources/js/components/events/OrganizerRegistrations.vue`
- Create: `resources/js/types/event.ts`
- Create: `resources/js/lib/eventState.ts`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `resources/js/composables/useMemberNavigationVisibility.ts`
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Test: `tests/Frontend/eventState.test.js`
- Test: `tests/Feature/Localization/InertiaTranslationsTest.php`

**Interfaces:**
- Consumes: event routes and DTOs from Tasks 2–4.
- Produces: five-item navigation in order Découvrir, Événements, Messages, Notifications, Profil.
- Produces: `eventState.ts` pure helpers for capacity labels, available actions, and Paris-local form values.

- [ ] **Step 1: Write failing pure frontend and translation tests**

```js
expect(availableEventActions({ role: 'member', status: 'accepted', started: false }))
    .toEqual(['withdraw']);
expect(availableEventActions({ role: 'pending', status: 'pending', started: false }))
    .toEqual([]);
```

Assert both catalogs cover page titles, fields, modes, statuses, action labels,
confirmation dialogs, capacity, privacy explanation, empty states, and errors.

- [ ] **Step 2: Run and verify missing frontend contract fails**

Run: `bun test tests/Frontend/eventState.test.js && php artisan test tests/Feature/Localization/InertiaTranslationsTest.php`

Expected: FAIL.

- [ ] **Step 3: Build responsive accessible pages and components**

Use existing Button, Card, Dialog, form input, error, and loading patterns.
Never infer privacy in Vue: render private sections only when present in the
server DTO. Keep all mutations busy-safe and show confirmation for cancellation,
refusal, and removal. Add the Events navigation item between Discovery and
Conversations.

- [ ] **Step 4: Run frontend checks**

Run: `bun test tests/Frontend/eventState.test.js && bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 5: Commit event UI**

```bash
git add resources/js/pages/Events resources/js/components/events resources/js/types/event.ts resources/js/lib/eventState.ts resources/js/components/MemberBottomNavigation.vue resources/js/composables/useMemberNavigationVisibility.ts lang/fr/events.php lang/en/events.php tests/Frontend/eventState.test.js tests/Feature/Localization/InertiaTranslationsTest.php
git commit -m "feat(events): add accessible member event interface"
```

### Task 7: Browser journeys and product documentation

**Files:**
- Create: `tests/Browser/EventTest.php`
- Modify: `docs/PRD.md`
- Modify: `docs/data-model.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/technical-architecture.md`

**Interfaces:**
- Consumes: complete event backend/UI and notification center.
- Produces: browser evidence for both registration modes, privacy, lifecycle, and notification navigation.

- [ ] **Step 1: Add failing Chromium journeys**

Create independent tests for: organizer creates an automatic event and member
joins; organizer accepts/refuses manual requests; pending viewer cannot see
private data; accepted viewer can; organizer changes date/location and member
opens the resulting notification; member withdraws; organizer removes; and
organizer cancels with historical visibility.

- [ ] **Step 2: Run browser tests and diagnose any failures**

Run: `php artisan test tests/Browser/EventTest.php --display-warnings`

Expected: PASS after the complete implementation; any failure must be fixed in
the owning task’s focused files before continuing.

- [ ] **Step 3: Update product, data, privacy, and architecture documents**

Move events out of the PRD’s MVP exclusion and add an implemented capability
row. Document both tables and state transitions in `data-model.md`, private
location/participant/notification constraints in `security-privacy.md`, and
Actions/Policies/routes/realtime notification flow in
`technical-architecture.md`.

- [ ] **Step 4: Run full relevant verification**

Run: `composer ci:check`

Expected: Pint, PHPStan, Wayfinder, frontend unit tests, Vite build, database
guard, and all Pest tests PASS.

Run: `docker build --target runtime --tag dlp-friends:ci .`

Expected: production image build PASS.

- [ ] **Step 5: Commit final verification artifacts**

```bash
git add tests/Browser/EventTest.php docs/PRD.md docs/data-model.md docs/security-privacy.md docs/technical-architecture.md
git commit -m "docs(events): document friendly event delivery"
```
