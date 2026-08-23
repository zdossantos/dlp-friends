# Bun Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace npm with Bun 1.3.14 for dependency installation and JavaScript script execution in local development, CI, Docker, and active developer documentation without changing application behavior or dependency versions.

**Architecture:** Keep the existing Vite, ESLint, Prettier, TypeScript, and Vitest toolchain and change only its package manager and command runner. Convert the npm lockfile to Bun's text lockfile, pin Bun at every execution boundary, and enforce the migration through a repository-level PHP infrastructure test.

**Tech Stack:** Bun 1.3.14, Laravel 13/PHP 8.4, Vue 3, Vite 8, Vitest 4, GitHub Actions, Docker BuildKit, Dependabot.

**Spec:** `docs/superpowers/specs/2026-08-23-bun-migration-design.md`

## Global Constraints

- Bun 1.3.14 is the only package manager and JavaScript command runner used by active project configuration.
- Existing direct and transitive dependency versions must not change during lockfile conversion.
- Existing JavaScript script names and behavior remain unchanged.
- GitHub required-check names remain unchanged.
- No Yarn or pnpm lockfile may be added.
- Historical files under `docs/superpowers/specs/` and `docs/superpowers/plans/` remain historical and are not mechanically rewritten.
- No Laravel or Vue application behavior changes are in scope.

---

### Task 1: Lock the project to Bun 1.3.14

**Files:**
- Create: `tests/Feature/Infrastructure/BunToolchainTest.php`
- Create: `bun.lock`
- Modify: `package.json`
- Modify: `composer.json`
- Delete: `package-lock.json`
- Delete: `.npmrc`
- Delete: `pnpm-workspace.yaml`

**Interfaces:**
- Consumes: the current `package.json`, `package-lock.json`, `.npmrc`, and Composer script contract.
- Produces: `packageManager: bun@1.3.14`, a text `bun.lock`, and unchanged script entry points callable through `bun run <name>`.

- [ ] **Step 1: Install an isolated Bun 1.3.14 executable for the migration**

Run:

```bash
curl -fsSL https://bun.sh/install -o /tmp/dlp-friends-bun-install.sh
BUN_INSTALL=/tmp/dlp-friends-bun-1.3.14 bash /tmp/dlp-friends-bun-install.sh bun-v1.3.14
/tmp/dlp-friends-bun-1.3.14/bin/bun --version
```

Expected: the final command prints `1.3.14`. Keep the project-scoped Bun path in subsequent commands so the user's globally installed Bun 1.2.18 is not modified.

- [ ] **Step 2: Capture the npm lockfile's resolved version inventory**

Run:

```bash
jq -r '.packages | to_entries[] | select(.key != "" and .value.version) | ((.value.name // (.key | split("node_modules/")[-1])) + "@" + .value.version)' package-lock.json | sort -u > /tmp/dlp-friends-package-lock-versions.txt
wc -l /tmp/dlp-friends-package-lock-versions.txt
```

Expected: the inventory is non-empty and contains one sorted `name@version` entry per resolved package version.

- [ ] **Step 3: Write the failing package-manager infrastructure test**

Create `tests/Feature/Infrastructure/BunToolchainTest.php` with:

```php
<?php

it('uses Bun as its only JavaScript package manager', function () {
    $package = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
    $composer = file_get_contents(base_path('composer.json'));

    expect($package['packageManager'])->toBe('bun@1.3.14')
        ->and($package['scripts']['test'])->toBe('bun run test:unit')
        ->and(base_path('bun.lock'))->toBeFile()
        ->and(base_path('package-lock.json'))->not->toBeFile()
        ->and(base_path('.npmrc'))->not->toBeFile()
        ->and(base_path('pnpm-workspace.yaml'))->not->toBeFile()
        ->and($composer)->toContain('bun install')
        ->and($composer)->toContain('bun run build')
        ->and($composer)->not->toContain('npm install')
        ->and($composer)->not->toContain('npm run');
});
```

