# Notification, Mobile and PWA Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver issues #222, #223 and #224 by synchronizing conversation notification state, eliminating mobile document overflow, and adapting brand assets to visual themes.

**Architecture:** Extend the existing conversation read action and notification controllers so Laravel remains authoritative, then add a focused swipe state helper and progressively enhanced notification row. Fix overflow at the owning components. Centralize runtime logo selection in the existing logo component while keeping installable PNG icons fixed and dark-palette compatible.

**Tech Stack:** PHP 8.4, Laravel 13, Pest, Vue 3 Composition API, TypeScript, Inertia 3, Tailwind CSS, Pest Browser, GD.

**Spec:** `docs/superpowers/specs/2026-09-29-notification-mobile-pwa-polish-design.md`

## Global Constraints

- Laravel is authoritative for notification read and delete state.
- All visible and accessible copy must exist in the French and English catalogs.
- No global `overflow-x: hidden` may conceal an incorrectly sized child.
- Minimum supported viewport is 320 px in light, dark, Halloween and Christmas combinations.
- Swipe actions must retain visible keyboard and assistive-technology alternatives.
- PWA icons remain one fixed set and use the dark DLP Friends palette rather than pure black.
- No notification action may delete a conversation, message, match, event or registration.

## Review Focus

- A legacy conversation notification without `target_id` must remain individually usable and must not be group-marked by accident.
- A realtime notification for another conversation must increment the unread count and remain unread.
- A vertical scroll beginning on a notification row must not reveal actions or prevent page scrolling.
- Extremely long localized titles and member names must not enlarge the document at 320 px.
- OS masking of maskable icons must retain the complete brand mark inside the central safe zone.

---

### Task 1: Synchronize conversation notification reads

**Files:**
- Modify: `app/Actions/MarkConversationRead.php`
- Modify: `resources/js/composables/useMemberRealtimeNotifications.ts`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `tests/Feature/NotificationCenterTest.php`
- Modify: `tests/Browser/ConversationTest.php`
- Modify: `tests/Frontend/memberNotifications.test.js`

**Interfaces:**
- Consumes: persisted notification data with `category`, `target_type` and `target_id`; existing `MarkConversationRead::handle(User, Conversation): void`.
- Produces: the same action also marks matching conversation notifications read; realtime context exposes the last persistent notification so the open conversation can acknowledge it through the existing conversation read endpoint.

- [ ] **Step 1: Write failing feature tests for grouped read state**

Add tests named `opening_a_conversation_marks_only_its_conversation_notifications_as_read` and `conversation_read_is_idempotent_and_ignores_legacy_notifications_without_a_target` asserting that message/match notifications for the selected conversation change, while another conversation, event notification and malformed legacy record do not.

- [ ] **Step 2: Run the feature tests and verify RED**

Run: `php artisan test tests/Feature/NotificationCenterTest.php --filter='conversation'`

Expected: FAIL because `MarkConversationRead` currently updates messages only.

- [ ] **Step 3: Extend `MarkConversationRead::handle(User $reader, Conversation $conversation): void`**

Inside its existing transaction, update unread notifications owned by `$reader` whose JSON data has `category = conversations`, `target_type = conversation` and `target_id = $conversation->id`. Do not return before this update when no unread message exists; dispatch `MessagesRead` only when messages changed.

- [ ] **Step 4: Run the feature tests and verify GREEN**

Run: `php artisan test tests/Feature/NotificationCenterTest.php --filter='conversation'`

Expected: PASS.

- [ ] **Step 5: Write failing frontend/browser tests for realtime acknowledgement**

Test that a persistent notification containing `target_type: conversation` and the currently open `target_id` triggers the conversation read endpoint and refreshes only shared auth state; assert that a different conversation still increments the counter. In the browser test, inject a notification while the conversation is open and assert the database notification becomes read without navigation.

- [ ] **Step 6: Run the realtime tests and verify RED**

Run: `bun test tests/Frontend/memberNotifications.test.js && php artisan test tests/Browser/ConversationTest.php --filter='notification'`

