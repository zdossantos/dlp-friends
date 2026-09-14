# Event Group Chat Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter à chaque événement un chat de groupe textuel temps réel, privé, avec compteurs non lus persistants et lecture seule après annulation ou sept jours après l’événement.

**Architecture:** Un agrégat `EventChat` distinct de la messagerie privée possède ses messages et curseurs de lecture. Une Policy unique protège les routes et le canal Reverb ; le panneau adaptatif des événements expose un onglet Discussion et réutilise les patterns d’historique, de déduplication et de reconnexion existants.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Pest, Reverb, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Bun 1.3.14, Pest Browser/Playwright.

**Spec:** `docs/superpowers/specs/2026-09-14-event-group-chat-design.md`

## Global Constraints

- Le chat reste séparé des `conversations` privées et n’ajoute ni pièce jointe, GIF, réaction, sondage, édition, suppression, présence ou saisie.
- Le contenu est du texte obligatoire limité à 2 000 caractères et l’auteur vient toujours de la session authentifiée.
- Seuls l’organisateur et les inscriptions `accepted` peuvent lire ; désinscription, retrait et blocage révoquent immédiatement l’accès.
- L’annulation et l’instant `starts_at + 7 jours` rendent l’envoi impossible sans supprimer l’historique.
- Aucun accusé individuel n’est exposé ; le curseur de lecture reste privé au membre.
- Tout texte visible, erreur et libellé accessible est fourni en français et en anglais.
- Le parcours reste utilisable au clavier, en thèmes clair/sombre et dès 320 px.
- Toute règle métier ou régression suit rouge, vert, refactorisation avec Pest.

---

### Task 1: Persist the one-to-one event chat aggregate

**Files:**
- Create: `database/migrations/2026_09_14_100000_create_event_chats_tables.php`
- Create: `app/Models/EventChat.php`
- Create: `app/Models/EventChatMessage.php`
- Create: `app/Models/EventChatRead.php`
- Create: `database/factories/EventChatFactory.php`
- Create: `database/factories/EventChatMessageFactory.php`
- Modify: `app/Models/Event.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/EventChatSchemaTest.php`
- Test: `tests/Unit/Models/EventChatTest.php`

**Interfaces:**
- Produces: `Event::chat(): HasOne`, `EventChat::event(): BelongsTo`, `EventChat::messages(): HasMany`, `EventChat::reads(): HasMany`, `EventChatMessage::author(): BelongsTo`, `EventChatRead::lastReadMessage(): BelongsTo`.
- Produces: unique `event_chats.event_id`, unique `event_chat_reads(event_chat_id,user_id)`, indexes for ordered history and unread counts.

- [ ] **Step 1: Write failing schema and relationship tests**

```php
it('stores one cascading chat per event with messages and member read cursors', function () {
    $event = Event::factory()->create();
    $chat = EventChat::factory()->for($event)->create();
    $author = User::factory()->create();
    $message = EventChatMessage::factory()->for($chat)->for($author, 'author')->create();
    EventChatRead::query()->create([
        'event_chat_id' => $chat->id,
        'user_id' => $author->id,
        'last_read_message_id' => $message->id,
    ]);

    expect($event->chat->is($chat))->toBeTrue();
    expect(fn () => EventChat::factory()->for($event)->create())
        ->toThrow(QueryException::class);

    $event->delete();
    expect(EventChat::find($chat->id))->toBeNull()
        ->and(EventChatMessage::find($message->id))->toBeNull();
});
```

- [ ] **Step 2: Run tests to verify the schema is absent**

Run: `php artisan test tests/Feature/EventChatSchemaTest.php tests/Unit/Models/EventChatTest.php`
Expected: FAIL because the models/tables do not exist.

- [ ] **Step 3: Add the migration, models, factories, and relations**

