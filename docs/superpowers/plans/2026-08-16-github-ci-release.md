# GitHub CI and Release Automation Implementation Plan

> **Superseded promotion steps:** The direct `develop → main` instructions in this historical plan are replaced by `docs/superpowers/plans/2026-08-16-clean-develop-main-promotion.md`. Do not execute the old `--head develop` commands.

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Deliver repeatable Laravel/Vue quality gates, automated develop-to-main promotion, Release Please versioning, Dependabot updates, and enforceable GitHub repository protections.

**Architecture:** One pull-request workflow exposes five independent required checks for PHP quality, backend tests, frontend quality, Vite compilation, and Docker construction. Separate least-privilege workflows create the long-lived promotion pull request and run Release Please, while versioned JSON payloads make the remote repository settings auditable and repeatable.

**Tech Stack:** GitHub Actions, gh CLI, Laravel 13, PHP 8.4, Composer 2, Pest 5, Larastan/PHPStan, Laravel Pint, Node.js 22, npm, Vue 3, TypeScript, Vitest, Vite, MySQL 8.4, Docker, Dependabot, Release Please 5.

## Global Constraints

- develop is the default integration branch; main is the production branch.
- All application changes reach develop by pull request.
- Promotion from develop to main uses a merge commit, is reviewed manually, and is never auto-merged.
- Pull requests into develop and main must pass all five named CI checks.
- No workflow deploys to Coolify or stores a Coolify credential.
- Release Please manages SemVer, CHANGELOG.md, v-prefixed tags, and GitHub Releases without publishing npm or Composer packages.
- Composer, npm, and GitHub Actions dependency updates target develop.
- Workflow permissions follow least privilege and third-party actions are pinned to immutable commits.
- main requires no approval while the repository has only one contributor, but still requires a pull request, successful checks, and resolved conversations.
- No workflow sends real e-mail or receives production secrets.

---

## File map

- Modify package.json and package-lock.json: expose the frontend test contract and lock Vitest dependencies.
- Modify composer.json: expose explicit PHP unit-test and static-analysis contracts.
- Create vitest.config.ts: define Vue test discovery and the jsdom environment.
- Replace .github/workflows/tests.yml with .github/workflows/ci.yml: own all pull-request checks.
- Create .github/workflows/promote-develop.yml: own idempotent develop-to-main pull-request creation.
- Create .github/workflows/release-please.yml: own release PRs, changelog updates, tags, and GitHub Releases.
- Create release-please-config.json and .release-please-manifest.json: own Release Please behavior and current version state.
- Modify .github/dependabot.yml: own dependency update scheduling for all three ecosystems.
- Create .github/pull_request_template.md and .github/CODEOWNERS: own lightweight contribution guidance and default ownership.
- Create .github/settings/repository.json: define auditable repository merge/default-branch settings.
- Create .github/settings/workflow-permissions.json: define the default GITHUB_TOKEN policy.
- Create .github/settings/develop-protection.json and .github/settings/main-protection.json: define branch protection payloads.
- Create .github/settings/README.md: document exact commands for applying and auditing remote settings.
- Modify docs/quality-ci-cd.md and README.md: keep the source-of-truth documentation aligned with the implemented commands and solo-maintainer exception.

### Task 1: Establish local quality command contracts

**Files:**
- Modify: package.json
- Modify: package-lock.json
- Modify: composer.json
- Create: vitest.config.ts

**Interfaces:**
- Consumes: the existing npm scripts lint:check, format:check, types:check, and build; the existing Composer scripts lint:check and types:check.
- Produces: npm run test and npm run test:unit; composer test:unit and composer analyse; a Vitest configuration that discovers resources/js/**/*.spec.ts.

- [ ] **Step 1: Prove the missing command contracts**

Run:

    npm run test:unit
    composer test:unit
    composer analyse

Expected: each command that is not yet declared exits non-zero and reports a missing script.

- [ ] **Step 2: Install and lock the frontend test dependencies**

Run:

    npm install --save-dev vitest @vue/test-utils jsdom

Expected: package.json gains the three development dependencies and package-lock.json records exact resolved versions.

- [ ] **Step 3: Add the frontend test scripts**

Make the package.json scripts contain:

    "test": "npm run test:unit",
    "test:unit": "vitest run --passWithNoTests"