Expected: FAIL because the realtime context does not expose persistent notifications to the open conversation.

- [ ] **Step 7: Implement realtime acknowledgement**

Add `latestPersistentNotification: ShallowRef<PersistentMemberNotification | null>` to `MemberRealtimeContext`. In `Conversations/Show.vue`, watch it and call `markConversationAsRead()` only when its conversation target matches `props.conversation.id`; after success reload only `auth` so the navigation badge and PWA badge reconcile.

- [ ] **Step 8: Run targeted tests and commit**

Run: `bun test tests/Frontend/memberNotifications.test.js && php artisan test tests/Feature/NotificationCenterTest.php tests/Browser/ConversationTest.php`

Expected: PASS.

Commit: `feat: synchronize conversation notification reads`

### Task 2: Add accessible notification read and delete actions

**Files:**
- Create: `app/Http/Controllers/NotificationMarkReadController.php`
- Create: `app/Http/Controllers/NotificationDestroyController.php`
- Create: `resources/js/lib/notificationSwipe.ts`
- Create: `tests/Frontend/notificationSwipe.test.js`
- Modify: `routes/web.php`
- Modify: `app/Support/MemberNotificationPresenter.php`
- Modify: `resources/js/components/notifications/NotificationItem.vue`
- Modify: `resources/js/types/notification.ts`
- Modify: `lang/fr/notifications.php`
- Modify: `lang/en/notifications.php`
- Modify: `tests/Feature/NotificationCenterTest.php`
- Modify: `tests/Browser/NotificationCenterTest.php`
- Modify: `tests/Feature/Localization/InertiaTranslationsTest.php`

**Interfaces:**
- Consumes: notification UUID scoped through `$request->user()->notifications()` and existing partner engagement recorder.
- Produces: named PATCH route `notifications.mark-read`, DELETE route `notifications.destroy`, presenter fields `read_url` and `delete_url`, plus pure `resolveNotificationSwipe(start, current, axis, width)` state helpers.

- [ ] **Step 1: Write failing ownership and resource-safety feature tests**

Assert that the owner can mark without navigation and delete a notification; another member receives 404; deletion leaves target conversation, messages and match intact; partner engagement read/dismiss records remain correct.

- [ ] **Step 2: Run feature tests and verify RED**

Run: `php artisan test tests/Feature/NotificationCenterTest.php tests/Feature/Partner/PartnerAnnouncementEngagementTest.php`

Expected: FAIL because the routes and controller responses do not exist.

- [ ] **Step 3: Implement scoped mutation controllers and presenter URLs**

Both controllers resolve through the authenticated member relation. `NotificationMarkReadController` records partner read engagement then marks read and returns back with an Inertia partial-friendly response. `NotificationDestroyController` records partner dismissal when applicable, deletes only the database notification and returns back. Add route helper output to the presenter and TypeScript contract.

- [ ] **Step 4: Run feature tests and verify GREEN**

Run: `php artisan test tests/Feature/NotificationCenterTest.php tests/Feature/Partner/PartnerAnnouncementEngagementTest.php`

Expected: PASS.

- [ ] **Step 5: Write failing pure gesture tests**

Cover horizontal reveal beyond threshold, sub-threshold snapback, vertical-axis cancellation, bounded translation, close-on-Escape and reduced-motion transition selection. Name the production behavior each assertion protects.

- [ ] **Step 6: Run gesture tests and verify RED**

Run: `bun test tests/Frontend/notificationSwipe.test.js`

Expected: FAIL because `notificationSwipe.ts` does not exist.

- [ ] **Step 7: Implement the pure swipe helper and progressive row UI**

Use Pointer Events with pointer capture only after horizontal intent is established. Keep the action rail inside an `overflow-hidden` row, cap translation to measured action width, allow only one open row through an emitted/open-id contract, and expose always-focusable read/delete buttons. Preserve the row’s existing open-target behavior and partner confirmation semantics.

- [ ] **Step 8: Add localized labels and browser coverage**

