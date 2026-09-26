# Complete Halloween and Christmas Themes Implementation Plan

> **For Codex:** REQUIRED SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extend the approved Halloween and Christmas art direction to the whole public and member experience, using only the existing Lucide icon library and preserving light/dark accessibility.

**Architecture:** Keep palette and activation in the existing seasonal-theme system. Add one reusable Vue surface decoration for member cards/panels, retain the organic fixed decorations at layout level, and render equivalent Lucide-derived decorative SVGs in the server-rendered public landing page. Apply bespoke compositions only where the visual hierarchy needs them: landing, discovery/match, profiles, events, conversations, and empty/list surfaces.

**Tech Stack:** Laravel 13, Blade, Inertia 3, Vue 3, TypeScript, Tailwind CSS, `@lucide/vue`, Pest Browser.

---

### Task 1: Reusable seasonal surface accents

**Files:**
- Modify: `resources/js/components/seasonal/SeasonalDecorations.vue`
- Modify: `resources/js/components/ui/card/Card.vue`
- Test: `tests/Browser/AppearanceTest.php`

- [x] Add a failing browser assertion for a seasonal decoration rendered inside a standard card.
- [x] Drive global, card, panel, and hero placements from one configuration-backed, pointer-events-none seasonal component.
- [x] Integrate it in the shared card primitive with card content above the decoration.
- [x] Run the targeted appearance test.

### Task 2: Complete the member-facing surfaces

**Files:**
- Modify: `resources/js/components/profile/ProfilePresentation.vue`
- Modify: `resources/js/components/events/EventCard.vue`
- Modify: `resources/js/pages/Conversations/Index.vue`
- Modify: `resources/js/pages/Notifications/Index.vue`
- Modify: `resources/js/components/seasonal/SeasonalDecorations.vue`
- Test: `tests/Browser/AppearanceTest.php`

- [x] Add failing assertions that the profile hero and organic list surfaces expose seasonal decorations.
- [x] Add low-opacity, non-repeating Lucide accents to profile heroes and the principal social surfaces.
- [x] Keep all seasonal SVGs decorative (`aria-hidden`, `focusable=false`) and outside interaction hit targets.
- [x] Run the targeted browser tests.

### Task 3: Theme the server-rendered public landing page

**Files:**
- Modify: `app/Http/Controllers/PublicLandingController.php`
- Create: `resources/views/components/seasonal-icon.blade.php`
- Modify: `resources/views/welcome.blade.php`
- Modify: `resources/views/components/public-partner-card.blade.php`
- Test: `tests/Feature/PublicLandingTest.php`
- Test: `tests/Browser/WelcomeAndRegistrationTest.php`

- [x] Add a failing feature test proving the active theme reaches the server-rendered landing page and exposes the matching root class.
- [x] Resolve the active seasonal theme in the public controller.
- [x] Add decorative Lucide-derived icons around the hero, benefit cards, partner cards, algorithm panel, steps, and final CTA with varied size/opacity/rotation.
- [x] Run the targeted feature and browser tests.

### Task 4: Mobile light/dark visual verification

**Files:**
- Modify if needed: `resources/css/app.css`
- Output (untracked): `artifacts/issue-185-189-theme-matrix/`

- [x] Run formatting, PHP lint/static analysis, type checks, and production build; document the repository's ESLint 10/plugin incompatibility.
- [x] Run relevant seasonal browser tests.
- [x] Capture representative mobile screenshots for Halloween and Christmas in light and dark modes across the public and member surfaces, reusing the approved match captures.
- [x] Review every capture for clipping, overlap, contrast, and organic placement; refine and repeat.
