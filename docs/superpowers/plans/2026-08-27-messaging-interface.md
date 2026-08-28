# Messaging Interface Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the member-only conversation list and accessible realtime chat interface required by issue #22.

**Architecture:** Laravel remains the authorization and serialization boundary. Two Inertia pages receive explicit conversation DTOs; the show page uses `Inertia::scroll()` over ascending message pages, starts on the last page, and prepends older pages. A focused Vue composable merges HTTP and Echo messages by database id while preserving loaded state across connection failures.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Laravel Echo/Reverb, Pest 5, Pest Browser/Chromium.

**Spec:** `docs/superpowers/specs/2026-08-27-messaging-interface-design.md`

## Global Constraints

- Keep all copy strictly friendly; never introduce romantic or dating language.
- A member may receive only conversations attached to their own matches; the admin role grants no message access.
- Initial history is exactly the ten newest messages, rendered oldest-to-newest; older history loads in groups of ten.
- Message content is rendered as plain text and remains limited to 2,000 characters.
- Loaded messages survive realtime disconnection and failed sends.
- Archived conversations remain readable and reject new messages.
- Reuse Inertia, Echo, existing UI primitives, and the existing avatar presentation; add no dependency.
- All behavior changes follow red, green, refactor and receive recent verification output.

---

### Task 1: Member-only conversation list contract

**Files:**
- Create: `app/Http/Controllers/ConversationIndexController.php`
- Modify: `app/Models/Conversation.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/ConversationIndexTest.php`

**Interfaces:**
- Produces: named route `conversations.index` and Inertia prop `conversations: ConversationSummary[]`.
- `ConversationSummary` fields: `id`, `participant`, `archived_at`, `latest_message`, `activity_at`.
- Consumes: `MemberMatch.lowUser`, `MemberMatch.highUser`, `Profile.avatar`, and `Conversation.messages`.

- [ ] **Step 1: Write the failing member-isolation and ordering tests**

Create factories inline in `ConversationIndexTest.php`, then assert the rendered component and exact props:

```php
use App\Models\Conversation;
use App\Models\MemberMatch;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a member sees only their conversations ordered by latest activity', function () {
    $member = User::factory()->withProfile()->create();
    $olderPeer = User::factory()->withProfile()->create();
    $newerPeer = User::factory()->withProfile()->create();
    $outsider = User::factory()->withProfile()->create();

    $older = conversationBetween($member, $olderPeer);
    $newer = conversationBetween($member, $newerPeer);
    $foreign = conversationBetween($olderPeer, $outsider);

    Message::factory()->for($older)->for($olderPeer, 'author')->create([
        'content' => 'Ancien échange', 'created_at' => now()->subHour(),
    ]);
    Message::factory()->for($newer)->for($newerPeer, 'author')->create([
        'content' => 'Dernier échange', 'created_at' => now(),
    ]);
    Message::factory()->for($foreign)->for($outsider, 'author')->create();

    $this->actingAs($member)->get('/conversations')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->has('conversations', 2)
            ->where('conversations.0.id', $newer->id)
            ->where('conversations.0.participant.id', $newerPeer->id)
            ->where('conversations.0.latest_message.content', 'Dernier échange')
            ->where('conversations.1.id', $older->id));
});

test('the conversation list exposes an empty collection', function () {
    $member = User::factory()->withProfile()->create();

    $this->actingAs($member)->get('/conversations')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Index')
            ->where('conversations', []));
});
```

Add this local helper:

```php
function conversationBetween(User $first, User $second): Conversation
{
    [$lowId, $highId] = collect([$first->id, $second->id])->sort()->values()->all();
    $match = MemberMatch::factory()->create([
        'user_low_id' => $lowId,
        'user_high_id' => $highId,
    ]);

    return $match->conversation()->create();
}
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test tests/Feature/ConversationIndexTest.php`

Expected: FAIL because `/conversations` does not exist.

- [ ] **Step 3: Implement the minimal authorized query and DTO mapping**

Add a reusable scope to `Conversation`:

