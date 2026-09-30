# Public SEO Foundations Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make DLP Friends technically sound and meaningfully discoverable for generic French and English searches about friendly Disneyland Paris connections.

**Architecture:** Laravel serves two bilingual guide families through one public guide controller and one shared SSR Blade template. `PublicUrls` owns every localized canonical URL; sitemap, internal links, metadata, and JSON-LD are generated from those stable definitions and tested as parsed documents rather than strings alone.

**Tech Stack:** Laravel 13, Blade, PHP/Pest, Tailwind CSS, Search Console operational workflow.

**Spec:** `docs/superpowers/specs/2026-09-30-analytics-seo-foundations-design.md`

## Global Constraints

- Content is strictly friendly, for adults, and explicitly independent from Disney and Disneyland Paris.
- Do not add Disney characters, logos, or unlicensed artwork.
- French and English pages are original adaptations, not thin keyword variants.
- Public content is server rendered and useful without JavaScript.
- Do not promise ranking, matches, or functionality the product does not provide.

## Review Focus

- An unsupported locale or slug must not create an indexable thin page; Task 2 tests 404 behavior.
- Every localized page must self-canonicalize and reciprocate `fr`, `en`, and `x-default`; Task 2 tests the full matrix.
- JSON-LD keys beginning with `@` must survive Blade rendering as valid JSON; Task 1 parses every block.
- New pages must be reachable through ordinary HTML links and the sitemap; Task 3 tests both paths.
- Private/authentication routes must remain `noindex, nofollow`; Task 3 retains the existing negative coverage.

---

### Task 1: Safe structured-data serialization

**Files:**
- Create: `resources/views/components/structured-data.blade.php`
- Modify: `resources/views/app.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/matching/show.blade.php`
- Modify: `tests/Feature/PublicLandingTest.php`
- Modify: `tests/Feature/PublicMatchingTest.php`

**Interfaces:**
- Produces: `<x-structured-data :value="$schema" />`, which serializes an array to one valid `application/ld+json` script without Blade interpreting `@context`.
- Consumes later: Task 2 uses the component for guide schemas.

- [ ] **Step 1: Add failing parsed-JSON tests**

Extract every `application/ld+json` block from landing and matching HTML, decode with `JSON_THROW_ON_ERROR`, and assert `@context === 'https://schema.org'`, the expected `@type`, canonical URL, and absence of `<?php`.

- [ ] **Step 2: Run the targeted tests and verify failure**

Run: `php artisan test tests/Feature/PublicLandingTest.php tests/Feature/PublicMatchingTest.php`

Expected: FAIL because the current rendered `@context` key is corrupted.

- [ ] **Step 3: Implement the reusable serializer**

Move inline schema arrays out of directive-sensitive script bodies and render them with the component using Laravel-safe JSON encoding. Preserve the existing truthful `WebApplication` data.

- [ ] **Step 4: Run the public-page tests**

Run: `php artisan test tests/Feature/PublicLandingTest.php tests/Feature/PublicMatchingTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/components/structured-data.blade.php resources/views/app.blade.php resources/views/welcome.blade.php resources/views/matching/show.blade.php tests/Feature/PublicLandingTest.php tests/Feature/PublicMatchingTest.php
git commit -m "fix(seo): render valid structured data"
```

### Task 2: Bilingual search-intent guides

**Files:**
- Create: `app/Http/Controllers/PublicGuideController.php`
- Create: `resources/views/guides/show.blade.php`
- Create: `lang/fr/guides.php`
- Create: `lang/en/guides.php`
- Create: `tests/Feature/PublicGuideTest.php`
- Modify: `app/Support/PublicUrls.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Middleware/PreventSearchIndexing.php`

**Interfaces:**
- Produces: `PublicUrls::friendshipsPath(string $locale)`, `friendships(string $locale)`, `soloVisitPath(string $locale)`, and `soloVisit(string $locale)`.
- Produces routes `/fr/rencontres-amicales-disneyland-paris`, `/en/disneyland-paris-friendships`, `/fr/aller-seul-disneyland-paris`, `/en/visiting-disneyland-paris-solo`.
- Consumes: `<x-structured-data>` from Task 1.

