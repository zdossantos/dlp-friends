# Personal Data Export Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow an authenticated active member to request, download, and automatically expire a private JSON export of their own account, profile, interests, matches, and messages.

**Architecture:** Persist each request in `user_data_exports`, generate its JSON asynchronously on a private filesystem disk, and stream it only through an authenticated temporary signed route. A dedicated action owns the document schema; a cleanup command removes expired files and rows.

**Tech Stack:** PHP 8.4, Laravel 13 queues/filesystems/signed URLs/scheduler, Inertia 3, Vue 3 Composition API, TypeScript, Pest.

**Spec:** `docs/superpowers/specs/2026-09-08-personal-data-export-deletion-design.md`

## Global Constraints

- The downloadable artifact is one UTF-8 `.json` file, not a ZIP archive.
- Files use a private configurable disk and never receive a public URL.
- Availability and request cooldown both default to 24 hours.
- All routes require an authenticated, verified, adult, active, fully onboarded member.
- User-visible copy must exist in `lang/fr/account.php` and `lang/en/account.php`; no hard-coded UI copy.
- Never export passwords, 2FA material, tokens, OAuth identifiers, session metadata, internal roles, or another member's private account data.

---

### Task 1: Export persistence and configuration

**Files:**
- Create: `database/migrations/2026_09_08_000000_create_user_data_exports_table.php`
- Create: `app/Enums/UserDataExportStatus.php`
- Create: `app/Models/UserDataExport.php`
- Create: `database/factories/UserDataExportFactory.php`
- Create: `config/data-control.php`
- Modify: `config/filesystems.php`
- Modify: `.env.example`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Settings/UserDataExportSchemaTest.php`

**Interfaces:**
- Produces: `User::dataExports(): HasMany`, `UserDataExport::user(): BelongsTo`, enum cases `Pending`, `Processing`, `Ready`, `Failed`.
- Produces config keys `data-control.exports.disk`, `expires_hours`, `cooldown_hours`.

- [ ] **Step 1: Write the failing schema test**

```php
it('persists a private export lifecycle for its owner', function () {
    $user = User::factory()->create();
    $export = UserDataExport::factory()->for($user)->create([
        'status' => UserDataExportStatus::Ready,
        'path' => "user-data-exports/{$user->id}/export.json",
        'expires_at' => now()->addDay(),
    ]);

    expect($export->user->is($user))->toBeTrue()
        ->and($export->status)->toBe(UserDataExportStatus::Ready)
        ->and($export->expires_at)->toBeInstanceOf(Carbon::class);
});
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Settings/UserDataExportSchemaTest.php`
Expected: FAIL because `UserDataExport` does not exist.

- [ ] **Step 3: Add the enum, migration, model, factory, relation, and config**

The migration columns are `id`, constrained `user_id` with cascade delete, indexed string `status`, nullable string `path`, nullable text `failure_reason`, nullable timestamp `expires_at`, and timestamps. Cast status to the enum and `expires_at` to `datetime`; fill only lifecycle fields. Add a private `exports` disk whose driver/root can target local private storage in development or the existing S3-compatible private bucket in production; document its environment variables in `.env.example`.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Settings/UserDataExportSchemaTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add .env.example app/Enums/UserDataExportStatus.php app/Models/User.php app/Models/UserDataExport.php config/data-control.php config/filesystems.php database/factories/UserDataExportFactory.php database/migrations/2026_09_08_000000_create_user_data_exports_table.php tests/Feature/Settings/UserDataExportSchemaTest.php
git commit -m "feat: persist personal data export requests"
```

### Task 2: JSON document builder and asynchronous generation

**Files:**
- Create: `app/Actions/BuildUserDataExport.php`
- Create: `app/Jobs/ExportUserData.php`
- Test: `tests/Feature/Jobs/ExportUserDataTest.php`

**Interfaces:**
- Consumes: `UserDataExportStatus`, config keys and model from Task 1.
- Produces: `BuildUserDataExport::handle(User $user): array<string, mixed>` and queued `ExportUserData::__construct(int $exportId)`.

- [ ] **Step 1: Write a failing content and exclusion test**

Create two matched members with selected active and archived interests, a conversation, and one message from each author. Set sentinel strings in `password`, `two_factor_secret`, `remember_token`, the other member's email and birth date. Dispatch the job synchronously and decode the stored file.

```php
expect(array_keys($payload))->toBe(['format_version', 'generated_at', 'account', 'profile', 'interests', 'matches', 'messages'])
    ->and($payload['messages'][0]['author'])->toBe('self')
    ->and($payload['messages'][1]['author'])->toBe('other')
    ->and(json_encode($payload))->not->toContain(
        'hashed-password-sentinel',
        'two-factor-sentinel',
        'remember-token-sentinel',
        'other-private@example.com',
    );
Storage::disk('exports')->assertExists($export->fresh()->path);
```

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Jobs/ExportUserDataTest.php`
Expected: FAIL because the builder and job do not exist.

- [ ] **Step 3: Implement the literal document contract**

Query matches where the user is low or high, eager-load both public profiles, conversations and messages, and map the other participant to only `id` and `display_name`. Query `profile.interestHistory` so inactive historical selections remain portable. Encode with `JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES` and write to `user-data-exports/{user_id}/{export_id}.json`.

The job must atomically claim `pending` or `failed` as `processing`, delete any previous path, write the file, then set `ready` and `expires_at`. On failure delete the partial file, mark `failed` with the exception class only (never its sensitive message), and rethrow.

- [ ] **Step 4: Verify GREEN and failure recovery**

Run: `php artisan test tests/Feature/Jobs/ExportUserDataTest.php`
Expected: PASS, including a test using a throwing fake disk that observes `failed` and no file.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/BuildUserDataExport.php app/Jobs/ExportUserData.php tests/Feature/Jobs/ExportUserDataTest.php
git commit -m "feat: generate private personal data exports"
```