Keep lint as the developer autofix command and retain lint:check, format:check, types:check, and build as non-mutating CI commands.

- [ ] **Step 4: Add the PHP test and analysis scripts**

Make the relevant composer.json scripts contain:

    "analyse": [
        "phpstan analyse"
    ],
    "types:check": [
        "@analyse"
    ],
    "test:unit": [
        "@php artisan test --testsuite=Unit"
    ]

Keep test as the full PHP gate: config clear, Pint check, analysis, and php artisan test. Update ci:check so it also runs npm run test after the existing frontend lint, format, and type checks.

- [ ] **Step 5: Configure Vitest for Vue**

Create vitest.config.ts with:

    import vue from '@vitejs/plugin-vue';
    import { defineConfig } from 'vitest/config';

    export default defineConfig({
        plugins: [vue()],
        test: {
            environment: 'jsdom',
            include: ['resources/js/**/*.spec.ts'],
        },
    });

- [ ] **Step 6: Verify every local contract**

Run:

    composer lint:check
    composer analyse
    composer test:unit
    php artisan test
    npm run lint:check
    npm run format:check
    npm run types:check
    npm run test
    npm run build

Expected: every command exits zero; npm run test reports no test files without treating their temporary absence as an error.

- [ ] **Step 7: Commit the command contracts**

Run:

    git add composer.json package.json package-lock.json vitest.config.ts
    git commit -m "test: establish PHP and Vue quality commands"

Expected: one focused commit containing only the script, dependency, and Vitest configuration changes.

### Task 2: Replace the starter workflow with parallel required checks

**Files:**
- Delete: .github/workflows/tests.yml
- Create: .github/workflows/ci.yml

**Interfaces:**
- Consumes: composer lint:check, composer analyse, php artisan test, npm run lint:check, npm run format:check, npm run types:check, npm run test, npm run build, and the repository Dockerfile.
- Produces: required check names PHP quality, Backend tests, Frontend quality, Vite build, and Docker build.

- [ ] **Step 1: Record the current workflow mismatch**

Run:

    rg -n "branches:|composer setup|composer ci:check" .github/workflows/tests.yml

Expected: the existing workflow targets push to main and a broad pull_request event, and runs one combined job rather than the five documented responsibilities.

- [ ] **Step 2: Replace tests.yml with ci.yml**