```php
Schema::create('event_chats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_id')->unique()->constrained()->cascadeOnDelete();
    $table->timestamps();
});

Schema::create('event_chat_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_chat_id')->constrained()->cascadeOnDelete();
    $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
    $table->text('content');
    $table->timestamps();
    $table->index(['event_chat_id', 'id']);
});

Schema::create('event_chat_reads', function (Blueprint $table) {
    $table->id();
    $table->foreignId('event_chat_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('last_read_message_id')->nullable()
        ->constrained('event_chat_messages')->nullOnDelete();
    $table->timestamps();
    $table->unique(['event_chat_id', 'user_id']);
});
```

At the end of `up()`, backfill existing events with an `insertOrIgnore` selecting every event id. Define `down()` in reverse dependency order. Add typed Eloquent relations and PHPDoc matching existing model conventions.

- [ ] **Step 4: Run the focused tests**

Run: `php artisan test tests/Feature/EventChatSchemaTest.php tests/Unit/Models/EventChatTest.php`
Expected: PASS.

- [ ] **Step 5: Commit the aggregate**

```bash
git add database/migrations/2026_09_14_100000_create_event_chats_tables.php app/Models/EventChat.php app/Models/EventChatMessage.php app/Models/EventChatRead.php database/factories/EventChatFactory.php database/factories/EventChatMessageFactory.php app/Models/Event.php app/Models/User.php tests/Feature/EventChatSchemaTest.php tests/Unit/Models/EventChatTest.php
git commit -m "feat(events): add group chat persistence"
```

### Task 2: Guarantee chat creation with every event

**Files:**
- Modify: `app/Actions/CreateEvent.php`
- Test: `tests/Feature/CreateEventTest.php`

**Interfaces:**
- Consumes: `Event::chat(): HasOne` from Task 1.
- Produces: `CreateEvent::handle(User $organizer, array $validated): Event` returning an event whose chat exists, with repeated chat guarantee idempotent through `firstOrCreate()`.

- [ ] **Step 1: Add a failing atomic/idempotent creation test**

```php
it('creates exactly one chat with the event', function () {
    $event = app(CreateEvent::class)->handle($this->user, validEventData());

    expect($event->chat)->not->toBeNull()
        ->and(EventChat::where('event_id', $event->id)->count())->toBe(1);

    $event->chat()->firstOrCreate();
    expect(EventChat::where('event_id', $event->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Verify the test fails because creation does not guarantee a chat**

Run: `php artisan test tests/Feature/CreateEventTest.php --filter='creates exactly one chat'`
Expected: FAIL with a missing related chat.

- [ ] **Step 3: Wrap event and chat creation in one transaction**

```php
return DB::transaction(function () use ($organizer, $validated): Event {
    $event = $organizer->organizedEvents()->create($validated);
    $event->chat()->firstOrCreate();

    return $event->load('chat');
});
```

Keep the existing Europe/Paris-to-UTC normalization before the transaction.

- [ ] **Step 4: Run event creation and schema tests**

Run: `php artisan test tests/Feature/CreateEventTest.php tests/Feature/EventChatSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit atomic creation**

```bash
git add app/Actions/CreateEvent.php tests/Feature/CreateEventTest.php
git commit -m "feat(events): create chat with event"
```

### Task 3: Centralize access and writable-window authorization

**Files:**
- Create: `app/Policies/EventChatPolicy.php`
- Modify: `app/Models/EventChat.php`
- Test: `tests/Feature/EventChatAuthorizationTest.php`

**Interfaces:**
- Produces: `EventChat::isReadOnly(): bool` and `EventChatPolicy::view(User $user, EventChat $chat): bool`, `send(User $user, EventChat $chat): bool`.
- Rule: `send` calls the same membership predicate as `view`, then rejects `cancelled_at !== null` and `now() >= starts_at->addDays(7)`.

- [ ] **Step 1: Write the authorization matrix before implementation**

```php
it('allows only the organizer and accepted registrations to view', function (string $role, bool $allowed) {
    [$user, $chat] = eventChatScenario($role);
    expect(Gate::forUser($user)->allows('view', $chat))->toBe($allowed);
})->with([
    'organizer' => ['organizer', true],
    'accepted' => ['accepted', true],
    'pending' => ['pending', false],
    'refused' => ['refused', false],
    'withdrawn' => ['withdrawn', false],
    'removed' => ['removed', false],
    'blocked' => ['blocked', false],
    'stranger' => ['stranger', false],
]);

it('becomes read only on cancellation and exactly seven days after start', function () {
    // Assert send allowed one second before the boundary, denied at the boundary,
    // and denied immediately when cancelled while view remains allowed.
});
```

