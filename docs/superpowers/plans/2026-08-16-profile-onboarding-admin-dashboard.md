# Profile Onboarding and Admin Dashboard Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move public identity from `users.username` to a non-unique `profiles.display_name`, require profile onboarding after e-mail verification, add `user`/`admin` roles, restrict and rebuild the dashboard for administrators, and apply the first complete visual-system slice.

**Architecture:** Keep `users` focused on authentication and eligibility, add one-to-one `profiles`, and add normalized `roles`/`user_roles`. Route every verified account through a deterministic landing controller, enforce profile completion and admin access with separate middleware, and expose purpose-built Inertia props to profile and dashboard pages. Reuse semantic Tailwind/shadcn-vue tokens across authentication, onboarding, profile, settings, navigation, and dashboard.

**Tech Stack:** PHP 8.3, Laravel 13, Fortify, Inertia 3, Vue 3, TypeScript 6, Tailwind CSS 4, shadcn-vue/Reka UI, Pest 5, Vitest 4, Wayfinder.

## Global Constraints

- `profiles.display_name` is required for completion, non-unique, whitespace-normalized, 1–80 characters, and has no character-set restriction.
- `profiles.bio` is optional and limited to 500 characters.
- `visit_frequency` accepts only `rarely`, `sometimes`, `often`, and `very_often` and is required for completion.
- `visibility` accepts only `visible` and `hidden` and defaults to `visible`.
- Profile onboarding occurs after e-mail verification and before ordinary product pages.
- A complete `user` lands on their own profile; a complete `admin` lands on the dashboard.
- Dashboard authorization is enforced server-side and returns HTTP 403 to non-admins.
- New and migrated accounts receive `user`; browser-based privilege elevation is out of scope.
- Existing usernames are copied into incomplete profiles before `users.username` is dropped.
- No profile photos, avatars, passions, user-management UI, discovery, matching, or messaging are included.
- The UI is mobile-first, light by default with a complete dark theme, violet-led with rose support and restrained gold accents.
- No Disney marks, characters, copied trade dress, or romantic vocabulary.
- Use starter-kit and shadcn-vue primitives before custom interactive primitives.
- Every production behavior follows red-green-refactor: write the focused failing test, run it and confirm the expected failure, then implement the minimum behavior.

---

## File Structure

### Domain and persistence

- `app/Enums/ProfileVisibility.php`: backed visibility values and French labels.
- `app/Enums/RoleName.php`: backed `user` and `admin` values.
- `app/Enums/VisitFrequency.php`: backed visit-frequency values and French labels.
- `app/Models/Profile.php`: member profile casts, relation, and completion predicate.
- `app/Models/Role.php`: stable role names and users relation.
- `app/Models/User.php`: account-only metadata plus profile/roles relations and `hasRole`.
- `database/migrations/2026_08_16_010000_create_profiles_and_roles.php`: schema, data backfill, role seed, and nullable transition for the legacy username.
- `database/migrations/2026_08_16_020000_drop_username_from_users.php`: final username removal after all consumers have migrated.
- `database/factories/ProfileFactory.php`: incomplete/complete profile states.
- `database/factories/RoleFactory.php`: role test data.

### Account and routing

- `app/Concerns/AccountValidationRules.php`: e-mail-only account validation shared by Fortify and settings.
- `app/Actions/Fortify/CreateNewUser.php`: transactionally create an account and assign `user`.
- `app/Http/Controllers/LandingController.php`: deterministic post-authentication redirect.
- `app/Http/Middleware/EnsureProfileIsComplete.php`: product-page onboarding gate.
- `app/Http/Middleware/EnsureUserHasRole.php`: parameterized role gate.
- `app/Http/Controllers/MemberProfileController.php`: onboarding, profile display, and editing.
- `app/Http/Requests/MemberProfileRequest.php`: normalized profile validation.
- `app/Policies/ProfilePolicy.php`: owner-only update policy.
- `app/Http/Controllers/Settings/AccountController.php`: account e-mail settings only.
- `app/Http/Requests/Settings/AccountUpdateRequest.php`: e-mail update validation only.
- `app/Console/Commands/AssignUserRole.php`: controlled CLI role assignment.
- `app/Http/Controllers/Admin/DashboardController.php`: safe administrative aggregates.

### Frontend

- `resources/js/components/profile/ProfileForm.vue`: shared accessible create/edit form.
- `resources/js/pages/profile/Create.vue`: required onboarding screen.
- `resources/js/pages/profile/Show.vue`: member destination and profile summary.
- `resources/js/pages/profile/Edit.vue`: member profile editing.
- `resources/js/pages/settings/Account.vue`: e-mail-only account settings.
- `resources/js/pages/Dashboard.vue`: real admin overview.
- `resources/js/types/auth.ts`: account, profile, enum, and role types.
- `resources/js/components/UserInfo.vue`: display name with e-mail fallback.
- `resources/js/components/AppSidebar.vue`: role-aware navigation.
- `resources/css/app.css`: semantic light/dark violet, rose, and gold tokens.

---

### Task 1: Add profile and role persistence with a safe username backfill