- [ ] **Step 4: Run the new test and verify the npm state fails it**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
```

Expected: FAIL because `packageManager` and `bun.lock` do not exist and npm files are still present.

- [ ] **Step 5: Change the package and Composer scripts to Bun**

In `package.json`, add the top-level field:

```json
"packageManager": "bun@1.3.14"
```

and change the `test` script to:

```json
"test": "bun run test:unit"
```

In `composer.json`, make `setup` install and build with:

```json
"bun install",
"bun run build"
```

and make `ci:check` use:

```json
"bun run lint:check",
"bun run format:check",
"bun run types:check",
"bun run test"
```

- [ ] **Step 6: Convert the npm lockfile with Bun 1.3.14**

Run while `package-lock.json` is still present:

```bash
/tmp/dlp-friends-bun-1.3.14/bin/bun install --lockfile-only
test -f bun.lock
```

Expected: Bun reports migration from `package-lock.json` and creates the text file `bun.lock` without changing dependency ranges in `package.json`.

- [ ] **Step 7: Compare the converted lockfile with the npm inventory**

Run:

```bash
perl -0pe 's/,\s*([}\]])/$1/g' bun.lock > /tmp/dlp-friends-bun-lock.json
jq -r --slurpfile lock package-lock.json '[.dependencies, .devDependencies, .optionalDependencies] | add | keys[] as $name | ($name + "@" + $lock[0].packages["node_modules/" + $name].version)' package.json | sort -u > /tmp/dlp-friends-npm-direct-versions.txt
jq -r --slurpfile manifest package.json '. as $lock | [$manifest[0].dependencies, $manifest[0].devDependencies, $manifest[0].optionalDependencies] | add | keys[] as $name | $lock.packages[$name][0]' /tmp/dlp-friends-bun-lock.json | sort -u > /tmp/dlp-friends-bun-direct-versions.txt
jq -r '.packages[] | select(type == "array" and (.[0] | type) == "string") | .[0]' /tmp/dlp-friends-bun-lock.json | sort -u > /tmp/dlp-friends-bun-lock-versions.txt
diff -u /tmp/dlp-friends-npm-direct-versions.txt /tmp/dlp-friends-bun-direct-versions.txt
comm -13 /tmp/dlp-friends-package-lock-versions.txt /tmp/dlp-friends-bun-lock-versions.txt
```

Expected: `diff` exits 0, proving every direct dependency keeps its resolved version. `comm` prints nothing, proving Bun introduced no package version absent from the npm lockfile. Bun may omit redundant platform-specific optional versions that npm had recorded; those omissions are acceptable because Bun normalizes platform selection in one lockfile.

- [ ] **Step 8: Remove npm-only files and perform a frozen clean install**

Delete `package-lock.json`, `.npmrc`, and `pnpm-workspace.yaml`, then run:

```bash
mv node_modules /tmp/dlp-friends-node-modules-before-bun
/tmp/dlp-friends-bun-1.3.14/bin/bun install --frozen-lockfile
```

Expected: installation succeeds without modifying `bun.lock` or `package.json`. Keep the previous `node_modules` in `/tmp` until all verification passes so it is recoverable.

- [ ] **Step 9: Run the package-manager infrastructure test**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
```

Expected: PASS.

- [ ] **Step 10: Commit the package-manager migration**

```bash
git add package.json composer.json bun.lock package-lock.json .npmrc pnpm-workspace.yaml tests/Feature/Infrastructure/BunToolchainTest.php
git commit -m "build: migrate dependencies to Bun"
```

---

### Task 2: Run Bun in GitHub Actions, Docker, and Dependabot

**Files:**
- Modify: `tests/Feature/Infrastructure/BunToolchainTest.php`
- Modify: `.github/workflows/ci.yml`
- Modify: `.github/dependabot.yml`
- Modify: `Dockerfile`