Add FR/EN strings for mark read, delete, confirmations and failure feedback. Browser tests exercise touch-equivalent drag, vertical scroll, keyboard focus, Escape, mutation results and network-failure snapback.

- [ ] **Step 9: Run targeted tests and commit**

Run: `bun test tests/Frontend/notificationSwipe.test.js && php artisan test tests/Feature/NotificationCenterTest.php tests/Feature/Partner/PartnerAnnouncementEngagementTest.php tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/NotificationCenterTest.php`

Expected: PASS.

Commit: `feat: add notification swipe actions`

### Task 3: Remove mobile document overflow at its sources

**Files:**
- Modify: `resources/js/layouts/MemberLayout.vue`
- Modify: `resources/js/pages/Conversations/Index.vue`
- Modify: `resources/js/pages/Notifications/Index.vue`
- Modify: `resources/js/components/notifications/NotificationItem.vue`
- Modify: `resources/js/components/events/EventWorkspace.vue`
- Modify: `resources/js/components/events/EventParticipantList.vue`
- Modify: `resources/js/components/events/EventParticipantProfile.vue`
- Modify: `tests/Browser/ConversationTest.php`
- Modify: `tests/Browser/NotificationCenterTest.php`
- Modify: `tests/Browser/EventTest.php`

**Interfaces:**
- Consumes: existing member shell, list and adaptive event components.
- Produces: each target surface maintains `document.documentElement.scrollWidth <= window.innerWidth` at 320 px while local intentional horizontal regions remain usable.

- [ ] **Step 1: Add failing browser assertions with adversarial long content**

For conversations, notifications, event lists and participants, create long unbroken localized/member content, resize to `320x700`, and assert document width does not exceed viewport width. Also assert the page can scroll vertically and interactive rows still open.

- [ ] **Step 2: Run browser tests and verify RED**

Run: `php artisan test tests/Browser/ConversationTest.php tests/Browser/NotificationCenterTest.php tests/Browser/EventTest.php --filter='viewport|overflow'`

Expected: at least one width assertion FAILS and identifies the owning surface.

- [ ] **Step 3: Fix only the overflowing component boundaries**

Apply `min-w-0`, `max-w-full`, wrapping/truncation with accessible full labels, and bounded positioning where computed-width evidence points. Do not add global horizontal clipping. Ensure the notification action rail is absolutely contained and does not participate in document width.

- [ ] **Step 4: Run browser tests in standard and themed states**

Run: `php artisan test tests/Browser/ConversationTest.php tests/Browser/NotificationCenterTest.php tests/Browser/EventTest.php --filter='viewport|overflow'`

Expected: PASS at 320 px in the exercised locale/theme matrix, with vertical interaction assertions passing.

- [ ] **Step 5: Commit**

Commit: `fix: contain mobile list overflow`

### Task 4: Adapt the runtime logo to themes

**Files:**
- Create: `public/brand/dlp-friends-logo-halloween.svg`
- Modify: `resources/js/components/AppLogoIcon.vue`
- Modify: `tests/Feature/BrandAssetsTest.php`
- Create: `tests/Frontend/appLogoIcon.test.js`
- Modify: `tests/Browser/AppearanceTest.php`

**Interfaces:**
- Consumes: `.dark`, `.seasonal-halloween` and `.seasonal-christmas` classes on `<html>`.
- Produces: `AppLogoIcon` keeps its public props and geometry while CSS variants select standard light, standard dark or Halloween artwork immediately.

- [ ] **Step 1: Write failing asset and selection tests**

Feature tests assert identical SVG viewBox/path geometry and the approved Halloween gradient colors. Frontend tests assert the class-based background mapping, stable sizing hooks and accessible-name behavior. Browser coverage switches appearance/seasonal classes and checks the computed background URL without page reload.

- [ ] **Step 2: Run tests and verify RED**

Run: `php artisan test tests/Feature/BrandAssetsTest.php && bun test tests/Frontend/appLogoIcon.test.js && php artisan test tests/Browser/AppearanceTest.php --filter='logo'`