Create .github/workflows/ci.yml with this structure:

    name: CI

    on:
      pull_request:
        branches:
          - develop
          - main
        types:
          - opened
          - synchronize
          - reopened
          - ready_for_review

    concurrency:
      group: ci-${{ github.event.pull_request.number }}
      cancel-in-progress: true

    permissions:
      contents: read

    jobs:
      php-quality:
        name: PHP quality
        if: github.event.pull_request.draft == false
        runs-on: ubuntu-latest
        steps:
          - name: Checkout
            uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
            with:
              persist-credentials: false
          - name: Set up PHP
            uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
            with:
              php-version: '8.4'
              tools: composer:v2
              coverage: none
          - name: Install Composer dependencies
            run: composer install --no-interaction --no-progress --prefer-dist
          - name: Check PHP formatting
            run: composer lint:check
          - name: Run static analysis
            run: composer analyse

      backend-tests:
        name: Backend tests
        if: github.event.pull_request.draft == false
        runs-on: ubuntu-latest
        services:
          mysql:
            image: mysql:8.4.10
            env:
              MYSQL_DATABASE: dlp_friends_test
              MYSQL_USER: dlp_friends
              MYSQL_PASSWORD: test-only-password
              MYSQL_ROOT_PASSWORD: test-only-root-password
            ports:
              - 3306:3306
            options: >-
              --health-cmd="mysqladmin ping -h 127.0.0.1 -ptest-only-root-password --silent"
              --health-interval=10s
              --health-timeout=5s
              --health-retries=10
        env:
          APP_ENV: testing
          APP_KEY: base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: dlp_friends_test
          DB_USERNAME: dlp_friends
          DB_PASSWORD: test-only-password
          MAIL_MAILER: array
          CACHE_STORE: array
          QUEUE_CONNECTION: sync
          SESSION_DRIVER: array
        steps:
          - name: Checkout
            uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
            with:
              persist-credentials: false
          - name: Set up PHP
            uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
            with:
              php-version: '8.4'
              tools: composer:v2
              coverage: none
          - name: Install Composer dependencies
            run: composer install --no-interaction --no-progress --prefer-dist
          - name: Run database migrations
            run: php artisan migrate --force
          - name: Run Pest
            run: php artisan test

      frontend-quality:
        name: Frontend quality
        if: github.event.pull_request.draft == false
        runs-on: ubuntu-latest
        steps:
          - name: Checkout
            uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
            with:
              persist-credentials: false
          - name: Set up Node.js
            uses: actions/setup-node@820762786026740c76f36085b0efc47a31fe5020 # v7.0.0
            with:
              node-version: '22'
              cache: npm
          - name: Install npm dependencies
            run: npm ci
          - name: Check frontend lint
            run: npm run lint:check
          - name: Check frontend formatting
            run: npm run format:check
          - name: Check TypeScript
            run: npm run types:check
          - name: Run Vitest
            run: npm run test

      vite-build:
        name: Vite build
        if: github.event.pull_request.draft == false
        runs-on: ubuntu-latest
        steps:
          - name: Checkout
            uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
            with:
              persist-credentials: false
          - name: Set up PHP
            uses: shivammathur/setup-php@f3e473d116dcccaddc5834248c87452386958240 # v2
            with:
              php-version: '8.4'
              tools: composer:v2
              coverage: none
          - name: Set up Node.js
            uses: actions/setup-node@820762786026740c76f36085b0efc47a31fe5020 # v7.0.0
            with:
              node-version: '22'
              cache: npm
          - name: Install Composer dependencies
            run: composer install --no-interaction --no-progress --prefer-dist
          - name: Install npm dependencies
            run: npm ci
          - name: Build Vite assets
            run: npm run build

      docker-build:
        name: Docker build
        if: github.event.pull_request.draft == false
        runs-on: ubuntu-latest
        steps:
          - name: Checkout
            uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
            with:
              persist-credentials: false
          - name: Build runtime image
            run: docker build --target runtime --tag dlp-friends:ci .

Delete .github/workflows/tests.yml after ci.yml is present.

- [ ] **Step 3: Validate workflow formatting and references**

Run:

    npx prettier --check .github/workflows/ci.yml
    rg -n "PHP quality|Backend tests|Frontend quality|Vite build|Docker build" .github/workflows/ci.yml
    rg -n "Coolify|webhook|SMTP_PASSWORD" .github/workflows/ci.yml

Expected: Prettier accepts the YAML, all five check names are present, and the forbidden deployment/production-secret search returns no matches.

- [ ] **Step 4: Execute the same commands outside Actions**

Run:

    composer lint:check
    composer analyse
    php artisan test
    npm run lint:check
    npm run format:check
    npm run types:check
    npm run test
    npm run build
    docker build --target runtime --tag dlp-friends:ci .

Expected: every command exits zero and the Docker build completes without publishing an image.

- [ ] **Step 5: Commit the CI workflow**

Run:

    git add .github/workflows/ci.yml .github/workflows/tests.yml
    git commit -m "ci: add parallel pull request quality gates"

### Task 3: Automate develop-to-main promotion

**Files:**
- Create: .github/workflows/promote-develop.yml

**Interfaces:**
- Consumes: pushes to develop and the repository-scoped GITHUB_TOKEN.
- Produces: at most one open pull request with head develop, base main, and title chore: promote develop to main.

- [ ] **Step 1: Create the idempotent promotion workflow**

Create .github/workflows/promote-develop.yml:

    name: Promote develop

    on:
      push:
        branches:
          - develop

    concurrency:
      group: promote-develop
      cancel-in-progress: false

    permissions:
      contents: read
      pull-requests: write

    jobs:
      promotion-pr:
        name: Open promotion pull request
        runs-on: ubuntu-latest
        env:
          GH_TOKEN: ${{ github.token }}
          GH_REPO: ${{ github.repository }}
        steps:
          - name: Create pull request when absent
            shell: bash
            run: |
              set -euo pipefail
              open_count="$(gh pr list --state open --base main --head develop --json number --jq 'length')"

              if [[ "$open_count" -eq 0 ]]; then
                gh pr create \
                  --base main \
                  --head develop \
                  --title "chore: promote develop to main" \
                  --body $'## Publication\n\nFusionner avec un **merge commit** après validation de la CI. Ne pas utiliser squash ou rebase : Release Please doit lire les Conventional Commits de develop.\n\nAucun déploiement Coolify n’est déclenché par ce workflow.'
              fi

