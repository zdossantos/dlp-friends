# GA4 Measurement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restore deterministic GA4 page views, remove member data from reports, and distinguish installed PWA usage from browser usage without changing visible tab titles.

**Architecture:** A pure route taxonomy produces sanitized page metadata and a pure display-mode detector produces `app_mode`. The existing consent-controlled loader configures every surface with automatic page views disabled; the Inertia tracker and the public Blade entry point each emit one explicit page view through the same payload rules.

**Tech Stack:** TypeScript, Bun test, Vue/Inertia 3, Laravel Blade, GA4 `gtag`.

**Spec:** `docs/superpowers/specs/2026-09-30-analytics-seo-foundations-design.md`

## Global Constraints

- Never send a display name, e-mail, UUID, numeric identifier, message, biography, query, or fragment to GA4.
- Keep every visible `<Head>` title and `document.title` unchanged.
- Do not load Google scripts or emit events before explicit consent.
- `app_mode` is exactly `pwa` or `browser`; `page_type` is stable and untranslated.
- Do not add a dependency.

## Review Focus

- A route with a numeric or UUID identifier must resolve to a generic title and normalized path; Task 1 tests both.
- An unknown route must never fall back to `document.title`; Task 1 tests the `application_page` fallback.
- iOS standalone mode lacks the standard media-query signal; Task 2 tests `navigator.standalone` independently.
- Consent may become available after Inertia has initialized; Task 3 tests the delayed ready event and duplicate protection.
- Public Blade pages must not double-count via automatic and explicit views; Task 4 tests `send_page_view: false` and one emitted payload.

---

### Task 1: Sanitized page taxonomy

**Files:**
- Create: `resources/js/lib/analyticsPage.ts`
- Create: `tests/Frontend/analyticsPage.test.js`
- Modify: `lang/fr/analytics.php`
- Modify: `lang/en/analytics.php`

**Interfaces:**
- Produces: `type AnalyticsPage = { pageType: string; pageTitle: string; pagePath: string }`.
- Produces: `resolveAnalyticsPage(path: string, locale: string): AnalyticsPage`.
- Consumes later: Tasks 3 and 4 use this resolver for every `page_view`.

- [ ] **Step 1: Write the failing taxonomy tests**

Test `conversation_list`, `conversation_detail`, `member_profile`, `own_profile`, `event_participant`, and `event_detail` in French and English. Assert numeric/UUID segments, query strings, fragments, and display-name-like input never occur in the result; assert an unknown path returns `application_page`, `DLP Friends`, and a normalized path.

- [ ] **Step 2: Run the tests and verify the red state**

Run: `bun test tests/Frontend/analyticsPage.test.js`

Expected: FAIL because `analyticsPage` does not exist.

- [ ] **Step 3: Implement the resolver and localized title catalogues**

Use a route-pattern allowlist ordered from specific to generic. Reuse `normalizeAnalyticsPath` or move it into this focused module without changing its public name. No branch may read `document.title`.

- [ ] **Step 4: Run the taxonomy tests**

Run: `bun test tests/Frontend/analyticsPage.test.js tests/Frontend/analytics.test.js`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/analyticsPage.ts tests/Frontend/analyticsPage.test.js lang/fr/analytics.php lang/en/analytics.php resources/js/lib/analytics.ts tests/Frontend/analytics.test.js
git commit -m "feat(analytics): sanitize page taxonomy"
```

### Task 2: PWA mode detection

**Files:**
- Create: `resources/js/lib/appMode.ts`
- Create: `tests/Frontend/appMode.test.js`

**Interfaces:**
- Produces: `type AppMode = 'pwa' | 'browser'`.
- Produces: `resolveAppMode(runtime?: { standaloneMedia: boolean; iosStandalone?: boolean }): AppMode`.
- Consumes later: Tasks 3 and 4 attach its value as `app_mode`.

- [ ] **Step 1: Write the failing detector tests**

Assert `pwa` for `(display-mode: standalone)` and for iOS `navigator.standalone === true`; assert `browser` when both signals are false or absent.

- [ ] **Step 2: Run the test and verify it fails**

Run: `bun test tests/Frontend/appMode.test.js`

Expected: FAIL because `appMode` does not exist.

- [ ] **Step 3: Implement `resolveAppMode`**

The default runtime reads `window.matchMedia('(display-mode: standalone)').matches` and the optional iOS property without parsing the user agent.

- [ ] **Step 4: Run the detector test**

Run: `bun test tests/Frontend/appMode.test.js`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/appMode.ts tests/Frontend/appMode.test.js
git commit -m "feat(analytics): identify PWA traffic"
```