**Interfaces:**
- Consumes: `bun.lock`, `packageManager: bun@1.3.14`, and the existing required-check names.
- Produces: reproducible Bun installs in the `Frontend quality`, `Vite build`, and Docker build paths; weekly Bun dependency updates through Dependabot.

- [ ] **Step 1: Extend the infrastructure test with CI, Docker, and Dependabot invariants**

Append a second test to `tests/Feature/Infrastructure/BunToolchainTest.php`:

```php
it('uses the pinned Bun toolchain in automation and Docker', function () {
    $ci = file_get_contents(base_path('.github/workflows/ci.yml'));
    $dockerfile = file_get_contents(base_path('Dockerfile'));
    $dependabot = file_get_contents(base_path('.github/dependabot.yml'));

    expect($ci)->toContain('oven-sh/setup-bun@0c5077e51419868618aeaa5fe8019c62421857d6')
        ->and($ci)->toContain("bun-version: '1.3.14'")
        ->and($ci)->toContain('bun install --frozen-lockfile')
        ->and($ci)->not->toContain('actions/setup-node')
        ->and($ci)->not->toContain('npm ci')
        ->and($dockerfile)->toContain('FROM oven/bun:1.3.14-alpine AS bun')
        ->and($dockerfile)->toContain('bun install --frozen-lockfile')
        ->and($dockerfile)->not->toContain('npm ci')
        ->and($dependabot)->toContain("package-ecosystem: 'bun'")
        ->and($dependabot)->not->toContain("package-ecosystem: 'npm'");
});
```

- [ ] **Step 2: Run the automation test and verify it fails**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
```

Expected: the first test passes and the automation test FAILS on the existing npm workflow.

- [ ] **Step 3: Replace Node/npm setup with pinned Bun in both frontend CI jobs**

In both `frontend-quality` and `vite-build`, replace `actions/setup-node` with:

```yaml
- name: Set up Bun
  uses: oven-sh/setup-bun@0c5077e51419868618aeaa5fe8019c62421857d6 # v2
  with:
    bun-version: '1.3.14'
- name: Cache Bun downloads
  uses: actions/cache@55cc8345863c7cc4c66a329aec7e433d2d1c52a9 # v6.1.0
  with:
    path: ~/.bun/install/cache
    key: ${{ runner.os }}-bun-1.3.14-${{ hashFiles('bun.lock') }}
    restore-keys: |
      ${{ runner.os }}-bun-1.3.14-
```

Replace each install with `bun install --frozen-lockfile`, and replace every active `npm run <script>` with `bun run <script>`. Preserve the job names `Frontend quality` and `Vite build` exactly.

- [ ] **Step 4: Supply Bun to the Alpine Docker build stage**

Add before the PHP build stage:

```dockerfile
FROM oven/bun:1.3.14-alpine AS bun
```

Remove `nodejs` and `npm` from the build-stage `apk add`, then add:

```dockerfile
COPY --from=bun /usr/local/bin/bun /usr/local/bin/bun
```

Replace the npm build commands with:

```dockerfile
&& bun install --frozen-lockfile \
&& bun run build \
```

- [ ] **Step 5: Change Dependabot to the Bun ecosystem**

Change the JavaScript dependency entry to:

```yaml
- package-ecosystem: 'bun'
  directory: '/'
  target-branch: 'main'
  schedule:
    interval: 'weekly'
  cooldown:
    default-days: 5
  open-pull-requests-limit: 10
  groups:
    bun-dependencies:
      patterns:
        - '*'
```

- [ ] **Step 6: Run the infrastructure tests**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
```

Expected: both tests PASS.

- [ ] **Step 7: Validate the workflow and Docker edits statically**

Run:

```bash
rg -n 'setup-node|cache: npm|npm ci|npm run|package-ecosystem: .npm.' .github/workflows .github/dependabot.yml Dockerfile
```

