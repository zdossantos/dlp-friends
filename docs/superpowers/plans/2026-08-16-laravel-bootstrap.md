# Laravel Bootstrap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Initialize DLP Friends from Laravel's official Vue/TypeScript starter kit and provide a bootable Docker Compose stack for development and Coolify production.

**Architecture:** Laravel, Inertia and Vue live at the repository root. A custom multi-stage image, explicitly without Laravel Sail, runs the web, queue, scheduler and Reverb processes; Compose adds private MySQL, Redis, MinIO and local Mailpit services. Production mail infrastructure is deferred.

**Tech Stack:** Current stable Laravel, PHP 8.4, Inertia, Vue 3, TypeScript, Tailwind CSS, shadcn-vue, Pest, Nginx, PHP-FPM, MySQL 8.4, Redis 7.4, MinIO, Mailpit, Laravel Reverb, Docker Compose.

## Global Constraints

- Use the official Laravel Vue starter kit with built-in authentication, TypeScript and Pest.
- Do not install or use Laravel Sail.
- Preserve the existing `README.md`, `docs/` tree and Git history while copying the generated application to the repository root.
- Lock Composer and npm dependencies in `composer.lock` and `package-lock.json`.
- Use one `compose.yaml`; Mailpit is the development mail sink and production SMTP is out of scope.
- Do not commit application secrets or production `.env` files.
- Do not run migrations automatically from a container entrypoint.
- Keep MySQL, Redis and internal application processes private to the Compose network.
- Pin every third-party container image to an explicit stable version.

---

### Task 1: Scaffold the official Laravel Vue application

**Files:**
- Create: Laravel starter-kit files at repository root
- Preserve: `README.md`
- Preserve: `docs/**`
- Modify: `.gitignore`

**Interfaces:**
- Consumes: Laravel Installer 5.13 or later and PHP 8.4.
- Produces: a root Laravel application with `artisan`, `composer.json`, `package.json`, Vue/TypeScript resources and Pest tests.

- [ ] **Step 1: Capture the pre-scaffold state**

Run:

```bash
git status --short
test -f README.md
test -f docs/technical-architecture.md
```

Expected: the documentation exists; existing untracked files remain visible and must not be overwritten.

- [ ] **Step 2: Generate the official starter kit in a temporary directory**

Run:

```bash
bootstrap_dir="$(mktemp -d /tmp/dlp-friends-bootstrap.XXXXXX)"
laravel new "$bootstrap_dir/app" --vue --pest --npm --database=mysql --no-interaction
```

Expected: the generated project contains `artisan`, `resources/js/app.ts`, `composer.lock`, `package-lock.json`, and no `vendor/laravel/sail` dependency.

- [ ] **Step 3: Verify the generated starter before copying it**

Run inside the generated application:

```bash
composer show laravel/framework inertiajs/inertia-laravel
npm ls vue typescript @inertiajs/vue3
composer show laravel/sail
```

Expected: Laravel, Inertia, Vue and TypeScript are installed; `composer show laravel/sail` exits non-zero.

- [ ] **Step 4: Copy the scaffold without replacing project documentation**

Use `rsync` from the generated application to the repository root, excluding `.git`, `README.md`, `docs`, `.env`, `vendor` and `node_modules`. Then merge relevant generated README setup information into the existing README manually; never replace the project README.

Run:

```bash
rsync -a --exclude=.git --exclude=README.md --exclude=docs --exclude=.env --exclude=vendor --exclude=node_modules "$bootstrap_dir/app/" ./
composer install --no-interaction
npm ci
```

Expected: dependencies install from both lockfiles and the existing documentation is unchanged.

- [ ] **Step 5: Run the generated checks**

Run:

```bash
php artisan test
npm run build
```

Expected: the generated Pest suite passes and Vite builds successfully.

- [ ] **Step 6: Commit the scaffold**

```bash
git add . ':!README.md' ':!docs'
git commit -m "chore: scaffold Laravel Vue starter kit"
```

### Task 2: Enable Reverb and add the application health contract

**Files:**
- Create: `config/broadcasting.php`
- Create: `config/reverb.php`
- Create: `routes/channels.php`
- Create: `tests/Feature/HealthCheckTest.php`
- Modify: `bootstrap/app.php`
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `package.json`
- Modify: `package-lock.json`
- Modify: `.env.example`

**Interfaces:**
- Consumes: the Laravel application produced by Task 1.
- Produces: `GET /up`, `php artisan reverb:start`, Redis-backed cache/session/queues and frontend Echo configuration.

- [ ] **Step 1: Write the health test**

Create `tests/Feature/HealthCheckTest.php`:

```php
<?php

test('the application health endpoint responds successfully', function () {
    $this->get('/up')->assertOk();
});
```

- [ ] **Step 2: Run the focused test before changing health configuration**

Run:

```bash
php artisan test tests/Feature/HealthCheckTest.php
```