- [ ] **Step 2: Validate idempotence and permissions statically**

Run:

    npx prettier --check .github/workflows/promote-develop.yml
    rg -n "pull-requests: write|gh pr list|gh pr create|open_count" .github/workflows/promote-develop.yml
    rg -n "gh pr merge|auto-merge|Coolify" .github/workflows/promote-develop.yml

Expected: the first search proves the least-privilege and existence-check logic; the second finds only the explanatory Coolify sentence and finds no merge command or auto-merge option.

- [ ] **Step 3: Commit the promotion workflow**

Run:

    git add .github/workflows/promote-develop.yml
    git commit -m "ci: automate develop promotion pull requests"

### Task 4: Configure Release Please for the web application

**Files:**
- Create: release-please-config.json
- Create: .release-please-manifest.json
- Create: .github/workflows/release-please.yml

**Interfaces:**
- Consumes: Conventional Commits merged into main and the RELEASE_PLEASE_TOKEN Actions secret.
- Produces: a release pull request on main, CHANGELOG.md updates, v-prefixed SemVer tags, and GitHub Releases; it produces no package publication.

- [ ] **Step 1: Create the manifest configuration**

Create release-please-config.json:

    {
      "$schema": "https://raw.githubusercontent.com/googleapis/release-please/main/schemas/config.json",
      "release-type": "simple",
      "include-v-in-tag": true,
      "include-component-in-tag": false,
      "changelog-sections": [
        { "type": "feat", "section": "Features" },
        { "type": "fix", "section": "Fixes" },
        { "type": "perf", "section": "Performance" },
        { "type": "docs", "section": "Documentation", "hidden": true },
        { "type": "refactor", "section": "Refactoring", "hidden": true },
        { "type": "test", "section": "Tests", "hidden": true },
        { "type": "chore", "section": "Maintenance", "hidden": true },
        { "type": "ci", "section": "Continuous integration", "hidden": true }
      ],
      "packages": {
        ".": {
          "package-name": "dlp-friends",
          "changelog-path": "CHANGELOG.md"
        }
      }
    }

Create .release-please-manifest.json:

    {
      ".": "0.0.0"
    }

- [ ] **Step 2: Create the release workflow**

Create .github/workflows/release-please.yml:

    name: Release Please

    on:
      push:
        branches:
          - main

    concurrency:
      group: release-please
      cancel-in-progress: false

    permissions:
      contents: write
      issues: write
      pull-requests: write

    jobs:
      release-please:
        name: Create release pull request or release
        runs-on: ubuntu-latest
        steps:
          - name: Run Release Please
            uses: googleapis/release-please-action@45996ed1f6d02564a971a2fa1b5860e934307cf7 # v5.0.0
            with:
              token: ${{ secrets.RELEASE_PLEASE_TOKEN }}
              target-branch: main
              config-file: release-please-config.json
              manifest-file: .release-please-manifest.json

The separate token is required because pull requests created with the built-in GITHUB_TOKEN do not trigger the pull_request CI workflow. Use a fine-grained user token limited to zdossantos/dlp-friends with Contents read/write, Pull requests read/write, and Issues read/write for Release Please labels, then store only that token as the RELEASE_PLEASE_TOKEN repository secret.

- [ ] **Step 3: Validate Release Please configuration**

Run:

    jq empty release-please-config.json .release-please-manifest.json
    npx prettier --check release-please-config.json .release-please-manifest.json .github/workflows/release-please.yml
    rg -n "npm publish|composer publish|Coolify|webhook" .github/workflows/release-please.yml release-please-config.json

Expected: both JSON files parse, Prettier accepts all files, and the forbidden publication/deployment search returns no matches.

- [ ] **Step 4: Commit the release configuration**

Run:

    git add release-please-config.json .release-please-manifest.json .github/workflows/release-please.yml
    git commit -m "ci: configure Release Please"

### Task 5: Add dependency maintenance and auditable GitHub governance

