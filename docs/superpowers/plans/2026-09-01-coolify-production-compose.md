# Coolify Production Compose Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer une stack Compose de production directement déployable par Coolify, avec Resend comme transport e-mail transactionnel.

**Architecture:** Un nouveau `compose.production.yaml` construit une image applicative nommée et la réutilise pour `web`, `worker`, `scheduler` et `reverb`. MySQL, Redis et MinIO restent privés ; Coolify fournit toutes les variables critiques et gère l’exposition de `web` et `reverb`.

**Tech Stack:** Docker Compose, Coolify, Laravel 13, Resend, MySQL 8.4, Redis 7.4, MinIO, Pest.

**Spec:** `docs/superpowers/specs/2026-09-01-coolify-production-compose-design.md` et issue GitHub #102.

## Global Constraints

- `compose.yaml` reste la stack locale et conserve Mailpit.
- `compose.production.yaml` ne publie aucun port hôte pour MySQL, Redis ou MinIO.
- Les migrations restent une commande opérateur explicite et ne sont exécutées par aucun entrypoint.
- Les secrets Laravel, MySQL, MinIO, Reverb et Resend ne possèdent aucun défaut de production dans Git.
- `web`, `worker`, `scheduler` et `reverb` utilisent la même image construite pour le commit déployé.
- Resend est le seul transport e-mail de production ; Mailpit reste local uniquement.

---

### Task 1: Protéger le contrat Compose de production

**Files:**
- Create: `tests/Feature/Infrastructure/ProductionDeploymentTest.php`
- Create: `compose.production.yaml`

**Interfaces:**
- Consumes: cible Docker `runtime`, commandes `web`, `worker`, `scheduler`, `reverb`, route `/up`.
- Produces: stack `compose.production.yaml` analysable par Coolify et Docker Compose.

- [ ] **Step 1: Écrire les tests Pest en échec**

Créer des tests qui exécutent réellement Docker Compose avec des valeurs
factices, décodent sa sortie JSON et vérifient séparément :

```php
it('resolves the production service topology without Mailpit', function () {
    $result = Process::path(base_path())->env(productionEnvironment())->run(
        'docker compose -f compose.production.yaml config --format json',
    );

    $result->throw();
    $compose = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);

    expect(array_keys($compose['services']))
        ->toEqualCanonicalizing(['web', 'worker', 'scheduler', 'reverb', 'mysql', 'redis', 'minio']);
});
```

Ajouter des assertions sur le modèle Compose résolu pour l’image partagée, les
healthchecks, les volumes MySQL/MinIO, l’absence de ports publiés sur les
services de données, `MAIL_MAILER: resend`, `RESEND_API_KEY`, et l’absence de
commande `migrate`. Un second test retire chaque variable obligatoire à tour de
rôle et vérifie que `docker compose config` échoue.

- [ ] **Step 2: Vérifier l’échec attendu**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: FAIL parce que `compose.production.yaml` n’existe pas.

- [ ] **Step 3: Écrire la configuration minimale**

Créer `compose.production.yaml` avec un ancrage applicatif, une image
`${APP_IMAGE_NAME:-dlp-friends-app}:${SOURCE_COMMIT:-production}`, les quatre
services applicatifs, les trois services de données privés, les variables
obligatoires, les healthchecks, les volumes et limites de ressources définis
dans la spécification. Seul `web` déclare `expose: ["80"]` et `reverb`
`expose: ["8080"]` ; aucun service ne publie `ports:`.

- [ ] **Step 4: Vérifier le passage au vert**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: PASS.

- [ ] **Step 5: Valider Compose avec des valeurs factices**

Run: `docker compose --env-file .env.example -f compose.production.yaml config --no-interpolate`

Expected: exit 0 et sept services résolus sans Mailpit.

- [ ] **Step 6: Commit**

```bash
git add compose.production.yaml tests/Feature/Infrastructure/ProductionDeploymentTest.php
git commit -m "feat: add Coolify production compose stack"
```

### Task 2: Activer le transport Resend de production

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `.env.example`
- Modify: `tests/Feature/Infrastructure/ProductionDeploymentTest.php`

