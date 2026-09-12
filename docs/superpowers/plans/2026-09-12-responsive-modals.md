# Responsive Modals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every application modal render as a shadcn-vue `Drawer` below 640 px and a shadcn-vue `Dialog` from 640 px upward, using the official responsive-modal recipe.

**Architecture:** A focused `useResponsiveModal()` composable will reproduce shadcn-vue's official computed component map verbatim: `Root`, `Trigger`, `Content`, `Header`, `Title`, `Description`, `Footer`, and `Close`. Existing feature components keep ownership of state, forms, translations, and business behavior; only their presentation primitives become dynamic components. Navigation `Sheet` components remain unchanged.

**Tech Stack:** Vue 3 Composition API, TypeScript, VueUse `useMediaQuery`, shadcn-vue wrappers backed by Reka UI, Tailwind CSS, Pest Browser, Playwright.

**Spec:** `docs/superpowers/specs/2026-09-12-responsive-modals-design.md`

## Global Constraints

- Reproduce the official shadcn-vue responsive-modal breakpoint exactly: `useMediaQuery('(min-width: 640px)')`.
- Use the existing `@/components/ui/dialog` and `@/components/ui/drawer` wrappers; add no dependency and no new low-level primitive.
- Preserve all existing server authorization, Inertia form behavior, translations, focus handling, dismissal rules, scroll restoration, and test selectors.
- On mobile, use the official example's content spacing pattern: `px-2 pb-8 *:px-4`; on desktop, retain each dialog's existing maximum width and layout.
- Do not migrate navigation/sidebar `Sheet` components.
- Follow red-green-refactor: each behavior group must fail for the expected missing drawer before production code changes.

---

### Task 1: Lock the responsive contract with browser tests

**Files:**
- Modify: `tests/Browser/EventTest.php`
- Modify: `tests/Browser/DiscoveryTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Modify: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: Existing `data-test` triggers and shadcn wrapper `data-slot` attributes.
- Produces: Browser assertions requiring `drawer-content` at 390×844 and `dialog-content` at 1280×900 for every modal family.

- [ ] **Step 1: Extend the event coverage**

In the existing event drawer test, open an event confirmation from inside the mobile event drawer and assert:

```php
->assertAttribute('[data-test="event-confirm-dialog"]', 'data-slot', 'drawer-content')
```

Resize to desktop, reopen the same confirmation, and assert `data-slot="dialog-content"`. Keep the existing event-panel scroll and back-navigation assertions intact.

- [ ] **Step 2: Extend match coverage**

In `a reciprocal like opens a dismissible match dialog only once`, set the viewport to `390, 844` before triggering the reciprocal like and replace the initial content assertion with:

```php
->assertPresent('[data-slot="drawer-content"]')
->assertPresent('[data-test="match-celebration-layer"]')
```

Add a desktop opening in the existing admin-created match scenario and assert `[data-slot="dialog-content"]`.

- [ ] **Step 3: Extend member and account coverage**

In the member profile blocking journey, open the confirmation at `390×844` and assert `[data-slot="drawer-content"]`. In the existing account deletion, passkey deletion, and two-factor setup journeys, add the same mobile assertion while retaining their form submission, validation, and focus assertions.

- [ ] **Step 4: Extend admin confirmation coverage**

At mobile width, assert that member deletion, avatar deletion, and both interest archive/delete confirmations use `[data-slot="drawer-content"]`; retain the database assertions proving each action still succeeds.

- [ ] **Step 5: Run the focused tests and observe RED**

Run:

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/EventTest.php tests/Browser/DiscoveryTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/AdminTest.php --filter='drawer|dialog|confirmation|blocking|passkey|two-factor|delet'
```

Expected: failures show the non-event mobile surfaces still expose `data-slot="dialog-content"` rather than `drawer-content`; no failure should be caused by missing fixtures or navigation.

- [ ] **Step 6: Commit the red contract**

```bash
git add tests/Browser/EventTest.php tests/Browser/DiscoveryTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/AdminTest.php
git commit -m "test: require responsive application modals"
```

### Task 2: Add the exact shadcn-vue responsive component map

**Files:**
- Create: `resources/js/composables/useResponsiveModal.ts`
- Modify: `resources/js/components/events/AdaptiveEventPanel.vue`
- Modify: `resources/js/components/events/EventConfirmationDialog.vue`
- Test: `tests/Browser/EventTest.php`