- [ ] **Step 2: Run the matrix and verify it fails**

Run: `php artisan test tests/Feature/EventChatAuthorizationTest.php`
Expected: FAIL because no chat policy exists.

- [ ] **Step 3: Implement the policy and derived read-only state**

```php
public function view(User $user, EventChat $chat): bool
{
    return $chat->event->organizer_user_id === $user->id
        || $chat->event->registrations()
            ->where('user_id', $user->id)
            ->where('status', EventRegistrationStatus::Accepted)
            ->exists();
}

public function send(User $user, EventChat $chat): bool
{
    return $this->view($user, $chat) && ! $chat->isReadOnly();
}
```

Implement `isReadOnly()` from cancellation and the exact seven-day boundary. Rely on registration status `Blocked` written by `BlockEventRegistrations`; do not duplicate block queries.

- [ ] **Step 4: Run the authorization suite**

Run: `php artisan test tests/Feature/EventChatAuthorizationTest.php tests/Feature/BlockUserTest.php`
Expected: PASS.

- [ ] **Step 5: Commit authorization**

```bash
git add app/Policies/EventChatPolicy.php app/Models/EventChat.php tests/Feature/EventChatAuthorizationTest.php
git commit -m "feat(events): authorize group chat access"
```

### Task 4: Send and broadcast event chat messages exactly once

**Files:**
- Create: `app/Actions/SendEventChatMessage.php`
- Create: `app/Events/EventChatMessageSent.php`
- Create: `app/Http/Requests/StoreEventChatMessageRequest.php`
- Create: `app/Http/Controllers/EventChatMessageController.php`
- Modify: `routes/web.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/StoreEventChatMessageTest.php`
- Test: `tests/Feature/EventChatBroadcastTest.php`

**Interfaces:**
- Produces: `SendEventChatMessage::handle(User $author, EventChat $chat, string $content): EventChatMessage`.
- Produces: `POST /events/{event}/chat/messages`, route `events.chat.messages.store`.
- Produces: private channel `event-chat.{chat}` and event `.event-chat.message.sent` with `id`, `event_chat_id`, `author_user_id`, `content`, `author`, `created_at`, `updated_at`.

- [ ] **Step 1: Write failing HTTP validation and policy tests**

```php
it('stores a valid message with the authenticated author', function () {
    $response = $this->actingAs($this->accepted)->post(
        route('events.chat.messages.store', $this->event),
        ['content' => str_repeat('a', 2000)],
    );

    $response->assertSuccessful();
    $this->assertDatabaseHas('event_chat_messages', [
        'event_chat_id' => $this->event->chat->id,
        'author_user_id' => $this->accepted->id,
        'content' => str_repeat('a', 2000),
    ]);
});
```

Add datasets for empty, 2,001 characters, outsider, every revoked status, cancellation, and J+7. Assert every refusal creates zero rows and dispatches zero events.

- [ ] **Step 2: Run HTTP tests and verify route/action absence**

Run: `php artisan test tests/Feature/StoreEventChatMessageTest.php`
Expected: FAIL because the route is undefined.

- [ ] **Step 3: Implement request, controller, action, and route**

```php
final class StoreEventChatMessageRequest extends FormRequest
{
    public function rules(): array
    {
        return ['content' => ['required', 'string', 'max:2000']];
    }
}
```

In the action, start a transaction, lock the chat and event, authorize `send`, create through `$lockedChat->messages()`, dispatch `EventChatMessageSent`, and return the message. The controller resolves the event’s chat server-side; it never accepts a chat or author id from the browser.

- [ ] **Step 4: Add failing broadcast payload/channel tests**

