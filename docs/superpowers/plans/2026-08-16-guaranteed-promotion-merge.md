# Guaranteed Promotion Merge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a human-triggered GitHub Actions workflow that validates the active promotion pull request and merges it exclusively with a merge commit, stopping the promotion loop and restoring Release Please commit visibility.

**Architecture:** Keep `.github/workflows/promote-develop.yml` responsible for constructing `automation/promote-develop` and opening the pull request. Put all validation and the exact guarded merge command in one shell script, cover it with a mocked `gh` regression suite, and call it from a minimal `workflow_dispatch` workflow.

**Tech Stack:** GitHub Actions YAML, Bash 3.2+, GitHub CLI, `jq`, Git.

## Global Constraints

- `develop` remains the mandatory integration branch for application changes.
- A human must explicitly trigger every production promotion.
- Only the exact open pull request `automation/promote-develop → main` may be merged.
- Promotion merges must use `gh pr merge --merge --match-head-commit`.
- Required checks and branch protection must never be bypassed.
- `develop` and `main` must never be force-pushed.
- `RELEASE_PLEASE_TOKEN` remains the repository-scoped automation token.
- Release Please remains the sole owner of SemVer, `CHANGELOG.md`, tags, and GitHub Releases.

---

## File Structure

- Create `.github/scripts/merge-promotion-pr.sh`: validate the exact promotion PR and perform the guarded merge.
- Create `.github/scripts/test-merge-promotion-pr.sh`: exercise every validation branch with a fake `gh` executable.
- Create `.github/workflows/promote-to-production.yml`: expose the human `workflow_dispatch` control.
- Modify `.github/workflows/promote-develop.yml`: replace unsafe standard-button instructions.
- Modify `docs/quality-ci-cd.md`: document the operator procedure and PR #36 recovery.

### Task 1: Guarded promotion merge script

**Files:**
- Create: `.github/scripts/merge-promotion-pr.sh`
- Create: `.github/scripts/test-merge-promotion-pr.sh`

**Interfaces:**
- Consumes: `GH_TOKEN`, `GH_REPO`, `gh`, `jq`, and optional `GITHUB_STEP_SUMMARY`.
- Produces: exit `0` only after `gh pr merge NUMBER --merge --match-head-commit SHA` succeeds; invalid states exit non-zero before merge.

- [ ] **Step 1: Write the failing regression test**

Create a test that installs this temporary `gh` mock and runs the subject under each `GH_SCENARIO`:

```bash
#!/usr/bin/env bash
set -euo pipefail
script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/merge-promotion-pr.sh"
test_root="$(mktemp -d)"
trap 'rm -rf "$test_root"' EXIT
mkdir -p "$test_root/bin"

cat > "$test_root/bin/gh" <<'MOCK'
#!/usr/bin/env bash
set -euo pipefail
printf '%q ' "$@" >> "$GH_CALLS"
printf '\n' >> "$GH_CALLS"
case "$1 $2" in
  "pr list")
    case "$GH_SCENARIO" in
      absent) printf '[]\n' ;;
      multiple) printf '[{"number":36},{"number":37}]\n' ;;
      wrong-refs) printf '[{"number":36,"headRefName":"feature/untrusted","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"CLEAN"}]\n' ;;
      draft) printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":true,"mergeStateStatus":"CLEAN"}]\n' ;;
      dirty) printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"DIRTY"}]\n' ;;
      *) printf '[{"number":36,"headRefName":"automation/promote-develop","baseRefName":"main","headRefOid":"abc123","isDraft":false,"mergeStateStatus":"CLEAN"}]\n' ;;
    esac ;;
  "pr checks") [[ "$GH_SCENARIO" != "checks-fail" ]] ;;
  "pr view") [[ "$GH_SCENARIO" == "head-changed" ]] && printf 'def456\n' || printf 'abc123\n' ;;
  "pr merge") ;;
  *) exit 64 ;;
esac
MOCK
chmod +x "$test_root/bin/gh"
```

Add helpers that clear the call log, run with `PATH="$test_root/bin:$PATH"`, and assert that `absent`, `multiple`, `wrong-refs`, `draft`, `dirty`, `checks-fail`, and `head-changed` all fail without a recorded `pr merge`. The success scenario must assert these exact calls:

```bash
grep -q '^pr list .*--base main .*--head automation/promote-develop ' "$test_root/calls"
grep -qx 'pr checks 36 --required ' "$test_root/calls"
grep -qx 'pr merge 36 --merge --match-head-commit abc123 ' "$test_root/calls"
```

- [ ] **Step 2: Run the test and verify RED**

Run `bash .github/scripts/test-merge-promotion-pr.sh`.

Expected: FAIL because `.github/scripts/merge-promotion-pr.sh` does not exist.

- [ ] **Step 3: Implement the minimal guarded merge**

Create `.github/scripts/merge-promotion-pr.sh` with this flow:

```bash
#!/usr/bin/env bash
set -euo pipefail
promotion_head="automation/promote-develop"
promotion_base="main"
pr_json="$(gh pr list --state open --base "$promotion_base" --head "$promotion_head" \
  --json number,headRefName,baseRefName,headRefOid,isDraft,mergeStateStatus)"
pr_count="$(jq 'length' <<< "$pr_json")"
[[ "$pr_count" -eq 1 ]] || { echo "Expected exactly one promotion PR; found $pr_count." >&2; exit 1; }

pr_number="$(jq -r '.[0].number' <<< "$pr_json")"
head_ref="$(jq -r '.[0].headRefName' <<< "$pr_json")"
base_ref="$(jq -r '.[0].baseRefName' <<< "$pr_json")"
head_sha="$(jq -r '.[0].headRefOid' <<< "$pr_json")"
is_draft="$(jq -r '.[0].isDraft' <<< "$pr_json")"
merge_state="$(jq -r '.[0].mergeStateStatus' <<< "$pr_json")"

[[ "$head_ref" == "$promotion_head" && "$base_ref" == "$promotion_base" ]] || { echo "Unexpected PR refs." >&2; exit 1; }
[[ "$is_draft" == "false" ]] || { echo "PR #$pr_number is a draft." >&2; exit 1; }
[[ "$merge_state" == "CLEAN" ]] || { echo "PR #$pr_number is not clean: $merge_state." >&2; exit 1; }

gh pr checks "$pr_number" --required
current_head_sha="$(gh pr view "$pr_number" --json headRefOid --jq '.headRefOid')"
[[ "$current_head_sha" == "$head_sha" ]] || { echo "PR head changed; rerun after checks." >&2; exit 1; }
gh pr merge "$pr_number" --merge --match-head-commit "$head_sha"
```

After a successful merge, append the PR number and verified SHA to `GITHUB_STEP_SUMMARY` only when that variable is non-empty.

- [ ] **Step 4: Run syntax and regression tests**

Run:

```bash
bash -n .github/scripts/merge-promotion-pr.sh
bash -n .github/scripts/test-merge-promotion-pr.sh
bash .github/scripts/test-merge-promotion-pr.sh
```

Expected: both syntax checks exit `0` and the regression suite prints `Guarded promotion merge tests passed.`

- [ ] **Step 5: Commit Task 1**

```bash
git add .github/scripts/merge-promotion-pr.sh .github/scripts/test-merge-promotion-pr.sh
git commit -m "ci: guard production promotion merges"
```

### Task 2: Human-triggered production workflow

**Files:**
- Create: `.github/workflows/promote-to-production.yml`
- Modify: `.github/workflows/promote-develop.yml`
- Modify: `.github/scripts/test-merge-promotion-pr.sh`

**Interfaces:**
- Consumes: Task 1's `.github/scripts/merge-promotion-pr.sh` and `RELEASE_PLEASE_TOKEN`.
- Produces: workflow `Promote to production`, available only through `workflow_dispatch` and serialized by concurrency group `promote-to-production`.

- [ ] **Step 1: Add failing workflow assertions**

Append before the test's success message:

```bash
workflow="$script_dir/../workflows/promote-to-production.yml"
promotion_workflow="$script_dir/../workflows/promote-develop.yml"
grep -q '^name: Promote to production$' "$workflow"
grep -q '^  workflow_dispatch:$' "$workflow"
grep -q 'RELEASE_PLEASE_TOKEN' "$workflow"
grep -q 'run: .github/scripts/merge-promotion-pr.sh' "$workflow"
grep -q 'Promote to production' "$promotion_workflow"
! grep -q 'Create a merge commit' "$promotion_workflow"
```

- [ ] **Step 2: Run the test and verify RED**

Run `bash .github/scripts/test-merge-promotion-pr.sh`.

Expected: FAIL because `.github/workflows/promote-to-production.yml` is absent.

- [ ] **Step 3: Create the workflow and update the PR instructions**

Create `.github/workflows/promote-to-production.yml`:

```yaml
name: Promote to production

on:
  workflow_dispatch:

concurrency:
  group: promote-to-production
  cancel-in-progress: false

permissions:
  contents: write
  pull-requests: write
  checks: read
  statuses: read

jobs:
  merge-promotion:
    name: Merge validated promotion
    runs-on: ubuntu-latest
    env:
      GH_TOKEN: ${{ secrets.RELEASE_PLEASE_TOKEN }}
      GH_REPO: ${{ github.repository }}
    steps:
      - name: Checkout trusted workflow source
        uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - name: Merge the validated promotion
        shell: bash
        run: .github/scripts/merge-promotion-pr.sh
```

Change `.github/workflows/promote-develop.yml` so the generated PR asks the operator to review, wait for the five checks, then run **Actions → Promote to production → Run workflow**. It must explicitly prohibit the standard merge button, **Update branch**, squash, and rebase.

- [ ] **Step 4: Verify GREEN and YAML syntax**

Run:

```bash
bash .github/scripts/test-merge-promotion-pr.sh
ruby -e "require 'yaml'; Dir['.github/workflows/*.yml'].each { |f| YAML.parse_file(f) }"
git diff --check
```

Expected: all commands exit `0`.

- [ ] **Step 5: Commit Task 2**

