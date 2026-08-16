# Clean Develop-to-Main Promotion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the blocked direct `develop → main` promotion with a tested automation branch that always contains the latest `main` and `develop`, while leaving Release Please as the sole versioning and GitHub Release authority.

**Architecture:** A focused Bash script builds `automation/promote-develop` locally from `origin/main` and merges `origin/develop` only when promotion is required. GitHub Actions publishes that automation-owned branch with an exact force-with-lease, maintains one pull request to `main`, and leaves both protected long-lived branches untouched.

**Tech Stack:** Git, Bash 5, GitHub Actions, GitHub CLI, Release Please 5, Prettier 3.

## Global Constraints

- `develop` remains the mandatory integration branch for application and dependency changes.
- `develop` and `main` remain protected; neither branch may be force-pushed or updated directly by the promotion workflow.
- `main` keeps strict required status checks and continues to require an up-to-date pull request.
- The automation branch is exactly `automation/promote-develop`.
- Promotion pull requests are reviewed and merged manually with a merge commit; the workflow never auto-merges them.
- Release Please remains configured with `target-branch: main` and is the only process that updates versions, `CHANGELOG.md`, tags, and GitHub Releases.
- The five required checks remain `PHP quality`, `Backend tests`, `Frontend quality`, `Vite build`, and `Docker build`.
- Coolify continues to deploy commits reaching `main`; changing deployment to tag-based releases is out of scope.
- Workflow writes use the repository-scoped `RELEASE_PLEASE_TOKEN`; no production secret is introduced.

---

## File map

- Create `.github/scripts/prepare-promotion-branch.sh`: pure Git decision and branch-construction logic, with no GitHub API or push side effects.
- Create `.github/scripts/test-prepare-promotion-branch.sh`: isolated temporary-repository regression tests for divergence, main advancement, and no-op promotion.
- Modify `.github/workflows/promote-develop.yml`: trigger synchronization, publish the automation branch safely, and maintain exactly one promotion pull request.
- Modify `.github/pull_request_template.md`: describe the automation-branch merge rule instead of a direct long-lived branch PR.
- Modify `docs/quality-ci-cd.md`: make the new promotion model the operational source of truth.
- Modify `docs/technical-architecture.md`: align production promotion terminology.
- Modify `docs/implementation-plan.md`: replace the obsolete direct `develop → main` checklist item.
- Modify `docs/superpowers/specs/2026-08-16-github-ci-release-design.md`: mark the original direct-branch design as superseded by the automation branch.
- Modify `docs/superpowers/plans/2026-08-16-github-ci-release.md`: add a supersession notice so old execution commands are not reused.

### Task 1: Build and test the pure Git promotion constructor

**Files:**
- Create: `.github/scripts/test-prepare-promotion-branch.sh`
- Create: `.github/scripts/prepare-promotion-branch.sh`

**Interfaces:**
- Consumes: `BASE_REF` (default `refs/remotes/origin/main`), `SOURCE_REF` (default `refs/remotes/origin/develop`), `PROMOTION_BRANCH` (default `automation/promote-develop`), and `GITHUB_OUTPUT` when running in Actions.
- Produces: a local promotion branch containing both input refs and an output line `promotion_required=true`, or no branch mutation and `promotion_required=false` when the source is already contained by the base.

- [ ] **Step 1: Write the failing temporary-repository regression test**

Create `.github/scripts/test-prepare-promotion-branch.sh` with this executable Bash structure:

```bash
#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/prepare-promotion-branch.sh"
test_root="$(mktemp -d)"
trap 'rm -rf "$test_root"' EXIT

assert_ancestor() {
    local repository="$1"
    local ancestor="$2"
    local descendant="$3"
    git -C "$repository" merge-base --is-ancestor "$ancestor" "$descendant" || {
        echo "Expected $ancestor to be an ancestor of $descendant" >&2
        exit 1
    }
}

git init --bare "$test_root/origin.git"
git init -b main "$test_root/seed"
git -C "$test_root/seed" config user.name "Promotion Test"
git -C "$test_root/seed" config user.email "promotion-test@example.com"
echo initial > "$test_root/seed/app.txt"
git -C "$test_root/seed" add app.txt
git -C "$test_root/seed" commit -m "chore: initialize test repository"
git -C "$test_root/seed" remote add origin "$test_root/origin.git"
git -C "$test_root/seed" push -u origin main
git --git-dir="$test_root/origin.git" symbolic-ref HEAD refs/heads/main
git -C "$test_root/seed" switch -c develop
echo feature >> "$test_root/seed/app.txt"
git -C "$test_root/seed" commit -am "feat: add test feature"
git -C "$test_root/seed" push -u origin develop

git clone "$test_root/origin.git" "$test_root/work"
git -C "$test_root/work" config user.name "Promotion Bot"
git -C "$test_root/work" config user.email "promotion-bot@example.com"
git -C "$test_root/work" fetch origin main develop

first_output="$test_root/first-output"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$first_output" "$subject"
)
grep -qx 'promotion_required=true' "$first_output"
assert_ancestor "$test_root/work" origin/main automation/promote-develop
assert_ancestor "$test_root/work" origin/develop automation/promote-develop

git -C "$test_root/seed" switch main
echo release > "$test_root/seed/VERSION"
git -C "$test_root/seed" add VERSION
git -C "$test_root/seed" commit -m "chore(main): release 1.1.0"
git -C "$test_root/seed" push origin main
git -C "$test_root/work" fetch origin main develop

second_output="$test_root/second-output"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$second_output" "$subject"
)
grep -qx 'promotion_required=true' "$second_output"
assert_ancestor "$test_root/work" origin/main automation/promote-develop
assert_ancestor "$test_root/work" origin/develop automation/promote-develop

git -C "$test_root/seed" merge --no-ff develop -m "chore: promote develop to main"
git -C "$test_root/seed" push origin main
git -C "$test_root/work" fetch origin main develop

third_output="$test_root/third-output"
before_sha="$(git -C "$test_root/work" rev-parse automation/promote-develop)"
(
    cd "$test_root/work"
    GITHUB_OUTPUT="$third_output" "$subject"
)
grep -qx 'promotion_required=false' "$third_output"
test "$before_sha" = "$(git -C "$test_root/work" rev-parse automation/promote-develop)"

echo "Promotion branch regression tests passed."
```

Mark it executable with `chmod +x .github/scripts/test-prepare-promotion-branch.sh`.

- [ ] **Step 2: Run the regression test to verify it fails**

Run:

```bash
.github/scripts/test-prepare-promotion-branch.sh
```

Expected: non-zero exit because `.github/scripts/prepare-promotion-branch.sh` does not exist.

- [ ] **Step 3: Implement the minimal promotion constructor**

Create `.github/scripts/prepare-promotion-branch.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

base_ref="${BASE_REF:-refs/remotes/origin/main}"
source_ref="${SOURCE_REF:-refs/remotes/origin/develop}"
promotion_branch="${PROMOTION_BRANCH:-automation/promote-develop}"
output_file="${GITHUB_OUTPUT:-/dev/null}"

if [[ "$(git rev-parse --is-shallow-repository)" == "true" ]]; then
    echo "Promotion requires a complete Git history." >&2
    exit 1
fi

git rev-parse --verify "$base_ref^{commit}" >/dev/null
git rev-parse --verify "$source_ref^{commit}" >/dev/null

if git merge-base --is-ancestor "$source_ref" "$base_ref"; then
    echo "promotion_required=false" >> "$output_file"
    exit 0
fi

git switch --force-create "$promotion_branch" "$base_ref"
git merge --no-ff --no-edit "$source_ref"
git merge-base --is-ancestor "$base_ref" HEAD
git merge-base --is-ancestor "$source_ref" HEAD
echo "promotion_required=true" >> "$output_file"
```

Mark it executable with `chmod +x .github/scripts/prepare-promotion-branch.sh`.

- [ ] **Step 4: Run syntax and behavioral verification**

Run:

```bash
bash -n .github/scripts/prepare-promotion-branch.sh
bash -n .github/scripts/test-prepare-promotion-branch.sh
.github/scripts/test-prepare-promotion-branch.sh
```

Expected: both syntax checks exit zero and the test prints `Promotion branch regression tests passed.`

- [ ] **Step 5: Commit the tested Git constructor**

```bash
git add .github/scripts/prepare-promotion-branch.sh .github/scripts/test-prepare-promotion-branch.sh
git commit -m "test: cover protected branch promotion history"
```

### Task 2: Replace the direct promotion with the automation branch

**Files:**
- Modify: `.github/workflows/promote-develop.yml`
- Test: `.github/scripts/test-prepare-promotion-branch.sh`