```php
it('broadcasts once after commit only on the authorized chat channel', function () {
    Event::fake([EventChatMessageSent::class]);
    $message = app(SendEventChatMessage::class)
        ->handle($this->accepted, $this->event->chat, 'Rendez-vous devant l’entrée.');

    Event::assertDispatchedTimes(EventChatMessageSent::class, 1);
    expect((new EventChatMessageSent($message))->broadcastAs())
        ->toBe('event-chat.message.sent');
});
```

Also POST to `/broadcasting/auth` for organizer, accepted, pending, refused, withdrawn, removed, blocked, and stranger.

- [ ] **Step 5: Implement the after-commit broadcast and channel authorization**

Use `ShouldBroadcast`, `ShouldDispatchAfterCommit`, `PrivateChannel("event-chat.{$chatId}")`, and `Gate::forUser($user)->allows('view', $chat)`. Do not broadcast on personal channels in this task; membership transitions are handled separately.

- [ ] **Step 6: Run message and broadcast suites**

Run: `php artisan test tests/Feature/StoreEventChatMessageTest.php tests/Feature/EventChatBroadcastTest.php`
Expected: PASS.

- [ ] **Step 7: Commit messaging**

```bash
git add app/Actions/SendEventChatMessage.php app/Events/EventChatMessageSent.php app/Http/Requests/StoreEventChatMessageRequest.php app/Http/Controllers/EventChatMessageController.php routes/web.php routes/channels.php tests/Feature/StoreEventChatMessageTest.php tests/Feature/EventChatBroadcastTest.php
git commit -m "feat(events): send group chat messages"
```

### Task 5: Paginate history and persist unread cursors

**Files:**
- Create: `app/Actions/MarkEventChatRead.php`
- Create: `app/Data/EventChatData.php`
- Create: `app/Http/Controllers/EventChatController.php`
- Create: `app/Http/Controllers/EventChatReadController.php`
- Create: `app/Http/Requests/EventChatIndexRequest.php`
- Modify: `app/Data/EventSummaryData.php`
- Modify: `app/Data/EventDetailData.php`
- Modify: `app/Data/EventWorkspaceData.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/EventChatHistoryTest.php`
- Test: `tests/Feature/EventChatUnreadTest.php`

**Interfaces:**
- Produces: `GET /events/{event}/chat`, route `events.chat.show`, returning panel kind `chat` with a 10-message paginator ordered chronologically inside each page.
- Produces: `POST /events/{event}/chat/read`, route `events.chat.read.store`, accepting `last_read_message_id: int`.
- Produces: `MarkEventChatRead::handle(User $reader, EventChat $chat, int $messageId): void` with monotonic `updateOrCreate` behavior.
- Produces: authorized event summaries with `chatUnreadCount: number` and details with `chat: { id, isReadOnly, readOnlyReason }`.

- [ ] **Step 1: Write failing history pagination tests**

```php
it('returns the ten newest messages in chronological display order', function () {
    EventChatMessage::factory()->count(12)->for($this->event->chat)->create();

    $response = $this->actingAs($this->accepted)
        ->get(route('events.chat.show', $this->event));

    $messages = $response->inertiaProps('panel.messages.data');
    expect($messages)->toHaveCount(10)
        ->and(array_column($messages, 'id'))->toBe(collect($messages)->pluck('id')->sort()->values()->all());
});
```

Assert older-page loading, no foreign access, author presentation, and read-only metadata.

- [ ] **Step 2: Verify history tests fail**

Run: `php artisan test tests/Feature/EventChatHistoryTest.php`
Expected: FAIL because the route/data object is absent.

- [ ] **Step 3: Implement authorized history and workspace panel data**

Query descending ids with `simplePaginate(10)`, then reverse only the page collection for display. Add the `chat` panel union to backend data and choose `origin=mine` for deep links. Ensure discovery responses never expose chat metadata to unauthorized viewers.

- [ ] **Step 4: Write failing cursor and unread-count tests**

