# Documentation Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the scattered current-reference documentation with a concise canonical PRD, agent persona, design-system reference, and exhaustive migration inventory while leaving every file under `docs/superpowers/` unchanged.

**Architecture:** Root entry points remain short and route readers to one canonical document per concern. Product intent and delivery status live in `docs/PRD.md`; visual rules live in `docs/design-system.md`; agent behavior lives in `avatar.md`; specialized technical, security, operations, quality, and engineering references remain separate. `docs/documentation-inventory.md` records the disposition and destination of every pre-migration Markdown file.

**Tech Stack:** Markdown, Git, shell-based repository checks, Laravel/PHP repository evidence

**Spec:** `docs/documentation-consolidation-design.md`

## Global Constraints

- Do not modify, move, or delete any file under `docs/superpowers/`.
- Do not change a business rule or extend the MVP.
- Keep DLP Friends strictly friendship-oriented and explicitly independent from Disney and Disneyland Paris.
- Treat code, migrations, routes, pages, and tests as the evidence for current implementation status.
- Treat validated product decisions as the source for the target MVP.
- Do not present planned capabilities as implemented.
- Every deleted source must have an explicit destination in `docs/documentation-inventory.md` before deletion.
- Keep installation, contribution, architecture, data, operations, quality, security, and engineering responsibilities in separate documents.

---

### Task 1: Build the exhaustive documentation inventory

**Files:**
- Create: `docs/documentation-inventory.md`
- Read: every Markdown file returned by `rg --files -g '*.md' -g '!vendor/**' -g '!node_modules/**'`
- Read: `routes/web.php`, `routes/settings.php`, `database/migrations/*.php`, `app/Models/*.php`, `resources/js/pages/**/*.vue`, `tests/Feature/**/*.php`, `tests/Browser/**/*.php`

**Interfaces:**
- Consumes: the architecture and migration rules from `docs/documentation-consolidation-design.md`
- Produces: one inventory row per Markdown file and the evidence baseline used by all later tasks

- [ ] **Step 1: Capture the immutable Superpowers baseline**

Run:

```sh
git ls-files -s docs/superpowers > /tmp/dlp-friends-superpowers-before.txt
git diff -- docs/superpowers
```

Expected: the diff is empty; the baseline lists all tracked plans and specifications.

- [ ] **Step 2: Generate the source list and incoming-link evidence**

Run:

```sh
rg --files -g '*.md' -g '!vendor/**' -g '!node_modules/**' | sort
rg -n '\[[^]]+\]\(([^)#]+\.md)(#[^)]+)?\)' --glob '*.md' --glob '!vendor/**' --glob '!node_modules/**'
```

Expected: the first command lists every Markdown source; the second lists every explicit internal Markdown link.

- [ ] **Step 3: Create the inventory with one explicit row per source**

Create `docs/documentation-inventory.md` with these columns:

```markdown
| Source | Audience | Role | Status | Duplication / incoming links | Decision | Destination / justification |
| --- | --- | --- | --- | --- | --- | --- |
```

Requirements for the rows:

- enumerate root documents, current `docs/*.md` references, this design and plan, and every individual file under `docs/superpowers/plans/` and `docs/superpowers/specs/`;
- mark `AGENTS.md`, `README.md`, and `CONTRIBUTING.md` as retained entry points;
- mark `product-vision.md`, `mvp-v1.md`, and `roadmap.md` as merging into `PRD.md` and then deleted;
- mark `ux-design.md` as merging into `PRD.md` and `design-system.md` and then deleted;
- mark every Superpowers file as retained unchanged, historical, and non-normative;
- state concrete incoming links or `aucun lien entrant explicite` rather than leaving cells vague.

- [ ] **Step 4: Add the implementation-status evidence table**

Document the evidence for at least these capabilities:

```markdown
| Capability | Status | Repository evidence |
| --- | --- | --- |
| Email/password registration and verification | Implemented | Fortify actions, auth routes, and feature tests |
| Adult eligibility | Implemented | eligibility migration, registration action, middleware, and tests |
| Profile and required catalog avatar | Implemented | profile/avatar models, controllers, pages, and tests |
| Interest catalog and configurable limit | Implemented | admin controllers, migrations, and tests |
| Discovery, reciprocal matches, and conversations | Implemented | discovery actions/services, routes, migrations, and tests |
| Realtime text messaging and read state | Implemented | message actions/events, controllers, migrations, and tests |
| Immediate member blocking | Implemented | block actions, routes, policies, and tests |
| Google and Apple login | Planned | no social account migration, route, controller, or test |
| Personal photo upload | Planned | no upload request/controller flow or profile storage field in current migrations |
| Account data export | Planned | no export route, controller, or test |
| Deferred account deletion | Partial | account deletion UI exists, but no documented asynchronous purge implementation |
```