**Interfaces:**
- Produces: `useResponsiveModal(): { isDesktop: Ref<boolean>; Modal: ComputedRef<{ Root; Trigger; Content; Header; Title; Description; Footer; Close }> }`.
- Consumes: Dialog and Drawer exports from the existing UI wrapper indexes.

- [ ] **Step 1: Implement the official component map**

Create the composable with the official recipe's names and breakpoint:

```ts
import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';

export function useResponsiveModal() {
    const isDesktop = useMediaQuery('(min-width: 640px)');
    const Modal = computed(() => ({
        Root: isDesktop.value ? Dialog : Drawer,
        Trigger: isDesktop.value ? DialogTrigger : DrawerTrigger,
        Content: isDesktop.value ? DialogContent : DrawerContent,
        Header: isDesktop.value ? DialogHeader : DrawerHeader,
        Title: isDesktop.value ? DialogTitle : DrawerTitle,
        Description: isDesktop.value ? DialogDescription : DrawerDescription,
        Footer: isDesktop.value ? DialogFooter : DrawerFooter,
        Close: isDesktop.value ? DialogClose : DrawerClose,
    }));

    return { isDesktop, Modal };
}
```

- [ ] **Step 2: Refactor the event panel to dynamic components**

Replace the duplicated `v-if` Drawer/Dialog trees with one tree using `<component :is="Modal.Root">`. Preserve `updateOpen`, `onOpenAutoFocus`, `event-panel`, maximum desktop dimensions, drawer back button, and body scrolling. Bind desktop-only close-button props conditionally so the mobile drawer has no close cross.

- [ ] **Step 3: Refactor event confirmations**

Replace all eight static Dialog tags in `EventConfirmationDialog.vue` with `Modal.*` dynamic components. Apply `:class="[{ 'px-2 pb-8 *:px-4': !isDesktop }]"` to content and preserve `event-confirm-*` selectors and close semantics.

- [ ] **Step 4: Run event tests and frontend checks**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/EventTest.php
bun run types:check
```

Expected: both commands pass; mobile event details and nested confirmation are drawers, desktop details and confirmation are dialogs.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useResponsiveModal.ts resources/js/components/events/AdaptiveEventPanel.vue resources/js/components/events/EventConfirmationDialog.vue tests/Browser/EventTest.php
git commit -m "refactor: adopt responsive event modals"
```

### Task 3: Migrate member, account, and security modals

**Files:**
- Modify: `resources/js/components/members/BlockMemberDialog.vue`
- Modify: `resources/js/components/DeleteUser.vue`
- Modify: `resources/js/components/PasskeyItem.vue`
- Modify: `resources/js/components/TwoFactorSetupModal.vue`
- Test: `tests/Browser/ProfileAndNavigationTest.php`

**Interfaces:**
- Consumes: `useResponsiveModal()` from Task 2.
- Produces: Responsive block, account deletion, passkey deletion, and two-factor surfaces with unchanged public props/models/events.

- [ ] **Step 1: Convert block-member confirmation**

Import `useResponsiveModal`, destructure `{ isDesktop, Modal }`, and replace Dialog tags with `<component :is="Modal.*">`. Preserve `v-model:open`, the translated member name, cancel close, destructive submit, and loading state.

- [ ] **Step 2: Convert account and passkey deletion**

Apply the same map to `DeleteUser.vue` and `PasskeyItem.vue`. Keep Inertia `Form` slots, password field focus/error behavior, reset callbacks, passkey identifier, and disabled buttons unchanged.

- [ ] **Step 3: Convert two-factor setup**

Render `TwoFactorSetupModal.vue` through `Modal.Root`, `Modal.Content`, `Modal.Header`, `Modal.Title`, and `Modal.Description`. Preserve QR loading, clipboard, verification step, OTP focus, error output, and `isOpen` model. Give the drawer a vertically scrollable body and safe bottom padding so the QR/OTP/actions remain reachable at 390×844.

- [ ] **Step 4: Run the member/account journeys**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/ProfileAndNavigationTest.php
bun run types:check
```

Expected: all tests pass, including mobile drawer assertions and the original destructive-action/2FA behaviors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/members/BlockMemberDialog.vue resources/js/components/DeleteUser.vue resources/js/components/PasskeyItem.vue resources/js/components/TwoFactorSetupModal.vue tests/Browser/ProfileAndNavigationTest.php
git commit -m "refactor: make account modals responsive"
```

