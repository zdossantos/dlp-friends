# Partner Space and Announcements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver issue 199 with least-privilege partner accounts, moderated public profiles, consented and idempotent in-app announcements, aggregate engagement statistics, audited role management, and complete personal-data exports.

**Architecture:** Keep the feature inside the existing Laravel/Inertia/Vue monolith. Use explicit Eloquent models and enums for partner profiles, immutable revisions, announcements, deliveries, aggregate counters, consent, and audits; use Policies, Form Requests, transactional Actions, Laravel database notifications, queued jobs, and database uniqueness/locking as the authoritative boundaries. Extend the existing Blade landing, notification center, admin member list, settings, deletion job, and direct JSON export instead of creating parallel infrastructure.

**Tech Stack:** PHP 8.4 with GD image functions, Laravel 13, MySQL 8.4, Redis queues, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-14-partner-space-design.md`

## Global Constraints

- Partner announcements use the in-app database and broadcast notification channel only; no e-mail or web push.
- The `partner` role is cumulative but never implicit; ordinary social features still require `user`, and administration still requires `admin`.
- The partner notification preference is disabled by default and independent from functional notifications.
- Public partner names and descriptions are mandatory in French and English; partner-authored announcement content is never translated automatically.
- Partner titles are at most 80 characters, bodies at most 500 characters, and destinations must be safe absolute HTTPS URLs.
- The configurable cooldown is 30 days by default and accepts 1 through 365 days.
- Public landing pages render at most six manually ordered published partners in server-rendered HTML without the Vue runtime.
- Delivery, unique engagement counters, cooldown enforcement, and retry behavior must remain correct under concurrent requests.
- Partner and admin analytics expose aggregates only, never recipient identities, e-mail addresses, devices, or individual histories.
- Aggregates and minimal audit records are retained for two years; identifiable delivery data is deleted with its member.
- All visible copy, validation, accessibility labels, confirmations, and errors are translated in `lang/fr` and `lang/en`.
- Do not add a campaign framework, repository layer, external analytics service, new frontend state library, or unrelated refactor.
- Every behavior change follows red, green, refactor; run the named test and observe the expected failure before production edits.

---

## File structure

- `app/Enums/PartnerRevisionStatus.php`, `PartnerAnnouncementStatus.php`, `PartnerDeliveryStatus.php`, and `RoleAuditAction.php` own persisted state values.
- `app/Models/PartnerProfile.php`, `PartnerProfileRevision.php`, `PartnerAnnouncement.php`, `PartnerAnnouncementDelivery.php`, `PartnerAnnouncementMetric.php`, `PartnerSetting.php`, `PartnerNotificationPreference.php`, and `RoleAudit.php` own relations and small reusable scopes.
- Focused `app/Actions/*` classes own role synchronization, revision submission/approval, announcement transitions, dispatch preparation, delivery, engagement accounting, deletion cleanup, and expiry.
- `app/Policies/*` and `app/Http/Requests/*` remain the only authorization and HTTP-validation boundaries.
- Partner pages live under `resources/js/pages/Partner`; partner admin pages live under `resources/js/pages/Admin/Partners`; shared partner components live under `resources/js/components/partners`.
- The existing notification presenter, center, settings page, member list, Blade landing, export Action, deletion job, scheduler, translations, and docs are extended in place.

---

### Task 1: Persistence contracts, enums, models, and partner role

**Files:**
- Create: `database/migrations/2026_09_14_200000_create_partner_domain_tables.php`
- Create: `app/Enums/PartnerRevisionStatus.php`
- Create: `app/Enums/PartnerAnnouncementStatus.php`
- Create: `app/Enums/PartnerDeliveryStatus.php`
- Create: `app/Enums/RoleAuditAction.php`
- Create: `app/Models/PartnerProfile.php`
- Create: `app/Models/PartnerProfileRevision.php`
- Create: `app/Models/PartnerAnnouncement.php`
- Create: `app/Models/PartnerAnnouncementDelivery.php`
- Create: `app/Models/PartnerAnnouncementMetric.php`
- Create: `app/Models/PartnerSetting.php`
- Create: `app/Models/PartnerNotificationPreference.php`
- Create: `app/Models/RoleAudit.php`
- Create: `database/factories/PartnerProfileFactory.php`
- Create: `database/factories/PartnerProfileRevisionFactory.php`
- Create: `database/factories/PartnerAnnouncementFactory.php`
- Create: `database/factories/PartnerAnnouncementDeliveryFactory.php`
- Modify: `app/Enums/RoleName.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Partner/PartnerSchemaTest.php`
- Test: `tests/Feature/Partner/PartnerRoleAuthorizationTest.php`
- Test: `tests/Unit/Models/PartnerModelsTest.php`

**Interfaces:**
- Produces: `RoleName::Partner`, all partner Eloquent relations, `PartnerProfile::published()`, `PartnerAnnouncement::ownedBy(User $user)`, `PartnerNotificationPreference::enabledFor(User $user): bool`, and singleton `PartnerSetting::current(): PartnerSetting`.
- Database guarantees: unique `partner_profiles.user_id`, unique `(partner_profile_id, status)` for the single mutable draft through an explicit `draft_key` nullable unique column, unique `(partner_announcement_id, user_id)`, unique delivery `click_token`, one metric row per announcement, and one preference row per user.

- [ ] **Step 1: Write schema and model tests that describe the complete persistence contract**

```php
it('stores the partner role and guarded partner domain tables', function () {
    expect(RoleName::Partner->value)->toBe('partner')
        ->and(Schema::hasColumns('partner_profiles', ['user_id', 'published_revision_id', 'is_published', 'position']))->toBeTrue()
        ->and(Schema::hasColumns('partner_profile_revisions', ['partner_profile_id', 'name_fr', 'name_en', 'description_fr', 'description_en', 'image_path', 'status', 'submitted_at', 'decided_at', 'decided_by', 'rejection_reason', 'draft_key']))->toBeTrue()
        ->and(Schema::hasColumns('partner_announcements', ['partner_profile_id', 'title', 'content', 'destination_url', 'status', 'run_uuid', 'audience_prepared_at', 'sending_started_at', 'sent_at', 'decided_by', 'decided_at', 'rejection_reason']))->toBeTrue()
        ->and(Schema::hasColumns('partner_announcement_deliveries', ['partner_announcement_id', 'user_id', 'notification_id', 'click_token', 'status', 'attempts', 'last_error', 'delivered_at', 'read_at', 'dismissed_at', 'first_clicked_at', 'click_count']))->toBeTrue()
        ->and(Schema::hasColumns('partner_announcement_metrics', ['partner_announcement_id', 'prepared_count', 'delivered_count', 'read_count', 'dismissed_count', 'unique_click_count', 'total_click_count', 'expires_at']))->toBeTrue();
});

it('prevents duplicate partner deliveries', function () {
    $delivery = PartnerAnnouncementDelivery::factory()->create();

    expect(fn () => PartnerAnnouncementDelivery::factory()->create([
        'partner_announcement_id' => $delivery->partner_announcement_id,
        'user_id' => $delivery->user_id,
    ]))->toThrow(QueryException::class);
});

it('does not grant social or admin access to a partner only account', function () {
    $partner = User::factory()->partnerOnly()->create();

    $this->actingAs($partner)->get(route('discovery.index'))->assertForbidden();
    $this->actingAs($partner)->get(route('admin.members.index'))->assertForbidden();
});
```

- [ ] **Step 2: Run the new tests and verify the red state**

Run: `php artisan test tests/Feature/Partner/PartnerSchemaTest.php tests/Feature/Partner/PartnerRoleAuthorizationTest.php tests/Unit/Models/PartnerModelsTest.php`

Expected: FAIL because the enums, tables, models, factories, and `RoleName::Partner` do not exist.

- [ ] **Step 3: Add the enums, migration, models, factories, relations, casts, and default records**

```php
enum PartnerAnnouncementStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Sending = 'sending';
    case Sent = 'sent';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}

public static function current(): self
{
    return self::query()->firstOrCreate(['id' => 1], ['cooldown_days' => 30]);
}

public static function enabledFor(User $user): bool
{
    return self::query()->whereBelongsTo($user)->where('enabled', true)->exists();
}
```

Insert `partner` with `insertOrIgnore`, seed the singleton setting with 30 days, use foreign keys with explicit deletion behavior, store `click_token` as a unique 64-character hash, and add indexes for announcement status, partner position, delivery status, and expiry scans. Wrap the existing profile/onboarding/member/social route tree with `role:user`; place partner routes as a sibling under `auth`, `verified`, `social`, and `role:partner`, so active adult partner-only accounts can manage partner data but cannot enter discovery, events, conversations, notifications, or member settings.

- [ ] **Step 4: Run the focused tests and model suite**

Run: `php artisan test tests/Feature/Partner/PartnerSchemaTest.php tests/Feature/Partner/PartnerRoleAuthorizationTest.php tests/Unit/Models/PartnerModelsTest.php tests/Unit/Models/UserTest.php`

Expected: PASS.

- [ ] **Step 5: Commit the persistence foundation**

```bash
git add app/Enums app/Models database/factories database/migrations/2026_09_14_200000_create_partner_domain_tables.php database/seeders/DatabaseSeeder.php routes/web.php tests/Feature/Partner/PartnerSchemaTest.php tests/Feature/Partner/PartnerRoleAuthorizationTest.php tests/Unit/Models/PartnerModelsTest.php tests/Unit/Models/UserTest.php
git commit -m "feat(partners): add partner domain persistence"
```

---

### Task 2: Audited role management from the admin member list

**Files:**
- Create: `app/Actions/SyncManageableUserRoles.php`
- Create: `app/Http/Controllers/Admin/MemberRoleController.php`
- Create: `app/Http/Requests/Admin/UpdateMemberRolesRequest.php`
- Create: `resources/js/components/admin/ManageMemberRolesDialog.vue`
- Modify: `app/Policies/UserPolicy.php`
- Modify: `app/Http/Controllers/Admin/MemberController.php`
- Modify: `resources/js/pages/Admin/Members/Index.vue`
- Modify: `resources/js/types/auth.ts`
- Modify: `routes/web.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Test: `tests/Feature/Admin/ManageMemberRolesTest.php`
- Test: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: `RoleName::User`, `RoleName::Partner`, `RoleAuditAction` and `RoleAudit` from Task 1.
- Produces: `SyncManageableUserRoles::handle(User $actor, User $target, array $roles): void`, `UserPolicy::manageRoles(User $actor, User $target): bool`, and route `PATCH admin/members/{member}/roles` named `admin.members.roles.update`.

- [ ] **Step 1: Add failing feature tests for authorization, exact synchronization, no-op behavior, and audit**

```php
it('lets an admin assign and remove manageable roles with an immutable audit', function () {
    $admin = User::factory()->admin()->create();
    $member = User::factory()->create();

    $this->actingAs($admin)->patch(route('admin.members.roles.update', $member), [
        'roles' => ['partner'],
        'confirmed' => true,
    ])->assertRedirect();

    expect($member->fresh('roles')->hasRole(RoleName::Partner))->toBeTrue()
        ->and($member->hasRole(RoleName::User))->toBeFalse();
    $this->assertDatabaseHas('role_audits', [
        'actor_user_id' => $admin->id,
        'target_user_id' => $member->id,
        'role' => 'partner',
        'action' => 'assigned',
    ]);
});

it('refuses self changes admin role changes and unconfirmed changes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.members.roles.update', $admin), [
        'roles' => ['user'],
        'confirmed' => true,
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run the role feature tests and verify they fail because the route is absent**

Run: `php artisan test tests/Feature/Admin/ManageMemberRolesTest.php`

Expected: FAIL with route `admin.members.roles.update` not defined.

- [ ] **Step 3: Implement the Policy, Form Request, transactional Action, controller, route, and member-list props**

```php
public function handle(User $actor, User $target, array $roles): void
{
    DB::transaction(function () use ($actor, $target, $roles): void {
        $locked = User::query()->lockForUpdate()->with('roles')->findOrFail($target->id);
        $wanted = collect($roles)->map(fn (string $role) => RoleName::from($role));
        $manageable = collect([RoleName::User, RoleName::Partner]);
        $before = $locked->roles->pluck('name')->filter(fn (RoleName $role) => $manageable->contains($role));

        foreach ($manageable as $role) {
            $had = $before->contains($role);
            $has = $wanted->contains($role);
            if ($had === $has) {
                continue;
            }
            $has ? app(AssignRole::class)->handle($locked, $role) : $locked->roles()->detach(Role::where('name', $role)->value('id'));
            RoleAudit::create(['actor_user_id' => $actor->id, 'target_user_id' => $locked->id, 'role' => $role, 'action' => $has ? RoleAuditAction::Assigned : RoleAuditAction::Removed]);
        }
    });
}
```

Validate `confirmed` as `accepted` and `roles.*` with `Rule::enum(RoleName::class)` plus an `after` hook rejecting `admin`. Return only `roles: Array<{name: 'user'|'admin'|'partner'}>` and `can_manage_roles` from `MemberController`.

- [ ] **Step 4: Add the accessible Vue confirmation dialog and browser scenario**

```vue
<ManageMemberRolesDialog
    v-if="member.can_manage_roles"
    :member-id="member.id"
    :display-name="member.display_name ?? member.email"
    :roles="member.roles"
/>
```

The dialog uses existing `Dialog`, `Checkbox`, `InputError`, and `Button` primitives, posts only `user`/`partner`, displays `admin` read-only, and requires a confirmation checkbox before enabling submit.

- [ ] **Step 5: Run role feature and browser tests**

Run: `php artisan test tests/Feature/Admin/ManageMemberRolesTest.php tests/Browser/AdminTest.php --filter='roles'`

Expected: PASS with translated labels and immediate partner-route revocation after removal.

- [ ] **Step 6: Commit audited role management**

```bash
git add app/Actions/SyncManageableUserRoles.php app/Http/Controllers/Admin/MemberRoleController.php app/Http/Requests/Admin/UpdateMemberRolesRequest.php app/Policies/UserPolicy.php app/Http/Controllers/Admin/MemberController.php resources/js/components/admin/ManageMemberRolesDialog.vue resources/js/pages/Admin/Members/Index.vue resources/js/types/auth.ts routes/web.php lang/fr/administration.php lang/en/administration.php tests/Feature/Admin/ManageMemberRolesTest.php tests/Browser/AdminTest.php
git commit -m "feat(admin): manage user roles with audit"
```

---

### Task 3: Partner profile drafts and secure images

**Files:**
- Create: `app/Actions/SavePartnerProfileDraft.php`
- Create: `app/Actions/SubmitPartnerProfileRevision.php`
- Create: `app/Actions/TransformPartnerImage.php`
- Create: `app/Http/Controllers/Partner/ProfileController.php`
- Create: `app/Http/Controllers/Partner/ProfileSubmissionController.php`
- Create: `app/Http/Controllers/Partner/ProfileImageController.php`
- Create: `app/Http/Requests/Partner/SavePartnerProfileRequest.php`
- Create: `app/Policies/PartnerProfilePolicy.php`
- Create: `resources/js/pages/Partner/Profile/Edit.vue`
- Create: `resources/js/components/partners/PartnerProfileForm.vue`
- Create: `lang/fr/partners.php`
- Create: `lang/en/partners.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Partner/PartnerProfileTest.php`
- Test: `tests/Feature/Partner/PartnerImageTest.php`

**Interfaces:**
- Consumes: partner models and `role:partner` middleware.
- Produces: `SavePartnerProfileDraft::handle(User $partner, array $data, ?UploadedFile $image): PartnerProfileRevision`, `SubmitPartnerProfileRevision::handle(User $partner): PartnerProfileRevision`, and controlled route `partner.profile-revisions.image`.

- [ ] **Step 1: Write failing tests for bilingual validation, draft replacement, immutable submission, authorization, and image sanitization**

```php
it('keeps a published revision visible while saving a new draft', function () {
    Storage::fake('s3');
    $partner = User::factory()->partner()->create();
    $profile = PartnerProfile::factory()->for($partner)->published()->create();
    $published = $profile->publishedRevision;

    $this->actingAs($partner)->put(route('partner.profile.update'), [
        'name_fr' => 'Nouvelle fiche', 'name_en' => 'New profile',
        'description_fr' => 'Description française', 'description_en' => 'English description',
        'image' => UploadedFile::fake()->image('partner.jpg', 1200, 800),
    ])->assertRedirect();

    expect($profile->fresh()->published_revision_id)->toBe($published->id)
        ->and($profile->revisions()->where('status', 'draft')->count())->toBe(1);
});
```

- [ ] **Step 2: Run the profile tests and observe the missing-route/model behavior failure**

Run: `php artisan test tests/Feature/Partner/PartnerProfileTest.php tests/Feature/Partner/PartnerImageTest.php`

Expected: FAIL because partner profile routes and Actions do not exist.

- [ ] **Step 3: Implement the Policy, Requests, image transformation, and transactional draft/submission Actions**

```php
public function rules(): array
{
    return [
        'name_fr' => ['required', 'string', 'max:100'],
        'name_en' => ['required', 'string', 'max:100'],
        'description_fr' => ['required', 'string', 'max:500'],
        'description_en' => ['required', 'string', 'max:500'],
        'image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024)->dimensions(Rule::dimensions()->minWidth(640)->minHeight(360)->maxWidth(6000)->maxHeight(6000))],
    ];
}
```

`TransformPartnerImage` reads the upload bytes, decodes with `imagecreatefromstring`, applies EXIF orientation for JPEG when available, resamples inside 1600×900 with GD, encodes a fresh WebP at quality 85 without metadata, stores on the configured private disk, and deletes a newly written file if the surrounding draft transaction fails. Reuse the avatar storage response style for authorized image streaming.

- [ ] **Step 4: Implement the partner edit page and form with current published state and pending decision**

Use multipart Inertia submission, existing Inputs/Textareas/InputError, an image preview URL created with `URL.createObjectURL`, translated rights copy, and distinct Save/Submit buttons. Do not expose storage paths.

- [ ] **Step 5: Run focused tests, frontend types, and formatting**

Run: `php artisan test tests/Feature/Partner/PartnerProfileTest.php tests/Feature/Partner/PartnerImageTest.php && bun run types:check && bun run format:check`

Expected: PASS.

- [ ] **Step 6: Commit partner profile drafting**

```bash
git add app/Actions/SavePartnerProfileDraft.php app/Actions/SubmitPartnerProfileRevision.php app/Actions/TransformPartnerImage.php app/Http/Controllers/Partner app/Http/Requests/Partner app/Policies/PartnerProfilePolicy.php resources/js/pages/Partner/Profile/Edit.vue resources/js/components/partners/PartnerProfileForm.vue lang/fr/partners.php lang/en/partners.php routes/web.php tests/Feature/Partner/PartnerProfileTest.php tests/Feature/Partner/PartnerImageTest.php
git commit -m "feat(partners): add moderated profile drafts"
```

---

### Task 4: Admin profile moderation and public landing cards

**Files:**
- Create: `app/Actions/ApprovePartnerProfileRevision.php`
- Create: `app/Actions/RejectPartnerProfileRevision.php`
- Create: `app/Actions/UpdatePublishedPartnerOrder.php`
- Create: `app/Http/Controllers/Admin/PartnerProfileController.php`
- Create: `app/Http/Controllers/Admin/PartnerProfileDecisionController.php`
- Create: `app/Http/Controllers/Admin/PartnerProfileOrderController.php`
- Create: `app/Http/Requests/Admin/DecidePartnerProfileRequest.php`
- Create: `app/Http/Requests/Admin/OrderPartnerProfilesRequest.php`
- Create: `resources/js/pages/Admin/Partners/Profiles.vue`
- Create: `resources/js/components/partners/PartnerModerationCard.vue`
- Create: `resources/views/components/public-partner-card.blade.php`
- Modify: `app/Http/Controllers/PublicLandingController.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `routes/web.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Test: `tests/Feature/Admin/PartnerProfileModerationTest.php`
- Test: `tests/Feature/PublicLandingTest.php`
- Test: `tests/Browser/AdminTest.php`
- Test: `tests/Browser/WelcomeAndRegistrationTest.php`

**Interfaces:**
- Consumes: immutable submitted revisions and private images from Task 3.
- Produces: `ApprovePartnerProfileRevision::handle(User $admin, PartnerProfileRevision $revision): void`, `RejectPartnerProfileRevision::handle(User $admin, PartnerProfileRevision $revision, ?string $reason): void`, and `UpdatePublishedPartnerOrder::handle(array $orderedIds): void`.

- [ ] **Step 1: Add failing tests for transactional publication, rejection, unpublish, ordering, locale, and six-card limit**

```php
it('renders only the first six published partners in the requested language', function () {
    PartnerProfile::factory()->count(7)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->published()->create();
    PartnerProfile::factory()->unpublished()->create();

    $response = $this->get('/en')->assertOk();

    $response->assertViewHas('partners', fn ($partners) => $partners->count() === 6)
        ->assertSee('data-test="public-partner-card"', false)
        ->assertDontSee('@vite([\'resources/js/app.ts\'])', false);
});
```

- [ ] **Step 2: Run the moderation and landing tests and verify the expected failures**

Run: `php artisan test tests/Feature/Admin/PartnerProfileModerationTest.php tests/Feature/PublicLandingTest.php`

Expected: FAIL because moderation endpoints and the `partners` landing view data do not exist.

- [ ] **Step 3: Implement locked approve/reject/unpublish/order Actions and admin endpoints**

Approval locks profile and revision, requires `pending_approval`, marks any prior approved revision historical without mutating its content, sets `published_revision_id`, and publishes. Ordering validates the exact set of currently published IDs and writes consecutive positions in one transaction.

- [ ] **Step 4: Render localized published cards in Blade and add accessible admin moderation UI**

```blade
@if ($partners->isNotEmpty())
    <section aria-labelledby="partners-title" class="mx-auto mt-24 w-full max-w-5xl">
        <h2 id="partners-title" class="text-center text-3xl font-semibold">{{ __('common.welcome.partners.title') }}</h2>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($partners as $partner)
                <x-public-partner-card :partner="$partner" :locale="$seo['locale']" />
            @endforeach
        </div>
    </section>
@endif
```

The card image uses the controlled public published-image route, has localized alt text based on the partner name, and no external link because links belong to approved announcements.

- [ ] **Step 5: Run feature/browser tests and build**

Run: `php artisan test tests/Feature/Admin/PartnerProfileModerationTest.php tests/Feature/PublicLandingTest.php tests/Browser/AdminTest.php tests/Browser/WelcomeAndRegistrationTest.php && bun run build`

Expected: PASS in both locales and responsive scenarios.

- [ ] **Step 6: Commit moderation and public presentation**

```bash
git add app/Actions/ApprovePartnerProfileRevision.php app/Actions/RejectPartnerProfileRevision.php app/Actions/UpdatePublishedPartnerOrder.php app/Http/Controllers/Admin/PartnerProfileController.php app/Http/Controllers/Admin/PartnerProfileDecisionController.php app/Http/Controllers/Admin/PartnerProfileOrderController.php app/Http/Requests/Admin/DecidePartnerProfileRequest.php app/Http/Requests/Admin/OrderPartnerProfilesRequest.php resources/js/pages/Admin/Partners/Profiles.vue resources/js/components/partners/PartnerModerationCard.vue resources/views/components/public-partner-card.blade.php app/Http/Controllers/PublicLandingController.php resources/views/welcome.blade.php routes/web.php lang/fr/administration.php lang/en/administration.php lang/fr/common.php lang/en/common.php tests/Feature/Admin/PartnerProfileModerationTest.php tests/Feature/PublicLandingTest.php tests/Browser/AdminTest.php tests/Browser/WelcomeAndRegistrationTest.php
git commit -m "feat(partners): moderate and publish partner profiles"
```

---

### Task 5: Announcement authoring, safe links, moderation, and cooldown

**Files:**
- Create: `app/Rules/SafeHttpsUrl.php`
- Create: `app/Actions/SavePartnerAnnouncement.php`
- Create: `app/Actions/SubmitPartnerAnnouncement.php`
- Create: `app/Actions/DecidePartnerAnnouncement.php`
- Create: `app/Http/Controllers/Partner/AnnouncementController.php`
- Create: `app/Http/Controllers/Partner/AnnouncementSubmissionController.php`
- Create: `app/Http/Controllers/Admin/PartnerAnnouncementController.php`
- Create: `app/Http/Controllers/Admin/PartnerAnnouncementDecisionController.php`
- Create: `app/Http/Controllers/Admin/PartnerSettingController.php`
- Create: `app/Http/Requests/Partner/SavePartnerAnnouncementRequest.php`
- Create: `app/Http/Requests/Admin/DecidePartnerAnnouncementRequest.php`
- Create: `app/Http/Requests/Admin/UpdatePartnerSettingRequest.php`
- Create: `app/Policies/PartnerAnnouncementPolicy.php`
- Create: `resources/js/pages/Partner/Announcements/Index.vue`
- Create: `resources/js/pages/Partner/Announcements/Edit.vue`
- Create: `resources/js/pages/Admin/Partners/Announcements.vue`
- Create: `resources/js/components/partners/PartnerAnnouncementForm.vue`
- Modify: `routes/web.php`
- Modify: `lang/fr/partners.php`
- Modify: `lang/en/partners.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Test: `tests/Feature/Partner/PartnerAnnouncementTest.php`
- Test: `tests/Feature/Admin/PartnerAnnouncementModerationTest.php`
- Test: `tests/Unit/Rules/SafeHttpsUrlTest.php`

**Interfaces:**
- Produces: `SafeHttpsUrl`, `SavePartnerAnnouncement::handle(User $partner, array $data, ?PartnerAnnouncement $announcement = null): PartnerAnnouncement`, `SubmitPartnerAnnouncement::handle(User $partner, PartnerAnnouncement $announcement): void`, and `DecidePartnerAnnouncement::approve|reject|cancel(...)`.

- [ ] **Step 1: Write failing unit and feature tests for content limits, ownership, immutable submission, transitions, dangerous links, and cooldown**

```php
it('rejects unsafe destinations', function (string $url) {
    Validator::make(['url' => $url], ['url' => [new SafeHttpsUrl]])->validate();
})->with([
    'javascript' => 'javascript:alert(1)',
    'credentials' => 'https://user:secret@example.com',
    'localhost' => 'https://localhost/offer',
    'private ipv4' => 'https://192.168.1.10/offer',
    'private ipv6' => 'https://[::1]/offer',
])->throws(ValidationException::class);

it('refuses approval during the partner cooldown', function () {
    $announcement = PartnerAnnouncement::factory()->pendingApproval()->create();
    PartnerAnnouncement::factory()->for($announcement->partnerProfile)->sent()->create(['sending_started_at' => now()->subDays(29)]);

    expect(fn () => app(DecidePartnerAnnouncement::class)->approve(User::factory()->admin()->create(), $announcement))
        ->toThrow(ValidationException::class);
});
```

- [ ] **Step 2: Run the tests and verify they fail for missing rule and Actions**

Run: `php artisan test tests/Unit/Rules/SafeHttpsUrlTest.php tests/Feature/Partner/PartnerAnnouncementTest.php tests/Feature/Admin/PartnerAnnouncementModerationTest.php`

Expected: FAIL because `SafeHttpsUrl` and announcement routes do not exist.

- [ ] **Step 3: Implement the URL rule and exact announcement state transitions**

Use `parse_url`, require `https`, host, no `user`/`pass`, reject `localhost`, `.local`, and IPs for which `FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE` fails. Do not perform network requests. Lock the announcement for every transition, re-run the rule at submit/approve, and reject changes unless status is `draft`.

- [ ] **Step 4: Implement partner/admin pages and singleton cooldown editing**

The partner list exposes status, submitted/decision dates, reason, Edit only for drafts, Submit, and Cancel only before sending. The admin page exposes pending announcements, safe destination as a visible external link with `rel="noopener noreferrer"`, approve/reject/cancel controls, and cooldown integer 1–365.

- [ ] **Step 5: Run focused tests and frontend checks**

Run: `php artisan test tests/Unit/Rules/SafeHttpsUrlTest.php tests/Feature/Partner/PartnerAnnouncementTest.php tests/Feature/Admin/PartnerAnnouncementModerationTest.php && bun run lint:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit announcement authoring and moderation**

```bash
git add app/Rules/SafeHttpsUrl.php app/Actions/SavePartnerAnnouncement.php app/Actions/SubmitPartnerAnnouncement.php app/Actions/DecidePartnerAnnouncement.php app/Http/Controllers/Partner/AnnouncementController.php app/Http/Controllers/Partner/AnnouncementSubmissionController.php app/Http/Controllers/Admin/PartnerAnnouncementController.php app/Http/Controllers/Admin/PartnerAnnouncementDecisionController.php app/Http/Controllers/Admin/PartnerSettingController.php app/Http/Requests/Partner/SavePartnerAnnouncementRequest.php app/Http/Requests/Admin/DecidePartnerAnnouncementRequest.php app/Http/Requests/Admin/UpdatePartnerSettingRequest.php app/Policies/PartnerAnnouncementPolicy.php resources/js/pages/Partner/Announcements resources/js/pages/Admin/Partners/Announcements.vue resources/js/components/partners/PartnerAnnouncementForm.vue routes/web.php lang/fr/partners.php lang/en/partners.php lang/fr/administration.php lang/en/administration.php tests/Feature/Partner/PartnerAnnouncementTest.php tests/Feature/Admin/PartnerAnnouncementModerationTest.php tests/Unit/Rules/SafeHttpsUrlTest.php
git commit -m "feat(partners): add moderated announcements"
```

---

### Task 6: Explicit member consent and notification navigation

**Files:**
- Create: `app/Http/Controllers/Settings/NotificationPreferenceController.php`
- Create: `app/Http/Requests/Settings/UpdateNotificationPreferenceRequest.php`
- Create: `resources/js/pages/settings/Notifications.vue`
- Modify: `routes/settings.php`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Test: `tests/Feature/Settings/PartnerNotificationPreferenceTest.php`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Consumes: `PartnerNotificationPreference::enabledFor(User $user): bool`.
- Produces: routes `notifications-preferences.edit` and `notifications-preferences.update`, and shared auth role name `partner` for navigation.

- [ ] **Step 1: Write failing tests for default-off, explicit opt-in, withdrawal, and independence from functional notifications**

```php
it('keeps partner announcements disabled until explicit opt in', function () {
    $user = User::factory()->create();

    expect(PartnerNotificationPreference::enabledFor($user))->toBeFalse();

    $this->actingAs($user)->patch(route('notification-preferences.update'), [
        'partner_announcements' => true,
    ])->assertRedirect();

    expect(PartnerNotificationPreference::enabledFor($user))->toBeTrue();
});
```

- [ ] **Step 2: Run the settings tests and verify the missing route failure**

Run: `php artisan test tests/Feature/Settings/PartnerNotificationPreferenceTest.php`

Expected: FAIL because notification preference routes do not exist.

- [ ] **Step 3: Implement controller, validation, routes, and settings page**

Upsert the one preference row with `enabled` and `updated_at`; never infer consent from another setting. The settings page uses a labeled `Switch`, explanatory text, and a save button with translated success/error feedback.

- [ ] **Step 4: Add partner navigation based on `auth.user.roles` and browser coverage**

Add one translated partner navigation group linking to profile, announcements, and statistics only when roles contain `partner`. Confirm that a removed role followed by navigation returns 403 and the next Inertia response omits the group.

- [ ] **Step 5: Run settings/browser tests and frontend checks**

Run: `php artisan test tests/Feature/Settings/PartnerNotificationPreferenceTest.php tests/Browser/ProfileAndNavigationTest.php --filter='partner' && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit consent and navigation**

```bash
git add app/Http/Controllers/Settings/NotificationPreferenceController.php app/Http/Requests/Settings/UpdateNotificationPreferenceRequest.php resources/js/pages/settings/Notifications.vue routes/settings.php resources/js/layouts/settings/Layout.vue resources/js/components/AppSidebar.vue resources/js/components/MemberBottomNavigation.vue app/Http/Middleware/HandleInertiaRequests.php lang/fr/account.php lang/en/account.php tests/Feature/Settings/PartnerNotificationPreferenceTest.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat(partners): add announcement consent controls"
```

---

### Task 7: Idempotent audience preparation and queued delivery

**Files:**
- Create: `app/Actions/StartPartnerAnnouncement.php`
- Create: `app/Actions/PreparePartnerAnnouncementAudience.php`
- Create: `app/Actions/DeliverPartnerAnnouncement.php`
- Create: `app/Actions/FinalizePartnerAnnouncement.php`
- Create: `app/Jobs/PreparePartnerAnnouncementAudience.php`
- Create: `app/Jobs/DeliverPartnerAnnouncement.php`
- Create: `app/Notifications/PartnerAnnouncementNotification.php`
- Create: `app/Http/Controllers/Admin/PartnerAnnouncementDispatchController.php`
- Create: `app/Http/Controllers/Admin/PartnerAnnouncementRetryController.php`
- Modify: `app/Models/User.php`
- Modify: `routes/web.php`
- Modify: `lang/fr/notifications.php`
- Modify: `lang/en/notifications.php`
- Test: `tests/Feature/Partner/PartnerAnnouncementDispatchTest.php`
- Test: `tests/Feature/Partner/PartnerAnnouncementConcurrencyTest.php`

**Interfaces:**
- Produces: `StartPartnerAnnouncement::handle(User $admin, PartnerAnnouncement $announcement): void`, `PreparePartnerAnnouncementAudience::handle(PartnerAnnouncement $announcement): void`, `DeliverPartnerAnnouncement::handle(PartnerAnnouncementDelivery $delivery): void`, and `FinalizePartnerAnnouncement::handle(PartnerAnnouncement $announcement): void`.

- [ ] **Step 1: Write failing tests for eligible recipients, transaction dispatch, cooldown locking, duplicates, revoked consent, partial failure, and retry**

```php
it('delivers once only to members still eligible at delivery time', function () {
    Notification::fake();
    $eligible = User::factory()->withPartnerAnnouncements()->create();
    $withdrawn = User::factory()->withPartnerAnnouncements()->create();
    $inactive = User::factory()->inactive()->withPartnerAnnouncements()->create();
    $announcement = PartnerAnnouncement::factory()->approved()->create();

    app(PreparePartnerAnnouncementAudience::class)->handle($announcement);
    $withdrawn->partnerNotificationPreference()->update(['enabled' => false]);
    foreach ($announcement->deliveries as $delivery) {
        app(DeliverPartnerAnnouncement::class)->handle($delivery);
        app(DeliverPartnerAnnouncement::class)->handle($delivery->fresh());
    }

    Notification::assertSentToTimes($eligible, PartnerAnnouncementNotification::class, 1);
    Notification::assertNothingSentTo($withdrawn);
    Notification::assertNothingSentTo($inactive);
});
```

- [ ] **Step 2: Run dispatch tests and verify failure because dispatch Actions are missing**

Run: `php artisan test tests/Feature/Partner/PartnerAnnouncementDispatchTest.php tests/Feature/Partner/PartnerAnnouncementConcurrencyTest.php`

Expected: FAIL on missing `StartPartnerAnnouncement`.

- [ ] **Step 3: Implement locked start and chunked audience preparation**

`StartPartnerAnnouncement` locks the partner profile and announcement, requires `approved`, rechecks `SafeHttpsUrl`, checks the latest other `sending_started_at` against `PartnerSetting::cooldown_days`, assigns `run_uuid`, creates the metric row, switches to `sending`, and dispatches `PreparePartnerAnnouncementAudience` with `DB::afterCommit`.

`PreparePartnerAnnouncementAudience` uses `User::query()->eligibleForPartnerAnnouncements()->chunkById(500, ...)`, `insertOrIgnore` on delivery rows, atomically sets `prepared_count`, marks `audience_prepared_at`, and dispatches one delivery job per newly inserted row.

- [ ] **Step 4: Implement idempotent delivery, failure metadata, retry, and finalization**

Lock each delivery. Return immediately for `delivered`/`skipped`; recheck active/verified/not-deleting/role/consent; create exactly one database notification whose ID is generated before send and persisted as `notification_id`; mark delivery and increment metric in one transaction. On final job failure, store a bounded exception class/message without payload or PII. Retry endpoints reset only `failed` to `pending`. Finalization requires `audience_prepared_at` and no `pending`/`failed` rows.

- [ ] **Step 5: Run dispatch tests repeatedly to exercise uniqueness**

Run: `php artisan test tests/Feature/Partner/PartnerAnnouncementDispatchTest.php tests/Feature/Partner/PartnerAnnouncementConcurrencyTest.php --repeat=3`

Expected: PASS with one notification per announcement/member and correct skipped counts.

- [ ] **Step 6: Commit reliable delivery**

```bash
git add app/Actions/StartPartnerAnnouncement.php app/Actions/PreparePartnerAnnouncementAudience.php app/Actions/DeliverPartnerAnnouncement.php app/Actions/FinalizePartnerAnnouncement.php app/Jobs/PreparePartnerAnnouncementAudience.php app/Jobs/DeliverPartnerAnnouncement.php app/Notifications/PartnerAnnouncementNotification.php app/Http/Controllers/Admin/PartnerAnnouncementDispatchController.php app/Http/Controllers/Admin/PartnerAnnouncementRetryController.php app/Models/User.php routes/web.php lang/fr/notifications.php lang/en/notifications.php tests/Feature/Partner/PartnerAnnouncementDispatchTest.php tests/Feature/Partner/PartnerAnnouncementConcurrencyTest.php
git commit -m "feat(partners): deliver announcements idempotently"
```

---

### Task 8: Notification reading, dismissal, tracked redirects, and atomic metrics

**Files:**
- Create: `app/Actions/RecordPartnerAnnouncementRead.php`
- Create: `app/Actions/DismissPartnerAnnouncement.php`
- Create: `app/Actions/RecordPartnerAnnouncementClick.php`
- Create: `app/Http/Controllers/PartnerAnnouncementDismissController.php`
- Create: `app/Http/Controllers/PartnerAnnouncementClickController.php`
- Modify: `app/Http/Controllers/NotificationReadController.php`
- Modify: `app/Http/Controllers/NotificationReadAllController.php`
- Modify: `app/Http/Controllers/NotificationIndexController.php`
- Modify: `app/Support/MemberNotificationPresenter.php`
- Modify: `resources/js/components/notifications/NotificationItem.vue`
- Modify: `resources/js/components/notifications/NotificationFilters.vue`
- Modify: `resources/js/lib/notificationFilters.ts`
- Modify: `resources/js/types/notification.ts`
- Modify: `routes/web.php`
- Modify: `lang/fr/notifications.php`
- Modify: `lang/en/notifications.php`
- Test: `tests/Feature/Partner/PartnerAnnouncementEngagementTest.php`
- Test: `tests/Feature/NotificationIndexTest.php`
- Test: `tests/Frontend/notificationFilters.test.js`

**Interfaces:**
- Produces: `RecordPartnerAnnouncementRead::handle(User $user, DatabaseNotification $notification): void`, `DismissPartnerAnnouncement::handle(User $user, DatabaseNotification $notification): void`, and `RecordPartnerAnnouncementClick::handle(string $token): string` returning the frozen safe destination.

- [ ] **Step 1: Write failing tests for read, read-all, dismissal ownership, total/unique concurrent clicks, and opaque redirect**

```php
it('counts all clicks but the first click only once', function () {
    $delivery = PartnerAnnouncementDelivery::factory()->delivered()->create(['click_count' => 0, 'first_clicked_at' => null]);

    expect(app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token))->toBe($delivery->announcement->destination_url);
    expect(app(RecordPartnerAnnouncementClick::class)->handle($delivery->click_token))->toBe($delivery->announcement->destination_url);

    expect($delivery->fresh()->click_count)->toBe(2)
        ->and($delivery->announcement->metric->fresh()->unique_click_count)->toBe(1)
        ->and($delivery->announcement->metric->total_click_count)->toBe(2);
});
```

- [ ] **Step 2: Run engagement tests and verify the missing Action failure**

Run: `php artisan test tests/Feature/Partner/PartnerAnnouncementEngagementTest.php tests/Feature/NotificationIndexTest.php`

Expected: FAIL because engagement Actions and dismissal/click routes do not exist.

- [ ] **Step 3: Implement engagement Actions with row locks and ownership checks**

Read increments only when both `notification.read_at` and `delivery.read_at` are null. Dismiss requires the authenticated owner, sets `dismissed_at`, increments once, and deletes the database notification only after the delivery timestamp is durable. Click uses `hash_equals` semantics through exact indexed token lookup, locks delivery and metric, increments total every time, unique only when `first_clicked_at` was null, and returns the already validated frozen URL.

- [ ] **Step 4: Extend notification presenter, filtering, and UI**

Add category `'partners'`, translation key `'notifications.items.partner_announcement'`, optional `dismiss_url`, and action label. Partner notification target points to the opaque click route. The dismiss button is a separate keyboard target, stops link propagation, asks for a translated confirmation, and removes the item through Inertia only after success.

- [ ] **Step 5: Run backend/frontend tests and types**

Run: `php artisan test tests/Feature/Partner/PartnerAnnouncementEngagementTest.php tests/Feature/NotificationIndexTest.php && bun test tests/Frontend/notificationFilters.test.js && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit engagement tracking**

```bash
git add app/Actions/RecordPartnerAnnouncementRead.php app/Actions/DismissPartnerAnnouncement.php app/Actions/RecordPartnerAnnouncementClick.php app/Http/Controllers/PartnerAnnouncementDismissController.php app/Http/Controllers/PartnerAnnouncementClickController.php app/Http/Controllers/NotificationReadController.php app/Http/Controllers/NotificationReadAllController.php app/Http/Controllers/NotificationIndexController.php app/Support/MemberNotificationPresenter.php resources/js/components/notifications/NotificationItem.vue resources/js/components/notifications/NotificationFilters.vue resources/js/lib/notificationFilters.ts resources/js/types/notification.ts routes/web.php lang/fr/notifications.php lang/en/notifications.php tests/Feature/Partner/PartnerAnnouncementEngagementTest.php tests/Feature/NotificationIndexTest.php tests/Frontend/notificationFilters.test.js
git commit -m "feat(partners): track announcement engagement"
```

---

### Task 9: Aggregate partner and admin statistics

**Files:**
- Create: `app/Data/PartnerAnnouncementStatisticsData.php`
- Create: `app/Http/Controllers/Partner/StatisticsController.php`
- Create: `app/Http/Controllers/Admin/PartnerStatisticsController.php`
- Create: `resources/js/pages/Partner/Statistics/Index.vue`
- Create: `resources/js/pages/Admin/Partners/Statistics.vue`
- Create: `resources/js/components/partners/AnnouncementStatisticsTable.vue`
- Modify: `routes/web.php`
- Modify: `lang/fr/partners.php`
- Modify: `lang/en/partners.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Test: `tests/Feature/Partner/PartnerStatisticsTest.php`
- Test: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: `PartnerAnnouncementMetric` and delivery operational states.
- Produces: `PartnerAnnouncementStatisticsData::from(PartnerAnnouncement $announcement, bool $includeOperations = false): array` with `prepared`, `delivered`, `read`, `dismissed`, `unique_clicks`, `total_clicks`, `read_rate`, `dismiss_rate`, `unique_click_rate`, and admin-only `pending`, `failed`, `skipped`.

- [ ] **Step 1: Write failing tests for ownership, aggregate-only payloads, zero denominators, and admin operational counts**

```php
it('returns aggregates without recipient identity', function () {
    $partner = User::factory()->partner()->create();
    $announcement = PartnerAnnouncement::factory()->forPartner($partner)->sent()->create();
    PartnerAnnouncementMetric::factory()->for($announcement)->create(['delivered_count' => 10, 'read_count' => 5, 'unique_click_count' => 2]);

    $response = $this->actingAs($partner)->get(route('partner.statistics.index'))->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->where('announcements.0.read_rate', 50.0)
        ->where('announcements.0.unique_click_rate', 20.0)
        ->missing('announcements.0.recipients'));
});
```

- [ ] **Step 2: Run statistics tests and verify missing controller/data failures**

Run: `php artisan test tests/Feature/Partner/PartnerStatisticsTest.php`

Expected: FAIL because statistics routes do not exist.

- [ ] **Step 3: Implement typed aggregate presentation and authorized queries**

Calculate rates as `round(numerator / delivered_count * 100, 1)` and return `0.0` for zero deliveries. Partner query always scopes through `ownedBy($request->user())`; admin query may include operational `withCount` values but never eager-load users or serialize deliveries.

- [ ] **Step 4: Build the shared responsive statistics table and two pages**

Use semantic `<table>` with horizontal scrolling at 320 px, localized caption and column headers, status badge text, formatted percentages, and admin-only pending/failed/skipped columns and Retry button. Do not add charts because the requested comparisons remain clearer and more accessible in a table.

- [ ] **Step 5: Run statistics/browser tests and frontend checks**

Run: `php artisan test tests/Feature/Partner/PartnerStatisticsTest.php tests/Browser/AdminTest.php --filter='partner statistics' && bun run lint:check && bun run types:check`

Expected: PASS.

- [ ] **Step 6: Commit aggregate dashboards**

```bash
git add app/Data/PartnerAnnouncementStatisticsData.php app/Http/Controllers/Partner/StatisticsController.php app/Http/Controllers/Admin/PartnerStatisticsController.php resources/js/pages/Partner/Statistics/Index.vue resources/js/pages/Admin/Partners/Statistics.vue resources/js/components/partners/AnnouncementStatisticsTable.vue routes/web.php lang/fr/partners.php lang/en/partners.php lang/fr/administration.php lang/en/administration.php tests/Feature/Partner/PartnerStatisticsTest.php tests/Browser/AdminTest.php
git commit -m "feat(partners): expose aggregate announcement statistics"
```

---

### Task 10: Personal-data export, account deletion, and two-year retention

**Files:**
- Create: `app/Actions/DeactivateDeletedPartner.php`
- Create: `app/Console/Commands/PurgeExpiredPartnerRecords.php`
- Modify: `app/Actions/BuildUserDataExport.php`
- Modify: `app/Actions/DeleteMember.php`
- Modify: `app/Jobs/PurgeDeletedUser.php`
- Modify: `routes/console.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Settings/DirectUserDataExportTest.php`
- Test: `tests/Feature/Partner/PartnerDeletionTest.php`
- Test: `tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php`

**Interfaces:**
- Produces: export keys `notification_preferences`, `role_history`, `partner_profile`, `partner_profile_revisions`, `partner_announcements`, `received_partner_announcements`; command `partners:purge-expired-records`; `DeactivateDeletedPartner::handle(User $user): void`.

- [ ] **Step 1: Write failing export tests for ordinary recipients and partner owners**

```php
it('exports only the members own partner interactions and partner-owned aggregates', function () {
    $user = User::factory()->partner()->withPartnerAnnouncements()->create();
    $ownAnnouncement = PartnerAnnouncement::factory()->forPartner($user)->sent()->create();
    $received = PartnerAnnouncementDelivery::factory()->forUser($user)->delivered()->create(['read_at' => now(), 'dismissed_at' => now(), 'click_count' => 2]);

    $payload = app(BuildUserDataExport::class)->handle($user);

    expect($payload['notification_preferences']['partner_announcements'])->toBeTrue()
        ->and($payload['received_partner_announcements'][0])->toHaveKeys(['announcement_id', 'delivered_at', 'read_at', 'dismissed_at', 'first_clicked_at', 'click_count'])
        ->and($payload['partner_announcements'][0]['id'])->toBe($ownAnnouncement->id)
        ->and(json_encode($payload))->not->toContain($received->announcement->partnerProfile->user->email);
});
```

- [ ] **Step 2: Run export/deletion/retention tests and verify the missing keys/command failures**

Run: `php artisan test tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Partner/PartnerDeletionTest.php tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php`

Expected: FAIL because export sections and purge command do not exist.

- [ ] **Step 3: Extend the direct export with deterministic, privacy-bounded sections**

Order every collection by primary key, emit ISO-8601 dates, serialize enums as values, omit storage paths, click tokens, notification IDs, recipient IDs, actor e-mail, delivery errors, and other members' data. Include target-side role audit with actor represented only as `system` or `administrator`.

- [ ] **Step 4: Integrate immediate deactivation and delayed deletion cleanup**

Call `DeactivateDeletedPartner` from both the self-service deletion request and `DeleteMember`: set profile unpublished, cancel pre-send announcements, stop sending announcements from creating further deliveries, and mark remaining pending rows skipped. In `PurgeDeletedUser` and the immediate admin deletion path, delete identifiable preferences and deliveries before deleting the user while preserving metric totals. Delete private draft images no longer referenced by retained audit data.

- [ ] **Step 5: Implement and schedule retention purge**

`partners:purge-expired-records` deletes metrics and audit/moderation records whose explicit `expires_at` is at or before `now()`, in chunks of 500, without touching active announcements. Schedule it daily at 03:30 Europe/Paris with `withoutOverlapping()` and `onOneServer()`.

- [ ] **Step 6: Run export, deletion, command, and scheduler tests**

Run: `php artisan test tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Partner/PartnerDeletionTest.php tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php tests/Feature/Console/DispatchDueAccountPurgesTest.php`

Expected: PASS, including repeated command execution.

- [ ] **Step 7: Commit privacy lifecycle changes**

```bash
git add app/Actions/DeactivateDeletedPartner.php app/Actions/BuildUserDataExport.php app/Actions/DeleteMember.php app/Console/Commands/PurgeExpiredPartnerRecords.php app/Jobs/PurgeDeletedUser.php app/Models/User.php routes/console.php tests/Feature/Settings/DirectUserDataExportTest.php tests/Feature/Partner/PartnerDeletionTest.php tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php
git commit -m "feat(partners): export and expire partner data"
```

---

### Task 11: End-to-end browser coverage, documentation, and complete verification

**Files:**
- Modify: `tests/Browser/AdminTest.php`
- Create: `tests/Browser/PartnerTest.php`
- Modify: `tests/Browser/WelcomeAndRegistrationTest.php`
- Modify: `docs/PRD.md`
- Modify: `docs/data-model.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/design-system.md`
- Modify: `docs/documentation-inventory.md`

**Interfaces:**
- Consumes: every user-visible route and state from Tasks 1–10.
- Produces: end-to-end acceptance evidence and documentation matching the delivered behavior.

- [ ] **Step 1: Add failing browser scenarios for the complete partner journey**

```php
test('partner and admin complete the moderated announcement journey', function () {
    $partner = User::factory()->partner()->create();
    $admin = User::factory()->admin()->create();

    visit('/login')->loginAs($partner)
        ->navigate('/partner/profile')
        ->assertSee(__('partners.profile.title'))
        ->type('name_fr', 'Partenaire français')
        ->type('name_en', 'English partner')
        ->type('description_fr', 'Description française')
        ->type('description_en', 'English description')
        ->attach('image', base_path('tests/Fixtures/partner-safe.jpg'))
        ->press(__('partners.profile.save'))
        ->press(__('partners.profile.submit'));

    visit('/login')->loginAs($admin)
        ->navigate('/admin/partners/profiles')
        ->press(__('administration.partners.approve'))
        ->assertSee(__('administration.partners.approved'));
});
```

Extend this into focused scenarios rather than one giant test: admin role assignment/removal, partner profile approval, announcement approval/send/stats, member opt-in/read/dismiss/click, and public FR/EN cards.

- [ ] **Step 2: Run each new browser scenario before final UI fixes**

Run: `php artisan test tests/Browser/PartnerTest.php tests/Browser/AdminTest.php tests/Browser/WelcomeAndRegistrationTest.php --filter='partner'`

Expected: FAIL on any remaining inaccessible label, incorrect redirect, missing dark-mode token, or responsive overflow.

- [ ] **Step 3: Make only the focused accessibility/responsive fixes exposed by the browser tests**

At 320×700 and desktop 1440×900, verify visible focus, 44 px primary targets, no document-level horizontal overflow, readable card text, dialog focus return, translated status/error text, and light/dark semantic tokens. Avoid layout refactors outside partner surfaces.

- [ ] **Step 4: Update product, model, architecture, privacy, design, and inventory documentation**

Document the delivered role boundary, consent default, state machines, storage relations, controlled images, idempotent queue flow, aggregate-only statistics, two-year retention, export fields, landing pattern, and mark the issue 199 capability implemented in the PRD matrix. Do not describe any unimplemented e-mail, push, targeting, or billing behavior.

- [ ] **Step 5: Regenerate routes and run all relevant static checks**

Run: `php artisan wayfinder:generate --with-form && composer lint:check && composer analyse && bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: every command exits 0 with no warnings introduced by this branch.

- [ ] **Step 6: Run the complete backend, frontend, and browser suites**

Run: `composer test`

Expected: PASS.

- [ ] **Step 7: Verify the production image**

Run: `docker build --target runtime --tag dlp-friends:issue-199 .`

Expected: build completes successfully.

- [ ] **Step 8: Run final repository checks and inspect the branch diff**

Run: `git diff --check && git status --short && git diff main...HEAD --stat`

Expected: no whitespace errors, only issue-199 files plus the approved spec/plan, and user-owned `.superpowers/` and `artifacts/` remain untouched.

- [ ] **Step 9: Commit documentation and final acceptance tests**

```bash
git add tests/Browser/PartnerTest.php tests/Browser/AdminTest.php tests/Browser/WelcomeAndRegistrationTest.php docs/PRD.md docs/data-model.md docs/technical-architecture.md docs/security-privacy.md docs/design-system.md docs/documentation-inventory.md
git commit -m "test(partners): cover partner workflows end to end"
```

---

## Final acceptance checklist

- [ ] Admin assigns/removes `user` and `partner` from the member list with confirmation and immutable audit; `admin` remains console-managed.
- [ ] Partner-only accounts cannot use social or admin routes; role removal immediately blocks partner routes.
- [ ] Published bilingual profiles survive draft edits and only approved revisions reach the two SSR landings.
- [ ] At most six published cards render in manual order at 320 px, desktop, light, dark, keyboard, FR, and EN.
- [ ] Unsafe links, invalid images/text, invalid state transitions, premature sends, and concurrent duplicate sends are rejected server-side.
- [ ] Delivery retries never resend to delivered/skipped members; newly ineligible or opted-out members are skipped.
- [ ] Reads, dismissals, total clicks, and unique clicks are atomic and reflected only as aggregates.
- [ ] Partner/admin statistics never expose recipient identity or individual history.
- [ ] Exports include consent, own interactions, role history, partner-owned content and aggregates, and no other member's personal data.
- [ ] Account deletion stops publication/delivery immediately and removes identifiable rows while anonymous aggregates expire after two years.
- [ ] French/English translations, documentation, static analysis, builds, Pest suites, browser suites, and production image all pass.
