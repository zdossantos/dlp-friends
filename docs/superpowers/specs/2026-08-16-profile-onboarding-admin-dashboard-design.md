# Profile Onboarding and Admin Dashboard Design

## Context

DLP Friends currently stores a required, unique `username` on `users` and
collects it during registration. Authenticated, verified, active adults are
sent to a placeholder dashboard. The product documentation instead describes
a post-verification profile onboarding and reserves public member information
for `profiles`.

This change separates account identity from public profile identity. Accounts
will use their e-mail address for authentication, while a non-unique
`display_name` will live on the member profile. It also introduces the planned
role foundation, restricts the dashboard to administrators, and applies the
first complete slice of the visual direction to authentication, onboarding,
profile, settings, navigation, and the new dashboard.

## Goals

- Remove `username` from the account model, registration, settings, shared
  frontend types, factories, seed data, and identity components.
- Create a one-to-one public profile containing `display_name`, `bio`, visit
  frequency, visibility, and explicit onboarding completion state.
- Require profile completion after e-mail verification and before access to
  ordinary authenticated product pages.
- Redirect a member with a complete profile to their own profile page.
- Add `user` and `admin` roles, assign `user` by default, and restrict the
  dashboard to administrators on the server.
- Replace the dashboard placeholders with a useful administrative overview.
- Apply the documented warm, contemporary, mobile-first visual direction to
  every surface touched by this change.
- Preserve existing usernames during the database transition.

## Non-goals

- Profile photos, catalogue avatars, passions, cities, distance, or romantic
  matching criteria.
- User administration, role assignment through the web UI, or a permissions
  matrix beyond the two initial roles.
- Discovery, swipes, matches, messaging, blocking, or moderation.
- A final visual audit of future MVP pages that do not exist yet.

## Data Model

### Users

`users` remains the private account and authentication record. It contains the
e-mail address, password, birth date, eligibility status, verification data,
passkey and two-factor data, sessions, and timestamps. It no longer contains a
`username` or any other public name.

### Profiles

Each user may have at most one profile. `profiles.user_id` is both a foreign key
and unique. A profile contains:

- `display_name`: required to complete onboarding, trimmed and whitespace
  normalized, non-unique, between 1 and 80 characters, with no character-set
  restriction beyond valid text accepted by Laravel;
- `bio`: nullable text, at most 500 characters;
- `visit_frequency`: nullable while migrating or drafting onboarding, but
  required for completion; accepted values are a backed enum with the initial
  values `rarely`, `sometimes`, `often`, and `very_often`;
- `visibility`: a backed enum with `visible` and `hidden`, defaulting to
  `visible`;
- `onboarding_completed_at`: nullable timestamp set only after all required
  onboarding data passes server validation;
- timestamps.

The profile model exposes an explicit completion predicate based on
`onboarding_completed_at`. Controllers and middleware use that predicate
instead of inferring completion from optional fields.

### Roles

`roles` stores stable role names. The initial records are `user` and `admin`.
`user_roles` links users and roles and has a composite uniqueness constraint so
the same role cannot be attached twice. A new account receives `user` inside
the same logical registration operation.

The model API provides a focused `hasRole(string|RoleName $role): bool`
capability. The application does not introduce a third-party permission
package for this two-role requirement.

### Existing-data transition

The forward migration creates the profile and role structures before removing
`users.username`. For every existing account it:

1. creates a profile whose `display_name` is the existing normalized username;
2. leaves `visit_frequency` and `onboarding_completed_at` null so the member is
   prompted to finish onboarding;
3. attaches the `user` role;
4. removes `users.username` only after the copy succeeds.

The migration is transactional where supported. Constraints prevent duplicate
profiles and duplicate role assignments. Its rollback restores a `username`
column from `profiles.display_name`; because display names are intentionally
non-unique, rollback generates deterministic unique suffixes when necessary.

## Authentication and Routing

Registration asks only for e-mail, birth date, password, and password
confirmation. Existing majority, e-mail uniqueness, password, status, rate
limit, passkey, and two-factor protections remain intact.

Fortify's post-authentication destination becomes one authenticated landing
route. The route uses Laravel's verified middleware, so an unverified member is
sent to the existing verification notice. Once verified, a landing action
applies this ordered decision:

1. no complete profile: redirect to profile onboarding;
2. complete profile plus `admin`: redirect to the admin dashboard;
3. complete profile without `admin`: redirect to the member's own profile.

The profile-onboarding routes require authentication, e-mail verification,
adult eligibility, and active account status, but deliberately do not require
a complete profile. Ordinary social routes add a profile-completion middleware.
The member profile and settings remain available to a complete member.

The dashboard requires all normal account/profile protections plus the
`admin` role. A non-admin direct request receives HTTP 403. Hiding the
navigation item is only a presentation improvement and is never the access
control.

## Profile Onboarding and Editing

The onboarding screen is a focused mobile-first form. It contains:

- `display_name`, required;
- `bio`, optional, with a visible character limit;
- `visit_frequency`, required, presented as understandable French choices;
- `visibility`, defaulted to visible and explained in plain language.