- [ ] **Step 1: Write failing feature tests for all four pages**

For each URL assert 200 SSR HTML, correct locale, unique title/description/H1/canonical, reciprocal hreflang plus French `x-default`, adult/friendly/independence wording, crawlable CTA and matching links, one H1, `WebPage` and visible-link-only `BreadcrumbList`. Assert unknown combinations return 404.

- [ ] **Step 2: Run the guide test and verify failure**

Run: `php artisan test tests/Feature/PublicGuideTest.php`

Expected: FAIL because routes and controller do not exist.

- [ ] **Step 3: Add URL helpers, explicit routes, controller, translations, and shared view**

Keep the route set explicit rather than accepting arbitrary slugs. The controller selects one of two known guide keys and builds canonical, alternates, breadcrumbs, CTA, and schema from `PublicUrls`; all visible prose lives in translation catalogues.

- [ ] **Step 4: Run the guide tests**

Run: `php artisan test tests/Feature/PublicGuideTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/PublicGuideController.php app/Support/PublicUrls.php app/Http/Middleware/PreventSearchIndexing.php routes/web.php resources/views/guides/show.blade.php lang/fr/guides.php lang/en/guides.php tests/Feature/PublicGuideTest.php
git commit -m "feat(seo): add bilingual friendship guides"
```

### Task 3: Landing relevance, internal links, and sitemap

**Files:**
- Modify: `resources/views/welcome.blade.php`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`
- Modify: `resources/views/sitemap.blade.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/PublicLandingTest.php`
- Modify: `tests/Feature/PublicGuideTest.php`

**Interfaces:**
- Consumes: all four `PublicUrls` guide helpers from Task 2.
- Produces: crawlable landing/footer links and two new localized sitemap groups.

- [ ] **Step 1: Add failing link and sitemap assertions**

Assert each landing links to its two same-locale guides with descriptive anchors; each guide links back to landing, matching, and relevant guide/legal pages; sitemap contains six editorial localized URLs with reciprocal hreflang and French `x-default`; private/auth routes remain excluded and noindexed.

- [ ] **Step 2: Run tests and verify failure**

Run: `php artisan test tests/Feature/PublicLandingTest.php tests/Feature/PublicGuideTest.php`

Expected: FAIL on missing guide links and sitemap entries.

- [ ] **Step 3: Improve the landing copy and add internal links/sitemap groups**

Use natural French phrasing around finding friendly adult Disneyland Paris fans, an equivalent English adaptation, and no keyword repetition. Keep existing CTAs and product claims accurate.

- [ ] **Step 4: Run the public SEO suite**

Run: `php artisan test tests/Feature/PublicLandingTest.php tests/Feature/PublicMatchingTest.php tests/Feature/PublicGuideTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/welcome.blade.php resources/views/sitemap.blade.php routes/web.php lang/fr/common.php lang/en/common.php tests/Feature/PublicLandingTest.php tests/Feature/PublicGuideTest.php
git commit -m "feat(seo): strengthen public discovery paths"
```

### Task 4: Search Console runbook and final public verification

**Files:**
- Modify: `docs/operations.md`

**Interfaces:**
- Produces: deployment checklist for URL inspection, structured-data validation, sitemap resubmission, indexing requests, and weekly/monthly measurement.

- [ ] **Step 1: Add the operational checklist**

Document the six editorial URLs, live inspection, Rich Results/Schema validation, sitemap resubmission, indexing requests, and the distinction between crawl, index, and rank issues. Record impressions, clicks, CTR, position, query, country, device, and page review cadence without promising results.

- [ ] **Step 2: Run documentation and application checks**

Run: `composer lint:check && composer analyse && bun run lint:check && bun run format:check && bun run types:check && bun run build`

Expected: all commands exit 0.

- [ ] **Step 3: Run the complete relevant feature suite**

Run: `php artisan test tests/Feature/PublicLandingTest.php tests/Feature/PublicMatchingTest.php tests/Feature/PublicGuideTest.php tests/Feature/AnalyticsConsentTest.php`

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add docs/operations.md
git commit -m "docs(seo): add search monitoring runbook"
```