```php
/** @param Builder<Conversation> $query */
public function scopeForMember(Builder $query, User $user): void
{
    $query->whereHas('memberMatch', fn (Builder $match) => $match
        ->where('user_low_id', $user->id)
        ->orWhere('user_high_id', $user->id));
}

/** @return HasOne<Message, $this> */
public function latestMessage(): HasOne
{
    return $this->hasOne(Message::class)->latestOfMany();
}
```

In `ConversationIndexController`, eager-load both member profiles/avatars and
`latestMessage`, order with `latest_message_created_at` from
`withMax('messages', 'created_at')` followed by `created_at`, and map only public
fields. Select the other member by comparing ids; never serialize either full
`User` model.

Register before `conversations.show`:

```php
Route::get('conversations', ConversationIndexController::class)
    ->name('conversations.index');
```

- [ ] **Step 4: Run targeted tests and static analysis**

Run: `php artisan test tests/Feature/ConversationIndexTest.php tests/Feature/ConversationTest.php`

Run: `composer analyse`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ConversationIndexController.php app/Models/Conversation.php routes/web.php tests/Feature/ConversationIndexTest.php
git commit -m "feat: expose member conversation list"
```

---

### Task 2: Ten-message Inertia history

**Files:**
- Modify: `app/Http/Controllers/ConversationController.php`
- Modify: `tests/Feature/ConversationTest.php`

**Interfaces:**
- Produces: `Conversations/Show` props `conversation`, `participant`, `currentUserId`, and scroll prop `messages`.
- `messages.data` is chronological within every merged result; paginator page name is `messages`.
- Consumes: `ConversationPolicy::view` and the DTO participant shape from Task 1.

- [ ] **Step 1: Replace the no-content expectation with failing Inertia prop tests**

Add fifteen sequential messages and assert the first visit contains ids 6–15,
then request `?messages=1` and assert ids 1–10 while keeping the outsider 403
test:

```php
test('a member initially receives only the ten newest messages in chronological order', function () {
    [$member, $peer, $conversation] = $this->conversationMembers(withProfiles: true);
    $messages = Message::factory()->count(15)->sequence(
        fn (Sequence $sequence) => [
            'author_user_id' => $sequence->index % 2 === 0 ? $member->id : $peer->id,
            'created_at' => now()->addSeconds($sequence->index),
        ],
    )->for($conversation)->create();

    $expected = $messages->slice(5)->pluck('id')->all();

    $this->actingAs($member)->get("/conversations/{$conversation->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Conversations/Show')
            ->where('conversation.id', $conversation->id)
            ->where('participant.id', $peer->id)
            ->where('currentUserId', $member->id)
            ->where('messages.data.*.id', $expected)
            ->has('messages.data', 10));
});
```

- [ ] **Step 2: Run the test and verify RED**

Run: `php artisan test tests/Feature/ConversationTest.php`

Expected: FAIL because the controller returns HTTP 204.

- [ ] **Step 3: Render the authorized history**

Authorize before loading relations. Count messages, compute
`$lastPage = max((int) ceil($count / 10), 1)`, and use the requested
`messages` page or `$lastPage` by default:

```php
$page = max($request->integer('messages', $lastPage), 1);
$messages = $conversation->messages()
    ->oldest('id')
    ->paginate(perPage: 10, pageName: 'messages', page: $page)
    ->through(fn (Message $message): array => [
        'id' => $message->id,
        'conversation_id' => $message->conversation_id,
        'author_user_id' => $message->author_user_id,
        'content' => $message->content,
        'created_at' => $message->created_at?->toISOString(),
    ]);
```

Return `Inertia::render('Conversations/Show', [...])` with
`'messages' => Inertia::scroll(fn () => $messages)`. Include `archived_at` as
ISO-8601 or `null` and only the participant’s public profile/avatar fields.

- [ ] **Step 4: Verify props, pagination and authorization**

Run: `php artisan test tests/Feature/ConversationTest.php`

Run: `composer analyse`

Expected: PASS, including existing outsider/admin denials.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ConversationController.php tests/Feature/ConversationTest.php
git commit -m "feat: expose paginated conversation history"
```

---

### Task 3: Conversation types, translations, navigation and empty list UI

