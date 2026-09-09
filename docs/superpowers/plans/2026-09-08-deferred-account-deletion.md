# Deferred Account Deletion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make a member account immediately inaccessible and undiscoverable, then safely purge all active data exactly 30 days after the confirmed request.

**Architecture:** Record `deletion_requested_at` alongside `pending_deletion`, revoke access transactionally, and dispatch an identity-guarded delayed purge job. A scheduled command redispatches overdue purges so loss of the original delayed job is recoverable.

**Tech Stack:** PHP 8.4, Laravel 13 database transactions/queues/scheduler/filesystems, Inertia 3, Vue 3, Pest.

**Spec:** `docs/superpowers/specs/2026-09-08-personal-data-export-deletion-design.md`

## Global Constraints

- Access ends immediately after password-confirmed deletion request.
- Purge is due exactly 30 days after the first request and cannot be postponed by retries.
- There is no restoration flow.
- The existing administrative deletion remains immediate.
- Backups are not rewritten and may retain encrypted data until automatic rotation, limited to 30 days.
- User-visible copy must be translated in French and English.

---

### Task 1: Persist and enforce pending deletion

**Files:**
- Create: `database/migrations/2026_09_08_010000_add_deletion_requested_at_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `app/Http/Middleware/EnsureUserCanAccessSocialFeatures.php`
- Test: `tests/Feature/Settings/AccountDeletionTest.php`

**Interfaces:**
- Produces cast `User::$deletion_requested_at` as immutable datetime.
- Continues using `UserStatus::PendingDeletion` as the access boundary.

- [ ] **Step 1: Write a failing persistence/access test**

```php
$user = User::factory()->withProfile()->create([
    'status' => UserStatus::PendingDeletion,
    'deletion_requested_at' => now(),
]);

$this->actingAs($user)->get(route('discovery.index'))->assertForbidden();
expect($user->fresh()->deletion_requested_at)->toBeInstanceOf(CarbonImmutable::class);
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Settings/AccountDeletionTest.php`
Expected: FAIL because the column/cast is absent.

- [ ] **Step 3: Add the nullable indexed timestamp and immutable cast**

Keep the middleware's explicit `status === active` check and add the regression assertion rather than duplicating access logic.

- [ ] **Step 4: Verify GREEN and commit**

Run: `php artisan test tests/Feature/Settings/AccountDeletionTest.php`
Expected: PASS.

```bash
git add app/Models/User.php database/migrations/2026_09_08_010000_add_deletion_requested_at_to_users_table.php tests/Feature/Settings/AccountDeletionTest.php
git commit -m "feat: persist pending account deletion"
```

### Task 2: Transactional deletion request and immediate revocation

**Files:**
- Create: `app/Actions/RequestAccountDeletion.php`
- Modify: `app/Http/Controllers/Settings/AccountController.php`
- Modify: `tests/Feature/Settings/AccountDeletionTest.php`
- Modify: `tests/Feature/Settings/AccountUpdateTest.php`

**Interfaces:**
- Produces: `RequestAccountDeletion::handle(User $user): CarbonImmutable`.
- Consumes: `PurgeDeletedUser::__construct(int $userId, string $requestedAt)` from Task 3; introduce the job shell with constructor only during RED, then complete it in Task 3.

- [ ] **Step 1: Write failing request/revocation tests**

```php
Queue::fake();
DB::table('sessions')->insert([
    ['id' => 'first', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ['id' => 'other', 'user_id' => $other->id, 'payload' => '', 'last_activity' => now()->timestamp],
]);
SocialAccount::factory()->for($user)->create();

$this->actingAs($user)->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('home'));