Use exact file paths in the final table after inspecting the repository; do not infer implementation from historical plans.

- [ ] **Step 5: Check inventory completeness**

Run a comparison between the sorted Markdown source list and the `Source` column. Manually confirm that every source appears exactly once and that every deletion has a destination.

Expected: no missing or duplicate inventory entry.

- [ ] **Step 6: Commit the audit**

```sh
git add docs/documentation-inventory.md
git commit -m "docs: inventory documentation sources"
```

### Task 2: Add the agent persona without duplicating operational instructions

**Files:**
- Create: `avatar.md`
- Modify: `AGENTS.md`
- Read: `docs/engineering-principles.md`, `docs/PRD.md` once Task 3 creates it

**Interfaces:**
- Consumes: the persona boundary from the design and the existing operational rules in `AGENTS.md`
- Produces: a stable agent-behavior reference and a short mandatory reading path

- [ ] **Step 1: Write `avatar.md` with four focused sections**

Use this structure and scope:

```markdown
# Persona de l’agent DLP Friends

## Rôle
Senior Laravel/Inertia/Vue collaborator; product-aware, security-conscious, and evidence-driven.

## Manière de collaborer
Outcome-first communication, concise progress, explicit assumptions, and questions only when a choice materially changes the result.

## Principes de décision
KISS, YAGNI, server-side authorization, observable tests, current-code verification, no unrelated refactoring, and preservation of user work.

## Style de réponse
French by default, plain language, concrete evidence, concise handoff, and no unsupported success claims.
```

Express the content naturally in French. Link to `AGENTS.md`, `docs/PRD.md`, and `docs/engineering-principles.md` for operational, product, and engineering detail instead of copying them.

- [ ] **Step 2: Shorten the mandatory business-reading path in `AGENTS.md`**

Replace the existing pre-business-change list with:

```markdown
Avant une modification métier, lire :

1. `avatar.md` pour la posture et les principes de collaboration ;
2. `docs/PRD.md` pour le contrat produit et l’état d’implémentation ;
3. le document spécialisé du domaine concerné dans `docs/`.
```

Keep the existing stack, commands, conventions, security, testing, and Git rules unless a later task replaces a duplicated paragraph with a canonical link.

- [ ] **Step 3: Verify boundaries and links**

Run:

```sh
rg -n 'composer |bun |docker |php artisan|migrate|feature/|fix/' avatar.md
rg -n 'avatar.md|docs/PRD.md' AGENTS.md
```

Expected: the first command returns no operational-command duplication; the second finds both new reading-path links.

- [ ] **Step 4: Commit the persona entry point**

```sh
git add avatar.md AGENTS.md
git commit -m "docs: add development agent persona"
```

### Task 3: Create the canonical PRD

**Files:**
- Create: `docs/PRD.md`
- Read: `docs/product-vision.md`, `docs/mvp-v1.md`, `docs/roadmap.md`, `docs/ux-design.md`, `docs/documentation-inventory.md`
- Read: repository evidence listed in Task 1

**Interfaces:**
- Consumes: validated product decisions from the four source documents and implementation evidence from the inventory
- Produces: the canonical contract referenced by `AGENTS.md`, `README.md`, and specialized documents

- [ ] **Step 1: Write the product foundation**

Create these sections:

```markdown
# PRD — DLP Friends
## Statut et usage du document
## Vision et proposition de valeur
## Public et principes non négociables
## Critères de succès du MVP
```

State explicitly that the service is for adults, strictly friendship-oriented, privacy-conscious, mobile-first, and independent from Disney and Disneyland Paris.

- [ ] **Step 2: Write the target MVP by capability**

Create concise subsections for account, profile, interests and avatars, discovery and matching, messaging and blocking, administration, data control, localization, and interface. Preserve all still-applicable rules from `mvp-v1.md` and the user journeys from `ux-design.md`, replacing detailed technical mechanics with links to specialized references.

- [ ] **Step 3: Add the implementation-status matrix**

Copy the verified capability/status/evidence conclusions from the inventory, using `Implémenté`, `Partiel`, and `Planifié`. Include a dated statement that this matrix is a repository snapshot and must be updated when delivery status changes.

