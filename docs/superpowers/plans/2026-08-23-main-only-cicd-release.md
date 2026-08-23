# Main-Only CI/CD and Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the `develop → main` promotion system with protected pull requests directly into `main`, Conventional Commit squash titles, Release Please releases, and main-targeted Dependabot updates.

**Architecture:** A trusted, read-only metadata workflow validates Conventional Commit pull request titles from the default branch, while the CI workflow runs five parallel application checks on every non-draft pull request into `main`. Release Please alone reacts to pushes on `main` and owns release pull requests, changelog updates, SemVer tags, and GitHub Releases; versioned GitHub settings reproduce the matching squash-only branch protection. The repository migration lands through one PR before remote defaults, protection, and obsolete branches are changed in a guarded order.

**Tech Stack:** GitHub Actions, Bash, GitHub CLI/API, Release Please v5, Dependabot v2, Composer/PHP 8.4, npm/Node 22, Pest, Vitest, Docker Buildx.

**Spec:** `docs/superpowers/specs/2026-08-23-main-only-cicd-release-design.md`

## Global Constraints

- `main` is the only primary branch and the only target for CI, Dependabot, and Release Please.
- Application changes enter `main` through pull requests; direct pushes, force-pushes, and branch deletion remain blocked.
- GitHub permits Squash & Merge only and uses the pull request title as the squash commit title.
- Required checks are `Conventional PR title`, `PHP quality`, `Backend tests`, `Frontend quality`, `Vite build`, and `Docker build`.
- Release Please uses SemVer tags named `vX.Y.Z`, updates `CHANGELOG.md`, creates GitHub Releases, and publishes no npm or Composer package.
- `RELEASE_PLEASE_TOKEN` remains the only Actions secret and must cause Release Please pull requests to run normal CI.
- Configuration and documentation changes use the approved TDD exception; executable title validation remains test-first.
- Never delete a remote branch until its tree still matches `main`; never remove a dirty local worktree or branch.
- Coolify remains outside GitHub Actions and observes only `main`.

---

### Task 1: Test and implement Conventional Commit pull request titles

**Files:**
- Create: `.github/scripts/test-validate-pr-title.sh`
- Create: `.github/scripts/validate-pr-title.sh`

**Interfaces:**
- Consumes: one pull request title as shell argument `$1`.
- Produces: exit code `0` for an accepted Conventional Commit title; exit code `1` and a concise diagnostic on stderr otherwise.

- [ ] **Step 1: Write the failing validator test**

Create `.github/scripts/test-validate-pr-title.sh` with executable Bash test cases:

```bash
#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
subject="$script_dir/validate-pr-title.sh"

valid_titles=(
    "feat: add member favorites"
    "fix(auth): preserve the session"
    "feat!: change the public contract"
    "feat(api)!: change the scoped contract"
    "chore(deps): bump locked dependencies"
    "revert: restore the previous behavior"
)

invalid_titles=(
    "Add member favorites"
    "Feat: use an uppercase type"
    "feature: use an unsupported type"
    "fix(auth) missing colon"
    "fix:"
    "fix:  "
    $'fix: \t'
    " fix: leading whitespace"
)

for title in "${valid_titles[@]}"; do
    "$subject" "$title"
done

for title in "${invalid_titles[@]}"; do
    if "$subject" "$title" >/dev/null 2>&1; then
        echo "Expected title to be rejected: $title" >&2
        exit 1
    fi
done

if "$subject" >/dev/null 2>&1; then
    echo "Expected a missing title to be rejected" >&2
    exit 1
fi

echo "Pull request title validation tests passed."
```

- [ ] **Step 2: Run the test to verify RED**

Run:

```bash
chmod +x .github/scripts/test-validate-pr-title.sh
.github/scripts/test-validate-pr-title.sh
```

Expected: FAIL because `.github/scripts/validate-pr-title.sh` does not exist.

- [ ] **Step 3: Implement the minimal validator**

Create `.github/scripts/validate-pr-title.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

title="${1:-}"
pattern='^(feat|fix|perf|refactor|docs|test|build|ci|chore|revert)(\([a-z0-9][a-z0-9._/-]*\))?!?: [^[:space:]].*$'

if [[ ! "$title" =~ $pattern ]]; then
    echo "Pull request title must follow Conventional Commits, for example: feat(scope): describe the change" >&2
    exit 1
fi
```

Make it executable:

```bash
chmod +x .github/scripts/validate-pr-title.sh
```

- [ ] **Step 4: Run the test to verify GREEN**