expect($user->fresh()->status)->toBe(UserStatus::PendingDeletion)
    ->and($user->fresh()->deletion_requested_at)->not->toBeNull()
    ->and(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
    ->and(DB::table('sessions')->where('user_id', $other->id)->exists())->toBeTrue()
    ->and($user->socialAccounts()->exists())->toBeFalse();
Queue::assertPushed(PurgeDeletedUser::class, fn ($job) => $job->delay->equalTo(now()->addDays(30)));
```

Also create a ready export on a fake exports disk and assert its file and row are removed. Repeat the action at the Action boundary and assert the original timestamp is unchanged and only one deadline is used.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Settings/AccountDeletionTest.php`
Expected: FAIL because the current controller deletes the user immediately.

- [ ] **Step 3: Implement request action and controller integration**

Lock the user row, require `active`, capture one immutable timestamp, update status/date, delete sessions and social accounts, and remove exports using the configured disk before deleting their rows. Queue the purge through `DB::afterCommit()` with `->delay($requestedAt->addDays(30))`. Then logout, invalidate the current session and regenerate its CSRF token in the controller.

- [ ] **Step 4: Verify GREEN and existing admin semantics**

Run: `php artisan test tests/Feature/Settings/AccountDeletionTest.php tests/Feature/Settings/AccountUpdateTest.php tests/Feature/Admin/ManageMembersTest.php`
Expected: PASS; member row remains pending while admin deletion still removes its target immediately.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/RequestAccountDeletion.php app/Http/Controllers/Settings/AccountController.php tests/Feature/Settings/AccountDeletionTest.php tests/Feature/Settings/AccountUpdateTest.php
git commit -m "feat: revoke accounts pending deletion"
```

### Task 3: Idempotent purge and recovery scheduler

**Files:**
- Create: `app/Jobs/PurgeDeletedUser.php`
- Create: `app/Console/Commands/DispatchDueAccountPurges.php`
- Modify: `routes/console.php`
- Test: `tests/Unit/Jobs/PurgeDeletedUserTest.php`
- Test: `tests/Feature/Console/DispatchDueAccountPurgesTest.php`

**Interfaces:**
- Produces: `PurgeDeletedUser::__construct(public int $userId, public string $requestedAt)` and `handle(): void`.
- Produces command `accounts:dispatch-due-purges`, scheduled hourly without overlap.

- [ ] **Step 1: Write failing purge safety tests**

Build a pending member with profile interests, roles, passkey, onboarding, terms acceptance, sent/received swipes, blocks, match, conversation, messages and a private export file. Freeze time at the deadline and execute the job.

```php
$job->handle();
expect(User::find($user->id))->toBeNull()
    ->and(DB::table('messages')->where('conversation_id', $conversation->id)->exists())->toBeFalse()
    ->and(Storage::disk('exports')->exists($exportPath))->toBeFalse();
$job->handle(); // idempotent replay
```

Add separate tests proving no deletion before 30 days, for an active account, or when `requestedAt` differs by one second. Each safety test asserts the user and unrelated member remain.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Unit/Jobs/PurgeDeletedUserTest.php tests/Feature/Console/DispatchDueAccountPurgesTest.php`
Expected: FAIL because job and command are incomplete/absent.

- [ ] **Step 3: Implement guarded purge and overdue redispatch**

Parse the serialized timestamp in UTC. Reload with a row lock and require matching ID, pending status, exact timestamp, and `deletion_requested_at <= now()->subDays(30)`. Delete known private files first; throw if the filesystem returns false for an existing file. Then delete the user in a transaction so cascades remove relational data.

The command selects only pending users due by `now()->subDays(30)`, chunks by ID, and dispatches one job with the stored timestamp per row. Schedule hourly with `withoutOverlapping()`; duplicate dispatch is safe because of job guards.

- [ ] **Step 4: Verify GREEN and migration cascades**

Run: `php artisan test tests/Unit/Jobs/PurgeDeletedUserTest.php tests/Feature/Console/DispatchDueAccountPurgesTest.php tests/Feature/ConversationSchemaTest.php tests/Feature/MessageSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/DispatchDueAccountPurges.php app/Jobs/PurgeDeletedUser.php routes/console.php tests/Feature/Console/DispatchDueAccountPurgesTest.php tests/Unit/Jobs/PurgeDeletedUserTest.php
git commit -m "feat: purge deleted accounts safely"
```

### Task 4: User communication and operational documentation

**Files:**
- Modify: `resources/js/components/DeleteUser.vue`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Modify: `docs/PRD.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/operations.md`

**Interfaces:**
- Keeps existing `AccountController.destroy.form()` and selectors.

- [ ] **Step 1: Write a failing bilingual browser expectation**

```php
$page->navigate(route('account.edit'))
    ->assertSee('L’accès à ton compte cessera immédiatement')
    ->assertSee('sous 30 jours');
```

Add the English locale equivalent and retain password-error focus behavior.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='delete'`
Expected: FAIL because current copy promises immediate data deletion.

- [ ] **Step 3: Update translated copy and sources of truth**

Explain immediate loss of access, irreversible scheduled purge within 30 days, and no restoration. Mark deferred deletion implemented in the PRD. Document queue/scheduler recovery and state clearly that encrypted daily database/file backups are not rewritten and expire automatically within 30 days.

- [ ] **Step 4: Run focused verification and commit**

Run: `php artisan test tests/Feature/Settings/AccountDeletionTest.php tests/Unit/Jobs/PurgeDeletedUserTest.php tests/Feature/Console/DispatchDueAccountPurgesTest.php && php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='delete'`
Expected: PASS.

```bash
git add docs lang resources/js/components/DeleteUser.vue tests
git commit -m "docs: document deferred account deletion"
```

### Task 5: Integrated quality gate for issues 25 and 26

**Files:**
- Modify only files required to correct failures caused by these two features.

**Interfaces:**
- Validates both plans as one release candidate.

- [ ] **Step 1: Generate frontend routes and run all targeted tests**

Run: `php artisan wayfinder:generate --with-form && php artisan test tests/Feature/Settings tests/Feature/Jobs tests/Unit/Jobs tests/Feature/Console/DispatchDueAccountPurgesTest.php tests/Feature/Admin/ManageMembersTest.php`
Expected: PASS with zero failures.

- [ ] **Step 2: Run backend quality checks**

Run: `composer lint:check && composer analyse && composer test`
Expected: all commands exit 0.

- [ ] **Step 3: Run frontend quality checks and build**

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run build`
Expected: all commands exit 0.

- [ ] **Step 4: Review migration, storage, and scheduler behavior**

Run: `php artisan migrate:fresh --seed --force && php artisan schedule:list && git diff --check && git status --short`
Expected: migrations and seed succeed; both cleanup/recovery schedules appear; no whitespace errors; only intended changes remain.

- [ ] **Step 5: Inspect any quality-only corrections**

Run: `git diff --name-only && git diff --check`
Expected: either no correction remains or every listed path is an intended issues 25/26 file with no whitespace error. If a correction was necessary, stage each listed intended path explicitly and commit it as `fix: satisfy personal data control quality gates`.
