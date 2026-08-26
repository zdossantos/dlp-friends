# Realtime Messages Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow either member of an active match conversation to persist a text message and broadcast it safely over Reverb.

**Architecture:** A Laravel HTTP endpoint validates and authorizes input before delegating to `SendMessage`. The action persists a `Message` and dispatches a small `MessageSent` broadcast after commit; the private channel delegates membership and active-state checks to `ConversationPolicy`.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Laravel Broadcasting/Reverb, Pest, MySQL 8.4.

**Spec:** `docs/superpowers/specs/2026-08-26-realtime-messages-design.md`

## Global Constraints

- Messages are text-only and limited to exactly 2,000 Unicode characters.
- Missing, empty and whitespace-only content is rejected.
- Only either member of a non-archived conversation may send or subscribe.
- An administrator receives no implicit access to private messages.
- Persisted content remains raw member text and is never logged or rendered as server-generated HTML.
- Broadcast execution happens after database commit and cannot roll back a persisted message.
- Attachments, GIFs, reactions, read state, editing, deletion, pagination and Vue UI are out of scope.

---

### Task 1: Message storage and Eloquent relationships

**Files:**
- Create: `database/migrations/2026_08_26_110000_create_messages_table.php`
- Create: `app/Models/Message.php`
- Create: `database/factories/MessageFactory.php`
- Modify: `app/Models/Conversation.php`
- Modify: `app/Models/User.php`
- Create: `tests/Feature/MessageSchemaTest.php`

**Interfaces:**
- Consumes: `Conversation::memberMatch()` and the existing `users` and `conversations` tables.
- Produces: `Message` with fillable `conversation_id`, `author_user_id`, and `content`; `Conversation::messages(): HasMany`; `User::authoredMessages(): HasMany`.

- [ ] **Step 1: Write the failing schema and relationship tests**

```php
<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MessageSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_store_their_conversation_author_and_raw_content(): void
    {
        expect(Schema::hasColumns('messages', [
            'id', 'conversation_id', 'author_user_id', 'content', 'created_at', 'updated_at',
        ]))->toBeTrue();

        $conversation = Conversation::query()->firstOrFail();
        $author = $conversation->memberMatch->lowUser;
        $message = Message::factory()->for($conversation)->for($author, 'author')->create([
            'content' => '<script>alert("x")</script>',
        ]);

        expect($message->conversation->is($conversation))->toBeTrue()
            ->and($message->author->is($author))->toBeTrue()
            ->and($conversation->messages()->sole()->is($message))->toBeTrue()
            ->and($author->authoredMessages()->sole()->is($message))->toBeTrue()
            ->and($message->content)->toBe('<script>alert("x")</script>');
    }
}
```

Add focused tests proving both foreign keys cascade on deletion. Create the match and conversation explicitly so the test does not rely on hidden factory side effects.

- [ ] **Step 2: Run the test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MessageSchemaTest.php`

Expected: FAIL because the `messages` table and `Message` model do not exist.

- [ ] **Step 3: Add the migration, model, factory and relationships**

Use this migration shape:

```php
Schema::create('messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
    $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
    $table->text('content');
    $table->timestamps();
});
```

Implement `Message` with `HasFactory`, `#[Fillable([...])]`, `conversation(): BelongsTo`, and `author(): BelongsTo`. Add `messages(): HasMany` to `Conversation` and `authoredMessages(): HasMany` to `User`, including the repository's PHPDoc generics and model properties.

- [ ] **Step 4: Run the focused tests and verify GREEN**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MessageSchemaTest.php tests/Feature/ConversationSchemaTest.php`

Expected: PASS with no warnings.

- [ ] **Step 5: Commit the storage slice**

```bash
git add app/Models/Conversation.php app/Models/Message.php app/Models/User.php database/factories/MessageFactory.php database/migrations/2026_08_26_110000_create_messages_table.php tests/Feature/MessageSchemaTest.php
git commit -m "feat: store conversation messages"
```

### Task 2: Authorized message action

**Files:**
- Create: `app/Actions/SendMessage.php`
- Modify: `app/Policies/ConversationPolicy.php`
- Create: `tests/Feature/SendMessageTest.php`

**Interfaces:**
- Consumes: `ConversationPolicy::send(User, Conversation): bool` and `Message::create(array): Message`.
- Produces: `SendMessage::handle(User $author, Conversation $conversation, string $content): Message`.

- [ ] **Step 1: Write failing action tests**

Write tests using real users, matches, conversations and `Gate::forUser(...)` that assert:

```php
$message = app(SendMessage::class)->handle($member, $conversation, 'Bonjour !');