Run:

```bash
.github/scripts/test-validate-pr-title.sh
```

Expected: `Pull request title validation tests passed.`

- [ ] **Step 5: Commit the tested validator**

```bash
git add .github/scripts/test-validate-pr-title.sh .github/scripts/validate-pr-title.sh
git commit -m "ci: validate conventional pull request titles"
```

---

### Task 2: Simplify CI to pull requests into `main`

**Files:**
- Modify: `.github/workflows/ci.yml`
- Create: `.github/workflows/pr-title.yml`
- Delete: `.github/workflows/promote-develop.yml`
- Delete: `.github/workflows/promote-to-production.yml`
- Delete: `.github/scripts/prepare-promotion-branch.sh`
- Delete: `.github/scripts/test-prepare-promotion-branch.sh`
- Delete: `.github/scripts/merge-promotion-pr.sh`
- Delete: `.github/scripts/test-merge-promotion-pr.sh`

**Interfaces:**
- Consumes: non-draft `pull_request` events whose base is `main`, plus trusted `pull_request_target` metadata events.
- Produces: the six stable check names required by `main-protection.json`.

- [ ] **Step 1: Separate trusted title validation and remove the `develop` trigger**

Change the workflow trigger to:

```yaml
on:
  pull_request:
    branches:
      - main
    types:
      - opened
      - synchronize
      - reopened
      - ready_for_review
```

Create `.github/workflows/pr-title.yml` so title edits are revalidated and pull
request code can never replace the validator:

```yaml
name: PR title

on:
  pull_request_target:
    branches:
      - main
    types:
      - opened
      - edited
      - synchronize
      - reopened
      - ready_for_review

permissions:
  contents: read

jobs:
  conventional-pr-title:
    name: Conventional PR title
    if: github.event.pull_request.draft == false
    runs-on: ubuntu-latest
    env:
      PR_TITLE: ${{ github.event.pull_request.title }}
    steps:
      - name: Checkout trusted validator
        uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          ref: ${{ github.event.repository.default_branch }}
          persist-credentials: false
      - name: Validate pull request title
        run: .github/scripts/validate-pr-title.sh "$PR_TITLE"
```

The title enters the shell only through `env`, never through direct expression interpolation in `run`. The workflow has read-only contents access, checks out the default branch explicitly, and never checks out or executes pull request code.

- [ ] **Step 2: Add Composer download caching**

In `php-quality`, `backend-tests`, `frontend-quality`, and `vite-build`, add the following immediately after `Set up PHP` and before `composer install`:

```yaml
      - name: Resolve Composer cache directory
        id: composer-cache
        run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"
      - name: Cache Composer downloads
        uses: actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9 # v6.1.0
        with:
          path: ${{ steps.composer-cache.outputs.dir }}
          key: ${{ runner.os }}-composer-${{ hashFiles('**/composer.lock') }}
          restore-keys: |
            ${{ runner.os }}-composer-
```

Keep npm caching through `actions/setup-node`, the Docker GHA cache, all five existing check names, and full job parallelism.

- [ ] **Step 3: Delete promotion automation**

Delete both promotion workflows and their four implementation/regression scripts listed in this task. Keep `release-please.yml` unchanged: it is already the sole release/tag workflow and targets `main`.

- [ ] **Step 4: Validate workflow structure**

Run:

```bash
ruby -e 'require "yaml"; Dir[".github/workflows/*.{yml,yaml}"].each { |f| YAML.parse_file(f); puts "valid #{f}" }'
rg -n 'branches:|Conventional PR title|actions/cache@|target-branch' .github/workflows
rg -n 'git push|gh release create|npm publish|composer publish' .github/workflows || true
```

Expected: all workflow YAML files parse; CI and PR title validation name only `main`; only Release Please contains `target-branch: main`; no direct push, manual release creation, or package publication command exists.

- [ ] **Step 5: Re-run executable script tests**

```bash
.github/scripts/test-validate-pr-title.sh
bash tests/Infrastructure/NginxInertiaHeadersTest.sh
```

Expected: both tests pass.

- [ ] **Step 6: Commit the simplified workflows**

```bash
git add .github/workflows .github/scripts
git commit -m "ci: run protected checks directly on main pull requests"
```

---

### Task 3: Point Dependabot and versioned GitHub settings at `main`