**Files:**
- Modify: .github/dependabot.yml
- Create: .github/pull_request_template.md
- Create: .github/CODEOWNERS
- Create: .github/settings/repository.json
- Create: .github/settings/workflow-permissions.json
- Create: .github/settings/develop-protection.json
- Create: .github/settings/main-protection.json
- Create: .github/settings/README.md

**Interfaces:**
- Consumes: the five CI check names from Task 2 and GitHub repository APIs.
- Produces: weekly dependency PRs to develop and exact JSON inputs for repeatable remote GitHub configuration.

- [ ] **Step 1: Expand Dependabot to all documented ecosystems**

Replace .github/dependabot.yml with:

    version: 2
    updates:
      - package-ecosystem: composer
        directory: /
        target-branch: develop
        schedule:
          interval: weekly
        cooldown:
          default-days: 5
        open-pull-requests-limit: 10
        groups:
          composer-dependencies:
            patterns:
              - '*'

      - package-ecosystem: npm
        directory: /
        target-branch: develop
        schedule:
          interval: weekly
        cooldown:
          default-days: 5
        open-pull-requests-limit: 10
        groups:
          npm-dependencies:
            patterns:
              - '*'

      - package-ecosystem: github-actions
        directory: /
        target-branch: develop
        schedule:
          interval: weekly
        cooldown:
          default-days: 5
        open-pull-requests-limit: 10
        groups:
          github-actions:
            patterns:
              - '*'

- [ ] **Step 2: Add the lightweight pull request template**

Create .github/pull_request_template.md:

    ## Résumé

    Décrire le changement et sa raison.

    ## Vérification

    - [ ] Les contrôles PHP concernés ont été exécutés.
    - [ ] Les contrôles frontend concernés ont été exécutés.
    - [ ] Le build Docker a été vérifié si l’infrastructure change.
    - [ ] Aucun secret ou fichier .env de production n’est inclus.
    - [ ] Le titre ou les commits suivent Conventional Commits.

    ## Livraison

    - [ ] Aucun déploiement Coolify n’est déclenché depuis GitHub Actions.
    - [ ] Une promotion develop vers main sera fusionnée avec un merge commit.

- [ ] **Step 3: Add default ownership**

Create .github/CODEOWNERS:

    * @zdossantos

- [ ] **Step 4: Version the repository and workflow-permission payloads**

Create .github/settings/repository.json:

    {
      "default_branch": "develop",
      "delete_branch_on_merge": true,
      "allow_squash_merge": true,
      "allow_merge_commit": true,
      "allow_rebase_merge": false
    }

Create .github/settings/workflow-permissions.json:

    {
      "default_workflow_permissions": "read",
      "can_approve_pull_request_reviews": true
    }

- [ ] **Step 5: Version the develop protection payload**

Create .github/settings/develop-protection.json:

    {
      "required_status_checks": {
        "strict": true,
        "contexts": [
          "PHP quality",
          "Backend tests",
          "Frontend quality",
          "Vite build",
          "Docker build"
        ]
      },
      "enforce_admins": true,
      "required_pull_request_reviews": {
        "dismiss_stale_reviews": true,
        "require_code_owner_reviews": false,
        "required_approving_review_count": 0,
        "require_last_push_approval": false
      },
      "restrictions": null,
      "required_linear_history": true,
      "allow_force_pushes": false,
      "allow_deletions": false,
      "block_creations": false,
      "required_conversation_resolution": true,
      "lock_branch": false,
      "allow_fork_syncing": false
    }

- [ ] **Step 6: Version the main protection payload**

Create .github/settings/main-protection.json:

    {
      "required_status_checks": {
        "strict": true,
        "contexts": [
          "PHP quality",
          "Backend tests",
          "Frontend quality",
          "Vite build",
          "Docker build"
        ]
      },
      "enforce_admins": true,
      "required_pull_request_reviews": {
        "dismiss_stale_reviews": true,
        "require_code_owner_reviews": false,
        "required_approving_review_count": 0,
        "require_last_push_approval": false
      },
      "restrictions": null,
      "required_linear_history": false,
      "allow_force_pushes": false,
      "allow_deletions": false,
      "block_creations": false,
      "required_conversation_resolution": true,
      "lock_branch": false,
      "allow_fork_syncing": false
    }