Expected: no matches.

- [ ] **Step 8: Commit automation and Docker changes**

```bash
git add tests/Feature/Infrastructure/BunToolchainTest.php .github/workflows/ci.yml .github/dependabot.yml Dockerfile
git commit -m "ci: run JavaScript tooling with Bun"
```

---

### Task 3: Update active documentation and remove npm residue

**Files:**
- Modify: `tests/Feature/Infrastructure/BunToolchainTest.php`
- Modify: `README.md`
- Modify: `CONTRIBUTING.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/quality-ci-cd.md`
- Modify: `docs/implementation-plan.md`
- Modify: `.gitignore`
- Modify: `.dockerignore`

**Interfaces:**
- Consumes: the Bun commands and CI/Docker behavior delivered by Tasks 1 and 2.
- Produces: current setup and contribution instructions that exclusively describe Bun, with historical design records left intact.

- [ ] **Step 1: Extend the infrastructure test with active-documentation invariants**

Append:

```php
it('documents Bun without npm or Yarn residue in active project files', function () {
    $activeDocumentation = collect([
        'README.md',
        'CONTRIBUTING.md',
        'docs/technical-architecture.md',
        'docs/quality-ci-cd.md',
        'docs/implementation-plan.md',
    ])->map(fn (string $path): string => file_get_contents(base_path($path)))->join("\n");
    $ignoreFiles = file_get_contents(base_path('.gitignore')).file_get_contents(base_path('.dockerignore'));

    expect($activeDocumentation)->toContain('Bun 1.3.14')
        ->and($activeDocumentation)->not->toMatch('/\bnpm (ci|install|run|test)\b/')
        ->and($activeDocumentation)->not->toContain('package-lock.json')
        ->and($ignoreFiles)->not->toContain('npm-debug.log')
        ->and($ignoreFiles)->not->toContain('yarn-error.log');
});
```