**Files:**
- Modify: `.github/dependabot.yml`
- Modify: `.github/settings/repository.json`
- Modify: `.github/settings/main-protection.json`
- Modify: `.github/settings/README.md`
- Delete: `.github/settings/develop-protection.json`
- Verify: `.github/settings/workflow-permissions.json`
- Verify: `.github/workflows/release-please.yml`
- Verify: `release-please-config.json`
- Verify: `.release-please-manifest.json`

**Interfaces:**
- Consumes: GitHub repository settings API and the existing `RELEASE_PLEASE_TOKEN` Actions secret.
- Produces: reproducible default-branch, merge-method, workflow-permission, and `main` protection API payloads.

- [ ] **Step 1: Redirect every Dependabot ecosystem**

Replace all three `target-branch: 'develop'` values in `.github/dependabot.yml` with:

```yaml
    target-branch: 'main'
```

Preserve the Composer, npm, and GitHub Actions ecosystems, weekly schedule, five-day cooldown, limits, and separate groups.

- [ ] **Step 2: Configure squash-only repository settings**

Replace `.github/settings/repository.json` with:

```json
{
    "default_branch": "main",
    "delete_branch_on_merge": true,
    "allow_squash_merge": true,
    "allow_merge_commit": false,
    "allow_rebase_merge": false,
    "squash_merge_commit_title": "PR_TITLE",
    "squash_merge_commit_message": "PR_BODY"
}
```

- [ ] **Step 3: Require the six checks on a linear `main`**

In `.github/settings/main-protection.json`, make `required_status_checks.contexts` exactly:

```json
[
    "Conventional PR title",
    "PHP quality",
    "Backend tests",
    "Frontend quality",
    "Vite build",
    "Docker build"
]
```

Set `required_linear_history` to `true`. Preserve strict status checks, admin enforcement, required pull requests with zero approvals, conversation resolution, and all force-push/deletion prohibitions.

- [ ] **Step 4: Remove obsolete protection and update application commands**

Delete `.github/settings/develop-protection.json`. Rewrite `.github/settings/README.md` so application commands are exactly:

```bash
gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json
gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json
```

The audit section must read repository settings, workflow permissions, `main` protection, branches, workflows, variables, and secret names. It must state that remote branch deletion happens only after the migration PR merges and the default branch is `main`.

- [ ] **Step 5: Validate configuration and release ownership**

Run:

```bash
php -r 'foreach (glob(".github/settings/*.json") as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo "valid $file\n"; } json_decode(file_get_contents("release-please-config.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents(".release-please-manifest.json"), true, 512, JSON_THROW_ON_ERROR);'
rg -n "package-ecosystem|target-branch" .github/dependabot.yml
rg -n "include-v-in-tag|release-type|package-name|changelog-path" release-please-config.json
rg -n "release-please|gh release|git tag|npm publish" .github/workflows
```

Expected: JSON parsing succeeds; all three Dependabot targets are `main`; Release Please remains `simple`, tags include `v`, and no competing release command exists.

- [ ] **Step 6: Commit configuration**

```bash
git add .github/dependabot.yml .github/settings .github/workflows/release-please.yml release-please-config.json .release-please-manifest.json
git commit -m "ci: configure repository automation for main"
```

---

### Task 4: Replace the obsolete contribution and release documentation

**Files:**
- Create: `CONTRIBUTING.md`
- Modify: `README.md`
- Modify: `.github/pull_request_template.md`
- Modify: `docs/product-vision.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/implementation-plan.md`
- Rewrite: `docs/quality-ci-cd.md`
- Delete: `docs/superpowers/plans/2026-08-16-clean-develop-main-promotion.md`
- Delete: `docs/superpowers/plans/2026-08-16-github-ci-release.md`
- Delete: `docs/superpowers/plans/2026-08-16-guaranteed-promotion-merge.md`
- Delete: `docs/superpowers/specs/2026-08-16-clean-develop-main-promotion-design.md`
- Delete: `docs/superpowers/specs/2026-08-16-github-ci-release-design.md`
- Delete: `docs/superpowers/specs/2026-08-16-guaranteed-promotion-merge-design.md`

**Interfaces:**
- Consumes: the approved main-only design and exact check names from Tasks 2–3.
- Produces: one coherent contributor workflow and no operative documentation for the deleted promotion system.

- [ ] **Step 1: Create the contributor guide**

Write `CONTRIBUTING.md` in French with these concrete sections:

1. prerequisites and local setup linking back to `README.md`;
2. create a current branch from `main` using `git switch main`, `git pull --ff-only`, then `git switch -c feature/<nom>` (or `fix/`, `chore/`, `docs/`, `refactor/`);
3. run PHP, frontend, build, and Docker checks relevant to the change;
4. open a pull request explicitly targeting `main`;
5. use Conventional Commit titles with the accepted types and breaking-change syntax;
6. wait for all six named checks and resolve conversations;
7. Squash & Merge only;
8. explain that merging application work updates a Release PR but does not publish;
9. explain that deliberately merging the Release PR creates `CHANGELOG.md`, `vX.Y.Z`, and the GitHub Release without publishing a package.

- [ ] **Step 2: Update the README and pull request template**

Add a concise `Contribution et releases` section to `README.md` linking to `CONTRIBUTING.md` and `docs/quality-ci-cd.md`. Replace the pull request template's promotion checklist with:

```markdown
## Intégration et release

- [ ] La pull request cible `main`.
- [ ] Le titre suit Conventional Commits et pourra devenir le commit squash.
- [ ] Aucun déploiement ou package n'est publié par cette pull request.
```

- [ ] **Step 3: Align source-of-truth documents**

Update the four existing documents as follows:

- `docs/product-vision.md`: state that every PR into `main` is validated automatically.
- `docs/technical-architecture.md`: replace separate integration/production branches and promotion automation with protected PRs directly into stable `main`; retain Coolify's observation of `main`.
- `docs/implementation-plan.md`: replace the obsolete task-2 file list and checklist with the implemented main-only CI, Release Please, Dependabot, and protection deliverables.
- `docs/quality-ci-cd.md`: describe the six checks, branch protection, Conventional Commit squash titles, weekly Dependabot PRs into `main`, Release Please accumulation/publication semantics, permissions, failure behavior, and the final `branch → PR main → CI → squash → Release PR → release` diagram.

- [ ] **Step 4: Delete historical plans that prescribe the removed system**

Delete the six promotion/old-CI plan and design documents listed under **Files**. Preserve the new spec and this implementation plan.

- [ ] **Step 5: Prove no operative `develop` dependency remains**

Run:

```bash
rg -n --hidden --glob '!vendor/**' --glob '!node_modules/**' --glob '!.git/**' --glob '!.worktrees/**' '\bdevelop\b' . || true
rg -n --hidden --glob '!vendor/**' --glob '!node_modules/**' --glob '!.git/**' --glob '!.worktrees/**' 'automation/promote-develop|Promote to production|Promote develop' . || true
```

Expected: the only `develop` matches are historical state and safety notes in the new 2026-08-23 spec/plan plus `ralouphie/getallheaders/tree/develop` in `composer.lock`; no promotion workflow phrase remains outside the new migration documents.

- [ ] **Step 6: Commit documentation**

```bash
git add CONTRIBUTING.md README.md .github/pull_request_template.md docs
git commit -m "docs: document direct contributions to main"
```

---

### Task 5: Run the complete local verification suite

**Files:**
- Verify only; modify a task-owned file only if a check exposes a migration defect.

**Interfaces:**
- Consumes: the complete repository state from Tasks 1–4.
- Produces: evidence that every required CI job and configuration audit passes before push.

- [ ] **Step 1: Verify shell, YAML, and JSON files**

```bash
bash -n .github/scripts/validate-pr-title.sh .github/scripts/test-validate-pr-title.sh tests/Infrastructure/NginxInertiaHeadersTest.sh
.github/scripts/test-validate-pr-title.sh
bash tests/Infrastructure/NginxInertiaHeadersTest.sh
ruby -e 'require "yaml"; Dir[".github/**/*.{yml,yaml}"].each { |f| YAML.parse_file(f); puts "valid #{f}" }'
php -r 'foreach (array_merge(glob(".github/settings/*.json"), ["release-please-config.json", ".release-please-manifest.json"]) as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo "valid $file\n"; }'
git diff --check main...HEAD
```

Expected: all commands exit zero and every YAML/JSON file is reported valid.

- [ ] **Step 2: Verify PHP quality and backend behavior**

```bash
composer lint:check
composer analyse
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test
```

Expected: Pint and PHPStan pass; Pest reports 76 tests and 277 assertions passing or a larger green count if unrelated tests were added upstream.

- [ ] **Step 3: Verify frontend quality, tests, and production build**

```bash
php artisan wayfinder:generate --with-form
npm run lint:check
npm run format:check
npm run types:check
npm test
npm run build
```

Expected: ESLint, Prettier, TypeScript, 12 Vitest tests (or more), and Vite build pass. Network access is required for the configured Bunny Fonts build plugin.

- [ ] **Step 4: Verify the Docker runtime image**