### Task 3: Request, authorization, temporary download, and cleanup

**Files:**
- Create: `app/Actions/RequestUserDataExport.php`
- Create: `app/Http/Controllers/Settings/UserDataExportController.php`
- Create: `app/Policies/UserDataExportPolicy.php`
- Create: `app/Console/Commands/CleanupExpiredUserDataExports.php`
- Modify: `routes/settings.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Settings/UserDataExportTest.php`
- Test: `tests/Feature/Console/CleanupExpiredUserDataExportsTest.php`

**Interfaces:**
- Produces: `RequestUserDataExport::handle(User $user): UserDataExport`.
- Produces named routes `data-export.store` and `data-export.download`.
- Produces command `data-exports:cleanup` scheduled hourly without overlap.

- [ ] **Step 1: Write failing request and download tests**

```php
Queue::fake();
$this->actingAs($user)->post(route('data-export.store'))->assertRedirect(route('account.edit'));
Queue::assertPushed(ExportUserData::class);

$url = URL::temporarySignedRoute('data-export.download', now()->addMinutes(10), ['export' => $export]);
$this->actingAs($user)->get($url)->assertDownload("dlp-friends-data-{$export->id}.json");
$this->actingAs($other)->get($url)->assertNotFound();
$this->actingAs($user)->get(route('data-export.download', $export))->assertForbidden();
```

Add cases for guest access, expired rows, missing files, a second request inside 24 hours, and two active requests. Assert the latter cases reuse/refuse without queuing another job.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Settings/UserDataExportTest.php tests/Feature/Console/CleanupExpiredUserDataExportsTest.php`
Expected: FAIL because routes and command do not exist.

- [ ] **Step 3: Implement protected lifecycle endpoints**

Use a transaction plus `lockForUpdate()` in the request action. Return the active recent export and only dispatch a newly created export with `DB::afterCommit`. Attach `throttle:3,60` to creation and `signed` to download; authorize ownership through the policy and return 404 for foreign ownership. Stream with `Storage::disk(...)->download(...)` after checking ready state, expiry and existence.

The cleanup command iterates expired rows by ID, deletes each file first, and deletes the row only when deletion succeeds. Schedule it with `Schedule::command('data-exports:cleanup')->hourly()->withoutOverlapping()`.

- [ ] **Step 4: Verify GREEN**

Run: `php artisan test tests/Feature/Settings/UserDataExportTest.php tests/Feature/Console/CleanupExpiredUserDataExportsTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/RequestUserDataExport.php app/Console/Commands/CleanupExpiredUserDataExports.php app/Http/Controllers/Settings/UserDataExportController.php app/Policies/UserDataExportPolicy.php routes/settings.php routes/console.php tests/Feature/Console/CleanupExpiredUserDataExportsTest.php tests/Feature/Settings/UserDataExportTest.php
git commit -m "feat: secure personal data export lifecycle"
```

### Task 4: Account settings interface and documentation

**Files:**
- Create: `resources/js/components/UserDataExport.vue`
- Modify: `app/Http/Controllers/Settings/AccountController.php`
- Modify: `resources/js/pages/settings/Account.vue`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Modify: `tests/Feature/Settings/UserDataExportTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Modify: `docs/PRD.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/operations.md`

**Interfaces:**
- Account page prop `dataExport: { status: string, expires_at: string|null, download_url: string|null }|null`.

- [ ] **Step 1: Write failing Inertia and browser assertions**

```php
$this->actingAs($user)->get(route('account.edit'))->assertInertia(fn (Assert $page) => $page
    ->where('dataExport.status', 'ready')
    ->where('dataExport.download_url', fn ($url) => str_contains($url, '/settings/data-export/')));
```

In the browser test assert the French export heading and request button are visible, request it, and observe the pending status without a full-page error.

- [ ] **Step 2: Verify RED**

Run: `php artisan test tests/Feature/Settings/UserDataExportTest.php tests/Browser/ProfileAndNavigationTest.php --filter='export'`
Expected: FAIL because the prop and component are absent.

- [ ] **Step 3: Implement bilingual UI and update operational truth**

Build the component with Inertia `Form`, translated pending/ready/failed/expired states, busy semantics, and a normal authenticated link for the signed download. Generate the signed URL in the controller only for a ready non-expired owned row. Mark export as implemented in the PRD and document private storage, queue, hourly cleanup, 24-hour expiry/cooldown, and secret exclusions.

- [ ] **Step 4: Verify task and commit**

Run: `php artisan wayfinder:generate --with-form && php artisan test tests/Feature/Settings/UserDataExportTest.php && php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='export' && bun run types:check`
Expected: PASS.

```bash
git add app/Http/Controllers/Settings/AccountController.php docs lang resources/js tests
git commit -m "feat: expose personal data exports in settings"
```