```bash
git add .github/workflows/promote-to-production.yml .github/workflows/promote-develop.yml .github/scripts/test-merge-promotion-pr.sh
git commit -m "ci: add manual production promotion action"
```

### Task 3: Documentation and complete local verification

**Files:**
- Modify: `docs/quality-ci-cd.md`

**Interfaces:**
- Consumes: the exact workflow delivered in Task 2.
- Produces: the canonical operator procedure and PR #36 recovery instructions.

- [ ] **Step 1: Verify the documentation test is RED**

Run:

```bash
rg -q 'Actions.*Promote to production.*Run workflow' docs/quality-ci-cd.md
! rg -q 'sélectionner explicitement \*\*Create a merge commit\*\*' docs/quality-ci-cd.md
```

Expected: non-zero because the old button-based instructions remain.

- [ ] **Step 2: Document the guarded flow**

Replace the old paragraph with:

```markdown
Pour publier la promotion, vérifier la PR et attendre les cinq checks, puis
lancer **Actions → Promote to production → Run workflow**. Cette action vérifie
à nouveau la PR et son SHA, puis impose un merge commit. Ne jamais utiliser le
bouton de fusion standard, **Update branch**, squash ou rebase sur une PR de
promotion.

Après une fusion correcte, `develop` devient un ancêtre de `main`, aucune PR de
promotion vide n'est recréée et Release Please peut analyser les commits
`feat:`/`fix:`. Pour récupérer l'incident des PR #34/#35, publier la PR #36 avec
cette action après le retour au vert de tous ses checks.
```

In the flow diagram, replace `Revue + CI + merge manuel` with `Revue + CI + action Promote to production`.

- [ ] **Step 3: Run full local verification**

```bash
bash .github/scripts/test-prepare-promotion-branch.sh
bash .github/scripts/test-merge-promotion-pr.sh
bash -n .github/scripts/prepare-promotion-branch.sh
bash -n .github/scripts/merge-promotion-pr.sh
ruby -e "require 'yaml'; Dir['.github/workflows/*.yml'].each { |f| YAML.parse_file(f) }"
rg -q 'Actions.*Promote to production.*Run workflow' docs/quality-ci-cd.md
! rg -q 'sélectionner explicitement \*\*Create a merge commit\*\*' docs/quality-ci-cd.md
git diff --check
```

Expected: every command exits `0`.

- [ ] **Step 4: Commit Task 3**

```bash
git add docs/quality-ci-cd.md
git commit -m "docs: explain guarded production promotion"
```

- [ ] **Step 5: Review the complete branch**

```bash
git diff --stat origin/develop...HEAD
git diff --check origin/develop...HEAD
git log --oneline origin/develop..HEAD
```

Expected: one design commit and three focused implementation commits; no branch-protection JSON or Release Please configuration changes.

### Task 4: Publish and recover production

**Files:**
- No additional local files.

**Interfaces:**
- Consumes: the completed branch, GitHub checks, PR #36, and both production workflows.
- Produces: implementation on `develop`, a true merge commit on `main`, no replacement promotion PR, and a Release Please version PR.

- [ ] **Step 1: Push and open the implementation PR**

Push `agent/guaranteed-promotion-merge`, then create a PR targeting `develop` titled `ci: guarantee production promotion merge commits` with a body summarizing the root cause, safeguards, tests, and PR #36 recovery.

- [ ] **Step 2: Wait for checks and merge into `develop`**

```bash
implementation_pr="$(gh pr list --state open --base develop --head agent/guaranteed-promotion-merge --json number --jq '.[0].number')"
test -n "$implementation_pr"
gh pr checks "$implementation_pr" --watch
gh pr merge "$implementation_pr" --squash --delete-branch
```

Expected: the implementation lands with the linear-history method required by `develop`.

- [ ] **Step 3: Validate the rebuilt PR #36**

```bash
gh pr view 36 --json headRefName,baseRefName,headRefOid,isDraft,mergeStateStatus,statusCheckRollup
gh pr checks 36 --watch --required
```

Expected: exact promotion refs, not draft, `CLEAN`, and all five checks successful.

- [ ] **Step 4: Dispatch the guarded action**

```bash
gh workflow run promote-to-production.yml --ref develop
promotion_run="$(gh run list --workflow promote-to-production.yml --event workflow_dispatch --limit 1 --json databaseId --jq '.[0].databaseId')"
test -n "$promotion_run"
gh run watch "$promotion_run"
```

Expected: the action succeeds and PR #36 is merged with a merge commit.

- [ ] **Step 5: Verify ancestry, loop termination, and Release Please**

```bash
git fetch origin main develop
git merge-base --is-ancestor origin/develop origin/main
gh pr list --state open --base main --head automation/promote-develop
gh run list --workflow release-please.yml --branch main --limit 3
gh pr list --state open --base main --search 'head:release-please--branches--main'
```

Expected: ancestry exits `0`, no open promotion PR exists, the latest Release Please run succeeds, and its next-version PR is open or updated. If the run succeeds without a PR, inspect its logs before any further mutation; never fabricate a version commit or tag.
