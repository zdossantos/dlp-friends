# DLP Friends Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the DLP Friends V1 friendly-match application, deployable on Coolify and protected by GitHub CI.

**Architecture:** Start from Laravel's official Vue starter kit, then keep all business rules in Laravel and render the application through Inertia/Vue. A Docker Compose stack runs web, queues, scheduler, Reverb, MySQL, Redis and MinIO as isolated services; `main` is the only production branch.

**Tech Stack:** Laravel, PHP, Inertia, Vue 3, TypeScript, Tailwind CSS, shadcn-vue, Reka UI, MySQL, Redis, Laravel Reverb, MinIO, Pest, Vitest, GitHub Actions, Docker Compose and Coolify.

## Global Constraints

- Create the application from Laravel's official Vue starter kit; do not install Inertia manually.
- Use currently stable compatible releases and lock every dependency.
- DLP Friends is friendship-only and restricted to members aged 18 or older.
- Laravel owns authentication, authorisation, matching, conversations and deletion; no separate API is created.
- A member controls profile visibility, data export and deletion from V1.
- Do not commit secrets, production `.env` files or unlicensed Disney assets.
- Every business rule below requires a Pest test; every Vue interactive component requires a focused Vitest test.
- Apply `docs/engineering-principles.md`: favour framework conventions and direct code; add an abstraction only when it removes a current, demonstrated complexity.

## Fondations livrées en amont

- [x] Issue #13 : séparation compte/profil, suppression de `username`, nom d'affichage non unique, onboarding après vérification et page de profil personnelle.
- [x] Fondation de l'issue #14 : rôles normalisés `user`/`admin`, commande d'attribution, middleware serveur et dashboard d'agrégats réservé aux administrateurs. Le catalogue administrable reste dans la tâche 4.
- [x] Tranche bornée de l'issue #27 : palette sémantique violet/rose/or, thèmes clair/sombre/système, identité DLP Friends et harmonisation des parcours existants. Les futurs écrans métier adopteront ces mêmes tokens.

---

### Task 1: Bootstrap the application and local services

**Files:**
- Create: Laravel starter-kit files at repository root
- Create: `compose.yaml`, `Dockerfile`, `.dockerignore`, `.env.example`
- Create: `docker/nginx/default.conf`, `docker/php/entrypoint.sh`
- Test: `tests/Feature/HealthCheckTest.php`

**Produces:** a bootable Laravel/Inertia/Vue application and a local MySQL/Redis/MinIO/Mailpit stack. Production mail infrastructure is deferred.

- [x] Create a temporary Laravel project using the official Vue starter kit with TypeScript, then place its application files at repository root without overwriting `README.md` or `docs/`.
- [x] Configure `compose.yaml` services named `web`, `worker`, `scheduler`, `reverb`, `mysql`, `redis`, `minio` and `mailpit`; keep MySQL and Redis private and define persistent volumes for MySQL and MinIO.
- [x] Write `HealthCheckTest` asserting `GET /up` returns HTTP 200.
- [x] Run `php artisan test tests/Feature/HealthCheckTest.php`; expect PASS.
- [x] Run `docker compose up --build` and verify `curl http://localhost:8000/up` returns 200.
- [x] Commit the bootstrap in focused Conventional Commit changesets.

### Task 2: Establish automated quality and branch delivery

**Files:**
- Create: `.github/workflows/ci.yml`, `.github/workflows/release-please.yml`
- Create: `.github/scripts/validate-pr-title.sh`, `.github/dependabot.yml`
- Create: `.github/settings/repository.json`, `.github/settings/main-protection.json`
- Create: `phpstan.neon`, `vitest.config.ts`, `eslint.config.js`
- Modify: `composer.json`, `package.json`, `README.md`, `CONTRIBUTING.md`
- Test: `tests/Feature/HealthCheckTest.php`
- Test: `.github/scripts/test-validate-pr-title.sh`

**Produces:** repeatable PHP/Vue checks, required pull request validation on
`main`, automated dependency updates and voluntary SemVer releases.

- [x] Add scripts named `test`, `test:unit`, `lint`, `format:check`, `types:check`, `analyse` and `build` that fail on errors.
- [x] Configure Pest, Laravel Pint, Larastan/PHPStan, ESLint, Vue type checking and Vitest.
- [x] Run six parallel checks for every non-draft pull request into `main`: Conventional PR title, PHP quality, backend tests, frontend quality, Vite build and Docker build.
- [x] Configure Dependabot for Composer, npm and GitHub Actions with pull requests into `main`.
- [x] Configure Release Please on `main` to maintain `CHANGELOG.md`, SemVer tags and GitHub Releases without publishing packages.
- [x] Version squash-only repository settings and a protected, linear `main`; disallow direct pushes, force-pushes and deletion.
- [x] Configure Coolify separately to watch `main` and deploy it automatically; do not add a Coolify webhook or deployment workflow to GitHub Actions.
- [x] Document direct contributions to `main`, Conventional Commit titles and voluntary Release PR merges.