**Interfaces:**
- Consumes: mailer `resend` déjà déclaré dans `config/mail.php` et clé `services.resend.key` de `config/services.php`.
- Produces: dépendance `resend/resend-php` et contrat de variables `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` pour Coolify.

- [ ] **Step 1: Ajouter le test de dépendance en échec**

Résoudre le transport Laravel avec une fausse clé, puis vérifier que le mailer
`resend` peut être construit. Le test doit échouer tant que la classe du SDK
Resend n’est pas installée :

```php
config()->set('mail.default', 'resend');
config()->set('services.resend.key', 're_test_only');

expect(fn () => app('mail.manager')->mailer()->getSymfonyTransport())
    ->not->toThrow(Throwable::class);
```

- [ ] **Step 2: Vérifier l’échec attendu**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: FAIL car `resend/resend-php` n’est pas une dépendance directe.

- [ ] **Step 3: Installer la dépendance officielle**

Run: `composer require resend/resend-php:^1.0 --no-interaction`

Expected: `composer.json` et `composer.lock` contiennent la dépendance sans modifier Laravel.

- [ ] **Step 4: Documenter les variables locales et de production**

Conserver le bloc SMTP/Mailpit local dans `.env.example`, puis ajouter un bloc
commenté de production indiquant `MAIL_MAILER=resend`, `RESEND_API_KEY`, une adresse
d’expéditeur vérifiée et le nom DLP Friends. Aucun exemple de clé réelle.

- [ ] **Step 5: Vérifier le passage au vert**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add composer.json composer.lock .env.example tests/Feature/Infrastructure/ProductionDeploymentTest.php
git commit -m "feat: configure Resend for production mail"
```

### Task 3: Documenter et vérifier l’exploitation Coolify

**Files:**
- Modify: `docs/operations.md`
- Modify: `docs/technical-architecture.md`
- Modify: `README.md`
- Modify: `tests/Feature/Infrastructure/ProductionDeploymentTest.php`

**Interfaces:**
- Consumes: noms de services et variables de `compose.production.yaml`.
- Produces: procédure opérateur reproductible pour le premier déploiement et les déploiements suivants.

- [ ] **Step 1: Ajouter le test documentaire en échec**

Les procédures destinées aux humains ne reçoivent pas de test de présence
textuelle. Ajouter à la place un scénario Compose qui vérifie que la commande
applicative reste `web`, `worker`, `scheduler` ou `reverb` et qu’aucune commande
de migration n’est injectée ; la documentation est relue pendant la revue.

- [ ] **Step 2: Vérifier l’échec attendu**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: FAIL car la procédure Coolify et Resend n’est pas encore documentée.

- [ ] **Step 3: Écrire la procédure opérateur**

Ajouter à `docs/operations.md` : création de la ressource Docker Compose depuis
`main`, sélection de `compose.production.yaml`, domaines HTTP/WSS, liste des
variables obligatoires, configuration Resend et domaine expéditeur, premier
déploiement, migration explicite, santé, redémarrage des workers, sauvegarde et
restauration MySQL/MinIO. Mettre à jour l’architecture et le README sans exposer
de secret.

- [ ] **Step 4: Vérifier le passage au vert**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: PASS.

- [ ] **Step 5: Exécuter les contrôles complets concernés**

Run: `composer lint:check`

Expected: PASS.

Run: `composer analyse`

Expected: PASS.

Run: `php artisan test tests/Feature/Infrastructure/BunToolchainTest.php tests/Feature/Infrastructure/ProductionDeploymentTest.php`

Expected: PASS.

Run: `docker compose --env-file .env.example -f compose.production.yaml config --no-interpolate`

Expected: PASS.

Run: `docker build --target runtime --tag dlp-friends:ci .`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add README.md docs/operations.md docs/technical-architecture.md tests/Feature/Infrastructure/ProductionDeploymentTest.php docs/superpowers/specs/2026-09-01-coolify-production-compose-design.md docs/superpowers/plans/2026-09-01-coolify-production-compose.md
git commit -m "docs: document Coolify production operations"
```
