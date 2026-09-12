# Persistent Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace ephemeral match/message alerts with a persistent, filterable in-app notification center while preserving realtime presentation.

**Architecture:** Laravel database and broadcast notifications become the durable notification contract. Focused controllers expose a paginated Inertia index and read mutations; the member shell shares only the unread count. Existing match and message Actions create notifications after committed domain writes, without storing message bodies.

**Tech Stack:** PHP 8.4, Laravel 13 notifications, MySQL 8.4, Inertia 3, Vue 3 Composition API, TypeScript, Reka UI, Pest, Pest Browser.

**Spec:** `docs/superpowers/specs/2026-09-09-friendly-events-notifications-profile-like-design.md`

## Global Constraints

- Notifications are in-app only; do not add e-mail or system push channels.
- Store no message body, detailed event location, e-mail, secret, or unnecessary personal data in notification payloads.
- Existing realtime match dialogs and message toasts remain immediate and must not be duplicated.
- All visible copy and validation messages must exist in French and English catalogs.
- Member routes retain the existing `auth`, `verified`, `social`, `profile.complete`, and `onboarding.complete` middleware chain.
- No historical notification backfill.

---

### Task 1: Notification persistence contract

**Files:**
- Create: `database/migrations/2026_09_09_100000_create_notifications_table.php`
- Create: `app/Enums/NotificationCategory.php`
- Create: `app/Notifications/NewMatchNotification.php`
- Create: `app/Notifications/NewMessageNotification.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/NotificationSchemaTest.php`
- Test: `tests/Feature/MemberNotificationTest.php`

**Interfaces:**
- Produces: `NotificationCategory::{Conversations,Events}` string-backed enum.
- Produces: `NewMatchNotification(MemberMatch $match, User $otherMember)` and `NewMessageNotification(Message $message)` using `database` and `broadcast` channels.
- Produces payload shape: `{category: string, translation_key: string, parameters: array<string,string|int>, target_type: 'conversation', target_id: int}`.

- [ ] **Step 1: Write failing schema and payload tests**

```php
it('stores database notifications for users', function () {
    expect(Schema::hasColumns('notifications', [
        'id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at',
    ]))->toBeTrue();
});

it('persists a message notification without its body', function () {
    Notification::fake();
    [$author, $recipient, $conversation] = memberConversation();
    $message = Message::factory()->for($conversation)->for($author, 'author')->create([
        'content' => 'private body',
    ]);

    $recipient->notify(new NewMessageNotification($message));

    Notification::assertSentTo($recipient, NewMessageNotification::class,
        fn ($notification) => ! str_contains(json_encode($notification->toArray($recipient)), 'private body'));
});
```

- [ ] **Step 2: Run the tests and verify the missing migration/classes fail**

Run: `php artisan test tests/Feature/NotificationSchemaTest.php tests/Feature/MemberNotificationTest.php`

Expected: FAIL because the notifications table and notification classes do not exist.

- [ ] **Step 3: Add the standard morph-based notifications migration, enum, relations, and minimal notification classes**

```php
enum NotificationCategory: string
{
    case Conversations = 'conversations';
    case Events = 'events';
}
```

Each notification implements `ShouldQueue`, uses the `Queueable` trait, calls
`afterCommit()` in its constructor, returns `['database', 'broadcast']` from
`via()`, and derives `target_id` from the persisted conversation.
`toBroadcast()` must reuse the same minimal array as `toArray()` and expose the
notification UUID assigned by Laravel.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/NotificationSchemaTest.php tests/Feature/MemberNotificationTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the persistence contract**

```bash
git add database/migrations/2026_09_09_100000_create_notifications_table.php app/Enums/NotificationCategory.php app/Notifications app/Models/User.php tests/Feature/NotificationSchemaTest.php tests/Feature/MemberNotificationTest.php
git commit -m "feat(notifications): add persistent notification contract"
```

### Task 2: Persist existing match and message events

**Files:**
- Modify: `app/Actions/CreateSwipe.php`
- Modify: `app/Actions/SendMessage.php`
- Modify: `app/Events/MatchCreated.php`
- Modify: `app/Events/MessageSent.php`
- Test: `tests/Feature/CreateSwipeTest.php`
- Test: `tests/Feature/StoreMessageTest.php`
- Test: `tests/Feature/MessageBroadcastTest.php`

**Interfaces:**
- Consumes: `NewMatchNotification` and `NewMessageNotification` from Task 1.
- Produces: exactly one durable notification per recipient/domain occurrence while retaining current `.match.created` and `.message.sent` broadcast contracts.

- [ ] **Step 1: Add failing behavior tests**