**Files:**
- Create: `app/Enums/ProfileVisibility.php`
- Create: `app/Enums/RoleName.php`
- Create: `app/Enums/VisitFrequency.php`
- Create: `app/Models/Profile.php`
- Create: `app/Models/Role.php`
- Modify: `app/Models/User.php`
- Create: `database/factories/ProfileFactory.php`
- Create: `database/factories/RoleFactory.php`
- Modify: `database/factories/UserFactory.php`
- Create: `database/migrations/2026_08_16_010000_create_profiles_and_roles.php`
- Create: `tests/Unit/Models/ProfileTest.php`
- Modify: `tests/Unit/Models/UserTest.php`
- Create: `tests/Feature/Database/ProfileRoleMigrationTest.php`

**Interfaces:**
- Produces: `Profile::isComplete(): bool`, `User::profile(): HasOne`, `User::roles(): BelongsToMany`, `User::hasRole(string|RoleName): bool`, `RoleName`, `VisitFrequency`, and `ProfileVisibility`.
- Produces: database guarantees of one profile per user and one assignment per user/role pair.
- Produces: a nullable legacy `users.username` transition column so every commit remains deployable until Task 4 removes the last consumers.
- Consumes: existing `users.username` only during the forward migration.

- [ ] **Step 1: Write failing model tests**

Add tests that express the public API before the models exist:

```php
public function test_profile_completion_requires_the_explicit_timestamp(): void
{
    $profile = Profile::factory()->make(['onboarding_completed_at' => null]);

    $this->assertFalse($profile->isComplete());
    $profile->onboarding_completed_at = now();
    $this->assertTrue($profile->isComplete());
}

public function test_user_can_check_a_known_role(): void
{
    $user = User::factory()->create();
    $admin = Role::query()->where('name', RoleName::Admin)->firstOrFail();
    $user->roles()->attach($admin);

    $this->assertTrue($user->hasRole(RoleName::Admin));
    $this->assertFalse($user->hasRole(RoleName::User));
}
```

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```bash
php artisan test tests/Unit/Models/ProfileTest.php tests/Unit/Models/UserTest.php
```

Expected: failure because `Profile`, `Role`, the enums, relations, and `hasRole` do not exist.

- [ ] **Step 3: Implement enums, models, casts, relations, and factories**

Use string-backed enums with an explicit label method:

```php
enum VisitFrequency: string
{
    case Rarely = 'rarely';
    case Sometimes = 'sometimes';
    case Often = 'often';
    case VeryOften = 'very_often';

    public function label(): string
    {
        return match ($this) {
            self::Rarely => 'Rarement',
            self::Sometimes => 'De temps en temps',
            self::Often => 'Souvent',
            self::VeryOften => 'Très souvent',
        };
    }
}
```

Implement the profile completion API exactly as:

```php
public function isComplete(): bool
{
    return $this->onboarding_completed_at !== null;
}
```

Implement role checks without issuing a second query when roles are already loaded:

```php
public function hasRole(string|RoleName $role): bool
{
    $value = $role instanceof RoleName ? $role->value : $role;

    return $this->roles->contains(
        fn (Role $assignedRole): bool => $assignedRole->name->value === $value,
    );
}
```

Add factory states `UserFactory::withProfile()` and `UserFactory::admin()` that create related records through `afterCreating`, and a `ProfileFactory::complete()` state that fills `visit_frequency` and `onboarding_completed_at`.

- [ ] **Step 4: Add the forward/backward migration and its failing preservation test**

The migration must create this shape before copying data:

```php
Schema::create('profiles', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    $table->string('display_name', 80);
    $table->text('bio')->nullable();
    $table->string('visit_frequency')->nullable();
    $table->string('visibility')->default(ProfileVisibility::Visible->value);
    $table->timestamp('onboarding_completed_at')->nullable();
    $table->timestamps();
});

Schema::create('roles', function (Blueprint $table) {
    $table->id();
    $table->string('name', 32)->unique();
    $table->timestamps();
});

Schema::create('user_roles', function (Blueprint $table) {
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('role_id')->constrained()->cascadeOnDelete();
    $table->primary(['user_id', 'role_id']);
});
```

In `ProfileRoleMigrationTest`, roll back only this migration, insert two legacy users with distinct usernames, re-run migrations, and assert:

```php
$this->assertDatabaseHas('profiles', [
    'user_id' => $legacyUserId,
    'display_name' => 'Magic Friend',
    'visit_frequency' => null,
    'onboarding_completed_at' => null,
]);
$this->assertTrue(Schema::hasColumn('users', 'username'));
$this->assertDatabaseCount('user_roles', 2);
```

The forward migration must insert `user` and `admin`, copy usernames in chunks, attach the `user` role, verify copied counts, and then make `users.username` nullable so new registration can stop collecting it without breaking the still-existing account settings during the staged rollout. Its rollback fills any null username from `profiles.display_name`, appending a deterministic `-<user_id>` suffix when necessary, restores the non-null unique constraint, then drops `user_roles`, `roles`, and `profiles` in that order. The column is removed only in Task 4 after every consumer has migrated.

- [ ] **Step 5: Run persistence tests and verify GREEN**

Run:

```bash
php artisan test tests/Unit/Models/ProfileTest.php tests/Unit/Models/UserTest.php tests/Feature/Database/ProfileRoleMigrationTest.php
```

