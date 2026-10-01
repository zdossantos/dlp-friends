# Admin Mobile-First Workspace Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the sidebar-based administration with a mobile-first bottom navigation, shared workspace switching, grouped submenus, and responsive administration screens usable from 320 px.

**Architecture:** Extract only the common behavior from the existing member/partner bottom navigation into focused bottom-navigation, group-sheet, and workspace-switcher components. Keep explicit navigation configurations per workspace, move the admin shell onto the shared primitives, and render card/table variants from the same server data for responsive admin lists.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Wayfinder, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-10-01-admin-mobile-first-workspace-design.md`

## Global Constraints

- Preserve every existing route, server-side authorization, confirmation, and business rule.
- Do not add a Notifications destination to the administration workspace.
- Show Member, Partner, and Administration workspaces only when the authenticated account has the corresponding `user`, `partner`, and `admin` roles.
- Keep all visible and accessibility copy in the French and English translation catalogs.
- Support keyboard navigation, focus restoration, light/dark/seasonal themes, reduced motion, iOS safe areas, and touch targets of at least 44 × 44 px.
- Prevent page-level horizontal scrolling at a 320 px viewport.
- Reuse existing Reka UI primitives and add no dependency.

## Review Focus

- A single-role administrator must not receive a useless workspace-switcher button; Task 2 adds the browser assertion.
- Combined roles in any order must expose exactly their authorized workspaces and mark the current one; Task 3 adds the role-matrix browser assertions.
- A grouped destination loaded by direct URL must activate both its entry and group trigger; Task 2 adds the direct-load assertions.
- Long translated labels and long member/partner values must not create horizontal overflow at 320 px; Tasks 4–6 add overflow assertions with representative long values.
- Mobile card variants must retain every action and accessible label available in desktop tables; Tasks 4 and 5 exercise actions from both viewport variants.

---

### Task 1: Shared bottom-navigation primitives

**Files:**
- Create: `resources/js/components/navigation/BottomNavigation.vue`
- Create: `resources/js/components/navigation/BottomNavigationGroup.vue`
- Create: `resources/js/components/navigation/WorkspaceSwitcher.vue`
- Modify: `resources/js/types/navigation.ts`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Produces: `BottomNavigationItem` and `WorkspaceDestination` types in `resources/js/types/navigation.ts`.
- Produces: `BottomNavigation` with `items: BottomNavigationItem[]` and default slot for supplementary buttons.
- Produces: `BottomNavigationGroup` with `label`, `icon`, `items`, `testId`, and active state derived from the current URL.
- Produces: `WorkspaceSwitcher` with no caller-supplied role logic; it reads Inertia auth roles and current URL, then renders only reachable alternate workspaces.

- [ ] **Step 1: Write failing shared-navigation browser tests**

Add tests named `shared workspace switcher exposes authorized destinations and marks the current workspace` and `bottom navigation group restores focus and marks a directly loaded child active`. Assert translated accessible labels, `aria-current`, sheet open/close behavior, and focus restoration.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='shared workspace switcher|bottom navigation group'`

Expected: FAIL because the shared components and selectors do not exist.

- [ ] **Step 3: Add navigation types and the three focused components**

Implement the interfaces above using the existing `Sheet` primitives, `useCurrentUrl`, Inertia router pending state, and the existing 48 px navigation controls. Keep badge support in `BottomNavigationItem`; keep workspace destinations explicit inside `WorkspaceSwitcher` through Wayfinder route imports.

- [ ] **Step 4: Add shared French and English labels**

Extend `common.workspace_switcher` with the Administration destination and add generic accessible labels for grouped navigation. Do not move domain-specific admin labels into `common.php`.

- [ ] **Step 5: Run the focused tests and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/navigation resources/js/types/navigation.ts lang/fr/common.php lang/en/common.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "refactor: share workspace navigation primitives"
```

### Task 2: Mobile-first administration shell and grouped navigation

**Files:**
- Create: `resources/js/components/admin/AdminBottomNavigation.vue`
- Create: `resources/js/components/admin/AdminPageHeader.vue`
- Modify: `resources/js/layouts/AdminLayout.vue`
- Modify: `resources/js/layouts/resolvePageLayout.ts`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Modify: `tests/Browser/AdminTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Consumes: shared components and types from Task 1.
- Produces: `AdminBottomNavigation` with Dashboard and Members direct links, Catalogues and Partners grouped sheets, and `WorkspaceSwitcher` as the fifth possible action.
- Produces: `AdminPageHeader` with `title`, optional `description`, and an `actions` slot.

- [ ] **Step 1: Write failing admin-shell navigation tests**

