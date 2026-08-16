# Adult and Active Accounts Design

## Context

GitHub issue #11 establishes the account eligibility rules required by the rest of the social MVP. Registration must require a date of birth and reject minors, while social features must remain unavailable to users whose email is unverified, whose account is inactive, or whose recorded age is below 18.

The existing application uses Laravel Fortify, Inertia, Vue, and the standard `auth` and `verified` middleware. The dashboard is currently the only authenticated product page and therefore acts as the first social route until profile and discovery routes are added.

## Data Model

Add a migration that extends `users` with:

- `birth_date`, a nullable date;
- `status`, a non-null string defaulting to `active`.

The pre-MVP account schema replaces the starter kit's generic `name` column with a unique `username` column. No first-name or last-name data is collected. Existing development data is disposable and the local database is recreated after this baseline migration changes.

`birth_date` remains nullable at the database level so the migration can be deployed safely over pre-existing users without inventing personal data. The application layer requires it for every new registration, and the social-access middleware denies users with no recorded birth date. A later account-completion flow may collect missing dates from legacy users.

Represent statuses with a string-backed `UserStatus` enum containing `Active` and `PendingDeletion`. Cast `birth_date` to a date and `status` to the enum on `User`. New factory users are adults and active by default, with explicit states available for minors, missing birth dates, and inactive accounts where tests need them.

## Registration and Age Calculation

The Fortify registration action accepts `birth_date`, validates it as a real date, and requires it to be on or before the date exactly 18 years before the current day. The comparison uses the application clock so tests can freeze time. Exactly 18 years old is accepted; one day younger is rejected. A minor receives the business-facing validation message `Vous devez être majeur pour vous inscrire.` instead of Laravel's generated cutoff-date message.

Registration also requires a unique `username` containing 3 to 30 Unicode letters, numbers, spaces, underscores, or hyphens. Leading and trailing whitespace is removed and consecutive whitespace is collapsed to a single space before validation and persistence. The normalized value must still satisfy the length and uniqueness rules. The database unique constraint remains the final concurrency-safe guarantee.

The registration page replaces the starter kit's full-name input with a required `Pseudo` input and adds a required native date input with an associated label, browser autocomplete metadata, stable tab order, and the existing `InputError` component. The username and date are passed to `User::create`, while `status` relies on the database/model default and is never client-controlled.

All starter-kit account surfaces use `username` instead of `name`: account settings, shared authenticated-user types, menu/header identity, initials, factories, seeders, and profile update validation. Passkey names and the application name remain unrelated and unchanged.

`User` exposes an `age` accessor calculated from `birth_date` at read time. It returns `null` when no date is recorded. The age is never persisted, preventing it from becoming stale.

## Email Verification and Social Access

`User` implements Laravel's `MustVerifyEmail` contract so the existing Fortify email-verification feature and `verified` middleware become effective.

Create an `EnsureUserCanAccessSocialFeatures` middleware with one responsibility: reject access unless the authenticated user has a birth date, is at least 18 today, and has status `active`. Register it under the `social` middleware alias. Inactive, underage, or incomplete legacy accounts receive HTTP 403 and do not learn about any social data.

Social routes use the ordered middleware stack `auth`, `verified`, `social`. Keeping `verified` separate preserves Laravel's native redirect to the verification notice. The existing dashboard moves into this group and establishes the pattern future profile, discovery, matching, and messaging routes must reuse. Account-management routes remain governed by their existing middleware so an ineligible account can still reach the controls needed to verify or eventually delete its account.

No custom Fortify authentication callback is introduced. Login throttling, password reset, two-factor authentication, passkeys, and session behavior therefore remain unchanged.

## Error Handling

- Invalid or missing registration dates use Laravel validation errors and return to the registration form through the existing Inertia flow.
- Invalid or duplicate usernames use field-level Laravel validation errors and never create or update a user.
- Unverified users are redirected by Laravel's `verified` middleware.
- Missing birth dates, minors, and inactive users receive HTTP 403 from the social middleware.
- Unknown status strings fail enum casting rather than silently granting access.

## Testing

Pest/PHPUnit feature tests cover:

- registration requires `birth_date`;
- a user one day short of 18 is rejected;
- the underage error says the member must be an adult and does not expose a cutoff date;
- a user exactly 18 is accepted and stored as active;
- registration requires a valid, unique username and stores normalized whitespace;
- account settings update `username` and enforce the same format and uniqueness rules;
- Fortify's existing registration and rate-limit behavior remains intact;
- the age accessor handles birthdays before, on, and after the current date, plus a missing date;
- the dashboard allows an authenticated, verified, adult, active user;
- the dashboard redirects an unverified user to the verification notice;
- the dashboard forbids inactive, underage, and missing-birth-date users.

The full PHP test suite, static analysis, formatting checks, frontend type checking, linting, and production build must pass.

## Out of Scope

- Documentary age verification.
- Editing the date of birth after registration.
- Age-based discovery filters.
- Preventing inactive users from authenticating entirely; this issue restricts social features while preserving access to account-management flows.
- A legacy-user birth-date collection interface.
- Collecting legal first names or last names.