Expected: PASS if the current stable starter already registers Laravel's default `/up` route; otherwise FAIL with 404, proving that the contract is absent.

- [ ] **Step 3: Register the health route when absent**

Ensure the application builder in `bootstrap/app.php` includes:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Do not duplicate the route if the starter already generated it.

- [ ] **Step 4: Install the official Reverb integration**

Run:

```bash
php artisan install:broadcasting --reverb --no-interaction
```

Expected: Laravel Reverb, Echo and the required configuration files are installed and locked.

- [ ] **Step 5: Configure non-secret environment defaults**

Set `.env.example` to use:

```dotenv
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
FILESYSTEM_DISK=local
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
BROADCAST_CONNECTION=reverb
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Keep `APP_KEY`, database passwords, MinIO keys and Reverb credentials as empty or clearly non-production example values.

- [ ] **Step 6: Verify health and frontend compilation**

Run:

```bash
php artisan test tests/Feature/HealthCheckTest.php
php artisan test
npm run build
```

Expected: all commands exit 0.

- [ ] **Step 7: Commit Reverb and health support**

```bash
git add bootstrap config routes tests composer.json composer.lock package.json package-lock.json .env.example resources/js
git commit -m "chore: add Reverb and health check"
```

### Task 3: Build the custom application image

**Files:**
- Create: `Dockerfile`
- Create: `.dockerignore`
- Create: `docker/nginx/default.conf`
- Create: `docker/php/entrypoint.sh`

**Interfaces:**
- Consumes: `public/index.php`, built Vite assets, Composer dependencies and the commands `web`, `worker`, `scheduler`, `reverb`.
- Produces: one immutable `dlp-friends-app` image used by four Compose services.

- [ ] **Step 1: Add the Docker build exclusions**

Create `.dockerignore` excluding `.git`, `.env`, `node_modules`, `vendor`, test caches, editor files and local Docker volumes, while retaining `.env.example`, tests and lockfiles.

- [ ] **Step 2: Add Nginx configuration**

Create `docker/nginx/default.conf` with root `/var/www/html/public`, `try_files $uri $uri/ /index.php?$query_string`, FastCGI forwarding to `127.0.0.1:9000`, denial of hidden files, a `/up` location routed through Laravel, and static asset cache headers.

- [ ] **Step 3: Add a fail-fast multi-command entrypoint**

Create executable `docker/php/entrypoint.sh` that switches on its first argument:

```sh
#!/bin/sh
set -eu

case "${1:-web}" in
  web)
    php-fpm -D
    exec nginx -g 'daemon off;'
    ;;
  worker)
    exec php artisan queue:work --sleep=3 --tries=3 --timeout=90 --memory=256
    ;;
  scheduler)
    exec php artisan schedule:work
    ;;
  reverb)
    exec php artisan reverb:start --host=0.0.0.0 --port=8080
    ;;
  *)
    exec "$@"
    ;;
esac
```

The script must not run `php artisan migrate` or generate secrets.

- [ ] **Step 4: Add the multi-stage Dockerfile**

Use explicit Node and PHP image versions. The stages are:

1. `frontend`: `npm ci` then `npm run build`;
2. `composer`: `composer install --no-dev --classmap-authoritative --no-interaction --no-scripts`, followed by scripts in the final stage if required;
3. `runtime`: PHP-FPM 8.4 Alpine with Nginx, curl and extensions `bcmath`, `exif`, `intl`, `opcache`, `pcntl`, `pdo_mysql`, `redis`, `sockets`, `zip`;
4. copy application, vendor and built assets; set writable ownership for `storage` and `bootstrap/cache`; install the entrypoint.

Use `CMD ["web"]` and `HEALTHCHECK` against `http://127.0.0.1/up`.

- [ ] **Step 5: Validate and build the image**

Run:

```bash
docker build --tag dlp-friends-app:test .
docker run --rm dlp-friends-app:test php -m
```

Expected: the image builds and all required PHP extensions appear.

- [ ] **Step 6: Prove migrations are not run by the entrypoint**

Run:

```bash
rg -n "artisan migrate|artisan key:generate" docker/php/entrypoint.sh Dockerfile
```

Expected: no matches.

- [ ] **Step 7: Commit the image definition**

```bash
git add Dockerfile .dockerignore docker/nginx docker/php
git commit -m "chore: add custom Laravel runtime image"
```

### Task 4: Define and verify the development Compose stack

**Files:**
- Create: `compose.yaml`
- Modify: `.env.example`

**Interfaces:**
- Consumes: the image commands from Task 3.
- Produces: `web`, `worker`, `scheduler`, `reverb`, `mysql`, `redis`, `minio` and `mailpit` services on a private network.

- [ ] **Step 1: Define shared application configuration**

In `compose.yaml`, use an `x-app` anchor with `build: .`, `env_file: .env`, a private network, `restart: unless-stopped`, and dependencies on healthy MySQL and Redis. Override only `command` and healthcheck per application process.