Cover a 320 × 844 viewport and desktop. Assert that the sidebar and admin Notifications link are absent; Dashboard and Members are direct links; Catalogues exposes Interests, Avatars, Onboarding, and Seasonal Themes; Partners exposes Profiles, Announcements, and Statistics; direct child URLs activate both child and trigger; a single-role admin has no workspace trigger.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/AdminTest.php --filter='admin navigation' --display-warnings`

Expected: FAIL against the current sidebar shell.

- [ ] **Step 3: Implement `AdminBottomNavigation` and translations**

Use generated Wayfinder destinations. Use stable `data-test` hooks for the navigation, Catalogues trigger, Partners trigger, and their child links. Do not import the admin notifications route.

- [ ] **Step 4: Replace `AdminLayout` sidebar composition**

Render the shared app-level overlays already provided by the old shell (`Toaster`, PWA update prompt, and Web Push invitation), a `main` content area with safe bottom padding, and `AdminBottomNavigation`. Remove breadcrumb translation plumbing that no longer has a visible consumer while preserving the layout resolver contract.

- [ ] **Step 5: Run the focused tests and verify GREEN**

Run the command from Step 2 plus:

`APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='administrator'`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/admin/AdminBottomNavigation.vue resources/js/components/admin/AdminPageHeader.vue resources/js/layouts/AdminLayout.vue resources/js/layouts/resolvePageLayout.ts lang/fr/administration.php lang/en/administration.php tests/Browser/AdminTest.php tests/Browser/ProfileAndNavigationTest.php
git commit -m "feat: add mobile-first admin navigation"
```

### Task 3: Migrate member and partner workspace switching to the shared component

**Files:**
- Modify: `resources/js/components/MemberBottomNavigation.vue`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Delete: `resources/js/components/AppSidebar.vue` if no remaining import exists
- Modify: `resources/js/components/NavMain.vue` only if it becomes unused and removal is safe

**Interfaces:**
- Consumes: `BottomNavigation` and `WorkspaceSwitcher` from Task 1.
- Produces: unchanged member and partner destinations and unread-count behavior through the shared primitives.

- [ ] **Step 1: Extend the failing role-matrix tests**

Cover `user`, `partner`, `admin`, `user+partner`, `user+admin`, `partner+admin`, and all three roles. Assert exact workspace destinations in member, partner, and admin contexts, including the absence of the current-only switcher.

- [ ] **Step 2: Run the role-matrix tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php --filter='workspace'`

Expected: FAIL where Administration is missing or switching remains locally implemented.

- [ ] **Step 3: Refactor `MemberBottomNavigation.vue`**

Keep explicit member and partner item arrays, unread notification badge behavior, and visibility rules. Replace its duplicated container, pending state, and workspace sheet with `BottomNavigation` and `WorkspaceSwitcher`.

- [ ] **Step 4: Remove sidebar-only code proven unused**

Use `rg` to confirm imports before deleting `AppSidebar.vue` or simplifying `NavMain.vue`. Do not remove generic sidebar primitives still used by settings or other layouts.

- [ ] **Step 5: Run navigation tests and verify GREEN**

Run the command from Step 2.

Expected: PASS with the existing member and partner destination counts preserved.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/MemberBottomNavigation.vue resources/js/components/AppSidebar.vue resources/js/components/NavMain.vue tests/Browser/ProfileAndNavigationTest.php
git commit -m "refactor: unify workspace switching"
```

### Task 4: Responsive member administration and onboarding progress

**Files:**
- Create: `resources/js/components/admin/AdminMemberCard.vue`
- Create: `resources/js/components/admin/OnboardingProgressCard.vue`
- Modify: `resources/js/pages/Admin/Members/Index.vue`
- Modify: `resources/js/pages/Admin/Onboarding/Index.vue`
- Modify: `tests/Browser/AdminTest.php`

**Interfaces:**
- Produces: `AdminMemberCard` with the same member data and role/conversation/delete actions as the desktop row.
- Produces: `OnboardingProgressCard` with the same identity and progression values as the desktop onboarding table row.

- [ ] **Step 1: Write failing mobile-card tests**

At 320 × 844, assert no document overflow, no member/onboarding data table, all representative long values remain contained, and all member actions work from cards. At desktop width, assert the detailed tables remain visible and the mobile cards are hidden.

- [ ] **Step 2: Run focused tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/AdminTest.php --filter='member catalog|tutorial' --display-warnings`

Expected: FAIL because the current fixed-width tables overflow or lack mobile card variants.

- [ ] **Step 3: Extract mobile cards and responsive variants**

Render card lists below the desktop breakpoint and tables at/above it from the existing props. Reuse `ManageMemberRolesDialog`, conversation actions, `DeleteMemberDialog`, existing translations, and existing data-test hooks where they describe the same action.

- [ ] **Step 4: Adopt `AdminPageHeader` and normalize page spacing**

Update both pages to the shared header/container rhythm and ensure the fixed bottom navigation never covers the final control.

- [ ] **Step 5: Run focused tests and verify GREEN**

Run the command from Step 2.

Expected: PASS at both viewport sizes.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/admin/AdminMemberCard.vue resources/js/components/admin/OnboardingProgressCard.vue resources/js/pages/Admin/Members/Index.vue resources/js/pages/Admin/Onboarding/Index.vue tests/Browser/AdminTest.php
git commit -m "feat: make admin member workflows mobile-first"
```

