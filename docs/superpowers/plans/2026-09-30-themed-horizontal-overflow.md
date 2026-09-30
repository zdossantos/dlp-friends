# Themed Horizontal Overflow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove unintended mobile horizontal scrolling on member surfaces for the standard, Halloween, and Christmas themes in both light and dark appearances.

**Architecture:** A browser regression test first identifies the overflowing node for each theme/appearance combination. The fix is applied at that component or flex/grid boundary, preserving intentional internal scrollers and avoiding another global clipping rule.

**Tech Stack:** Vue 3, Tailwind CSS, Pest Browser, Chromium.

**Spec:** `docs/superpowers/specs/2026-09-30-analytics-seo-foundations-design.md`

## Global Constraints

- Test at exactly 320 × 700 before changing CSS.
- Cover standard, Halloween, and Christmas in light and dark appearance.
- Do not hide overflow globally to conceal the source.
- Preserve intentional internal horizontal scrollers and all seasonal decoration.
- Make the smallest component-scoped change that removes the overflow.

## Review Focus

- Fixed seasonal decorations can extend beyond their containing block; Task 1 records the widest offending element before fixing it.
- Long notification/member content may be the true source rather than the theme; Task 1 uses deliberately long content.
- Dark appearance must not alter layout width; Task 1 runs the identical assertions in both appearances.
- A document can fit while an intermediate member shell still overflows; Task 1 asserts both root and shell dimensions.
- An intentional inner scroller must remain usable; Task 1 asserts any pre-existing scrollable table/list keeps its overflow behavior.

---

### Task 1: Reproduce, isolate, and fix themed overflow

**Files:**
- Modify: `tests/Browser/AppearanceTest.php`
- Modify: the exact offending Vue component or layout identified by the red test (expected candidates: `resources/js/components/seasonal/SeasonalDecorations.vue`, `resources/js/layouts/MemberLayout.vue`, or a surface-specific flex/grid child)

**Interfaces:**
- Produces: browser coverage that activates each persisted seasonal theme and appearance, then checks `document.documentElement.scrollWidth <= window.innerWidth` and member-shell width on notifications and conversations.

- [ ] **Step 1: Write the failing matrix test**

Create users/content with long unbroken values, resize to 320 × 700, and loop over standard/Halloween/Christmas and light/dark. Activate seasonal themes through their persisted model state and navigate afresh. On failure, compute the widest element whose right edge exceeds the viewport and include its selector/class in the assertion message. Also assert the intended local scroller, if present on the chosen fixture, remains scrollable.

- [ ] **Step 2: Run only the new browser test and confirm red**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/AppearanceTest.php --filter='themed member surfaces never overflow horizontally' --display-warnings`

Expected: FAIL for at least one non-standard seasonal/appearance combination and report the overflowing node. If it does not fail, reproduce on the user-observed surface before changing CSS and move the fixture/assertion there.

- [ ] **Step 3: Trace the containing-block chain**

Inspect computed width, `min-width`, transforms, and overflow from the reported node through its ancestors. Record the root cause in a short test comment only if the fixture would otherwise be non-obvious.

- [ ] **Step 4: Apply the minimal component-scoped correction**

Add only the required `min-w-0`, `max-w-full`, wrapping, containment, or decoration-boundary class at the first incorrect boundary. Do not add `overflow-x: hidden` to `html`, `body`, or another global ancestor.

- [ ] **Step 5: Run the new regression and adjacent browser tests**

Run: `APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/AppearanceTest.php tests/Browser/ConversationTest.php tests/Browser/NotificationCenterTest.php --display-warnings`

Expected: PASS for all theme/appearance combinations with no JavaScript errors.

- [ ] **Step 6: Commit**

```bash
git add tests/Browser/AppearanceTest.php resources/js
git commit -m "fix(ui): prevent themed horizontal overflow"
```