**Interfaces:**
- Consumes: the Task 1 constructor, `RELEASE_PLEASE_TOKEN`, `origin/main`, `origin/develop`, and the existing shared Docker cache job.
- Produces: at most one open PR with head `automation/promote-develop`, base `main`, and title `chore: promote develop to main`; closes an obsolete open promotion when `main` already contains `develop`.

- [ ] **Step 1: Prove the old workflow still targets `develop` directly**

Run:

```bash
rg -n -- '--head develop|branches:|contents: read' .github/workflows/promote-develop.yml
```

Expected: the workflow only triggers on `develop`, has read-only contents permission, and passes `--head develop` to `gh pr`.

- [ ] **Step 2: Expand triggers, permissions, and checkout**

Modify `.github/workflows/promote-develop.yml` so that:

```yaml
on:
  push:
    branches:
      - develop
      - main
  workflow_dispatch:

concurrency:
  group: promote-develop
  cancel-in-progress: false

permissions:
  contents: write
  pull-requests: write
```

Keep `Warm shared Docker cache`, but run it only for `develop` pushes and manual executions:

```yaml
if: github.event_name == 'workflow_dispatch' || github.ref_name == 'develop'
```

The promotion job must use `if: always() && (needs.docker-cache.result == 'success' || needs.docker-cache.result == 'skipped')`, check out `main` with `fetch-depth: 0` and `token: ${{ secrets.RELEASE_PLEASE_TOKEN }}`, then configure the bot identity:

```bash
git config user.name "github-actions[bot]"
git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
git fetch --no-tags origin main develop
```

- [ ] **Step 3: Construct and safely publish the automation branch**

Add a step with `id: prepare` that runs `.github/scripts/prepare-promotion-branch.sh`.

When `steps.prepare.outputs.promotion_required == 'true'`, publish only the technical branch with an exact lease:

```bash
promotion_ref="refs/heads/automation/promote-develop"
remote_sha="$(git ls-remote --heads origin "$promotion_ref" | cut -f1)"

if [[ -n "$remote_sha" ]]; then
    git push \
        --force-with-lease="$promotion_ref:$remote_sha" \
        origin "HEAD:$promotion_ref"
else
    git push origin "HEAD:$promotion_ref"
fi
```

The command must never contain `develop:` or `main:` as a push destination.

- [ ] **Step 4: Maintain exactly one PR and handle the no-op state**

For a required promotion, query the exact pair and create the PR only when absent:

```bash
open_count="$(gh pr list \
    --state open \
    --base main \
    --head automation/promote-develop \
    --json number \
    --jq 'length')"

if [[ "$open_count" -eq 0 ]]; then
    gh pr create \
        --base main \
        --head automation/promote-develop \
        --title "chore: promote develop to main" \
        --body $'## Publication\n\nCette branche est reconstruite automatiquement depuis le dernier `main`, puis fusionne `develop`. Les cinq checks doivent réussir.\n\nFusionner avec **Create a merge commit**. Ne pas utiliser **Update branch**, squash ou rebase.\n\nCoolify déploiera le commit arrivé sur `main`; Release Please ouvrira ensuite la PR de version.'
fi
```

When `promotion_required == 'false'`, close an exact open automation PR if one remains:

```bash
pr_number="$(gh pr list \
    --state open \
    --base main \
    --head automation/promote-develop \
    --json number \
    --jq '.[0].number // empty')"

if [[ -n "$pr_number" ]]; then
    gh pr close "$pr_number" \
        --comment "Fermeture automatique : main contient déjà tous les commits de develop."
fi
```

- [ ] **Step 5: Validate workflow syntax and invariants**

Run:

```bash
npx prettier --check .github/workflows/promote-develop.yml
.github/scripts/test-prepare-promotion-branch.sh
rg -n -- '--head automation/promote-develop|--force-with-lease|target-branch: main' .github/workflows/promote-develop.yml .github/workflows/release-please.yml
if rg -n -- 'git push.*(develop|main)' .github/workflows/promote-develop.yml; then exit 1; fi
```

Expected: formatting and regression tests pass, the exact automation head and Release Please target are present, and no push destination targets a protected branch.

- [ ] **Step 6: Commit the promotion workflow**

```bash
git add .github/workflows/promote-develop.yml
git commit -m "ci: promote develop through an automation branch"
```

### Task 3: Align every active workflow document

**Files:**
- Modify: `.github/pull_request_template.md`
- Modify: `docs/quality-ci-cd.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/implementation-plan.md`
- Modify: `docs/superpowers/specs/2026-08-16-github-ci-release-design.md`
- Modify: `docs/superpowers/plans/2026-08-16-github-ci-release.md`