### Task 5: Responsive partner statistics and moderation surfaces

**Files:**
- Modify: `resources/js/components/partners/AnnouncementStatisticsTable.vue`
- Modify: `resources/js/components/partners/PartnerModerationCard.vue`
- Modify: `resources/js/pages/Admin/Partners/Profiles.vue`
- Modify: `resources/js/pages/Admin/Partners/Announcements.vue`
- Modify: `resources/js/pages/Admin/Partners/Statistics.vue`
- Modify: `tests/Browser/AdminTest.php`

**Interfaces:**
- Produces: `AnnouncementStatisticsTable` with a mobile card rendering and the current desktop table, sharing the same typed statistics input.
- Preserves: all moderation, ordering, retry, approval, rejection, cancellation, and unpublish actions.

- [ ] **Step 1: Write failing partner-surface responsive tests**

Assert 320 px card statistics without overflow, desktop table visibility, long partner/announcement content wrapping, and successful representative moderation/retry actions in each viewport.

- [ ] **Step 2: Run focused tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/AdminTest.php --filter='partner' --display-warnings`

Expected: FAIL for the new responsive structure assertions.

- [ ] **Step 3: Add responsive statistics variants**

Render semantic cards on mobile and retain the detailed table on desktop. Keep only aggregate and operational values already supplied; add no recipient identity.

- [ ] **Step 4: Normalize moderation layouts**

Use `AdminPageHeader`, wrap long text safely, stack decisions and reason fields on mobile, keep 44 px actions, and ensure explicit ordering controls remain usable by touch and keyboard.

- [ ] **Step 5: Run focused tests and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/partners/AnnouncementStatisticsTable.vue resources/js/components/partners/PartnerModerationCard.vue resources/js/pages/Admin/Partners tests/Browser/AdminTest.php
git commit -m "feat: adapt partner administration for mobile"
```

### Task 6: Normalize the remaining administration screens

**Files:**
- Modify: `resources/js/pages/Dashboard.vue`
- Modify: `resources/js/pages/Admin/Interests/Index.vue`
- Modify: `resources/js/pages/Admin/Avatars/Index.vue`
- Modify: `resources/js/pages/Admin/SeasonalThemes/Index.vue`
- Modify: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: `AdminPageHeader` and the shell spacing contract from Task 2.
- Preserves: existing create, edit, archive, activate, order, schedule, and settings actions.

- [ ] **Step 1: Write failing 320 px regression tests**

For each page, assert `document.documentElement.scrollWidth <= document.documentElement.clientWidth`, final controls sit above the bottom navigation, long French and English labels wrap, and representative existing actions remain reachable.

- [ ] **Step 2: Run focused tests and verify RED**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/AdminTest.php --filter='dashboard|interest|avatar|seasonal' --display-warnings`

Expected: FAIL for the new layout contract assertions.

- [ ] **Step 3: Apply the shared page header and mobile-first layout rules**

Stack forms and actions by default, introduce wider grids only at existing breakpoints, add `min-w-0` and safe wrapping where content can expand, and preserve explicit reorder controls.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run the command from Step 2.

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/Dashboard.vue resources/js/pages/Admin/Interests/Index.vue resources/js/pages/Admin/Avatars/Index.vue resources/js/pages/Admin/SeasonalThemes/Index.vue tests/Browser/AdminTest.php
git commit -m "feat: polish admin screens for small viewports"
```

### Task 7: Documentation and full verification

**Files:**
- Modify: `docs/design-system.md`
- Modify: `docs/PRD.md` only if its implementation matrix describes the old admin shell
- Modify: any generated Wayfinder file only through the documented generator

**Interfaces:**
- Consumes: completed behavior from Tasks 1–6.
- Produces: documented admin navigation and a fully verified branch.

- [ ] **Step 1: Document the delivered admin shell**

Describe the five-action maximum, grouped sheet behavior, role-based workspace switching, card/table responsive rule, and the fact that notifications remain in the member workspace.

- [ ] **Step 2: Generate Wayfinder and run frontend checks**

Run:

```bash
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

Expected: all commands exit 0.

- [ ] **Step 3: Run backend checks and the full affected browser suite**

Run:

```bash
composer lint:check
composer analyse
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' ./vendor/bin/pest tests/Browser/AdminTest.php tests/Browser/ProfileAndNavigationTest.php --display-warnings
```

Expected: all commands exit 0 with no failed tests.

- [ ] **Step 4: Inspect the branch diff and formatting**

Run: `git diff --check && git status --short && git diff --stat main...HEAD`

Expected: no whitespace errors; only planned files and pre-existing unrelated untracked files are present.

- [ ] **Step 5: Commit documentation and generated changes**

```bash
git add docs/design-system.md docs/PRD.md resources/js/routes resources/js/actions
git commit -m "docs: document mobile-first administration"
```