- [ ] **Step 4: Add roadmap and explicit exclusions**

Include V2 visit-companion intent, later evaluation items, and all MVP exclusions. Keep reporting/moderation future work distinct from current blocking behavior. Do not introduce new commitments.

- [ ] **Step 5: Add canonical cross-references**

Link to:

- `data-model.md` for storage and matching mechanics;
- `technical-architecture.md` for current implementation architecture;
- `design-system.md` for visual rules;
- `security-privacy.md` for privacy and deletion requirements;
- `operations.md` and `quality-ci-cd.md` for operational success criteria.

- [ ] **Step 6: Verify product language and status claims**

Run:

```sh
rg -ni 'dating|romanti|amour|petit ami|petite amie' docs/PRD.md
rg -n 'Implémenté|Partiel|Planifié|indépendant|non affili' docs/PRD.md
```

Expected: no romantic positioning; all three statuses and the non-affiliation statement are present.

- [ ] **Step 7: Commit the canonical PRD**

```sh
git add docs/PRD.md
git commit -m "docs: add canonical product requirements"
```

### Task 4: Create the design system and deduplicate specialized references

**Files:**
- Create: `docs/design-system.md`
- Modify: `docs/data-model.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/operations.md`
- Modify: `docs/quality-ci-cd.md`
- Modify: `docs/engineering-principles.md`
- Read: `docs/ux-design.md`, `resources/css/app.css`, `resources/js/components/ui/**`, `resources/js/layouts/**`

**Interfaces:**
- Consumes: current visual implementation, UX source rules, and the canonical PRD
- Produces: one visual source of truth and specialized references limited to their own responsibilities

- [ ] **Step 1: Audit implemented visual tokens and primitives**

Run:

```sh
sed -n '1,280p' resources/css/app.css
rg --files resources/js/components/ui resources/js/layouts | sort
rg -n 'dark:|sm:|md:|lg:|aria-|sr-only|focus-visible' resources/js --glob '*.vue'
```

Record only tokens, component families, breakpoints, focus behavior, and themes actually supported by the code. Mark aspirations as guidance rather than implementation.

- [ ] **Step 2: Write `docs/design-system.md`**

Use these sections:

```markdown
# Design system DLP Friends
## Responsabilité et relation avec le PRD
## Principes visuels
## Tokens de couleur et thèmes
## Typographie, espacement, rayons et ombres
## Composants et états
## Responsive et navigation
## Accessibilité
## Assets et propriété intellectuelle
## Règles d’évolution
```

Document the current warm-neutral, violet, pink, and limited-gold language; light/dark/system themes; mobile-first behavior; keyboard/focus/contrast rules; component reuse order; and prohibition on unauthorized Disney assets. Link product journeys back to `PRD.md`.

- [ ] **Step 3: Deduplicate specialized references**

For each retained document:

- keep normative details unique to that domain;
- replace repeated product scope with a short link to `PRD.md`;
- replace repeated visual direction with a short link to `design-system.md`;
- preserve concrete technical, data, security, operational, quality, and engineering requirements;
- correct statements that claim a planned capability currently exists.

- [ ] **Step 4: Verify each document has one responsibility**

Run:

```sh
for file in docs/data-model.md docs/technical-architecture.md docs/security-privacy.md docs/operations.md docs/quality-ci-cd.md docs/engineering-principles.md docs/design-system.md; do rg '^(#|##) ' "$file"; done
```

Expected: headings match the document responsibility; product vision and roadmap sections occur only in `PRD.md`.

- [ ] **Step 5: Commit the design and specialized references**

```sh
git add docs/design-system.md docs/data-model.md docs/technical-architecture.md docs/security-privacy.md docs/operations.md docs/quality-ci-cd.md docs/engineering-principles.md
git commit -m "docs: consolidate design and domain references"
```

### Task 5: Switch entry points and remove merged sources

**Files:**
- Modify: `README.md`
- Modify: `CONTRIBUTING.md`
- Modify: `docs/documentation-inventory.md`
- Delete: `docs/product-vision.md`
- Delete: `docs/mvp-v1.md`
- Delete: `docs/roadmap.md`
- Delete: `docs/ux-design.md`

**Interfaces:**
- Consumes: all canonical documents created in Tasks 2–4
- Produces: the final contributor reading path with no links to deleted sources

- [ ] **Step 1: Update `README.md` documentation routing**

Replace the current documentation table with a concise ordered table:

1. `docs/PRD.md` — product contract and implementation status;
2. `docs/design-system.md` — visual and accessibility rules;
3. retained specialized documents — data, architecture, security, operations, quality, engineering;
4. `docs/documentation-inventory.md` — audit and migration traceability.

Mention that `docs/superpowers/` contains historical, non-normative records and is not required reading for current development.

- [ ] **Step 2: Keep `CONTRIBUTING.md` procedural and add canonical links**

Preserve setup, branching, checks, PR, merge, and release instructions. Add links to `AGENTS.md` for agent workflow and `docs/quality-ci-cd.md` for detailed CI/release policy instead of duplicating new product material.

- [ ] **Step 3: Confirm all migration destinations before deletion**

For every section of the four source files, identify its destination in `PRD.md`, `design-system.md`, or a retained specialized reference. Update inventory rows with the final decision and destination.

Expected: no still-applicable rule has only a deleted source.

- [ ] **Step 4: Delete only the four approved merged sources**

Use `apply_patch` to delete:

```text
docs/product-vision.md
docs/mvp-v1.md
docs/roadmap.md
docs/ux-design.md
```

Do not delete, rename, move, or edit any path under `docs/superpowers/`.

- [ ] **Step 5: Search for stale references**

Run:

```sh
rg -n 'product-vision\.md|mvp-v1\.md|roadmap\.md|ux-design\.md' --glob '*.md' --glob '!docs/superpowers/**'
```

Expected: no result outside the inventory’s historical source/destination rows and the committed design/plan records where old filenames are intentionally discussed.

- [ ] **Step 6: Commit the entry-point migration**

```sh
git add README.md CONTRIBUTING.md docs/documentation-inventory.md docs/product-vision.md docs/mvp-v1.md docs/roadmap.md docs/ux-design.md
git commit -m "docs: switch to canonical documentation"
```

### Task 6: Verify links, preservation, scope, and final readability

**Files:**
- Modify if needed: only files outside `docs/superpowers/` already in scope above
- Verify: all tracked Markdown files

**Interfaces:**
- Consumes: the complete consolidated documentation set
- Produces: reproducible evidence that acceptance criteria and preservation constraints hold

- [ ] **Step 1: Verify local Markdown targets**

Run a read-only link checker that extracts Markdown destinations, ignores `http:`, `https:`, `mailto:`, and in-page-only anchors, resolves relative paths from each source file, and fails if a local target does not exist. Use the bundled runtime or a temporary script under `/tmp`; do not add a project dependency.

Expected: every local Markdown target exists.

- [ ] **Step 2: Search deleted-source references and normative duplication**

Run:

```sh
rg -n 'product-vision\.md|mvp-v1\.md|roadmap\.md|ux-design\.md' --glob '*.md' --glob '!docs/superpowers/**'
rg -n 'Google|Apple|photo personnelle|export des données|suppression.*30 jours' docs/*.md README.md AGENTS.md CONTRIBUTING.md avatar.md
```

Expected: old filenames occur only in explicit migration records; planned capabilities are labeled planned/partial where applicable and normative details have one canonical home.

- [ ] **Step 3: Prove Superpowers files are byte-for-byte untouched**

Run:

```sh
git ls-files -s docs/superpowers > /tmp/dlp-friends-superpowers-after.txt
diff -u /tmp/dlp-friends-superpowers-before.txt /tmp/dlp-friends-superpowers-after.txt
git diff HEAD~5 -- docs/superpowers
```

Expected: both comparisons are empty. If the commit count differs, compare against the commit immediately before Task 1 instead of assuming `HEAD~5`.

- [ ] **Step 4: Validate formatting and repository diff**

Run:

```sh
git diff --check
git status --short
git diff --stat HEAD~5
```

Expected: no whitespace errors; only approved documentation files changed; no file under `docs/superpowers/` changed.

- [ ] **Step 5: Perform the two-reader review**

Read `README.md`, `CONTRIBUTING.md`, and `AGENTS.md` twice:

- as a new contributor, confirm setup, checks, contribution workflow, and canonical documentation are discoverable without historical plans;
- as a development agent, confirm persona, PRD, domain references, constraints, and verification expectations form a short exact route.

Record and fix any broken route, repeated instruction, ambiguous status, or missing source-of-truth pointer outside `docs/superpowers/`.

- [ ] **Step 6: Commit final verification corrections if any**

```sh
git add README.md CONTRIBUTING.md AGENTS.md avatar.md docs
git commit -m "docs: verify consolidated references"
```

Skip this commit if verification required no correction.