```php
it('advances only its own cursor and counts only later messages by others', function () {
    $own = EventChatMessage::factory()->for($this->event->chat)->for($this->accepted, 'author')->create();
    $other = EventChatMessage::factory()->for($this->event->chat)->for($this->organizer, 'author')->create();

    $this->actingAs($this->accepted)->post(route('events.chat.read.store', $this->event), [
        'last_read_message_id' => $other->id,
    ])->assertSuccessful();

    expect(EventChatRead::whereBelongsTo($this->accepted)->sole()->last_read_message_id)->toBe($other->id);
    expect(eventSummaryFor($this->accepted, $this->event)['chatUnreadCount'])->toBe(0);
});
```

Add tests for no cursor, own messages excluded, foreign chat id rejected, older cursor ignored, reconnect/server recalculation, and no per-participant receipt in any response.

- [ ] **Step 5: Implement monotonic read state and unread aggregation**

Authorize view, verify the target message belongs to the chat, lock the existing cursor, and update only when the target id is greater. Compute unread counts using `where('author_user_id', '!=', $viewer->id)` and `where('id', '>', $lastReadId ?? 0)` in eager aggregates to avoid N+1 queries.

- [ ] **Step 6: Run history, unread, and workspace tests**

Run: `php artisan test tests/Feature/EventChatHistoryTest.php tests/Feature/EventChatUnreadTest.php tests/Feature/EventWorkspaceTest.php`
Expected: PASS.

- [ ] **Step 7: Commit history and unread state**

```bash
git add app/Actions/MarkEventChatRead.php app/Data/EventChatData.php app/Http/Controllers/EventChatController.php app/Http/Controllers/EventChatReadController.php app/Http/Requests/EventChatIndexRequest.php app/Data/EventSummaryData.php app/Data/EventDetailData.php app/Data/EventWorkspaceData.php routes/web.php tests/Feature/EventChatHistoryTest.php tests/Feature/EventChatUnreadTest.php tests/Feature/EventWorkspaceTest.php
git commit -m "feat(events): add chat history and unread counts"
```

### Task 6: Notify clients immediately when chat access changes

**Files:**
- Create: `app/Events/EventChatAccessChanged.php`
- Modify: `app/Actions/DecideEventRegistration.php`
- Modify: `app/Actions/WithdrawFromEvent.php`
- Modify: `app/Actions/RemoveEventParticipant.php`
- Modify: `app/Actions/BlockEventRegistrations.php`
- Modify: `app/Actions/CancelEvent.php`
- Create: `resources/js/composables/useEventChatAccess.ts`
- Modify: `resources/js/composables/useMemberRealtimeNotifications.ts`
- Test: `tests/Feature/EventChatAccessBroadcastTest.php`
- Test: `tests/Frontend/eventChatAccess.test.js`

**Interfaces:**
- Produces: personal event `.event-chat.access.changed` on `App.Models.User.{id}` with `{ event_id, access: 'granted'|'revoked'|'read_only' }`.
- Produces: `useEventChatAccess(onChange)` which partially reloads `organized`, `participating`, and `panel` and leaves/rejoins `event-chat.{id}` through reactive component mounting.

- [ ] **Step 1: Write failing transition broadcast tests**

```php
it('broadcasts access changes after committed registration transitions', function () {
    Event::fake([EventChatAccessChanged::class]);
    app(DecideEventRegistration::class)->handle($this->organizer, $this->pending, true);
    Event::assertDispatched(fn (EventChatAccessChanged $event) =>
        $event->userId === $this->pending->user_id && $event->access === 'granted');
});
```

Cover acceptance, withdrawal, removal, blocking, and cancellation (`read_only` to organizer plus accepted participants). Assert refusal and unrelated updates do not grant access.

- [ ] **Step 2: Verify transition tests fail**

Run: `php artisan test tests/Feature/EventChatAccessBroadcastTest.php`
Expected: FAIL because the event is absent.

- [ ] **Step 3: Implement minimal after-commit personal broadcasts**

Dispatch only after the database transition is successful. For blocking, collect affected accepted registrations before changing their status and notify those participant ids. For cancellation, notify the organizer and registrations still accepted. Payloads contain no title, location, participant identity, or message content.

- [ ] **Step 4: Write and implement frontend event-handling tests**

```js
it('maps access events to a targeted event-workspace reload', () => {
    expect(eventChatAccessReloadOptions({ event_id: 12, access: 'revoked' }))
        .toEqual({ only: ['organized', 'participating', 'panel'], preserveScroll: true });
});
```

