# Horizon brand integration plan

**Goal:** Deliver issue #293 and the approved six logo variants on every existing brand surface.
**Architecture:** Keep the exact four paths in a versioned source SVG. Generate all raster and vector derivatives explicitly with existing Playwright. Share CSS logo selection between Blade and Vue; retain existing accessible names and translated beta text.
**Spec:** https://github.com/zdossantos/dlp-friends/issues/293 and user approval of the six previews, followed by confirmation of original adaptation on 2026-10-19.

- [x] Inventory existing logos, favicon, Apple, PWA/maskable, beta, notifications and installer screenshots; preserve unrelated local work in an isolated worktree from origin/main.
- [x] Add failing Pest checks for exact geometry, safe circle and transparent notification badge; confirm failures before implementation.
- [x] Generate six palettes with transparent cutout; include monochromes and approved editable signatures. Replace every existing production/beta asset from the same generator.
- [x] Select all six variants in both public and application headers, keeping accessible semantics and readable logo tile contrast.
- [x] Refresh affected tests and installation screenshots, then run asset, theme, beta and PWA regressions.
- [x] Run frontend/PHP quality and build checks and review the diff. PR publication follows the verified commit.

Review focus: seasonal dark precedence, alpha on arbitrary backgrounds, every foreground pixel inside the 80% maskable circle, beta distinction, existing public/notification/offline references and cached assets.

## Verification evidence

- Unit/Feature: 1,279 passed, 2 skipped; 28,417 assertions.
- Chromium: 230 passed, 4 optional Reverb cases skipped; 2,127 assertions; native output (`PAO_DISABLE=1`) exits 0. The initial machine reporter returned two warnings without details; the full native run did not reproduce them.
- Frontend: ESLint, Prettier, vue-tsc, 137 unit tests and Vite build pass with Bun 1.3.14.
- PHP: Pint and PHPStan pass. Docker runtime image builds successfully.
- Reproducibility: 152 generated assets checked, zero differences.
- Independent review: no concrete defects found.
- Existing dirty main checkout was preserved. Worktree starts at the current origin/main (9004f72), because the main checkout could not fast-forward over unrelated local changes.