expect($message->content)->toBe('Bonjour !')
    ->and($message->author->is($member))->toBeTrue()
    ->and($message->conversation->is($conversation))->toBeTrue();
```

Add separate cases for the other match member, an outsider, an unrelated administrator, and an archived conversation. Each refusal must throw `AuthorizationException` and leave `messages` empty. Before writing the tests, identify the production change that would make each assertion fail: removing the membership check, removing the archive check, or granting an admin bypass.

- [ ] **Step 2: Run the test and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/SendMessageTest.php`

Expected: FAIL because `SendMessage` does not exist.

- [ ] **Step 3: Implement the minimal action**

```php
final class SendMessage
{
    public function handle(User $author, Conversation $conversation, string $content): Message
    {
        Gate::forUser($author)->authorize('send', $conversation);

        return DB::transaction(fn (): Message => $conversation->messages()->create([
            'author_user_id' => $author->id,
            'content' => $content,
        ]));
    }
}
```

Keep the Policy's existing no-admin-bypass behavior. Do not add logging, normalization, HTML conversion, or broadcast code in this task.

- [ ] **Step 4: Run action and existing conversation tests**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/SendMessageTest.php tests/Feature/ConversationTest.php`

Expected: PASS with no warnings.

- [ ] **Step 5: Commit the action slice**

```bash
git add app/Actions/SendMessage.php app/Policies/ConversationPolicy.php tests/Feature/SendMessageTest.php
git commit -m "feat: authorize message sending"
```

### Task 3: Validated HTTP endpoint and raw JSON contract

**Files:**
- Create: `app/Http/Requests/StoreMessageRequest.php`
- Create: `app/Http/Controllers/MessageController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/StoreMessageTest.php`

**Interfaces:**
- Consumes: `SendMessage::handle(User, Conversation, string): Message`.
- Produces: named route `conversations.messages.store` and HTTP 201 JSON `{data: {id, conversation_id, author_user_id, content, created_at, updated_at}}`.

- [ ] **Step 1: Write failing endpoint tests**

Cover a valid request from each member and assert the exact JSON contract plus `assertDatabaseHas`. Add datasets for missing content, `''`, whitespace-only content, non-string content and `str_repeat('é', 2001)`, all returning 422 and no row. Assert `str_repeat('é', 2000)` succeeds, proving character rather than byte length. Add 403 cases for outsider, unrelated admin and archived conversation.

Include this raw-text regression:

```php
$content = '<img src=x onerror=alert(1)>';

$this->actingAs($member)
    ->postJson(route('conversations.messages.store', $conversation), ['content' => $content])
    ->assertCreated()
    ->assertJsonPath('data.content', $content)
    ->assertHeader('content-type', 'application/json');

$this->assertDatabaseHas('messages', ['content' => $content]);
```

- [ ] **Step 2: Run the endpoint tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/StoreMessageTest.php`

Expected: FAIL with route-not-found because the endpoint does not exist.

- [ ] **Step 3: Implement validation, controller and route**

Use a request rule equivalent to:

```php
'content' => [
    'required',
    'string',
    'max:2000',
    function (string $attribute, mixed $value, Closure $fail): void {
        if (is_string($value) && trim($value) === '') {
            $fail(__('validation.required', ['attribute' => __('message')]));
        }
    },
],
```

Prefer a small reusable validation rule only if the existing Laravel rules cannot express whitespace-only rejection without transforming member content. The controller authorizes via the action, calls it with `$request->user()`, and returns an explicit array so Eloquent cannot leak future fields. Register the POST route beside `conversations.show` under the existing social/profile middleware group.