```bash
docker build --target runtime --tag dlp-friends:issue-44 .
```

Expected: the runtime image builds successfully and no registry push occurs.

- [ ] **Step 5: Audit the final diff and branch**

```bash
git status --short --branch
git log --oneline main..HEAD
git diff --stat main...HEAD
git diff --name-status main...HEAD
```

Expected: only the intended commits/files appear; generated Wayfinder modules, dependencies, and build outputs remain ignored; the worktree is clean.

---

### Task 6: Open, validate, and merge the single migration pull request

**Files:**
- Remote GitHub pull request and workflow runs only.

**Interfaces:**
- Consumes: branch `chore/issue-44-main-only-cicd` and the current five-check `main` protection.
- Produces: one squash commit on `main` with the new workflow/configuration; issue #44 remains open until the remote cleanup is complete.

- [ ] **Step 1: Refresh and push the implementation branch**

```bash
git fetch origin
git rebase origin/main
git push --set-upstream origin chore/issue-44-main-only-cicd
```

Expected: rebase is clean and the remote work branch is created without touching `main`.

- [ ] **Step 2: Open one PR directly into `main`**

```bash
gh pr create \
  --base main \
  --head chore/issue-44-main-only-cicd \
  --title "ci: migrate repository automation directly to main" \
  --body $'## Résumé\n\n- exécute la CI et Dependabot directement vers `main`\n- conserve Release Please comme unique mécanisme de release\n- versionne les protections squash-only et documente le nouveau flux\n- prépare la suppression sûre des branches et automatisations obsolètes\n\nRefs #44'
```

Expected: one non-draft PR targets `main`; its title passes the new title validator.

- [ ] **Step 3: Wait for required PR checks**

```bash
gh pr checks --watch --required
```

Expected before the settings migration: the five currently protected application checks pass. The migration PR title is also validated locally; `PR title` cannot run remotely until its trusted workflow exists on the repository's current default branch.

- [ ] **Step 4: Squash-merge the PR**

```bash
pr_number="$(gh pr view --json number --jq '.number')"
head_sha="$(gh pr view --json headRefOid --jq '.headRefOid')"
gh pr merge "$pr_number" --squash --match-head-commit "$head_sha" --delete-branch
```

Expected: the PR merges into `main` as `ci: migrate repository automation directly to main`; no direct push is used.

- [ ] **Step 5: Confirm the post-merge automations**

```bash
gh run list --branch main --limit 10 --json databaseId,name,event,status,conclusion,headSha,url
gh pr list --state open --base main --json number,title,headRefName,url
```

Expected: Release Please runs on the new `main`. If repository history requires a release, one Release PR is open and its CI begins; otherwise the successful Release Please run reports no release change needed.

---

### Task 7: Apply remote settings and clean obsolete branches

**Files:**
- Apply: `.github/settings/repository.json`
- Apply: `.github/settings/workflow-permissions.json`
- Apply: `.github/settings/main-protection.json`
- Mutate: GitHub repository branches and local worktrees/branches.

**Interfaces:**
- Consumes: merged `main`, successful PR checks, versioned API payloads, and the pre-migration branch audit.
- Produces: a protected main-only remote repository, a clean local checkout, and completed issue #44.

- [ ] **Step 1: Re-audit remote branch trees before any deletion**

```bash
main_sha="$(gh api repos/zdossantos/dlp-friends/git/ref/heads/main --jq '.object.sha')"
main_parent_sha="$(gh api "repos/zdossantos/dlp-friends/git/commits/$main_sha" --jq '.parents[0].sha')"
main_parent_tree="$(gh api "repos/zdossantos/dlp-friends/git/commits/$main_parent_sha" --jq '.tree.sha')"
develop_sha="$(gh api repos/zdossantos/dlp-friends/git/ref/heads/develop --jq '.object.sha')"
develop_tree="$(gh api "repos/zdossantos/dlp-friends/git/commits/$develop_sha" --jq '.tree.sha')"
promotion_sha="$(gh api repos/zdossantos/dlp-friends/git/ref/heads/automation/promote-develop --jq '.object.sha')"
promotion_tree="$(gh api "repos/zdossantos/dlp-friends/git/commits/$promotion_sha" --jq '.tree.sha')"
printf 'pre-migration=%s\ndevelop=%s\npromotion=%s\n' "$main_parent_tree" "$develop_tree" "$promotion_tree"
test "$main_parent_tree" = "$develop_tree"
test "$main_parent_tree" = "$promotion_tree"
git fetch origin main develop automation/promote-develop
git diff --name-status "$main_parent_sha" "$main_sha"
```