### Task 3: Implement accounts, adulthood and social login

**Files:**
- Create: `database/migrations/*_add_birth_date_and_status_to_users_table.php`, `database/migrations/*_create_social_accounts_table.php`
- Create: `app/Models/SocialAccount.php`, `app/Actions/Fortify/ValidateAdultRegistration.php`
- Modify: `app/Models/User.php`, `app/Providers/FortifyServiceProvider.php`, `routes/web.php`, registration Inertia pages
- Test: `tests/Feature/Auth/AdultRegistrationTest.php`, `tests/Feature/Auth/SocialLoginTest.php`

**Produces:** e-mail/password, Google and Apple authentication for verified adult accounts.

- [ ] Write a failing Pest dataset test for birth dates exactly under 18, exactly 18 and over 18; assert the first is rejected and the others may register.
- [ ] Implement `ValidateAdultRegistration` and register it in Fortify; store `birth_date` as a date and `status` as `active` by default.
- [ ] Write failing callback tests asserting a Google/Apple provider identity is linked once to `social_accounts(provider, provider_user_id)` and a repeat callback reuses the same user.
- [ ] Configure Laravel Socialite routes, callback validation and provider linking; require verified e-mail before social features.
- [ ] Run `php artisan test tests/Feature/Auth`; expect PASS.
- [ ] Commit: `feat: add adult authentication and social login`.

### Task 4: Build profiles, image processing and administration catalogue

**Files:**
- Create: migrations for `passion_categories`, `passions`, `passion_profile`, `avatars`
- Create: `app/Models/Passion.php`, `app/Models/PassionCategory.php`, `app/Models/Avatar.php`
- Create: `app/Jobs/ProcessProfileImage.php`, `app/Http/Controllers/ProfileController.php`, `app/Http/Controllers/Admin/*Controller.php`
- Create: `resources/js/Pages/Profile/Edit.vue`, `resources/js/Pages/Admin/Passions/Index.vue`
- Test: `tests/Feature/ProfileTest.php`, `tests/Feature/Admin/PassionCatalogueTest.php`, `tests/Unit/ProcessProfileImageTest.php`

**Produces:** editable public profiles, safe optional images and database-managed passions/avatars.

- [ ] Write failing tests for hidden/visible profile state, multiple passions, non-admin access denial and an admin creating a category and passion. Preserve the existing non-unique display-name rule.
- [ ] Implement the catalogue models and migrations with foreign keys and unique catalogue constraints, reusing the existing profile and role foundations.
- [ ] Write a failing queued-image test: a valid image dispatches `ProcessProfileImage`; an executable or oversized image is rejected.
- [ ] Implement upload validation, EXIF stripping, resized image variants and private MinIO keys in `ProcessProfileImage`.
- [ ] Implement profile editing and protected admin catalogue CRUD; forbid all admin message access.
- [ ] Run the three named test files and `npm run types:check`; expect PASS.
- [ ] Commit: `feat: add profiles passions avatars and admin catalogue`.

### Task 5: Implement discovery, swipes and reciprocal matches

**Files:**
- Create: migrations for `swipes`, `matches`; `app/Models/Swipe.php`, `app/Models/Match.php`
- Create: `app/Services/DiscoveryService.php`, `app/Actions/CreateSwipe.php`, `app/Policies/ProfilePolicy.php`
- Create: `resources/js/Pages/Discover/Index.vue`, `resources/js/Components/Discover/SwipeCard.vue`
- Test: `tests/Unit/DiscoveryServiceTest.php`, `tests/Feature/SwipeTest.php`, `resources/js/Components/Discover/SwipeCard.spec.ts`

**Produces:** swipe discovery ordered by transparent affinity and a match after reciprocal likes.

- [ ] Write failing tests for score `common passions + 0.25 same visit frequency`, exclusion of self/hidden/evaluated/blocked profiles, and equal-score random tie handling.
- [ ] Implement `DiscoveryService::for(User $user): Collection` with the documented exclusions and sort order.
- [ ] Write failing feature tests proving a single like creates no match and the reverse like creates one canonical match only once.
- [ ] Implement `CreateSwipe::handle(User $actor, User $target, string $decision): Match|null` with a unique `(actor,target)` swipe constraint and canonical match IDs.
- [ ] Write and run a Vue test asserting the card emits only `like` or `pass`; implement keyboard-accessible buttons plus swipe gesture enhancement.
- [ ] Run all discovery tests; expect PASS.
- [ ] Commit: `feat: add affinity discovery and reciprocal matching`.