### Task 4: Migrate discovery and administration modals

**Files:**
- Modify: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/js/components/admin/DeleteMemberDialog.vue`
- Modify: `resources/js/pages/Admin/Avatars/Index.vue`
- Modify: `resources/js/pages/Admin/Interests/Index.vue`
- Test: `tests/Browser/DiscoveryTest.php`
- Test: `tests/Browser/AdminTest.php`

**Interfaces:**
- Consumes: `useResponsiveModal()` from Task 2.
- Produces: Responsive match celebration and all administration confirmations; no route, request, or emitted event changes.

- [ ] **Step 1: Convert the match celebration**

Use `Modal.Root/Content/Header/Title/Description/Footer` while keeping controlled `open`, one-time dismissal, conversation navigation, avatar, animation layer, `z-index`, and `data-test` hooks. Ensure the drawer's celebration layer still spans the viewport and its actions remain visible.

- [ ] **Step 2: Convert member deletion**

Use the component map in `DeleteMemberDialog.vue`; preserve its model, trigger slot, translations, cancel action, and delete request.

- [ ] **Step 3: Convert avatar confirmations**

Replace the static dialog imports/tags in `Admin/Avatars/Index.vue` with the shared map. Keep each row's form processing/error state scoped to its own confirmation.

- [ ] **Step 4: Convert interest confirmations**

Replace both archive/reactivate and delete static Dialog trees in `Admin/Interests/Index.vue` with `Modal.*`. Preserve generated Wayfinder actions, ordering controls, processing state, and validation messages.

- [ ] **Step 5: Run discovery and admin journeys**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/DiscoveryTest.php tests/Browser/AdminTest.php
bun run types:check
```

Expected: both browser suites and TypeScript pass; each mobile confirmation is a drawer and each desktop confirmation remains a dialog.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/discovery/MatchDialog.vue resources/js/components/admin/DeleteMemberDialog.vue resources/js/pages/Admin/Avatars/Index.vue resources/js/pages/Admin/Interests/Index.vue tests/Browser/DiscoveryTest.php tests/Browser/AdminTest.php
git commit -m "refactor: make discovery and admin modals responsive"
```

### Task 5: Verify completeness, quality, and Docker runtime

**Files:**
- Modify if needed: `docs/design-system.md`
- Verify: all files changed by Tasks 1–4

**Interfaces:**
- Consumes: The completed responsive modal migration.
- Produces: Documented convention and fresh release-quality evidence.

- [ ] **Step 1: Prove no feature-level static Dialog remains**

Run:

```bash
rg -n "from '@/components/ui/dialog'|<Dialog" resources/js --glob '!components/ui/**' --glob '!components/ui/sheet/**'
```

Expected: only `useResponsiveModal.ts` imports the Dialog family; feature components contain no static `<Dialog...>` tags. Review every result rather than deleting legitimate wrapper code.

- [ ] **Step 2: Document the convention**

Add a concise responsive-overlay section to `docs/design-system.md`: feature modals consume `useResponsiveModal`, use Drawer below 640 px and Dialog at/above 640 px, and navigation continues to use Sheet.

- [ ] **Step 3: Run frontend quality checks**

```bash
bun run lint:check
bun run format:check
bun run types:check
bun run build
```

Expected: all commands exit successfully with no warnings introduced by dynamic component bindings.

- [ ] **Step 4: Run complete backend and browser verification**

```bash
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' composer test
```

Expected: the complete PHP, frontend, and Pest Browser suite passes with only the repository's documented skips.

- [ ] **Step 5: Build and smoke-test Docker**

```bash
docker compose up --build -d
docker compose ps
```

Expected: the production image builds and all required services report healthy/running; migrations remain explicit and are not added to entrypoints.

- [ ] **Step 6: Commit documentation or final fixes**

```bash
git add docs/design-system.md
git commit -m "docs: document responsive modal convention"
```

- [ ] **Step 7: Review the final diff**

```bash
git diff main...HEAD --check
git status --short
```

Expected: no whitespace errors and no uncommitted files. Confirm the diff contains no unrelated Sheet migration or business-rule change.