### Task 3: Explicit Inertia page views

**Files:**
- Modify: `resources/js/lib/analytics.ts`
- Modify: `resources/js/lib/analyticsConsent.ts`
- Modify: `tests/Frontend/analytics.test.js`
- Modify: `tests/Frontend/analyticsConsent.test.js`

**Interfaces:**
- Consumes: `resolveAnalyticsPage(path, locale)` and `resolveAppMode()`.
- Produces: one explicit GA4 `page_view` payload containing `page_type`, `page_title`, `page_path`, `page_location`, `app_mode`, and an optional normalized `page_referrer`.

- [ ] **Step 1: Extend the failing analytics tests**

Assert the exact generic payload for `/conversations/42?from=Alice`, a customized document title that never reaches the payload, one initial view, one view per successful navigation, normalized referrers, both app modes, and no subscription/event when consent never activates. Assert all GA configurations include `{ send_page_view: false }`, not only SPA configuration.

- [ ] **Step 2: Run the tests and verify the intended failures**

Run: `bun test tests/Frontend/analytics.test.js tests/Frontend/analyticsConsent.test.js`

Expected: FAIL on missing taxonomy fields/app mode and Blade auto-page-view configuration.

- [ ] **Step 3: Update the tracker and GA activation**

Extend `AnalyticsRuntime` with locale and app-mode inputs, resolve metadata for the initial URL and each navigation, and guard `startTracking` so repeated ready signals cannot subscribe or emit twice. Always configure `send_page_view: false`.

- [ ] **Step 4: Run all analytics frontend tests**

Run: `bun test tests/Frontend/analyticsPage.test.js tests/Frontend/appMode.test.js tests/Frontend/analytics.test.js tests/Frontend/analyticsConsent.test.js`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/analytics.ts resources/js/lib/analyticsConsent.ts tests/Frontend/analytics.test.js tests/Frontend/analyticsConsent.test.js
git commit -m "fix(analytics): send explicit sanitized page views"
```

### Task 4: Explicit public-page views and operations

**Files:**
- Modify: `resources/views/components/analytics-consent.blade.php`
- Modify: `resources/js/analyticsConsent.ts`
- Create: `tests/Feature/AnalyticsConsentTest.php`
- Modify: `tests/Frontend/analyticsConsent.test.js`
- Modify: `docs/operations.md`

**Interfaces:**
- Consumes: analytics page identifiers rendered as `data-analytics-page-type`, `data-analytics-page-title`, and `data-analytics-locale`.
- Produces: one public document `page_view` after consent with the same fields as the Inertia payload.

- [ ] **Step 1: Write failing public analytics tests**

Assert public markup contains only generic analytics attributes, has no member-derived values, emits one explicit view after stored or newly granted consent, and never emits before consent. Assert `send_page_view: false` for public documents.

- [ ] **Step 2: Run the targeted tests and verify failure**

Run: `bun test tests/Frontend/analyticsConsent.test.js && php artisan test tests/Feature/AnalyticsConsentTest.php`

Expected: FAIL on missing public metadata/explicit event.

- [ ] **Step 3: Implement public emission and document GA4 setup**

Pass the generic page identity through the component, emit after activation, and document creation of event-scoped custom dimensions `app_mode` and `page_type`, including the lack of historical backfill and a PWA-versus-browser exploration.

- [ ] **Step 4: Run analytics verification**

Run: `bun test tests/Frontend/analyticsPage.test.js tests/Frontend/appMode.test.js tests/Frontend/analytics.test.js tests/Frontend/analyticsConsent.test.js && php artisan test tests/Feature/AnalyticsConsentTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/analytics-consent.blade.php resources/js/analyticsConsent.ts tests/Feature/AnalyticsConsentTest.php tests/Frontend/analyticsConsent.test.js docs/operations.md
git commit -m "feat(analytics): measure public and PWA traffic"
```
