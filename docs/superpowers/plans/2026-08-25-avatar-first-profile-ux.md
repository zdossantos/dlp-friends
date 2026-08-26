# Avatar-first Profile UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn avatar selection, profile display, and discovery into an avatar-first, accessible four-step mobile experience.

**Architecture:** Keep one native Inertia `Form` and the existing backend payload. Add focused Vue presentation components for the stepper and carousel, then compose the same avatar hero treatment in profile and discovery without new dependencies or database fields.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS 4, Pest Browser.

**Spec:** `docs/superpowers/specs/2026-08-25-avatar-first-profile-ux-design.md`

## Global Constraints

- Preserve existing field names, routes, validation, authorization, and create/update methods.
- Use only existing catalog images and their `primary_color` and `secondary_color` values.
- Keep all language friendship-oriented and avoid romantic imagery.
- Support 320px viewports, keyboard operation, 44px targets, dark mode, and `prefers-reduced-motion`.
- Add no frontend dependency.

---

### Task 1: Accessible four-step profile form

**Files:**
- Create: `resources/js/components/profile/ProfileFormStepper.vue`
- Create: `resources/js/components/profile/AvatarCarousel.vue`
- Modify: `resources/js/components/profile/ProfileForm.vue`
- Modify: `resources/js/components/profile/InterestTagSelector.vue`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- `ProfileFormStepper` consumes `currentStep: number` and emits `select(step: number)` for reachable steps.
- `AvatarCarousel` consumes `avatars: AvatarOption[]`, `modelValue: number | null`, and emits `update:modelValue(id: number)`.
- `ProfileForm` preserves the current public props and submitted input names.

- [ ] **Step 1: Write failing browser tests** for four-step progress, keyboard carousel selection, retained identity values after Back/Continue, and final create submission.
- [ ] **Step 2: Run the targeted profile browser tests** with `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/ProfileAndNavigationTest.php --filter='profile onboarding' --display-warnings`; expect failures because the wizard controls and step semantics do not exist.
- [ ] **Step 3: Implement the stepper and carousel** with stable Back/Continue controls, pointer swipe, Left/Right keys, explicit selected state, hidden native form inputs, and reduced-motion classes.
- [ ] **Step 4: Refactor `ProfileForm` into four panels** while keeping all form values reactive and submitting only from the preview step. Move visit frequency beside interests and render visibility in Preview.
- [ ] **Step 5: Run the targeted profile tests** and confirm they pass without JavaScript errors.

### Task 2: Avatar-first member profile

**Files:**
- Modify: `resources/js/components/profile/AvatarPortrait.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- `AvatarPortrait` keeps `avatar: AvatarOption` and supports caller sizing through inherited classes.
- The profile exposes `data-test="profile-avatar-hero"` for observable layout checks.

- [ ] **Step 1: Write a failing browser test** asserting the profile avatar hero, gradient treatment, readable information panel, and labelled edit/settings/logout actions.
- [ ] **Step 2: Run the completed-member profile test** and verify failure because the hero contract is absent.
- [ ] **Step 3: Implement the large gradient hero and overlapping opaque information sheet** using the avatar colors and responsive sizing.
- [ ] **Step 4: Run the targeted completed-member test** and confirm it passes in mobile mode without horizontal overflow or JavaScript errors.

### Task 3: Avatar-first discovery card and visible decisions

**Files:**
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Test: `tests/Browser/DiscoveryTest.php`

**Interfaces:**
- Preserve `profile`, `locked`, and `preview` props and `like`/`pass` emits.
- Keep ArrowLeft/ArrowRight and pointer-swipe behavior.
- Add visible buttons whose accessible names remain `Passer ce profil` and `Aimer ce profil`.

- [ ] **Step 1: Write failing browser assertions** for the large avatar hero, opaque information sheet, common-interest hierarchy, and visible decision buttons.
- [ ] **Step 2: Run the top-card accessibility test** and verify failure because the current decision controls are screen-reader-only and the hero is small.
- [ ] **Step 3: Implement the immersive avatar hero, overlapping information sheet, and visible decision controls** without changing decision events.
- [ ] **Step 4: Run all discovery browser tests** and confirm keyboard, pointer threshold, retry, stack, and match behavior remain green.

### Task 4: Responsive and quality verification

**Files:**
- Modify if required: files from Tasks 1–3 only

**Interfaces:**
- No new interfaces.

- [ ] **Step 1: Run frontend static checks** with `bun run lint:check`, `bun run format:check`, and `bun run types:check`.
- [ ] **Step 2: Run the production frontend build** with `bun run build`.
- [ ] **Step 3: Run relevant browser suites** with `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/ProfileAndNavigationTest.php tests/Browser/DiscoveryTest.php --display-warnings`.
- [ ] **Step 4: Run relevant backend tests** with `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/MemberProfileTest.php tests/Feature/DiscoveryPageTest.php tests/Unit/DiscoveryServiceTest.php`.
- [ ] **Step 5: Inspect `git diff --check` and `git status --short`**, then review the implementation against every requirement in the spec.