**Interfaces:**
- Consumes: the exact branch name and behavior implemented in Task 2.
- Produces: one consistent operational description of `feature → develop → automation/promote-develop → main → Release Please`.

- [ ] **Step 1: Record obsolete guidance before editing**

Run:

```bash
rg -n -- '--head develop|PR `develop → main`|pull request `develop` vers `main`|PR automatique develop → main' .github docs
```

Expected: matches appear in the current promotion workflow documentation and the earlier CI/release design or plan.

- [ ] **Step 2: Update the source-of-truth operational docs**

In `docs/quality-ci-cd.md`, replace the direct promotion section with these rules:

- pushes on `develop` reconstruct `automation/promote-develop` from the latest `main` and merge `develop` into it;
- the workflow opens at most one PR `automation/promote-develop → main`;
- only the automation branch may be force-pushed, and only with `--force-with-lease`;
- the PR is manually merged with a merge commit after all five checks;
- **Update branch**, squash, and rebase are not used for promotions;
- Release Please remains downstream on `main`.

In `docs/technical-architecture.md`, replace “PR `develop` vers `main`” with “PR de promotion construite depuis `main` et intégrant `develop`”.

In `docs/implementation-plan.md`, replace the obsolete `gh pr list --head develop` checklist item with construction and idempotence requirements for `automation/promote-develop`.

- [ ] **Step 3: Update contribution and historical guidance**

Change `.github/pull_request_template.md` so its promotion checkbox names `automation/promote-develop → main` and merge commit selection.

Update `docs/superpowers/specs/2026-08-16-github-ci-release-design.md` to describe the automation branch, `contents: write`, exact force-with-lease, and unchanged strict protections.

Add this notice immediately after the title in `docs/superpowers/plans/2026-08-16-github-ci-release.md`:

```markdown
> **Superseded promotion steps:** The direct `develop → main` instructions in this historical plan are replaced by `docs/superpowers/plans/2026-08-16-clean-develop-main-promotion.md`. Do not execute the old `--head develop` commands.
```

- [ ] **Step 4: Verify documentation consistency**

Run:

```bash
npx prettier --check .github/pull_request_template.md docs/quality-ci-cd.md docs/technical-architecture.md docs/implementation-plan.md docs/superpowers/specs/2026-08-16-github-ci-release-design.md docs/superpowers/plans/2026-08-16-github-ci-release.md
rg -n 'automation/promote-develop' .github/pull_request_template.md docs/quality-ci-cd.md docs/technical-architecture.md docs/implementation-plan.md docs/superpowers/specs/2026-08-16-github-ci-release-design.md
```

Expected: Prettier accepts every edited document and each active document names the automation branch.

- [ ] **Step 5: Commit the aligned documentation**

```bash
git add .github/pull_request_template.md docs/quality-ci-cd.md docs/technical-architecture.md docs/implementation-plan.md docs/superpowers/specs/2026-08-16-github-ci-release-design.md docs/superpowers/plans/2026-08-16-github-ci-release.md
git commit -m "docs: document reliable main promotions"
```

### Task 4: Run the complete local verification gate

**Files:**
- Verify: `.github/scripts/prepare-promotion-branch.sh`
- Verify: `.github/scripts/test-prepare-promotion-branch.sh`
- Verify: `.github/workflows/promote-develop.yml`
- Verify: `.github/workflows/release-please.yml`
- Verify: `.github/settings/develop-protection.json`
- Verify: `.github/settings/main-protection.json`

**Interfaces:**
- Consumes: all implementation and documentation changes from Tasks 1–3.
- Produces: evidence that promotion history, syntax, protected-branch destinations, Release Please ownership, and versioned branch protections remain correct.

- [ ] **Step 1: Run Bash and Git-history verification**

```bash
bash -n .github/scripts/prepare-promotion-branch.sh
bash -n .github/scripts/test-prepare-promotion-branch.sh
.github/scripts/test-prepare-promotion-branch.sh
```

Expected: all commands exit zero and the regression test reports success.

- [ ] **Step 2: Run formatting and repository checks**

```bash
npx prettier --check .github/workflows/promote-develop.yml .github/pull_request_template.md docs/quality-ci-cd.md docs/technical-architecture.md docs/implementation-plan.md docs/superpowers/specs/2026-08-16-github-ci-release-design.md docs/superpowers/plans/2026-08-16-github-ci-release.md
git diff --check origin/develop...HEAD
```

