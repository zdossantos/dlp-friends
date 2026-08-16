# Adult and Active Accounts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Require adult registrations and restrict social routes to verified, adult users with active accounts.

**Architecture:** Store birth date and a typed account status on `users`, calculate age from the application clock, and compose Laravel's existing `auth` and `verified` middleware with one reusable `social` eligibility middleware. Keep Fortify authentication and account-management routes unchanged.

**Tech Stack:** PHP 8.3, Laravel 13, Fortify, PHPUnit/Pest infrastructure, Inertia 3, Vue 3, TypeScript.

## Global Constraints

- Existing users must migrate without fabricated birth dates; a missing date denies social access.
- Exactly 18 years old is accepted and one day younger is rejected.
- `status` supports exactly `active` and `pending_deletion`; new users default to `active`.
- Fortify login throttling, password reset, two-factor authentication, passkeys, and sessions remain unchanged.
- Account-management routes keep their existing middleware.

---

### Task 1: Account eligibility domain model

**Files:**
- Create: `app/Enums/UserStatus.php`
- Create: `database/migrations/2026_08_16_000000_add_eligibility_fields_to_users_table.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Create: `tests/Unit/Models/UserTest.php`

**Interfaces:**
- Produces: `UserStatus::Active`, `UserStatus::PendingDeletion`, `User::$birth_date`, `User::$status`, and nullable integer `$user->age`.
- Produces: adult, active users from `UserFactory` by default.

- [ ] **Step 1: Write failing age and default-state tests**

Freeze time at `2026-08-16` and assert that birth dates `2008-08-16`, `2000-08-15`, and `2000-08-17` calculate ages `18`, `26`, and `25`; assert that `null` yields `null`. Persist a factory user and assert an enum-cast `UserStatus::Active` and an adult birth date.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `php artisan test tests/Unit/Models/UserTest.php`

Expected: FAIL because eligibility fields, enum, and age accessor do not exist.

- [ ] **Step 3: Implement the minimal domain model**

Create a string-backed enum:

```php
enum UserStatus: string
{
    case Active = 'active';
    case PendingDeletion = 'pending_deletion';
}
```

Add nullable `birth_date` and defaulted `status` columns. Add both attributes to `User` fillable metadata, cast them to `date` and `UserStatus::class`, and expose age through an `Attribute` accessor using `Carbon::age`. Update the factory with `birth_date => today()->subYears(25)` and `status => UserStatus::Active`.

- [ ] **Step 4: Run the focused tests and verify GREEN**

Run: `php artisan test tests/Unit/Models/UserTest.php`

Expected: PASS.

### Task 2: Adult-only Fortify registration

**Files:**
- Modify: `tests/Feature/Auth/RegistrationTest.php`
- Modify: `app/Actions/Fortify/CreateNewUser.php`
- Modify: `app/Models/User.php`

**Interfaces:**
- Consumes: `User::$birth_date` and `UserStatus::Active` from Task 1.
- Produces: registration input `birth_date: YYYY-MM-DD` with an inclusive 18-year cutoff.

- [ ] **Step 1: Write failing registration boundary tests**

Freeze time at `2026-08-16`. Update the successful registration payload to include `2008-08-16`. Add separate tests asserting validation failure for missing `birth_date` and `2008-08-17`, and successful persistence for `2008-08-16` with active status.

- [ ] **Step 2: Run the registration tests and verify RED**

Run: `php artisan test tests/Feature/Auth/RegistrationTest.php`

Expected: FAIL because `birth_date` is neither validated nor persisted.

- [ ] **Step 3: Implement minimal registration validation**

Add rules equivalent to:

```php
'birth_date' => [
    'required',
    Rule::date()->beforeOrEqual(today()->subYears(18)),
],
```

Persist the validated input through `User::create`. Do not accept `status` from the request.

- [ ] **Step 4: Run registration and auth tests and verify GREEN**

Run: `php artisan test tests/Feature/Auth/RegistrationTest.php tests/Feature/Auth/EmailVerificationTest.php`

Expected: PASS.

### Task 3: Social eligibility middleware

**Files:**
- Create: `app/Http/Middleware/EnsureUserCanAccessSocialFeatures.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Models/User.php`
- Modify: `tests/Feature/DashboardTest.php`

**Interfaces:**
- Consumes: nullable `$user->age` and enum `$user->status`.
- Produces: middleware alias `social`, returning the next response only for an adult active user.

- [ ] **Step 1: Write the failing access-matrix tests**

Keep the guest redirect assertion. Assert that a verified adult active user receives 200; an unverified adult active user redirects to `verification.notice`; and inactive, underage, or missing-birth-date users receive 403.

- [ ] **Step 2: Run dashboard tests and verify RED**

Run: `php artisan test tests/Feature/DashboardTest.php`

Expected: FAIL because email verification is not activated on `User` and social eligibility is not enforced.

- [ ] **Step 3: Implement minimal middleware composition**

Make `User` implement `MustVerifyEmail`. Create middleware that aborts with 403 unless:

```php
$request->user()?->status === UserStatus::Active
    && $request->user()->age !== null
    && $request->user()->age >= 18
```

Register `social` through Laravel's middleware alias configuration and change the dashboard group to `['auth', 'verified', 'social']`.

- [ ] **Step 4: Run dashboard and email-verification tests and verify GREEN**

Run: `php artisan test tests/Feature/DashboardTest.php tests/Feature/Auth/EmailVerificationTest.php`

Expected: PASS.

### Task 4: Accessible registration field

**Files:**
- Modify: `resources/js/pages/auth/Register.vue`

**Interfaces:**
- Consumes: Fortify registration parameter `birth_date`.
- Produces: required native date control named `birth_date` with its validation error.

- [ ] **Step 1: Add the native date field**

Insert the date input between email and password:

```vue
<Label for="birth_date">Date of birth</Label>
<Input
    id="birth_date"
    type="date"
    required
    autocomplete="bday"
    name="birth_date"
/>
<InputError :message="errors.birth_date" />
```

Renumber the tab indexes so they remain sequential.

- [ ] **Step 2: Run frontend static checks**

Run: `npm run lint:check && npm run format:check && npm run types:check`

Expected: PASS with no errors.

### Task 5: Full verification and issue completion evidence

**Files:**
- Modify only files needed to correct verification failures caused by this issue.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: a clean, fully verified implementation of GitHub issue #11.

- [ ] **Step 1: Run backend quality gates**

Run: `composer lint:check && composer types:check && php artisan test`

Expected: all commands PASS without warnings attributable to the change.

- [ ] **Step 2: Run frontend quality gates and production build**

Run: `npm run lint:check && npm run format:check && npm run types:check && npm run test && npm run build`

Expected: all commands PASS.

- [ ] **Step 3: Inspect the final diff**

Run: `git diff --check && git status --short && git diff --stat HEAD~1`

Expected: no whitespace errors and only issue #11 files plus the approved design and plan.