Keep a pure helper for reload options so Bun can test behavior without a socket. The composable subscribes through the already-mounted personal-channel infrastructure and invokes `router.reload` only on event workspace pages.

- [ ] **Step 5: Run backend and frontend transition tests**

Run: `php artisan test tests/Feature/EventChatAccessBroadcastTest.php && bun test tests/Frontend/eventChatAccess.test.js`
Expected: PASS.

- [ ] **Step 6: Commit live access transitions**

```bash
git add app/Events/EventChatAccessChanged.php app/Actions/DecideEventRegistration.php app/Actions/WithdrawFromEvent.php app/Actions/RemoveEventParticipant.php app/Actions/BlockEventRegistrations.php app/Actions/CancelEvent.php resources/js/composables/useEventChatAccess.ts resources/js/composables/useMemberRealtimeNotifications.ts tests/Feature/EventChatAccessBroadcastTest.php tests/Frontend/eventChatAccess.test.js
git commit -m "feat(events): sync chat access in real time"
```

### Task 7: Build the event discussion tab and realtime message state

**Files:**
- Create: `resources/js/components/events/EventChat.vue`
- Create: `resources/js/components/events/EventChatMessageItems.vue`
- Create: `resources/js/components/events/EventChatComposer.vue`
- Create: `resources/js/composables/useEventChatMessages.ts`
- Create: `resources/js/composables/useEventChatRealtime.ts`
- Create: `resources/js/lib/eventChatState.ts`
- Modify: `resources/js/components/events/EventDetail.vue`
- Modify: `resources/js/components/events/EventPanelContent.vue`
- Modify: `resources/js/components/events/EventWorkspace.vue`
- Modify: `resources/js/types/event.ts`
- Test: `tests/Frontend/eventChatState.test.js`
- Test: `tests/Browser/EventChatTest.php`

**Interfaces:**
- Consumes: `panel.kind === 'chat'`, message paginator, routes from Tasks 4–5, and `.event-chat.message.sent`.
- Produces: `mergeEventChatMessages(current, incoming)` sorted and deduplicated by id.
- Produces: keyboard-accessible tabs `details`, `participants`, `chat`; opening chat marks the latest loaded message read.

- [ ] **Step 1: Write failing pure-state tests**

```js
it('deduplicates the HTTP response and Reverb event by persistent id', () => {
    expect(mergeEventChatMessages([{ id: 4, content: 'A' }], [
        { id: 4, content: 'A' },
        { id: 5, content: 'B' },
    ]).map(({ id }) => id)).toEqual([4, 5]);
});
```

Add ordering, older-page merge, and unread increment excluding current author.

- [ ] **Step 2: Verify pure-state tests fail**

Run: `bun test tests/Frontend/eventChatState.test.js`
Expected: FAIL because the helper is absent.

- [ ] **Step 3: Implement typed message state and realtime composable**

Mirror the useful parts of `useConversationMessages.ts` and `useConversationRealtime.ts`, but omit private-conversation read receipts, presence, and typing. On reconnect, issue an Inertia partial reload of `panel.messages`; merge by id and preserve loaded history.

- [ ] **Step 4: Write failing browser tests for the tab and composer**

```php
it('lets two accepted sessions exchange one realtime group message', function () {
    // Open the same event chat in organizer and participant browser sessions,
    // send one message, assert it appears once in both timelines, then reload
    // and assert the participant unread count is server-consistent.
});
```

Also assert deep-link opening, tab keyboard navigation, Enter/Shift+Enter, 2,000-character boundary, draft preservation on HTTP error, history pagination anchor, reconnect banner, cancellation/J+7 read-only copy, and revoked access closing the chat.

- [ ] **Step 5: Implement the adaptive chat UI**

Use the existing Tabs primitives. `EventDetail` owns the three-tab navigation and links chat to `events.chat.show` with `origin=mine`. `EventChat` renders a flex column with scrollable history and composer, announces only newly received messages through `aria-live="polite"`, and swaps the composer for localized read-only copy. Preserve current drawer/modal focus and scroll restoration behavior.