- [ ] **Step 4: Verify GREEN and regenerate Wayfinder**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/StoreMessageTest.php tests/Feature/SendMessageTest.php
php artisan wayfinder:generate --with-form
bun run types:check
```

Expected: all commands PASS; generated route types include `conversations.messages.store`.

- [ ] **Step 5: Commit the HTTP slice**

```bash
git add app/Http/Controllers/MessageController.php app/Http/Requests/StoreMessageRequest.php routes/web.php resources/js/actions resources/js/routes tests/Feature/StoreMessageTest.php
git commit -m "feat: expose message sending endpoint"
```

### Task 4: After-commit broadcast and private conversation channel

**Files:**
- Create: `app/Events/MessageSent.php`
- Modify: `app/Actions/SendMessage.php`
- Modify: `routes/channels.php`
- Create: `tests/Feature/MessageBroadcastTest.php`

**Interfaces:**
- Consumes: `Message` and `ConversationPolicy::send(User, Conversation): bool`.
- Produces: `MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit`, public event name `message.sent`, private channel `conversation.{id}`, and an explicit `broadcastWith(): array` payload matching the endpoint's message fields.

- [ ] **Step 1: Write failing broadcast and channel tests**

Use `Event::fake([MessageSent::class])` around the action and assert the event is dispatched with the persisted message. Instantiate the event directly to assert:

```php
expect($event->broadcastAs())->toBe('message.sent')
    ->and($event->broadcastOn()->name)->toBe("private-conversation.{$conversation->id}")
    ->and($event->broadcastWith())->toMatchArray([
        'id' => $message->id,
        'conversation_id' => $conversation->id,
        'author_user_id' => $author->id,
        'content' => $message->content,
    ]);
```

Exercise the registered authorization callback through Laravel's `/broadcasting/auth` endpoint with `channel_name=private-conversation.{id}` and a valid `socket_id`. Assert both members are accepted while outsider, unrelated admin and members of an archived conversation receive 403.

Add a transaction rollback test that dispatches inside a transaction and throws: no `MessageSent` is observed after rollback. Add a persistence-independence test using the fake broadcast driver or queued broadcast job failure boundary: after the action returns, the message row exists before any broadcast job executes.

- [ ] **Step 2: Run the broadcast tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MessageBroadcastTest.php`

Expected: FAIL because `MessageSent` and the conversation channel are absent.

- [ ] **Step 3: Implement the event, after-commit dispatch and channel**

Create a serializable event with `Dispatchable`, `InteractsWithSockets`, `SerializesModels`, `ShouldBroadcast`, and `ShouldDispatchAfterCommit`. Also set `$afterCommit = true` on the broadcast job contract so both event dispatch and queued broadcasting respect the committed transaction boundary. Return `new PrivateChannel("conversation.{$this->message->conversation_id}")`, `message.sent`, and the explicit scalar payload. Dispatch it only after the message has been created inside `SendMessage`.

Register the channel without duplicating authorization logic:

```php
Broadcast::channel('conversation.{conversation}', function (User $user, Conversation $conversation): bool {
    return Gate::forUser($user)->allows('send', $conversation);
});
```

- [ ] **Step 4: Verify all messaging behavior**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MessageSchemaTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php tests/Feature/ConversationTest.php`

Expected: PASS with no warnings.

- [ ] **Step 5: Commit the realtime slice**

```bash
git add app/Actions/SendMessage.php app/Events/MessageSent.php routes/channels.php tests/Feature/MessageBroadcastTest.php
git commit -m "feat: broadcast conversation messages"
```

### Task 5: Full verification and delivery

**Files:**
- Modify only files required to address failures caused by this feature.

**Interfaces:**
- Consumes: all previous task outputs.
- Produces: a clean branch ready for pull request review.

- [ ] **Step 1: Run backend quality checks**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer lint:check
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer analyse
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test
```

Expected: Pint, PHPStan, Wayfinder, Vite and all Pest tests pass with no warnings.

- [ ] **Step 2: Run frontend checks affected by generated routes**

Run:

```bash
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

Expected: all commands PASS.

- [ ] **Step 3: Inspect the final diff and repository state**

Run:

```bash
git diff --check main...HEAD
git status --short
git log --oneline main..HEAD
```

Expected: no whitespace errors, no unexpected generated/build files, and only issue 21 commits.

- [ ] **Step 4: Request code review before delivery**

Use `superpowers:requesting-code-review`, address every correctness or scope finding, and repeat the relevant verification commands after changes.

- [ ] **Step 5: Prepare the pull request**

Use Conventional Commit title `feat: send and broadcast realtime messages`, target `main`, link `Closes #21`, and summarize authorization, validation, persistence-before-broadcast, channel privacy and test evidence.