Expected: Prettier and Git whitespace checks exit zero.

- [ ] **Step 3: Verify safety and ownership invariants**

```bash
test "$(jq -r '.required_status_checks.strict' .github/settings/develop-protection.json)" = true
test "$(jq -r '.required_status_checks.strict' .github/settings/main-protection.json)" = true
test "$(jq -r '.required_linear_history' .github/settings/develop-protection.json)" = true
test "$(jq -r '.allow_force_pushes' .github/settings/develop-protection.json)" = false
test "$(jq -r '.allow_force_pushes' .github/settings/main-protection.json)" = false
rg -n 'target-branch: main' .github/workflows/release-please.yml
rg -n -- '--force-with-lease=.*automation/promote-develop|promotion_ref="refs/heads/automation/promote-develop"' .github/workflows/promote-develop.yml
if rg -n -- 'git push.*(develop|main)' .github/workflows/promote-develop.yml; then exit 1; fi
```

Expected: every assertion passes; Release Please still targets `main`; only the automation branch is published.

- [ ] **Step 4: Confirm the branch is clean and commits are focused**

```bash
git status --short
git log --oneline origin/develop..HEAD
```

Expected: the worktree is clean and the branch contains the design, regression test, workflow, and documentation commits only.

### Task 5: Publish the fix and migrate PR #10 safely

**Files:**
- Remote branch: `agent/clean-promotion-workflow`
- Implementation PR target: `develop`
- Replacement promotion PR target: `main`
- Obsolete PR: `#10`

**Interfaces:**
- Consumes: a fully verified local branch and GitHub repository protections.
- Produces: the implementation merged into `develop`, a green `automation/promote-develop → main` promotion PR, and PR #10 closed with a replacement link; production promotion remains unmerged.

- [ ] **Step 1: Push the implementation branch and create its PR**

```bash
git push -u origin agent/clean-promotion-workflow
gh pr create \
    --base develop \
    --head agent/clean-promotion-workflow \
    --title "ci: make develop promotions reliable" \
    --body $'## Résumé\n\n- construit une branche de promotion depuis le dernier main\n- fusionne develop sans réécrire les branches protégées\n- conserve Release Please sur main\n- ajoute une simulation Git de non-régression\n\n## Vérification\n\n- [x] tests de construction de branche\n- [x] syntaxe Bash\n- [x] formatage du workflow et de la documentation'
```

Expected: one open implementation PR targets `develop`.

- [ ] **Step 2: Wait for the required checks and merge only the implementation**

```bash
implementation_pr="$(gh pr view --json number --jq '.number')"
gh pr checks "$implementation_pr" --watch
gh pr merge "$implementation_pr" --squash --delete-branch
```

Expected: all five checks succeed and `develop` advances through one squash commit. This merge changes integration automation only; it does not promote to production.

- [ ] **Step 3: Wait for the promotion workflow**

```bash
promotion_run_id="$(gh run list \
    --workflow promote-develop.yml \
    --branch develop \
    --limit 1 \
    --json databaseId \
    --jq '.[0].databaseId')"
gh run watch "$promotion_run_id" --exit-status
```

Expected: `Promote develop` succeeds and publishes `automation/promote-develop`.

- [ ] **Step 4: Verify the replacement promotion PR and its checks**

```bash
promotion_pr="$(gh pr list \
    --state open \
    --base main \
    --head automation/promote-develop \
    --json number \
    --jq '.[0].number')"
test -n "$promotion_pr"
gh pr view "$promotion_pr" --json headRefName,baseRefName,mergeable,mergeStateStatus,url
gh pr checks "$promotion_pr" --watch
```

Expected: the head is `automation/promote-develop`, the base is `main`, GitHub no longer reports `BEHIND`, and all five required checks succeed.

- [ ] **Step 5: Close PR #10 only after the replacement is green**

```bash
replacement_url="$(gh pr view "$promotion_pr" --json url --jq '.url')"
gh pr close 10 \
    --comment "Remplacée par $replacement_url. La nouvelle branche de promotion part du dernier main et intègre develop sans réécrire les branches protégées."
```

Expected: PR #10 is closed with a direct link to the tested replacement.

- [ ] **Step 6: Leave the production promotion for manual review**

```bash
gh pr view "$promotion_pr" --json state,isDraft,mergeStateStatus,statusCheckRollup,url
```

Expected: the replacement promotion remains open and ready for the deliberate **Create a merge commit** production decision. Do not merge it, enable auto-merge, or select squash/rebase.