- [ ] **Step 6: Add card and tab unread badges**

Pass `chatUnreadCount` through `EventSummary` only for authorized `mine` cards. Render an accessible numeric badge in `EventCard.vue` and beside the Discussion tab; use visible text/ARIA so color is not the only signal.

- [ ] **Step 7: Generate routes and run focused frontend/browser checks**

Run: `php artisan wayfinder:generate --with-form && bun test tests/Frontend/eventChatState.test.js && php artisan test tests/Browser/EventChatTest.php`
Expected: PASS, including two browser sessions and 320 px coverage.

- [ ] **Step 8: Commit the interface**

```bash
git add resources/js/components/events/EventChat.vue resources/js/components/events/EventChatMessageItems.vue resources/js/components/events/EventChatComposer.vue resources/js/composables/useEventChatMessages.ts resources/js/composables/useEventChatRealtime.ts resources/js/lib/eventChatState.ts resources/js/components/events/EventDetail.vue resources/js/components/events/EventPanelContent.vue resources/js/components/events/EventWorkspace.vue resources/js/components/events/EventCard.vue resources/js/types/event.ts tests/Frontend/eventChatState.test.js tests/Browser/EventChatTest.php resources/js/routes
git commit -m "feat(events): add group chat interface"
```

### Task 8: Localize every event chat surface

**Files:**
- Modify: `lang/fr/events.php`
- Modify: `lang/en/events.php`
- Modify: `app/Support/FrontendTranslations.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`
- Modify: `tests/Browser/EventChatTest.php`

**Interfaces:**
- Produces matching `events.chat.*` keys in French and English for tab, composer, placeholders, loading, reconnect, empty state, unread badge, validation/access errors, cancellation and J+7 read-only reasons, and ARIA labels.

- [ ] **Step 1: Extend translation coverage with exact required keys**

```php
it('exposes matching event chat translations in French and English', function () {
    $keys = [
        'events.chat.tab', 'events.chat.empty', 'events.chat.placeholder',
        'events.chat.send', 'events.chat.unread', 'events.chat.reconnect',
        'events.chat.read_only.cancelled', 'events.chat.read_only.archived',
        'events.chat.errors.forbidden', 'events.chat.errors.too_long',
    ];

    foreach ($keys as $key) {
        expect(__($key, locale: 'fr'))->not->toBe($key)
            ->and(__($key, locale: 'en'))->not->toBe($key);
    }
});
```

- [ ] **Step 2: Run and observe missing-key failures**

Run: `php artisan test tests/Feature/Localization/InertiaTranslationsTest.php --filter='event chat'`
Expected: FAIL for the new keys.

- [ ] **Step 3: Add paired catalogs and expose them to Inertia**

Use friendly, non-romantic wording. Keep placeholders, `aria-label`s, live announcements, server validation and read-only explanations out of Vue literals.

- [ ] **Step 4: Run localization and browser tests in both locales**

Run: `php artisan test tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/EventChatTest.php`
Expected: PASS with French and English assertions.

- [ ] **Step 5: Commit localization**

```bash
git add lang/fr/events.php lang/en/events.php app/Support/FrontendTranslations.php tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/EventChatTest.php
git commit -m "feat(events): localize group chat"
```

### Task 9: Apply account deletion, export, and documentation rules

**Files:**
- Modify: `app/Actions/BuildUserDataExport.php`
- Modify: `tests/Feature/Settings/DirectUserDataExportTest.php`
- Modify: `tests/Feature/Settings/AccountDeletionTest.php`
- Modify: `tests/Feature/Admin/ManageMembersTest.php`
- Modify: `docs/PRD.md`
- Modify: `docs/data-model.md`
- Modify: `docs/security-privacy.md`

**Interfaces:**
- Consumes: cascading author/read foreign keys from Task 1 and `EventChatPolicy::view` from Task 3.
- Produces: export key `event_chat_messages`, containing only the requesting member’s authored messages from chats they can still view.

- [ ] **Step 1: Write failing privacy tests**