```php
Notification::fake();
$match = app(CreateSwipe::class)->handle($second, $first, SwipeDecision::Like);
Notification::assertSentToTimes($first, NewMatchNotification::class, 1);
Notification::assertSentToTimes($second, NewMatchNotification::class, 1);

$message = app(SendMessage::class)->handle($author, $conversation, 'secret');
Notification::assertSentTo($recipient, NewMessageNotification::class);
Notification::assertNotSentTo($author, NewMessageNotification::class);
```

Also assert duplicate/concurrent match creation cannot notify either member twice.

- [ ] **Step 2: Run targeted tests and verify notification assertions fail**

Run: `php artisan test tests/Feature/CreateSwipeTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php`

Expected: FAIL only on the new notification assertions.

- [ ] **Step 3: Notify recipients after domain persistence**

In `CreateSwipe`, inside the `if ($matchCreated === 1)` branch, call
`$recipient->notify(new NewMatchNotification($match, $otherMember))` for both
members. In `SendMessage`, resolve the other match member and call
`notify(new NewMessageNotification($message))`. Keep `MatchCreated::dispatch`
and `MessageSent::dispatch` unchanged for current realtime UI contracts.

- [ ] **Step 4: Run the targeted suite**

Run: `php artisan test tests/Feature/CreateSwipeTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php`

Expected: PASS.

- [ ] **Step 5: Commit event integration**

```bash
git add app/Actions/CreateSwipe.php app/Actions/SendMessage.php app/Events/MatchCreated.php app/Events/MessageSent.php tests/Feature/CreateSwipeTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php
git commit -m "feat(notifications): persist match and message alerts"
```

### Task 3: Notification HTTP API and filtering

**Files:**
- Create: `app/Http/Controllers/NotificationIndexController.php`
- Create: `app/Http/Controllers/NotificationReadController.php`
- Create: `app/Http/Controllers/NotificationReadAllController.php`
- Create: `app/Http/Requests/NotificationIndexRequest.php`
- Create: `app/Support/MemberNotificationPresenter.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Test: `tests/Feature/NotificationCenterTest.php`

**Interfaces:**
- Produces: `GET /notifications?category=conversations|events&unread=1` named `notifications.index`.
- Produces: `PATCH /notifications/{notification}/read` named `notifications.read`.
- Produces: `PATCH /notifications/read-all` named `notifications.read-all`.
- Produces shared prop `auth.unread_notifications_count: int`.

- [ ] **Step 1: Write failing authorization, filtering, pagination, and read tests**

```php
$this->actingAs($member)->get(route('notifications.index', [
    'category' => 'events', 'unread' => 1,
]))->assertOk()->assertInertia(fn (Assert $page) => $page
    ->component('Notifications/Index')
    ->where('filters.category', 'events')
    ->where('filters.unread', true)
    ->has('notifications.data', 1));

$this->actingAs($other)->patch(route('notifications.read', $notification))->assertNotFound();
$this->actingAs($member)->patch(route('notifications.read', $notification))
    ->assertRedirect($expectedConversationUrl);
```

Cover invalid categories, a deleted/inaccessible target, mark-all limited to the authenticated member, and the shared unread count.

- [ ] **Step 2: Run and verify routes/controllers are missing**

Run: `php artisan test tests/Feature/NotificationCenterTest.php`

Expected: FAIL with missing routes.

- [ ] **Step 3: Implement request validation, presenter, controllers, and routes**

`NotificationIndexRequest` accepts nullable enum category and boolean unread.
`MemberNotificationPresenter::present(DatabaseNotification $notification): array`
returns `id`, `category`, `translation_key`, `parameters`, `target_url`,
`read_at`, and `created_at`. Resolve target URLs server-side from the typed
payload; never accept an arbitrary URL from stored data or the request.

- [ ] **Step 4: Run focused tests**

Run: `php artisan test tests/Feature/NotificationCenterTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the HTTP contract**

```bash
git add app/Http/Controllers/NotificationIndexController.php app/Http/Controllers/NotificationReadController.php app/Http/Controllers/NotificationReadAllController.php app/Http/Requests/NotificationIndexRequest.php app/Support/MemberNotificationPresenter.php app/Http/Middleware/HandleInertiaRequests.php routes/web.php tests/Feature/NotificationCenterTest.php
git commit -m "feat(notifications): add filterable notification center API"
```

### Task 4: Notification center UI and navigation badge

**Files:**
- Create: `resources/js/pages/Notifications/Index.vue`
- Create: `resources/js/components/notifications/NotificationFilters.vue`
- Create: `resources/js/components/notifications/NotificationItem.vue`
- Create: `resources/js/types/notification.ts`
- Create: `resources/js/lib/notificationFilters.ts`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `resources/js/types/auth.ts`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Create: `lang/fr/notifications.php`
- Create: `lang/en/notifications.php`
- Modify: `app/Support/FrontendTranslations.php`
- Test: `tests/Frontend/notificationFilters.test.js`
- Test: `tests/Feature/Localization/BackendTranslationsTest.php`
- Test: `tests/Feature/Localization/InertiaTranslationsTest.php`