Expected: all tests pass and the migration preservation assertions succeed on SQLite.

- [ ] **Step 6: Commit the persistence slice**

```bash
git add app/Enums app/Models database/factories database/migrations tests/Unit/Models tests/Feature/Database
git commit -m "feat: add member profiles and roles"
```

---

### Task 2: Remove username from registration and assign the default role

**Files:**
- Create: `app/Concerns/AccountValidationRules.php`
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `config/fortify.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Create: `resources/js/pages/auth/Register.spec.ts`

**Interfaces:**
- Consumes: `RoleName::User` and `User::roles()` from Task 1.
- Produces: registration payload `{ email, birth_date, password, password_confirmation }` and a user with the `user` role.
- Produces: Fortify home `/app` for login, registration, and verification completion.

- [ ] **Step 1: Rewrite registration tests to describe the new account contract**

Replace username assertions with:

```php
public function test_new_users_register_without_a_public_name_and_receive_the_user_role(): void
{
    Carbon::setTestNow('2026-08-16');

    $response = $this->post(route('register.store'), [
        'email' => 'test@example.com',
        'birth_date' => '2008-08-16',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'test@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    $this->assertNull($user->username);
    $this->assertTrue($user->fresh('roles')->hasRole(RoleName::User));
    $this->assertNull($user->profile);
    $response->assertRedirect('/app');
}
```

Keep the exact majority boundary and e-mail/password regression tests. Delete the username-required, uniqueness, punctuation, minimum, and maximum tests.

- [ ] **Step 2: Add a failing Vue registration test**

Mount `Register.vue` with shallow stubs and assert that no input named `username` exists while `email`, `birth_date`, `password`, and `password_confirmation` do exist:

```ts
expect(wrapper.find('input[name="username"]').exists()).toBe(false);
expect(wrapper.find('input[name="email"]').exists()).toBe(true);
expect(wrapper.find('input[name="birth_date"]').exists()).toBe(true);
```

- [ ] **Step 3: Run registration tests and verify RED**

Run:

```bash
php artisan test tests/Feature/Auth/RegistrationTest.php
npm run test:unit -- resources/js/pages/auth/Register.spec.ts
```

Expected: backend fails because username is still validated/stored and no role is attached; frontend fails because the username field still renders.

- [ ] **Step 4: Implement account-only registration**

Move only `emailRules()` into `AccountValidationRules`. Make `CreateNewUser::create()` validate e-mail, birth date, and password, then create the account and attach the default role in one database transaction:

```php
return DB::transaction(function () use ($input): User {
    $user = User::query()->create([
        'email' => $input['email'],
        'birth_date' => $input['birth_date'],
        'password' => $input['password'],
    ]);

    $role = Role::query()->where('name', RoleName::User)->firstOrFail();
    $user->roles()->attach($role);

    return $user;
});
```

Remove the username field from `Register.vue`, renumber tab order, translate the remaining starter copy to French, and set `config/fortify.php` home to `/app`. Keep `ProfileValidationRules` temporarily because the old account-settings endpoint still consumes it; Task 4 deletes both together.

- [ ] **Step 5: Run registration tests and verify GREEN**

Run:

```bash
php artisan test tests/Feature/Auth/RegistrationTest.php
npm run test:unit -- resources/js/pages/auth/Register.spec.ts
```

Expected: both commands pass.

- [ ] **Step 6: Commit the registration slice**

```bash
git add app/Actions/Fortify app/Concerns config/fortify.php resources/js/pages/auth/Register.vue resources/js/pages/auth/Register.spec.ts tests/Feature/Auth/RegistrationTest.php
git commit -m "feat: separate account registration from profiles"
```

---

### Task 3: Add deterministic landing and profile-onboarding backend

**Files:**
- Create: `app/Http/Controllers/LandingController.php`
- Create: `app/Http/Controllers/MemberProfileController.php`
- Create: `app/Http/Requests/MemberProfileRequest.php`
- Create: `app/Http/Middleware/EnsureProfileIsComplete.php`
- Create: `app/Policies/ProfilePolicy.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/LandingTest.php`
- Create: `tests/Feature/MemberProfileTest.php`

**Interfaces:**
- Consumes: `Profile::isComplete()`, `User::hasRole()`, and the profile enums from Task 1.
- Produces named routes: `app`, `member-profile.create`, `member-profile.store`, `member-profile.show`, `member-profile.edit`, and `member-profile.update`.
- Produces middleware alias `profile.complete`.

- [ ] **Step 1: Write failing landing-route tests**

Cover the ordered redirects with complete database state:

```php
public function test_verified_member_without_a_complete_profile_lands_on_onboarding(): void
{
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('app'))
        ->assertRedirect(route('member-profile.create'));
}

public function test_complete_user_lands_on_their_profile(): void
{
    $user = User::factory()->withProfile()->create();

    $this->actingAs($user)
        ->get(route('app'))
        ->assertRedirect(route('member-profile.show'));
}

public function test_complete_admin_lands_on_the_dashboard(): void
{
    $admin = User::factory()->withProfile()->admin()->create();

    $this->actingAs($admin)
        ->get(route('app'))
        ->assertRedirect(route('dashboard'));
}
```

Also assert guests go to login, unverified users go to the verification notice, and inactive/underage users receive 403.

- [ ] **Step 2: Write failing onboarding feature tests**

Test create-page props, normalization, enum validation, owner-only mutation, one-profile-per-user behavior, and completion:

```php
$response = $this->actingAs($user)->post(route('member-profile.store'), [
    'display_name' => '  Magic   Friend  ',
    'bio' => 'Toujours partant pour une attraction.',
    'visit_frequency' => VisitFrequency::Often->value,
    'visibility' => ProfileVisibility::Visible->value,
]);

$response->assertRedirect(route('app'));
$this->assertDatabaseHas('profiles', [
    'user_id' => $user->id,
    'display_name' => 'Magic Friend',
    'visit_frequency' => 'often',
    'visibility' => 'visible',
]);
$this->assertNotNull($user->fresh()->profile?->onboarding_completed_at);
```

Add separate tests for an 81-character display name, a 501-character bio, unknown enum values, a second POST updating rather than duplicating, and incomplete members being redirected away from protected profile-display routes.

- [ ] **Step 3: Run landing/profile tests and verify RED**

Run:

```bash
php artisan test tests/Feature/LandingTest.php tests/Feature/MemberProfileTest.php
```

Expected: failure because the routes, controller, request, policy, and middleware do not exist.

- [ ] **Step 4: Implement the landing controller and route groups**

Use a single invokable controller:

```php
public function __invoke(Request $request): RedirectResponse
{
    $user = $request->user()->loadMissing(['profile', 'roles']);

    if (! $user->profile?->isComplete()) {
        return to_route('member-profile.create');
    }

    return $user->hasRole(RoleName::Admin)
        ? to_route('dashboard')
        : to_route('member-profile.show');
}
```

Register `profile.complete` in `bootstrap/app.php`. Define `/app` and onboarding create/store inside `auth`, `verified`, and `social`; define member profile show/edit/update inside an additional `profile.complete` group. Leave the dashboard route name present for the landing contract; Task 5 will replace its placeholder implementation and add the admin gate.

- [ ] **Step 5: Implement request normalization, controller, and Policy**

Normalize only display name:

```php
protected function prepareForValidation(): void
{
    $displayName = $this->input('display_name');

    $this->merge([
        'display_name' => is_string($displayName)
            ? preg_replace('/\s+/u', ' ', trim($displayName))
            : $displayName,
    ]);
}
```

Use `Rule::enum(VisitFrequency::class)` and `Rule::enum(ProfileVisibility::class)`. In store/update, derive `user_id` exclusively from `$request->user()`, call `updateOrCreate`, set `onboarding_completed_at` only when null, authorize updates through `ProfilePolicy`, and redirect via `route('app')` after onboarding completion.

- [ ] **Step 6: Run landing/profile tests and verify GREEN**

Run:

```bash
php artisan test tests/Feature/LandingTest.php tests/Feature/MemberProfileTest.php
```

Expected: all redirect, validation, persistence, and authorization cases pass.

- [ ] **Step 7: Commit the routing/onboarding backend slice**

```bash
git add app/Http/Controllers/LandingController.php app/Http/Controllers/MemberProfileController.php app/Http/Requests/MemberProfileRequest.php app/Http/Middleware/EnsureProfileIsComplete.php app/Policies/ProfilePolicy.php bootstrap/app.php routes/web.php tests/Feature/LandingTest.php tests/Feature/MemberProfileTest.php
git commit -m "feat: require verified profile onboarding"
```

---

### Task 4: Build the member profile experience and account-only settings

**Files:**
- Create: `resources/js/components/profile/ProfileForm.vue`
- Create: `resources/js/components/profile/ProfileForm.spec.ts`
- Create: `resources/js/pages/profile/Create.vue`
- Create: `resources/js/pages/profile/Show.vue`
- Create: `resources/js/pages/profile/Edit.vue`
- Create: `resources/js/pages/profile/Show.spec.ts`
- Create: `app/Http/Controllers/Settings/AccountController.php`
- Create: `app/Http/Requests/Settings/AccountUpdateRequest.php`
- Create: `resources/js/pages/settings/Account.vue`
- Delete: `app/Concerns/ProfileValidationRules.php`
- Delete: `app/Http/Controllers/Settings/ProfileController.php`
- Delete: `app/Http/Requests/Settings/ProfileUpdateRequest.php`
- Delete: `resources/js/pages/settings/Profile.vue`
- Modify: `routes/settings.php`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/types/auth.ts`
- Modify: `resources/js/components/UserInfo.vue`
- Modify: `resources/js/components/UserMenuContent.vue`
- Modify: `resources/js/components/AppHeader.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Delete: `tests/Feature/Settings/ProfileUpdateTest.php`
- Create: `tests/Feature/Settings/AccountUpdateTest.php`
- Create: `database/migrations/2026_08_16_020000_drop_username_from_users.php`
- Create: `tests/Feature/Database/UsernameRemovalMigrationTest.php`
- Create: `resources/js/components/UserInfo.spec.ts`

**Interfaces:**
- Consumes: member-profile routes and props from Task 3.
- Produces: `Auth.user.profile: Profile | null`, `Auth.user.roles: RoleName[]`, and role-aware identity presentation.
- Produces: account settings routes `account.edit` and `account.update`; member profile routes retain all `member-profile.*` names.

- [ ] **Step 1: Write failing frontend form and identity tests**

For `ProfileForm`, assert:

```ts
expect(wrapper.find('input[name="display_name"]').attributes('maxlength')).toBe('80');
expect(wrapper.find('textarea[name="bio"]').attributes('maxlength')).toBe('500');
expect(wrapper.find('[name="visit_frequency"]').exists()).toBe(true);
expect(wrapper.text()).toContain('Visible dans les suggestions');
```

For `UserInfo`, cover both branches:

```ts
expect(renderUser({ profile: { display_name: 'Magic Friend' } }).text()).toContain('Magic Friend');
expect(renderUser({ profile: null, email: 'test@example.com' }).text()).toContain('test@example.com');
```

For `Show.vue`, assert display name, calculated age, bio, translated frequency, visibility badge, and edit link render from props.

- [ ] **Step 2: Rewrite account-settings feature tests and verify RED**

Create the replacement test with account terminology and delete the old profile-settings test. Assert account updates accept only e-mail and re-trigger verification when it changes:

```php
$response = $this->actingAs($user)->patch(route('account.update'), [
    'email' => 'updated@example.com',
]);

$response->assertRedirect(route('account.edit'));
$this->assertSame('updated@example.com', $user->refresh()->email);
$this->assertNull($user->email_verified_at);
```

Assert that sending `display_name` to the account endpoint does not alter the profile.

Run:

```bash
php artisan test tests/Feature/Settings/AccountUpdateTest.php
npm run test:unit -- resources/js/components/profile/ProfileForm.spec.ts resources/js/pages/profile/Show.spec.ts resources/js/components/UserInfo.spec.ts
```

Expected: failures because the new routes/components/types do not exist and existing identity still reads `username`.

- [ ] **Step 3: Implement explicit shared auth props and TypeScript types**

Share only the required user shape:

```php
'auth' => [
    'user' => fn () => $request->user()?->loadMissing(['profile', 'roles']),
],
```

Keep sensitive fields hidden on the model and type the frontend contract explicitly:

```ts
export type RoleName = 'user' | 'admin';
export type VisitFrequency = 'rarely' | 'sometimes' | 'often' | 'very_often';
export type ProfileVisibility = 'visible' | 'hidden';

export type Profile = {
    display_name: string;
    bio: string | null;
    visit_frequency: VisitFrequency | null;
    visibility: ProfileVisibility;
    onboarding_completed_at: string | null;
};

export type User = {
    id: number;
    email: string;
    email_verified_at: string | null;
    profile: Profile | null;
    roles: Array<{ name: RoleName }>;
    two_factor_enabled?: boolean;
};
```

- [ ] **Step 4: Build profile pages and the reusable form**

Use one component with `mode: 'create' | 'edit'`, profile defaults, enum options passed from the backend, and a Wayfinder form action. The display name input uses `autocomplete="nickname"`; the bio uses a native accessible textarea styled with the existing input tokens; visit frequency uses the existing shadcn select; visibility uses the existing checkbox with explanatory text. Render `InputError` adjacent to every field and disable submit while processing.

`Create.vue` uses a focused onboarding card without the normal product navigation. `Show.vue` is the standard member landing surface. `Edit.vue` reuses `ProfileForm` within `AppLayout`.

- [ ] **Step 5: Rename account settings and remove username assumptions**

Create `AccountController`/`AccountUpdateRequest` from the existing settings behavior, retaining e-mail verification reset and account deletion, but validating only e-mail. Move the page to `settings/Account.vue`, update settings navigation copy, route names, generated Wayfinder imports, breadcrumbs, and tests. Delete the old misleading settings profile files.

Move account, security, and appearance settings behind `auth`, `verified`, `social`, and `profile.complete`; retain `RequirePassword` and throttling on the existing sensitive endpoints. When an e-mail change clears verification, redirect to the verification notice rather than back into the now-protected account page.

Add the final migration that first verifies every non-null legacy username already has a matching profile row, then drops `users.username`. Its rollback restores a nullable username, fills deterministic unique values from `profiles.display_name` (or `member-<user_id>` when no profile exists), and finally restores the unique/non-null constraint. In `UsernameRemovalMigrationTest`, roll back this migration, create accounts covering duplicate display names and a missing profile, migrate forward, assert the column is absent, roll back once, and assert every restored username is non-null and unique.

Update identity components to compute:

```ts
const identityLabel = computed(
    () => props.user.profile?.display_name || props.user.email,
);
```

Use that label for avatar alt text, initials, menus, headers, and logo destinations. A user without a complete profile must never trigger a null-property error.

- [ ] **Step 6: Run backend/frontend tests and verify GREEN**

Run:

```bash
php artisan test tests/Feature/MemberProfileTest.php tests/Feature/Settings/AccountUpdateTest.php tests/Feature/Database/UsernameRemovalMigrationTest.php
npm run test:unit -- resources/js/components/profile/ProfileForm.spec.ts resources/js/pages/profile/Show.spec.ts resources/js/components/UserInfo.spec.ts
npm run types:check
```

Expected: profile UI, account settings, identity fallbacks, and types pass.

- [ ] **Step 7: Commit the member experience slice**

```bash
git add app/Concerns/ProfileValidationRules.php app/Http/Controllers/Settings app/Http/Requests/Settings app/Http/Middleware/HandleInertiaRequests.php database/migrations/2026_08_16_020000_drop_username_from_users.php routes/settings.php resources/js/components resources/js/layouts/settings resources/js/pages/profile resources/js/pages/settings resources/js/types tests/Feature/Database/UsernameRemovalMigrationTest.php tests/Feature/Settings
git commit -m "feat: add member profile experience"
```

---

### Task 5: Protect and rebuild the administrator dashboard

**Files:**
- Create: `app/Http/Middleware/EnsureUserHasRole.php`
- Create: `app/Console/Commands/AssignUserRole.php`
- Create: `app/Actions/AssignRole.php`
- Create: `app/Http/Controllers/Admin/DashboardController.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `resources/js/pages/Dashboard.vue`
- Create: `resources/js/pages/Dashboard.spec.ts`
- Modify: `resources/js/components/AppSidebar.vue`
- Create: `resources/js/components/AppSidebar.spec.ts`
- Modify: `tests/Feature/DashboardTest.php`
- Create: `tests/Feature/Console/AssignUserRoleTest.php`

**Interfaces:**
- Consumes: `User::hasRole`, `RoleName`, complete-profile middleware, and shared auth props.
- Produces: middleware alias `role`, `AssignRole::handle(User, RoleName): bool`, and command `user:assign-role {email} {role}`.
- Produces dashboard props `stats` and `recentRegistrations` with no raw User models.

- [ ] **Step 1: Replace dashboard tests with failing authorization and aggregate tests**

Keep guest, verification, active, adult, and profile-completion coverage. Replace ordinary-user success with:

```php
public function test_complete_user_cannot_visit_the_dashboard(): void
{
    $user = User::factory()->withProfile()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertForbidden();
}
```

Add an admin Inertia assertion:

```php
$this->actingAs($admin)
    ->get(route('dashboard'))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->where('stats.totalAccounts', 4)
        ->where('stats.completedProfiles', 2)
        ->has('recentRegistrations')
        ->missing('recentRegistrations.0.birth_date')
        ->missing('recentRegistrations.0.password'));
```

- [ ] **Step 2: Write failing command tests**

Cover success, repeated assignment, unknown e-mail, and unknown role:

```php
$this->artisan('user:assign-role', [
    'email' => $user->email,
    'role' => RoleName::Admin->value,
])->assertSuccessful();

$this->assertTrue($user->fresh('roles')->hasRole(RoleName::Admin));
$this->assertDatabaseCount('user_roles', 2);
```

The count is two because the account retains its default `user` assignment and gains `admin`.

- [ ] **Step 3: Write failing dashboard/navigation Vue tests**

Assert the dashboard renders five meaningful stat cards, an empty recent-registration state, and safe member fields. Assert sidebar visibility by role:

```ts
expect(renderSidebar(['user']).text()).not.toContain('Administration');
expect(renderSidebar(['admin']).text()).toContain('Administration');
expect(renderSidebar(['admin']).text()).toContain('Dashboard');
```

- [ ] **Step 4: Run dashboard/command/UI tests and verify RED**

Run:

```bash
php artisan test tests/Feature/DashboardTest.php tests/Feature/Console/AssignUserRoleTest.php
npm run test:unit -- resources/js/pages/Dashboard.spec.ts resources/js/components/AppSidebar.spec.ts
```

Expected: failures because role middleware, command, aggregates, and role-aware dashboard UI do not exist.

- [ ] **Step 5: Implement role assignment and middleware**

`AssignRole::handle` resolves only an existing enum-backed role and uses `syncWithoutDetaching`; return `true` only when a new pivot row is attached. The command parses the enum with `RoleName::tryFrom`, resolves the user by e-mail, returns `Command::FAILURE` with a clear message for either unknown value, and reports an already-present assignment as successful/idempotent.

Register the parameterized alias and enforce it as:

```php
public function handle(Request $request, Closure $next, string $role): Response
{
    abort_unless(
        $request->user()?->loadMissing('roles')->hasRole($role),
        Response::HTTP_FORBIDDEN,
    );

    return $next($request);
}
```

- [ ] **Step 6: Implement safe dashboard queries and admin route**

Replace the Inertia placeholder route with `Admin\DashboardController`. Apply `auth`, `verified`, `social`, `profile.complete`, and `role:admin`. Return scalar counts for total, active, verified, completed profiles, and recent registrations. Map recent rows to this exact safe shape:

```php
[
    'email' => $user->email,
    'status' => $user->status->value,
    'profile_completed' => $user->profile?->isComplete() ?? false,
    'registered_at' => $user->created_at->toIso8601String(),
]
```

Limit recent registrations to 8 and order newest first.

- [ ] **Step 7: Build the dashboard and role-aware navigation**

Use semantic Card, Badge, and Skeleton primitives. Render responsive statistic cards with text labels and values, then a recent-registration list/table that remains usable on narrow screens. Use the gold accent only on the completed-profile highlight. In `AppSidebar`, always show `Mon profil` and show the `Administration` dashboard item only when the authenticated user has `admin`. Remove starter-kit repository/documentation footer links.

- [ ] **Step 8: Run dashboard/command/UI tests and verify GREEN**

Run:

```bash
php artisan test tests/Feature/DashboardTest.php tests/Feature/Console/AssignUserRoleTest.php tests/Feature/LandingTest.php
npm run test:unit -- resources/js/pages/Dashboard.spec.ts resources/js/components/AppSidebar.spec.ts
npm run types:check
```

Expected: all authorization, aggregate, command, navigation, and type tests pass.

- [ ] **Step 9: Commit the administration slice**

```bash
git add app/Actions/AssignRole.php app/Console/Commands/AssignUserRole.php app/Http/Controllers/Admin app/Http/Middleware/EnsureUserHasRole.php bootstrap/app.php routes/web.php resources/js/pages/Dashboard.vue resources/js/pages/Dashboard.spec.ts resources/js/components/AppSidebar.vue resources/js/components/AppSidebar.spec.ts tests/Feature/DashboardTest.php tests/Feature/Console
git commit -m "feat: add protected admin dashboard"
```

---

### Task 6: Apply the visual system across authentication, profile, settings, and shell

**Files:**
- Modify: `resources/css/app.css`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/js/app.ts`
- Modify: `resources/js/composables/useAppearance.ts`
- Create: `resources/js/composables/useAppearance.spec.ts`
- Modify: `resources/js/components/AppearanceTabs.vue`
- Modify: `resources/js/components/AppLogo.vue`
- Modify: `resources/js/components/AppLogoIcon.vue`
- Modify: `resources/js/layouts/auth/AuthSimpleLayout.vue`
- Modify: `resources/js/layouts/auth/AuthCardLayout.vue`
- Modify: `resources/js/layouts/auth/AuthSplitLayout.vue`
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/layouts/app/AppHeaderLayout.vue`
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue`
- Modify: `resources/js/pages/auth/Login.vue`
- Modify: `resources/js/pages/auth/Register.vue`
- Modify: `resources/js/pages/auth/VerifyEmail.vue`
- Modify: `resources/js/pages/auth/ForgotPassword.vue`
- Modify: `resources/js/pages/auth/ConfirmPassword.vue`
- Modify: `resources/js/pages/auth/ResetPassword.vue`
- Modify: `resources/js/pages/auth/TwoFactorChallenge.vue`
- Modify: `resources/js/pages/settings/Account.vue`
- Modify: `resources/js/pages/settings/Appearance.vue`
- Modify: `resources/js/pages/settings/Security.vue`
- Modify: `resources/js/pages/profile/Create.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `resources/js/pages/profile/Edit.vue`
- Modify: `resources/js/pages/Dashboard.vue`
- Create: `resources/js/layouts/auth/AuthCardLayout.spec.ts`

**Interfaces:**
- Consumes: all pages and role-aware shell from Tasks 2–5.
- Produces: semantic CSS variables used by all touched pages and persisted `light | dark | system` appearance behavior.

- [ ] **Step 1: Write failing appearance and auth-layout tests**

Test explicit and system appearance without testing implementation details:

```ts
localStorage.setItem('appearance', 'dark');
initializeTheme();
expect(document.documentElement.classList.contains('dark')).toBe(true);

localStorage.removeItem('appearance');
mockMatchMedia(true);
initializeTheme();
expect(document.documentElement.classList.contains('dark')).toBe(true);
```

Mount `AuthCardLayout` and assert it exposes a visible DLP Friends brand label, heading, description, theme control, and a landmark containing the form slot.

- [ ] **Step 2: Run visual-system unit tests and verify RED**

Run:

```bash
npm run test:unit -- resources/js/composables/useAppearance.spec.ts resources/js/layouts/auth/AuthCardLayout.spec.ts
```

Expected: layout test fails because the existing auth surface lacks the required brand/theme structure; appearance tests reveal any listener cleanup or default-theme mismatch that must be corrected.

- [ ] **Step 3: Replace neutral tokens with the approved semantic palette**

Define all existing shadcn semantic variables for both `:root` and `.dark`. Use violet for `--primary`, rose-tinted `--secondary`, warm neutral backgrounds/surfaces, and gold only for `--accent`/selected chart highlights. Keep destructive red distinct. Update sidebar tokens consistently and preserve WCAG-readable foreground pairings. Set the Inertia progress color from the same violet family.

Do not introduce page-level hard-coded violet/rose/gold values when an existing semantic token applies.

- [ ] **Step 4: Harmonize layouts and theme controls**

Give auth layouts a warm gradient/surface treatment, DLP Friends wordmark, compact appearance toggle, skip-friendly main landmark, responsive padding, and consistent Card spacing. Keep the logo code-native and abstract. Give application layouts the same surface/radius/spacing language. Translate `AppearanceTabs` labels to `Clair`, `Sombre`, and `Système`, add `type="button"`, `aria-pressed`, and visible focus.

Ensure `initializeTheme` respects stored appearance first, system preference second, and does not register duplicate media-query listeners during tests or hot reload.

- [ ] **Step 5: Harmonize touched pages and interaction states**

Translate remaining starter English copy on the listed auth, profile, settings, and dashboard pages. Standardize headings, descriptions, label spacing, disabled buttons, inline errors, success notices, empty states, and mobile widths. Keep all inputs labeled, focus-visible, keyboard reachable, and never communicate status by color alone.

- [ ] **Step 6: Run frontend tests, types, lint, and format checks**

Run:

```bash
npm run test:unit
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

Expected: all frontend tests and checks pass without warnings or type errors.

- [ ] **Step 7: Commit the visual-system slice**

```bash
git add resources/css/app.css resources/views/app.blade.php resources/js/app.ts resources/js/composables resources/js/components resources/js/layouts resources/js/pages
git commit -m "feat: apply warm accessible visual system"
```

---

### Task 7: Update documentation, seed data, generated routes, and complete verification

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `README.md`
- Modify: `docs/data-model.md`
- Modify: `docs/ux-design.md`
- Modify: `docs/mvp-v1.md`
- Modify: `docs/technical-architecture.md`
- Modify: `resources/js/routes/index.ts`
- Modify: `resources/js/routes/profile/index.ts`
- Create: `resources/js/routes/member-profile/index.ts`
- Create: `resources/js/routes/account/index.ts`
- Modify generated controller action files under: `resources/js/actions/App/Http/Controllers/`
- Modify: `tests/Feature/HealthCheckTest.php`

**Interfaces:**
- Consumes: all completed runtime behavior.
- Produces: reproducible seed account, synchronized Wayfinder artifacts, accurate documentation, and release evidence.

- [ ] **Step 1: Make seed data use the real domain APIs**

Seed `user` and `admin` roles idempotently, create `test@example.com` without username, create a completed profile with `display_name` `Test User`, attach `user`, then call `AssignRole` for `admin`. Do not duplicate pivot-attachment logic in the seeder.

- [ ] **Step 2: Regenerate and verify Wayfinder artifacts**

Run the repository-supported Wayfinder generation through the Vite plugin by building once:

```bash
npm run build
```

Then inspect generated routes and action imports with:

```bash
rg -n "member-profile|account|dashboard|MemberProfileController|AccountController|DashboardController" resources/js/routes resources/js/actions
```

Expected: all new named routes/actions exist and no generated import references deleted `Settings\ProfileController` files.

- [ ] **Step 3: Update documentation and add a smoke assertion**

Document:

- account/profile separation and non-unique display names in `docs/data-model.md`;
- verification → onboarding → profile/admin routing in `docs/ux-design.md` and `docs/mvp-v1.md`;
- roles, middleware, command, and dashboard boundaries in `docs/technical-architecture.md`;
- the completed issue #13 scope, early role foundation from #14, and bounded DA slice from #27 in the relevant domain documents;
- local migration, seed login, and `php artisan user:assign-role email admin` usage in `README.md`.

Extend `HealthCheckTest` only with a route-name smoke assertion for `app`, `member-profile.show`, and `dashboard`; authorization behavior remains in the focused feature tests.

- [ ] **Step 4: Run the complete backend verification**

Run:

```bash
php artisan migrate:fresh --seed
php artisan test
composer run lint:check
composer run types:check
```

Expected: migrations and seed succeed; the complete PHP suite, Pint, and PHPStan pass.

- [ ] **Step 5: Run the complete frontend verification**

Run:

```bash
npm run test:unit
npm run types:check
npm run lint:check
npm run format:check
npm run build
```

Expected: Vitest, Vue TypeScript, ESLint, Prettier, and the production build pass.

- [ ] **Step 6: Perform the manual browser acceptance pass**

Verify at mobile width and desktop width, in both light and dark themes:

1. register without a public name;
2. confirm the verification notice blocks onboarding before verification;
3. verify the e-mail and land on profile onboarding;
4. complete the profile and land on the profile page;
5. confirm a normal member receives 403 from `/dashboard` and never sees its navigation item;
6. assign admin with the Artisan command, sign in again, and land on the dashboard;
7. confirm dashboard statistics/recent registrations and account/profile editing;
8. traverse every control by keyboard and confirm visible focus, labeled errors, disabled submission, and readable contrast.

- [ ] **Step 7: Commit docs and release evidence**

```bash
git add database/seeders README.md docs resources/js/routes resources/js/actions tests/Feature/HealthCheckTest.php
git commit -m "docs: document profile onboarding and roles"
```

- [ ] **Step 8: Confirm a clean final state**

Run:

```bash
git status --short
git log -7 --oneline
```

Expected: no uncommitted files and one focused commit for each implementation slice plus the approved design and plan commits.

---

## Plan Self-review Checklist

- Every requirement in `docs/superpowers/specs/2026-08-16-profile-onboarding-admin-dashboard-design.md` maps to a task above.
- Profile and account terms use separate controllers, routes, pages, and validation rules.
- All role names, enum values, route names, prop names, and method signatures are consistent across backend and frontend tasks.
- Username data is copied before deletion, and rollback accounts for non-unique display names.
- Backend authorization is independent from navigation visibility.
- Each behavior-changing task contains an explicit RED command before implementation and GREEN command afterward.
- No third-party permission package, user-management UI, passion catalogue, or future social feature has entered scope.