Submission uses a dedicated Form Request and a profile Policy. The server
normalizes repeated and surrounding whitespace in `display_name`, validates
every field, upserts only the authenticated member's profile, and sets
`onboarding_completed_at` after successful validation. Completion redirects to
the landing route, which then sends the member to the correct destination.

The profile page shows the member's display name, calculated age, bio, visit
frequency, and visibility state. The owner can reach a separate edit action.
Profile editing reuses the same validation rules without resetting the original
completion timestamp. Account settings retain e-mail and security controls but
contain no public-name field.

Existing migrated members see their former username prefilled as the display
name and must select a visit frequency before continuing.

## Role Administration

All newly registered and migrated accounts receive `user`. Administrators are
promoted through an explicit Artisan command that accepts an account e-mail and
one of the known role names. The command:

- fails clearly when the account or role does not exist;
- is idempotent when the assignment already exists;
- does not create arbitrary role names;
- reports the resulting assignment without exposing sensitive account data.

There is no browser route for privilege elevation in this scope. Seed data may
use the same application service as the command rather than duplicating role
attachment logic.

## Admin Dashboard

The dashboard replaces the starter placeholders with real aggregate data:

- total accounts;
- active accounts;
- verified accounts;
- completed profiles;
- recent registrations, limited to a small list with e-mail, account status,
  profile completion state, and registration date.

Queries are performed server-side and the page receives purpose-built Inertia
props rather than raw model collections. The page does not expose birth dates,
password metadata, two-factor secrets, passkeys, or other unnecessary private
data. Aggregate cards and the recent-registration list include loading-safe,
empty, and responsive states.

## Visual Direction

The first production visual slice follows `docs/ux-design.md`:

- white/light surfaces by default and a complete dark theme;
- violet as the principal action and navigation color;
- rose as a supporting color;
- restrained gold only for meaningful highlights;
- no Disney marks, characters, copied trade dress, or romantic vocabulary;
- mobile-first layouts with comfortable desktop expansion;
- starter-kit and shadcn-vue primitives before custom interaction widgets.

Semantic CSS tokens define backgrounds, surfaces, text, borders, primary,
secondary, accent, destructive, focus, and muted states in both themes. Pages
consume semantic tokens rather than hard-coded palette values.

Authentication, e-mail verification, onboarding, profile, account settings,
navigation, and dashboard share a coherent shell, typography, spacing, form
states, and feedback patterns. The member navigation shows profile and
settings. The admin navigation additionally shows the dashboard.

Every control has a visible label, keyboard access, visible focus, sufficient
contrast, and a non-color indication for status and errors. Submissions disable
duplicate actions and expose server validation without losing entered values.

## Error Handling and Security

- Authorization is enforced by middleware and Policies before data is loaded.
- Profile ownership is derived from the authenticated user, never trusted from
  a browser-supplied user ID.
- Incomplete profiles cannot enter protected product routes through a guessed
  URL.
- Profile creation and role assignment are idempotent and protected by database
  uniqueness constraints.
- The landing route has deterministic branches and cannot redirect back to
  itself.
- Unknown visit-frequency, visibility, and role values fail validation.
- Dashboard queries expose only the minimum administrative summary needed by
  the page.
- Database migration failure must occur before dropping the old username data.

## Testing Strategy

Backend tests use Pest/PHPUnit and follow a red-green-refactor cycle. They cover:

- registration succeeds without `username` and stores no public name on
  `users`;
- every new account receives `user`;
- majority, e-mail verification, active-status, and authentication protections
  remain effective;
- verification followed by landing redirects an incomplete member to
  onboarding;
- profile validation, normalization, creation, uniqueness by user, completion,
  editing, and authorization;
- a complete ordinary member lands on their profile;
- an administrator lands on the dashboard;
- a normal member receives 403 from the dashboard;
- dashboard aggregates and recent-registration props are accurate and exclude
  sensitive fields;
- role assignment command success, unknown account, unknown role, and
  idempotence;
- the migration preserves existing usernames as display names and assigns the
  default role.

Frontend tests use Vitest and Vue Test Utils. They cover registration field
removal, onboarding interactions and errors, role-aware navigation, profile
rendering, dashboard states, theme persistence, and keyboard-relevant states.

The final verification runs the focused PHP and Vue tests, the complete PHP
suite, frontend tests, type checking, lint, formatting checks, and the relevant
production build. A manual responsive and keyboard pass covers registration,
verification, onboarding, member profile, admin dashboard, direct non-admin
dashboard access, and both themes.

## Documentation and Issue Mapping

This work implements issue #13 with the explicit post-verification onboarding
behavior added by this design. It brings forward the role foundation planned in
issue #14 without implementing passion administration. It implements a bounded
first slice of issue #27; the final visual and accessibility audit remains open
for future MVP pages.

The data model, UX design, MVP scope, implementation plan, and README are
updated where their current `username`, profile, routing, role, or dashboard
descriptions would otherwise conflict with the implemented behavior.