- [ ] **Step 2: Define application services**

Configure:

- `web`: command `web`, publish `${APP_PORT:-8000}:80`, healthcheck `curl --fail http://127.0.0.1/up`;
- `worker`: command `worker`, healthcheck verifies `queue:work` is running;
- `scheduler`: command `scheduler`, healthcheck verifies `schedule:work` is running;
- `reverb`: command `reverb`, publish `${REVERB_PORT:-8080}:8080`, TCP/HTTP healthcheck.

- [ ] **Step 3: Define private infrastructure services**

Configure explicit image versions, healthchecks and restart policies:

- `mysql`: `mysql:8.4`, no host port, named volume `mysql-data`;
- `redis`: `redis:7.4-alpine`, no host port;
- `minio`: a versioned MinIO image, named volume `minio-data`, publish API `${MINIO_PORT:-9000}:9000` and console `${MINIO_CONSOLE_PORT:-9001}:9001` only for local administration;
- `mailpit`: a versioned Mailpit image, publish SMTP `${MAILPIT_SMTP_PORT:-1025}:1025` and UI `${MAILPIT_UI_PORT:-8025}:8025`.

- [ ] **Step 4: Render the configuration**

Create a local `.env` from `.env.example`, generate `APP_KEY` locally, and run:

```bash
docker compose config --quiet
```

Expected: exit 0 with no unresolved required variable.

- [ ] **Step 5: Start the development stack and migrate explicitly**

Run:

```bash
docker compose up --build --detach
docker compose exec web php artisan migrate --force
docker compose ps
```

Expected: all development services become healthy; migration is an explicit command.

- [ ] **Step 6: Verify external and internal health**

Run:

```bash
curl --fail http://localhost:8000/up
docker compose exec web php artisan about
docker compose exec web php artisan test tests/Feature/HealthCheckTest.php
```

Expected: `/up` returns HTTP 200 and the test passes inside the image.

- [ ] **Step 7: Verify private ports**

Run `docker compose ps` and inspect `docker compose config`. Expected: MySQL and Redis have no published host ports; only the documented application, Reverb, MinIO and Mailpit ports are published.

- [ ] **Step 8: Stop without deleting persistent volumes**

Run:

```bash
docker compose down
docker volume ls
```

Expected: containers stop and `mysql-data` / `minio-data` remain.

- [ ] **Step 9: Commit the development stack**

```bash
git add compose.yaml .env.example
git commit -m "chore: add local Docker services"
```

### Task 5: Document and complete the bootstrap

**Files:**
- Modify: `README.md`
- Modify: `docs/technical-architecture.md`
- Test: `tests/Feature/HealthCheckTest.php`

**Interfaces:**
- Consumes: all deliverables from Tasks 1–4.
- Produces: a newcomer-ready local setup and accurate technical documentation.

- [ ] **Step 1: Document prerequisites and local setup**

Add README sections for PHP/Composer/Node when running checks on the host, Docker Desktop, `.env` creation, `APP_KEY` generation, `docker compose up --build -d`, explicit migrations, service URLs and Artisan/npm commands through the web container.

- [ ] **Step 2: Document operational boundaries**

State explicitly: no Laravel Sail; Mailpit is local-only; production SMTP is deferred; migrations are never automatic; MySQL/Redis are private; persistent data is removed only with an explicit `docker compose down --volumes` command.

- [ ] **Step 3: Record the documented outcome**

Record only checks actually observed and keep production SMTP explicitly deferred.

- [ ] **Step 4: Run final verification**

Run:

```bash
composer validate --strict
php artisan test
npm ci
npm run build
docker compose config --quiet
docker compose build
docker compose up --detach
curl --fail http://localhost:8000/up
docker compose ps
docker compose down
git diff --check
```

Expected: every command exits 0, all development services are healthy, `/up` returns 200 and volumes are retained.

- [ ] **Step 5: Confirm Sail and secrets are absent**

Run:

```bash
composer show laravel/sail
rg -n "laravel/sail|vendor/bin/sail" composer.json composer.lock package.json README.md docs Dockerfile compose.yaml
git grep -nE "APP_KEY=base64:|MINIO_ROOT_PASSWORD=.+"
```

Expected: `composer show` exits non-zero; searches find no Sail usage or committed real secrets.

- [ ] **Step 6: Commit the completed bootstrap**

```bash
git add README.md docs/technical-architecture.md
git commit -m "docs: document Laravel Docker bootstrap"
```

## Self-review

- Every accepted design section maps to Tasks 1–5.
- The plan preserves existing documentation, forbids Sail, locks dependencies, separates all long-running processes, avoids automatic migrations and defines health evidence.
- Production SMTP infrastructure remains deferred and outside this bootstrap.
- Production credentials and DNS changes remain operator-controlled and outside Git.
- No application feature beyond the bootstrap scope is introduced.