- [ ] **Step 2: Run the documentation test and verify it fails**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
```

Expected: the first two tests pass and the documentation test FAILS on npm commands and npm/yarn log exclusions.

- [ ] **Step 3: Update setup and contribution instructions**

In `README.md` and `CONTRIBUTING.md`:

- replace the Node/npm prerequisite with Bun 1.3.14;
- replace `npm ci` with `bun install --frozen-lockfile`;
- replace `npm run <script>` and `npm test` with `bun run <script>`;
- keep PHP, Composer, Docker, branch, release, and required-check instructions unchanged;
- retain prose saying no npm package is published when it describes release output rather than package-manager usage.

- [ ] **Step 4: Update active architecture and CI documentation**

In `docs/technical-architecture.md`, replace `package-lock.json` with `bun.lock` and state that Bun 1.3.14 is pinned.

In `docs/quality-ci-cd.md`, document `bun.lock`, the Bun download cache, Bun Dependabot updates, and Bun commands while preserving the six checks and release behavior.

In `docs/implementation-plan.md`, replace the remaining active `npm run types:check` command with `bun run types:check` and update the completed dependency-automation wording from npm to Bun.

- [ ] **Step 5: Remove obsolete npm and Yarn log exclusions**

Delete `npm-debug.log` and `yarn-error.log` from `.gitignore`, and delete `npm-debug.log*` and `yarn-error.log*` from `.dockerignore`.

- [ ] **Step 6: Run the infrastructure tests and formatting check**

Run:

```bash
php artisan test tests/Feature/Infrastructure/BunToolchainTest.php
/tmp/dlp-friends-bun-1.3.14/bin/bun run format:check
```

Expected: all infrastructure tests PASS and Prettier reports no formatting errors.

- [ ] **Step 7: Search for active package-manager residue**

Run:

```bash
rg -n --hidden '\b(npm (ci|install|run|test)|npx|package-lock\.json|yarn |pnpm )\b' -g '!.git/**' -g '!node_modules/**' -g '!vendor/**' -g '!docs/superpowers/specs/**' -g '!docs/superpowers/plans/**'
```

Expected: no active commands or lockfile references. Prose stating that no npm package is published may remain and must be reviewed as release terminology rather than rewritten mechanically.

- [ ] **Step 8: Commit documentation and cleanup**

```bash
git add tests/Feature/Infrastructure/BunToolchainTest.php README.md CONTRIBUTING.md docs/technical-architecture.md docs/quality-ci-cd.md docs/implementation-plan.md .gitignore .dockerignore
git commit -m "docs: document Bun development workflow"
```

---

### Task 4: Verify the full migrated toolchain

**Files:**
- Verify only; modify only if a verification failure exposes a migration defect in the files already listed.

**Interfaces:**
- Consumes: all migrated configuration, automation, Docker, tests, and documentation.
- Produces: evidence that Bun preserves the existing development, quality, build, and runtime-image behavior.

- [ ] **Step 1: Confirm frozen-install reproducibility and a clean working tree lockfile**

Run:

```bash
/tmp/dlp-friends-bun-1.3.14/bin/bun install --frozen-lockfile
git diff --exit-code -- package.json bun.lock
```

Expected: install succeeds and neither manifest nor lockfile changes.

- [ ] **Step 2: Run all frontend quality and build commands through Bun**

Run:

```bash
/tmp/dlp-friends-bun-1.3.14/bin/bun run lint:check
/tmp/dlp-friends-bun-1.3.14/bin/bun run format:check
/tmp/dlp-friends-bun-1.3.14/bin/bun run types:check
/tmp/dlp-friends-bun-1.3.14/bin/bun run test
/tmp/dlp-friends-bun-1.3.14/bin/bun run build
```

Expected: every command exits 0 with the existing lint, formatting, type, Vitest, and Vite suites passing.

- [ ] **Step 3: Smoke-test the development server**

Run:

```bash
/tmp/dlp-friends-bun-1.3.14/bin/bun run dev --host 127.0.0.1 > /tmp/dlp-friends-vite.log 2>&1 &
vite_pid=$!
curl --retry 10 --retry-delay 1 --retry-connrefused http://127.0.0.1:5173/@vite/client --output /dev/null
kill "$vite_pid"
wait "$vite_pid" 2>/dev/null || true
```

Expected: curl receives a successful response from Vite and the process stops cleanly.

- [ ] **Step 4: Run PHP quality and test suites**

Run:

```bash
composer lint:check
composer analyse
php artisan test
```

Expected: Pint, PHPStan, and all PHP tests PASS.

- [ ] **Step 5: Build the production Docker image**

Run:

```bash
docker build --target runtime --tag dlp-friends:issue-45 .
```

Expected: Docker resolves `oven/bun:1.3.14-alpine`, installs from `bun.lock` with the frozen flag, builds Vite assets, and produces the runtime image without installing npm or Node in the PHP build stage.

- [ ] **Step 6: Run final repository audits**

Run:

```bash
git diff --check
git status --short
test -f bun.lock
test ! -e package-lock.json
test ! -e .npmrc
test ! -e yarn.lock
test ! -e pnpm-lock.yaml
rg -n --hidden '\b(npm (ci|install|run|test)|npx|package-lock\.json|yarn |pnpm )\b' -g '!.git/**' -g '!node_modules/**' -g '!vendor/**' -g '!docs/superpowers/specs/**' -g '!docs/superpowers/plans/**'
```

Expected: no whitespace errors, only intended issue-45 changes in Git, exactly `bun.lock` as the JavaScript lockfile, and no active npm/npx/Yarn/pnpm commands. Review release prose separately as specified above.

- [ ] **Step 7: Commit any verification-only correction**

If and only if the checks required a scoped correction, stage only those files and run:

```bash
git commit -m "fix: complete Bun toolchain migration"
```

If no correction was needed, do not create an empty commit.