### Task 6: Add private real-time conversations and blocking

**Files:**
- Create: migrations for `conversations`, `messages`, `blocks`
- Create: `app/Models/Conversation.php`, `app/Models/Message.php`, `app/Models/Block.php`, `app/Events/MessageSent.php`
- Create: `app/Actions/SendMessage.php`, `app/Actions/BlockUser.php`, `app/Policies/ConversationPolicy.php`, `routes/channels.php`
- Create: `resources/js/Pages/Messages/Show.vue`, `resources/js/Components/Messages/Composer.vue`
- Test: `tests/Feature/ConversationTest.php`, `tests/Feature/BlockUserTest.php`, `resources/js/Components/Messages/Composer.spec.ts`

**Produces:** text-only private chat created from a match, with immediate bilateral blocking.

- [ ] Write failing tests for one conversation per match, unauthorised conversation access, 2,000-character limit, and a private broadcast channel denial for non-members.
- [ ] Implement `SendMessage::handle(User $author, Conversation $conversation, string $body): Message`, validate plain text and broadcast `MessageSent` on the authorised private channel.
- [ ] Write failing tests that a block removes suggestions, archives the conversation and rejects messages for both members without revealing the blocker.
- [ ] Implement `BlockUser::handle(User $blocker, User $blocked): void` transactionally; make every discovery and conversation policy consult blocks.
- [ ] Write/run the Composer test for disabled sending on archived conversations; implement the matching UI state.
- [ ] Run feature, unit and Vue tests; expect PASS.
- [ ] Commit: `feat: add matched messaging and blocking`.

### Task 7: Deliver privacy controls and data lifecycle

**Files:**
- Create: `app/Actions/ExportUserData.php`, `app/Jobs/PurgeDeletedUser.php`, `app/Http/Controllers/Settings/PrivacyController.php`
- Create: `resources/js/Pages/Settings/Privacy.vue`
- Modify: `app/Console/Kernel.php` or scheduler registration file, policies and profile queries
- Test: `tests/Feature/Settings/PrivacyTest.php`, `tests/Unit/PurgeDeletedUserTest.php`

**Produces:** profile hiding, data export and safe account-deletion flow.

- [ ] Write failing tests for hidden profiles disappearing from discovery, export containing profile/passions/matches/messages, and deletion revoking sessions immediately.
- [ ] Implement hide/unhide and `ExportUserData` as a queued downloadable JSON archive excluding secrets and other users' private data.
- [ ] Write a failing test that deletion marks `pending_deletion`, dispatches a job and makes authentication/social routes unavailable immediately.
- [ ] Implement `PurgeDeletedUser`, scheduled to run within 30 days, deleting images/credentials/profile/social links/swipes/matches/conversations/messages in the documented order.
- [ ] Run privacy tests and a full `php artisan test`; expect PASS.
- [ ] Commit: `feat: add member privacy controls and deletion`.

### Task 8: Apply the visual system and verify the release stack

**Files:**
- Create: `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/ThemeToggle.vue`, `resources/js/lib/theme.ts`
- Modify: `resources/css/app.css`, Inertia pages, `compose.yaml`, `README.md`, all relevant `docs/*.md`
- Test: `resources/js/Components/ThemeToggle.spec.ts`, `tests/Feature/HealthCheckTest.php`

**Produces:** responsive light/dark interface, documented operational deployment and release evidence.

- [ ] Write a failing ThemeToggle test for persisted `light`/`dark` preference and system-preference fallback.
- [ ] Implement violet/rose tokens with restrained gold accents in both themes; ensure focus, disabled and error states meet accessible contrast requirements.
- [ ] Apply the shared layout to profile, discovery, matches, messages and settings pages; use shadcn-vue first and Reka UI only for missing accessible primitives.
- [ ] Verify every Compose service has required environment variables, health checks, restart policy and no public database/cache port; verify Mailpit remains the only local development mail sink.
- [ ] Run `docker compose up --build`, all PHP/Vue checks and a manual browser smoke test: registration, profile, discovery, reciprocal match, message, block, hide and deletion request.
- [ ] Commit: `feat: finish V1 visual system and deployment readiness`.

## Coverage self-review

Every V1 area in `docs/mvp-v1.md` maps to Tasks 3–8. The V2 visit-companion feature, report moderation, groups, payments and advanced discovery remain intentionally absent. External production credentials and rights-confirmed avatar assets are configuration inputs, not code tasks.