**Files:**
- Create: `resources/js/types/conversation.ts`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/types/i18n.ts`
- Modify: `app/Support/FrontendTranslations.php`
- Modify: `lang/fr/frontend.php`
- Modify: `lang/en/frontend.php`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Create: `resources/js/pages/Conversations/Index.vue`
- Create: `tests/Browser/ConversationTest.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`

**Interfaces:**
- Produces TypeScript types `ConversationParticipant`, `ConversationMessage`, `ConversationSummary`, `ConversationDetails`, and `PaginatedMessages`.
- Produces navigation key `navigation.conversations` and the accessible list page.
- Consumes route helpers `conversations.index` and `conversations.show` generated by Wayfinder.

- [ ] **Step 1: Write failing localization and browser tests**

Assert the French and English `navigation.conversations` shared prop. In the
browser file, create a completed member and assert `/conversations` shows
« Aucun échange pour le moment », the bottom navigation exposes « Échanges »,
and a populated conversation links to its show URL with the peer name and latest
message.

```php
test('a completed member can open the empty conversation list from mobile navigation', function () {
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member);

    visit('/conversations')->on()->mobile()
        ->assertSee('Mes échanges')
        ->assertSee('Aucun échange pour le moment')
        ->assertPresent('[aria-label="Échanges"][aria-current="page"]')
        ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true)
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/ConversationTest.php`

Expected: FAIL because the page, key, and navigation item do not exist.

- [ ] **Step 3: Add exact frontend contracts and translated copy**

Define:

```ts
export type ConversationMessage = {
    id: number;
    conversation_id: number;
    author_user_id: number;
    content: string;
    created_at: string | null;
};

export type ConversationParticipant = {
    id: number;
    display_name: string;
    avatar: AvatarOption;
};