```php
it('exports only self-authored messages from event chats still visible to the member', function () {
    $export = app(BuildUserDataExport::class)->handle($this->user);

    expect($export['event_chat_messages'])->toContain([
        'event_id' => $this->event->id,
        'author' => 'self',
        'content' => 'Mon message',
        'created_at' => $this->message->created_at?->toIso8601String(),
        'updated_at' => $this->message->updated_at?->toIso8601String(),
    ])->not->toContain('Message d’un autre membre');
});
```

Add deletion assertions for authored messages/read cursors, organizer event cascade, participant deletion leaving other messages intact, and excluded revoked chats.

- [ ] **Step 2: Run privacy tests and observe missing export/cascade expectations**

Run: `php artisan test tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Settings/AccountDeletionTest.php tests/Feature/Admin/ManageMembersTest.php`
Expected: FAIL on the new export key before implementation; cascade tests document the migration behavior.

- [ ] **Step 3: Implement the authorized self-authored export**

Query `EventChatMessage` by `author_user_id`, constrain to chats where the user is organizer or currently accepted, order by id, and serialize `event_id`, `author => self`, `content`, `created_at`, and `updated_at`. Do not export other authors, read cursors, participant lists, or private locations through this key.

- [ ] **Step 4: Update product, data-model, and privacy documentation**

Document the four new entities/relations, the implemented chat capability, J+7 read-only rule, dynamic access, channel authorization, unread cursor privacy, export behavior, and deletion cascades. Keep the implementation matrix factual.

- [ ] **Step 5: Run privacy and documentation-adjacent tests**

Run: `php artisan test tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Settings/AccountDeletionTest.php tests/Feature/Admin/ManageMembersTest.php`
Expected: PASS.

- [ ] **Step 6: Commit privacy integration and docs**

```bash
git add app/Actions/BuildUserDataExport.php tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Settings/AccountDeletionTest.php tests/Feature/Admin/ManageMembersTest.php docs/PRD.md docs/data-model.md docs/security-privacy.md
git commit -m "feat(events): integrate chat data controls"
```

### Task 10: Run complete verification and prepare delivery

**Files:**
- Modify only files required to fix failures caused by Tasks 1–9.

**Interfaces:**
- Produces: a branch whose generated routes, formatting, static analysis, types, production build, PHP/frontend tests, browser coverage, and runtime image all pass.

- [ ] **Step 1: Regenerate route bindings**

Run: `php artisan wayfinder:generate --with-form`
Expected: generated TypeScript contains the event chat show, store, and read routes.

- [ ] **Step 2: Run targeted event-chat suites once more**

Run: `php artisan test tests/Feature/EventChatSchemaTest.php tests/Unit/Models/EventChatTest.php tests/Feature/EventChatAuthorizationTest.php tests/Feature/StoreEventChatMessageTest.php tests/Feature/EventChatBroadcastTest.php tests/Feature/EventChatHistoryTest.php tests/Feature/EventChatUnreadTest.php tests/Feature/EventChatAccessBroadcastTest.php tests/Browser/EventChatTest.php && bun test tests/Frontend/eventChatState.test.js tests/Frontend/eventChatAccess.test.js`
Expected: PASS.

- [ ] **Step 3: Run backend quality gates**

Run: `composer lint:check && composer analyse && composer test`
Expected: all commands exit 0.

- [ ] **Step 4: Run frontend quality gates**

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run build`
Expected: all commands exit 0.

- [ ] **Step 5: Run the production image build**

Run: `docker build --target runtime --tag dlp-friends:ci .`
Expected: runtime image builds successfully.

- [ ] **Step 6: Inspect the final diff and whitespace**

Run: `git status --short && git diff --check && git diff --stat main...HEAD`
Expected: only issue #191 files are present, no whitespace errors, and `.superpowers/` plus `artifacts/` remain untracked and untouched.

If a verification step fails, return to the task that owns the behavior, add a
failing regression assertion where needed, apply the smallest fix, rerun that
task’s focused command, and commit with the exact file list from that task.
Do not commit unrelated or pre-existing untracked files.