**Interfaces:**
- Consumes: routes and payload from Task 3.
- Produces: a four-item interim member navigation and accessible unread badge;
  the events plan inserts the fifth destination.
- Produces: `applyNotificationFilters(category: string|null, unread: boolean): URLSearchParams` as a pure tested helper.

- [ ] **Step 1: Write failing frontend and localization tests**

```js
expect(applyNotificationFilters('events', true).toString())
    .toBe('category=events&unread=1');
expect(applyNotificationFilters(null, false).toString()).toBe('');
```

Assert the FR/EN catalogs contain navigation, filters, empty state, read state,
mark-all action, and accessible unread-count strings.

- [ ] **Step 2: Run tests and verify missing UI contract fails**

Run: `bun test tests/Frontend/notificationFilters.test.js && php artisan test tests/Feature/Localization/BackendTranslationsTest.php tests/Feature/Localization/InertiaTranslationsTest.php`

Expected: FAIL on the missing helper and translations.

- [ ] **Step 3: Implement the page, filters, items, translations, and navigation**

Render four destinations in this order: discovery, conversations,
notifications, profile. Do not introduce an unresolved Events route here; the
events plan inserts it between discovery and conversations. The badge uses
visible text for counts below 100 and `99+` above it, plus a localized
`aria-label` with the exact count.

- [ ] **Step 4: Run frontend, localization, type, and build checks**

Run: `bun test tests/Frontend/notificationFilters.test.js && php artisan test tests/Feature/Localization/BackendTranslationsTest.php tests/Feature/Localization/InertiaTranslationsTest.php && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 5: Commit the notification UI**

```bash
git add resources/js/pages/Notifications resources/js/components/notifications resources/js/types/notification.ts resources/js/lib/notificationFilters.ts resources/js/components/MemberBottomNavigation.vue resources/js/types/auth.ts lang/fr/common.php lang/en/common.php lang/fr/notifications.php lang/en/notifications.php app/Support/FrontendTranslations.php tests/Frontend/notificationFilters.test.js tests/Feature/Localization
git commit -m "feat(notifications): add persistent notification center UI"
```

### Task 5: Realtime synchronization and browser journey

**Files:**
- Modify: `resources/js/composables/useMemberRealtimeNotifications.ts`
- Modify: `resources/js/lib/memberNotifications.ts`
- Modify: `resources/js/layouts/MemberLayout.vue`
- Modify: `resources/js/pages/Notifications/Index.vue`
- Modify: `tests/Frontend/memberNotifications.test.js`
- Create: `tests/Browser/NotificationCenterTest.php`

**Interfaces:**
- Consumes: Laravel broadcast notification payload and shared unread count.
- Produces: one unread increment per new UUID, existing match dialog behavior, and no duplicate message toast.

- [ ] **Step 1: Add failing deduplication and browser tests**

```js
expect(registerNotification(seen, { id: 'same-id' })).toBe(true);
expect(registerNotification(seen, { id: 'same-id' })).toBe(false);
```

The browser test creates a notification, opens Notifications, filters unread
conversations, clicks the item, lands on the conversation, and verifies the
badge decreases.

- [ ] **Step 2: Run tests and confirm failure**

Run: `bun test tests/Frontend/memberNotifications.test.js && php artisan test tests/Browser/NotificationCenterTest.php`

Expected: FAIL because durable broadcast synchronization is absent.

- [ ] **Step 3: Subscribe to broadcast notifications and reconcile state**

Keep the existing `.match.created`/`.message.sent` listeners for presentation.
Add Laravel notification UUID tracking for unread counts and notification-list
insertion. When the current page already received the same UUID through an
Inertia response, ignore the realtime duplicate.

- [ ] **Step 4: Run targeted and full plan checks**

Run: `bun test tests/Frontend/memberNotifications.test.js tests/Frontend/notificationFilters.test.js && php artisan test tests/Feature/MemberNotificationTest.php tests/Feature/NotificationCenterTest.php tests/Browser/NotificationCenterTest.php && composer lint:check && composer analyse && bun run types:check && bun run build`

Expected: PASS.

- [ ] **Step 5: Commit realtime integration**

```bash
git add resources/js/composables/useMemberRealtimeNotifications.ts resources/js/lib/memberNotifications.ts resources/js/layouts/MemberLayout.vue resources/js/pages/Notifications/Index.vue tests/Frontend/memberNotifications.test.js tests/Browser/NotificationCenterTest.php
git commit -m "feat(notifications): synchronize persistent realtime alerts"
```