export type PaginatedMessages = {
    data: ConversationMessage[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
```

Add all French source strings used by both pages to `lang/en/frontend.php` and
add `conversations` to both structured navigation catalogs and
`TranslationMessages`.

- [ ] **Step 4: Build the mobile list and navigation entry**

Use `MessageCircle` in `MemberBottomNavigation.vue`, between discovery and
profile, with `activeParents: ['/conversations']`. In `Index.vue`, use
`Head`, `Link`, `AvatarPortrait`, semantic headings, a `min-h-11` row target,
visible « Archivé » text, `line-clamp-1` preview, and an explicit empty state.

- [ ] **Step 5: Generate routes and verify GREEN**

Run: `php artisan wayfinder:generate --with-form`

Run: `php artisan test tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/ConversationTest.php`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Support/FrontendTranslations.php lang resources/js/components/MemberBottomNavigation.vue resources/js/pages/Conversations/Index.vue resources/js/types tests/Browser/ConversationTest.php tests/Feature/Localization/InertiaTranslationsTest.php
git commit -m "feat: add conversation list interface"
```

---

### Task 4: Accessible message timeline and older-history scroll

**Files:**
- Create: `resources/js/components/conversations/MessageTimeline.vue`
- Create: `resources/js/pages/Conversations/Show.vue`
- Modify: `tests/Browser/ConversationTest.php`

**Interfaces:**
- `MessageTimeline` consumes `messages: PaginatedMessages`, `currentUserId: number`, and a scroll container element.
- Produces a `role="log"`, chronological bubbles, an `aria-live="polite"` announcement node, initial bottom positioning, and stable anchor restoration.
- `Show.vue` owns the three-zone mobile layout and passes data to later realtime/composer tasks.

- [ ] **Step 1: Add failing history, text-safety and mobile tests**

Seed fifteen messages, including `<img src=x onerror=window.__messageXss=true>`,
then assert only the newest ten initially, the literal text appears, no image is
created inside the log, the page starts near its bottom, and scrolling to the
top loads the older five without horizontal overflow.

```php
$page = visit("/conversations/{$conversation->id}")->on()->mobile()
    ->assertPresent('[role="log"][aria-label="Historique des messages"]')
    ->assertSee('<img src=x onerror=window.__messageXss=true>')
    ->assertNotPresent('[role="log"] img[src="x"]')
    ->assertScript('window.__messageXss !== true', true)
    ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth', true);

$page->script("document.querySelector('[data-test=message-scroll]').scrollTop = 0");
$page->assertSee('Message 1')->assertNoJavaScriptErrors();
```

- [ ] **Step 2: Run the browser test and verify RED**

Run: `php artisan test tests/Browser/ConversationTest.php`

Expected: FAIL because `Conversations/Show.vue` does not exist.

- [ ] **Step 3: Implement the timeline with native Inertia scroll**

Use:

```vue
<InfiniteScroll data="messages" only-previous preserve-url>
    <ol role="list" class="flex flex-col gap-2">
        <li v-for="message in messages.data" :key="message.id">
            <p class="break-words whitespace-pre-wrap">{{ message.content }}</p>
        </li>
    </ol>
</InfiniteScroll>
```

Mark the scroll container with `scroll-region`, `role="log"`,
`aria-label="Historique des messages"`, `aria-relevant="additions text"`, and
`data-test="message-scroll"`. Before a prepend, capture
`scrollHeight - scrollTop`; after Vue updates, restore that distance. On mount,
scroll to bottom. Respect reduced motion by using immediate scrolling when
`matchMedia('(prefers-reduced-motion: reduce)')` matches.

- [ ] **Step 4: Verify history behavior**

Run: `php artisan test tests/Feature/ConversationTest.php tests/Browser/ConversationTest.php`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/conversations/MessageTimeline.vue resources/js/pages/Conversations/Show.vue tests/Browser/ConversationTest.php
git commit -m "feat: render accessible conversation history"
```

---

### Task 5: Reliable composer and optimistic HTTP merge

**Files:**
- Create: `resources/js/composables/useConversationMessages.ts`
- Create: `resources/js/components/conversations/MessageComposer.vue`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `tests/Browser/ConversationTest.php`

**Interfaces:**
- `useConversationMessages(initial)` produces `visibleMessages`, `mergeMessage(message)`, and `mergeMessages(messages)`, always sorted by id and deduplicated by id.
- `MessageComposer` consumes `conversationId`, `archived`, and `onSent(message)`.
- The composer POSTs `{ content }` to the generated message-store route and consumes `{ data: ConversationMessage }`.

- [ ] **Step 1: Write failing composer behavior tests**

Test empty/archived disabled state, the 2,000-character limit, `Enter` submit,
`Shift+Enter` newline, disabled controls while an intercepted XHR is pending,
focus retention after success, and text retention after a forced 500 response.

```php
$page->fill('content', 'Bonjour !')
    ->keys('textarea[name="content"]', 'Enter')
    ->assertSee('Bonjour !')
    ->assertValue('content', '')
    ->assertScript('document.activeElement.name === "content"', true);
```

Use the existing XMLHttpRequest interception pattern from
`DiscoveryTest.php` to hold and fail only URLs containing `/messages`.

- [ ] **Step 2: Run the browser test and verify RED**

Run: `php artisan test tests/Browser/ConversationTest.php`

Expected: FAIL because the composer is absent.

- [ ] **Step 3: Implement the idempotent local collection**

Store messages in a `ref`, merge with a `Map<number, ConversationMessage>`, and
replace the array with `Array.from(map.values()).sort((a, b) => a.id - b.id)`.
Watch Inertia’s merged `initial.data` and merge new pages instead of replacing
the local collection.

- [ ] **Step 4: Implement the composer**

Use a native `fetch` with `Accept: application/json`, `Content-Type:
application/json`, CSRF token, and `X-Socket-ID` from `useSocketId()` so the
author does not receive its own broadcast. Disable while pending, empty after
`trim()`, or archived. On 422 use `errors.content[0]`; on network/5xx display
« Le message n’a pas pu être envoyé. Réessayez. ». Preserve content on error.
On 201 call `onSent(response.data)`, clear content, await `nextTick()`, focus.

- [ ] **Step 5: Verify GREEN**

Run: `php artisan test tests/Feature/StoreMessageTest.php tests/Browser/ConversationTest.php`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/composables/useConversationMessages.ts resources/js/components/conversations/MessageComposer.vue resources/js/pages/Conversations/Show.vue tests/Browser/ConversationTest.php
git commit -m "feat: add resilient message composer"
```

---

### Task 6: Echo events, connection loss and recovery

**Files:**
- Create: `resources/js/composables/useConversationRealtime.ts`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `tests/Browser/ConversationTest.php`

**Interfaces:**
- `useConversationRealtime(conversationId, onMessage, onReconnect)` produces `status`, `retry()`, and subscribes to `.message.sent` on `conversation.{id}`.
- Consumes `useEcho`, `useConnectionStatus`, and `echo` from `@laravel/echo-vue`.
- On reconnection, `Show.vue` performs `router.reload({ only: ['messages'], preserveScroll: true, preserveState: true })`; the Task 5 merge keeps all loaded messages and deduplicates refreshed rows.

- [ ] **Step 1: Write failing realtime and recovery tests**

In the browser test, replace the Echo connector callbacks before navigation with
a deterministic fake, capture the `.message.sent` callback, and assert:

1. an incoming payload appears once when invoked twice;
2. a `disconnected` transition shows the status and keeps existing messages;
3. « Réessayer » calls `disconnect()` then `connect()`;
4. a subsequent `connected` transition hides the status and issues an Inertia
   partial reload for `messages`.

Assert the banner contract:

```php
->assertPresent('[role="status"][aria-live="polite"]')
->assertSee('Connexion temps réel interrompue')
->assertSee('Réessayer');
```

- [ ] **Step 2: Run the browser test and verify RED**

Run: `php artisan test tests/Browser/ConversationTest.php`

Expected: FAIL because no Echo listener or connection banner exists.

- [ ] **Step 3: Implement realtime subscription and retry**

Map Echo’s `connected|disconnected|connecting|reconnecting|failed`
statuses to the three UI states. Use
`useEcho<ConversationMessage>(\`conversation.${conversationId}\`, '.message.sent', onMessage)` and
`useConnectionStatus()`. Only call `onReconnect` for a transition from a
non-connected state to `connected`, not on initial mount.

Implement retry with the configured singleton:

```ts
const retry = () => {
    status.value = 'connecting';
    const instance = echo();
    instance.disconnect();
    instance.connect();
};
```

- [ ] **Step 4: Render resilient status and archived state**

Keep the timeline mounted regardless of connection status. Show the reconnect
banner above the log, use `role="status" aria-live="polite"`, and show a separate
« Cette conversation est archivée. » panel instead of the composer when
`archived_at !== null`.

- [ ] **Step 5: Verify GREEN**

Run: `php artisan test tests/Feature/MessageBroadcastTest.php tests/Browser/ConversationTest.php`

Run: `bun run lint:check && bun run format:check && bun run types:check`

Expected: PASS with no JavaScript errors.

- [ ] **Step 6: Commit**

```bash
git add resources/js/composables/useConversationRealtime.ts resources/js/pages/Conversations/Show.vue tests/Browser/ConversationTest.php
git commit -m "feat: connect conversations to realtime messages"
```

---

### Task 7: Full regression and delivery verification

**Files:**
- Modify only files required to fix failures introduced by Tasks 1–6.

**Interfaces:**
- Produces a clean issue #22 branch ready for review.
- Consumes all preceding task contracts.

- [ ] **Step 1: Run focused messaging tests together**

Run:

```bash
php artisan test tests/Feature/ConversationIndexTest.php tests/Feature/ConversationTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php tests/Browser/ConversationTest.php
```

Expected: PASS with zero warnings.

- [ ] **Step 2: Run every repository quality gate**

Run:

```bash
composer lint:check
composer analyse
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
composer test
git diff --check
```

Expected: all commands PASS; generated Wayfinder files are current; no diff
whitespace errors.

- [ ] **Step 3: Inspect final scope**

Run: `git status --short && git diff main...HEAD --stat && git log --oneline main..HEAD`

Confirm that only messaging interface, navigation, translation, test, design,
and plan files changed. Confirm no `.env`, build artifact, log, or private
message fixture is tracked.

- [ ] **Step 4: Commit any verification-only correction**

If Step 2 required a correction, commit only that correction:

Stage each path shown by `git status --short` individually with `git add`, then
run `git diff --cached --name-only` to confirm the set before committing with
`git commit -m "fix: complete messaging interface verification"`.

If the worktree is already clean, do not create an empty commit.