The false required_linear_history value permits the required develop-to-main merge commit while preserving every other protection.

- [ ] **Step 7: Document exact application and audit commands**

Create .github/settings/README.md with:

    # Réglages GitHub versionnés

    Ces fichiers sont des entrées exactes pour l’API GitHub du dépôt
    zdossantos/dlp-friends. Ils ne contiennent aucun secret.

    ## Application

        gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
        gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json
        gh api --method PUT repos/zdossantos/dlp-friends/branches/develop/protection --input .github/settings/develop-protection.json
        gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json

    ## Audit

        gh api repos/zdossantos/dlp-friends
        gh api repos/zdossantos/dlp-friends/actions/permissions/workflow
        gh api repos/zdossantos/dlp-friends/branches/develop/protection
        gh api repos/zdossantos/dlp-friends/branches/main/protection

    Appliquer les protections après le premier passage des cinq checks CI,
    afin que GitHub connaisse leurs noms. Ne jamais placer un jeton dans ces
    fichiers.

- [ ] **Step 8: Validate governance files**

Run:

    jq empty .github/settings/*.json
    npx prettier --check .github/dependabot.yml .github/pull_request_template.md .github/settings
    rg -n "target-branch: develop|package-ecosystem" .github/dependabot.yml

Expected: every JSON payload parses, formatting passes, and Dependabot shows exactly composer, npm, and github-actions targeting develop.

- [ ] **Step 9: Commit governance configuration**

Run:

    git add .github/dependabot.yml .github/pull_request_template.md .github/CODEOWNERS .github/settings
    git commit -m "ci: add dependency and repository governance"

### Task 6: Align project documentation with the implemented flow

**Files:**
- Modify: docs/quality-ci-cd.md
- Modify: README.md

**Interfaces:**
- Consumes: all command names, workflow names, merge rules, and the solo-maintainer exception defined in Tasks 1 through 5.
- Produces: source-of-truth operating instructions that do not contradict the actual repository.

- [ ] **Step 1: Update the CI documentation**

Change docs/quality-ci-cd.md so it explicitly states:

- CI runs for pull requests to develop and main.
- The five required checks are PHP quality, Backend tests, Frontend quality, Vite build, and Docker build.
- develop uses linear history for feature pull requests.
- develop-to-main promotion uses a merge commit so Release Please sees every Conventional Commit.
- main currently requires zero approvals because there is only one contributor; increase this to one when a second reviewer is added.
- Release Please uses a fine-grained RELEASE_PLEASE_TOKEN only to ensure its pull requests trigger CI.

- [ ] **Step 2: Add local CI commands to README**

Add a concise Qualité section to README.md containing:

    composer lint:check
    composer analyse
    php artisan test
    npm run lint:check
    npm run format:check
    npm run types:check
    npm run test
    npm run build
    docker build --target runtime --tag dlp-friends:ci .

Link that section to docs/quality-ci-cd.md for the branch and release policy.

- [ ] **Step 3: Check documentation consistency**

Run:

    rg -n "approbation|historique linéaire|merge commit|RELEASE_PLEASE_TOKEN|Backend tests" docs/quality-ci-cd.md README.md
    npx prettier --check README.md docs/quality-ci-cd.md

Expected: the documented exception, merge strategy, token purpose, and check names are present; formatting succeeds.

- [ ] **Step 4: Commit the documentation**

Run:

    git add README.md docs/quality-ci-cd.md
    git commit -m "docs: document GitHub quality and release flow"

### Task 7: Verify locally, publish, and apply GitHub settings

**Files:**
- Verify: all files changed in Tasks 1 through 6
- Remote state: zdossantos/dlp-friends branches, Actions permissions, secret, pull requests, and branch protections

**Interfaces:**
- Consumes: a working gh authentication, the RELEASE_PLEASE_TOKEN value supplied through a secure prompt, all committed workflow files, and all JSON settings payloads.
- Produces: merged automation on develop, an open promotion PR to main, protected develop/main branches, and audited remote settings.

- [ ] **Step 1: Run the complete local verification suite**

Run:

    composer lint:check
    composer analyse
    php artisan test
    npm run lint:check
    npm run format:check
    npm run types:check
    npm run test
    npm run build
    jq empty release-please-config.json .release-please-manifest.json .github/settings/*.json
    npx prettier --check .github README.md docs/quality-ci-cd.md release-please-config.json .release-please-manifest.json
    docker build --target runtime --tag dlp-friends:ci .
    git diff --check

Expected: every command exits zero and git diff --check prints nothing.

- [ ] **Step 2: Verify GitHub authentication and repository identity**

Run:

    gh auth status
    gh repo view zdossantos/dlp-friends --json nameWithOwner,defaultBranchRef,visibility,url

Expected: gh reports an authenticated zdossantos account with repository administration access, and nameWithOwner equals zdossantos/dlp-friends. If the terminal still reports an invalid token, stop before remote writes and re-authenticate this exact gh environment.

- [ ] **Step 3: Ensure main exists before enabling promotion**

Run:

    gh api repos/zdossantos/dlp-friends/git/ref/heads/main

Expected: either the ref exists, or GitHub returns 404. On 404, read the current develop SHA and create main at that exact commit:

    develop_sha="$(gh api repos/zdossantos/dlp-friends/git/ref/heads/develop --jq '.object.sha')"
    gh api --method POST repos/zdossantos/dlp-friends/git/refs --field ref=refs/heads/main --field sha="$develop_sha"

Then rerun the read and expect refs/heads/main.

- [ ] **Step 4: Set the Actions secret without exposing it**

Run:

    gh secret set RELEASE_PLEASE_TOKEN --repo zdossantos/dlp-friends

Expected: gh reads the fine-grained token from the interactive secure prompt and confirms the secret name. Never place the token in command history, a file, tool output, or Git.

- [ ] **Step 5: Apply repository and workflow settings needed before merge**

Run:

    gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
    gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json

Expected: the repository default branch is develop, deleted merged branches are enabled, squash and merge commits are allowed, rebase merging is disabled, default workflow permission is read, and Actions may create pull requests.

- [ ] **Step 6: Publish the implementation pull request**

Push the implementation branch and open a pull request targeting develop with title:

    ci: establish GitHub automation

The body must summarize the five CI jobs, promotion workflow, Release Please token requirement, Dependabot coverage, and versioned remote settings. Do not push the implementation directly to develop.

- [ ] **Step 7: Observe the first CI run and confirm check names**

Run:

    gh pr checks --watch

Expected: PHP quality, Backend tests, Frontend quality, Vite build, and Docker build all complete successfully. If GitHub displays a different context name, update both protection JSON files to the exact successful check-run name, validate them with jq, commit, and rerun the PR checks before continuing.

- [ ] **Step 8: Merge the implementation into develop**

Merge the implementation pull request with squash merge so develop remains linear. Use the Conventional Commit title:

    ci: establish GitHub automation

Expected: the pull request closes, develop advances once, and Promote develop opens exactly one pull request from develop to main.

- [ ] **Step 9: Apply both branch protections**

Run:

    gh api --method PUT repos/zdossantos/dlp-friends/branches/develop/protection --input .github/settings/develop-protection.json
    gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json

Expected: both requests succeed after GitHub has observed all five check names.

- [ ] **Step 10: Audit the remote configuration**

Run:

    gh api repos/zdossantos/dlp-friends --jq '{default_branch,delete_branch_on_merge,allow_squash_merge,allow_merge_commit,allow_rebase_merge}'
    gh api repos/zdossantos/dlp-friends/actions/permissions/workflow
    gh api repos/zdossantos/dlp-friends/branches/develop/protection
    gh api repos/zdossantos/dlp-friends/branches/main/protection
    gh pr list --state open --base main --head develop --json number,title,url

Expected: remote values match the four versioned JSON files and exactly one promotion pull request is open.

- [ ] **Step 11: Validate the promotion PR without merging it automatically**

Run:

    gh pr checks --watch <promotion-pr-number>

Expected: all five CI checks pass. Leave the promotion pull request open for the deliberate merge-commit publication decision; do not enable auto-merge and do not merge it with squash or rebase.

- [ ] **Step 12: Record final evidence**

Run:

    git status --short
    git log --oneline --decorate -10

Expected: the local implementation worktree is clean. Report the implementation pull request, the promotion pull request, the five successful checks, the protected branches, and the fact that the production promotion remains a manual merge commit.