Expected: both obsolete branches still match the parent of the issue 44 squash commit, and the displayed parent-to-`main` diff contains only this migration. If a comparison fails or the diff contains unrelated changes, stop before changing the default branch or deleting anything.

- [ ] **Step 2: Apply repository and workflow settings**

From a checkout of the merged `main`, run:

```bash
gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json
```

Expected: `main` becomes default, only squash merge remains enabled, merged branches are deleted, workflow permissions default to read, and Actions may create pull requests.

- [ ] **Step 3: Apply the six-check `main` protection**

```bash
gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json
```

Immediately verify:

```bash
gh api repos/zdossantos/dlp-friends/branches/main/protection
```

Expected: strict mode, six exact checks, required PRs, linear history, admin enforcement, conversation resolution, and no force-push or deletion.

- [ ] **Step 4: Remove `develop` protection and delete obsolete remote branches**

Only after Steps 1–3 pass:

```bash
gh api --method DELETE repos/zdossantos/dlp-friends/branches/develop/protection
gh api --method DELETE repos/zdossantos/dlp-friends/git/refs/heads/develop
gh api --method DELETE repos/zdossantos/dlp-friends/git/refs/heads/automation/promote-develop
```

Expected: each delete succeeds; `main` remains protected and default.

- [ ] **Step 5: Audit remote acceptance criteria**

```bash
gh repo view --json defaultBranchRef,mergeCommitAllowed,rebaseMergeAllowed,squashMergeAllowed,deleteBranchOnMerge
gh api repos/zdossantos/dlp-friends/branches --paginate --jq '.[].name'
gh workflow list --all
gh api repos/zdossantos/dlp-friends/actions/permissions/workflow
gh api repos/zdossantos/dlp-friends/actions/variables
gh secret list --app actions
gh pr list --state open --base main --json number,title,headRefName,url
```

Expected: `main` is default; only squash is enabled; neither obsolete branch exists; active workflows are CI, PR title, Release Please, and Dependabot Updates; no Actions variable exists; `RELEASE_PLEASE_TOKEN` is the only secret.

If a Release PR is open, wait for all six checks with `gh pr checks --watch --required <number>` but do not merge it: release publication remains a deliberate product decision.

- [ ] **Step 6: Refresh `main` and re-check local worktree safety**

From `/Users/zdossantos/Projects/dlp-friends`:

```bash
git fetch --all --prune
git switch main
git pull --ff-only
git worktree list --porcelain
git -C .worktrees/clean-promotion-workflow status --short --branch
git -C .worktrees/profile-onboarding-admin-dashboard status --short --branch
```

Expected: root `main` matches GitHub and both old worktrees are still clean. If either is dirty, preserve it and report it.

- [ ] **Step 7: Remove clean obsolete worktrees and local branches**

When both status checks are clean:

```bash
git worktree remove .worktrees/clean-promotion-workflow
git worktree remove .worktrees/profile-onboarding-admin-dashboard
git branch -D \
  agent/adult-active-accounts \
  agent/bootstrap-release-1-0-0 \
  agent/clean-promotion-workflow \
  agent/fix-promotion-checkout \
  agent/github-ci-release \
  agent/guaranteed-promotion-merge \
  agent/reconcile-main-history \
  agent/share-docker-cache \
  chore/up-to-date \
  dependabot/npm_and_yarn/develop/npm-dependencies-6c1d560dd3 \
  develop \
  develop-to-main \
  feature/profile-onboarding-admin-dashboard
```

Expected: only `main` and the current issue worktree branch remain locally.

- [ ] **Step 8: Remove the completed issue worktree last**

After ensuring the migration PR is merged and root `main` contains the plan/spec:

```bash
git worktree remove .worktrees/issue-44-main-only-cicd
git branch -D chore/issue-44-main-only-cicd
git worktree prune
git branch -a -vv
git status --short --branch
```

Expected: root `main` is clean, no obsolete local or remote branch remains, and all durable documentation is available from the root checkout.

- [ ] **Step 9: Close issue 44 with final evidence**

```bash
gh issue close 44 \
  --reason completed \
  --comment "Migration terminée : `main` est la branche par défaut et de release, les six checks sont obligatoires, Release Please et Dependabot ciblent `main`, et les branches/automatisations obsolètes ont été supprimées."
```

Expected: issue #44 is closed only after every repository and cleanup acceptance check has passed.