Expected: FAIL because the Halloween asset and mapping are absent.

- [ ] **Step 3: Add the SVG variant and extend `AppLogoIcon`**

Reuse the exact brand path geometry. Define theme selectors in the component’s Tailwind classes/CSS so Halloween wins over light/dark standard variants, while Christmas deliberately reuses the contrast-valid standard variants. Keep one DOM box and the current accessible semantics.

- [ ] **Step 4: Run targeted tests and commit**

Run: `php artisan test tests/Feature/BrandAssetsTest.php && bun test tests/Frontend/appLogoIcon.test.js && php artisan test tests/Browser/AppearanceTest.php --filter='logo'`

Expected: PASS.

Commit: `feat: adapt brand logo to seasonal themes`

### Task 5: Regenerate and validate PWA icons

**Files:**
- Modify: `public/pwa/icon-192.png`
- Modify: `public/pwa/icon-512.png`
- Modify: `public/pwa/icon-maskable-192.png`
- Modify: `public/pwa/icon-maskable-512.png`
- Modify: `public/apple-touch-icon.png`
- Modify: `tests/Feature/BrandAssetsTest.php`
- Modify: `tests/Feature/PwaManifestTest.php`

**Interfaces:**
- Consumes: existing manifest paths and dark palette background `hsl(258 30% 8%)` (`#120e1b`).
- Produces: valid 192, 512, maskable and Apple PNGs with opaque `#120e1b` background where applicable and the maskable mark inside the central safe zone.

- [ ] **Step 1: Write failing pixel and safe-zone tests**

Assert exact PNG dimensions/types, non-black opaque background pixels matching `#120e1b`, visible mark contrast, transparent/rounded Apple corners as currently required, and no non-background maskable pixels outside the central 80% safe region. Assert manifest entries still map exactly to the files and purposes.

- [ ] **Step 2: Run asset tests and verify RED**

Run: `php artisan test tests/Feature/BrandAssetsTest.php tests/Feature/PwaManifestTest.php`

Expected: FAIL on the current black/background or safe-zone expectations.

- [ ] **Step 3: Regenerate the five PNG assets from the canonical SVG geometry**

Use the repository’s available image tooling or a temporary deterministic script under `/tmp`; do not add a runtime dependency. Preserve expected dimensions and alpha behavior, use `#120e1b`, and center maskable artwork within the asserted safe region.

- [ ] **Step 4: Run asset tests and inspect rendered icons**

Run: `php artisan test tests/Feature/BrandAssetsTest.php tests/Feature/PwaManifestTest.php`

Expected: PASS. Render a contact sheet of all sizes and visually verify mark integrity, contrast and maskable margins.

- [ ] **Step 5: Commit**

Commit: `fix: align PWA icons with dark brand palette`

### Task 6: Full verification and delivery

**Files:**
- Modify only if a verification failure directly belongs to issues #222, #223 or #224.

**Interfaces:**
- Consumes: all five completed tasks.
- Produces: a verified branch ready for review with no unrelated files staged.

- [ ] **Step 1: Generate Wayfinder and run format/lint/type checks**

Run: `php artisan wayfinder:generate --with-form && composer lint:check && composer analyse && bun run lint:check && bun run format:check && bun run types:check`

Expected: all commands exit 0.

- [ ] **Step 2: Run full automated suites**

Run: `composer test && bun test`

Expected: all tests pass with no failures or warnings attributable to the branch.

- [ ] **Step 3: Build production frontend**

Run: `bun run build`

Expected: Vite exits 0 and produces the service worker/assets without missing manifest entries.

- [ ] **Step 4: Review requirements and diff**

Run: `git diff main...HEAD --check && git status --short && git diff --stat main...HEAD`

Expected: no whitespace errors; only scoped source, tests, docs and generated Wayfinder/assets are included. Confirm each acceptance criterion in the spec maps to a passing test or visual inspection.

- [ ] **Step 5: Commit any generated route artifacts and final scoped corrections**

Commit: `chore: finalize notification mobile and PWA polish`
