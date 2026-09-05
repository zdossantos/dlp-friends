# Garage Object Storage Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer le MinIO embarqué en production par un service Garage Coolify indépendant sans modifier les clés des objets ni rendre le bucket public.

**Architecture:** Le Compose de production reçoit un endpoint S3 externe obligatoire et ne gère plus de service ni de volume objet. Garage 2.3.0 fonctionne sur un seul nœud avec deux volumes persistants, une API S3 privée sur le réseau prédéfini Coolify et une clé limitée au bucket applicatif. La bascule suit une copie MinIO vers Garage, une comparaison inventaire/taille/ETag lorsque pertinent, puis des essais Laravel avant conservation de MinIO comme retour arrière.

**Tech Stack:** Garage 2.3.0, Coolify, Docker Compose, Laravel Filesystem/Flysystem S3, AWS SDK PHP, Pest.

**Spec:** GitHub issue #134 et `docs/operations.md`

## Global Constraints

- Le bucket et les endpoints Garage restent privés.
- Aucun secret ni identifiant n'est ajouté au dépôt ou aux journaux.
- Le développement local conserve MinIO ; seul le Compose de production bascule vers Garage.
- Les données MinIO d'origine sont conservées jusqu'à validation explicite de la migration.
- Les migrations Laravel restent des actions opérateur explicites.

---

### Task 1: Contrat du Compose de production

**Files:**
- Modify: `tests/Feature/Infrastructure/ProductionDeploymentTest.php`
- Modify: `compose.production.yaml`
- Modify: `.env.example`

**Interfaces:**
- Consumes: variables S3 Laravel existantes.
- Produces: `AWS_ENDPOINT` obligatoire et configurable pour les quatre processus Laravel, sans service `minio` ni volume `minio-data`.

- [ ] **Step 1: Write the failing test**

Ajouter des assertions exigeant exactement `web`, `worker`, `scheduler`, `reverb`, un `AWS_ENDPOINT` externe fourni par l'environnement, aucun `depends_on.minio` et aucun volume de production.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`
Expected: FAIL car le Compose contient encore `minio` et fixe `AWS_ENDPOINT` à `http://minio:9000`.

- [ ] **Step 3: Write minimal implementation**

Supprimer `minio`, `minio-data`, les variables racine MinIO et la dépendance locale. Définir `AWS_ENDPOINT: ${AWS_ENDPOINT:?AWS_ENDPOINT is required}` et conserver `AWS_USE_PATH_STYLE_ENDPOINT: "true"`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`
Expected: PASS.

### Task 2: Test d'intégration S3 Garage

**Files:**
- Create: `tests/Feature/Infrastructure/GarageObjectStorageTest.php`
- Create: `compose.garage-test.yaml`
- Create: `docker/garage/garage-test.toml`

**Interfaces:**
- Consumes: Docker, image `dxflrs/garage:v2.3.0`, Laravel `Storage::build`.
- Produces: preuve automatisée des opérations `put`, `exists`, `get`, `response` et `delete` contre Garage.

- [ ] **Step 1: Write the failing test**

Créer un test Pest marqué par une variable d'activation qui utilise un bucket et une clé Garage temporaires, puis vérifie écriture, lecture, existence, réponse et suppression avec adressage path-style.

- [ ] **Step 2: Run test to verify it fails**

Run: `GARAGE_INTEGRATION=1 php artisan test tests/Feature/Infrastructure/GarageObjectStorageTest.php`
Expected: FAIL avant le démarrage/configuration du service Garage isolé.

- [ ] **Step 3: Write minimal implementation**

Ajouter le Compose et la configuration de test à volumes temporaires ; initialiser le layout mono-nœud, créer le bucket privé et attacher une clé avec lecture/écriture/propriétaire uniquement sur ce bucket.

- [ ] **Step 4: Run test to verify it passes**

Run: `GARAGE_INTEGRATION=1 php artisan test tests/Feature/Infrastructure/GarageObjectStorageTest.php`
Expected: PASS.

### Task 3: Procédure Garage et migration

**Files:**
- Create: `docs/garage-externalization.md`
- Modify: `docs/operations.md`
- Modify: `docs/technical-architecture.md`

**Interfaces:**
- Consumes: service Garage Coolify, ancienne source MinIO, outil `rclone` ou client S3.
- Produces: procédures reproductibles de déploiement, migration, intégrité, sauvegarde, restauration, bascule et retour arrière.

- [ ] **Step 1: Write the documentation contract test**

Étendre le test d'infrastructure pour exiger que la documentation nomme Garage 2.3.0, les volumes `/var/lib/garage/meta` et `/var/lib/garage/data`, la santé `/health`, la copie avec conservation des clés, le contrôle d'intégrité, la sauvegarde et le retour arrière.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`
Expected: FAIL car la procédure Garage n'existe pas.

- [ ] **Step 3: Write minimal documentation**

Documenter les commandes exactes Garage/Coolify, les droits de clé limités au bucket, l'inventaire avant/après, les essais Laravel, la conservation de MinIO et la restauration sur environnement isolé.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php`
Expected: PASS.

### Task 4: Livraison et bascule de production

**Files:**
- Modify through Coolify UI: Garage resource and application environment.

**Interfaces:**
- Consumes: release GHCR issue #134 et données MinIO existantes.
- Produces: Garage indépendant, bucket privé migré et application configurée avec le nouvel endpoint.

- [ ] **Step 1: Verify repository change**

Run: `composer lint:check && composer analyse && php artisan test tests/Feature/Infrastructure/ProductionDeploymentTest.php && git diff --check`
Expected: toutes les commandes réussissent.

- [ ] **Step 2: Deliver through GitHub**

Créer un commit Conventional Commit, pousser la branche, ouvrir la PR vers `main`, attendre les checks puis fusionner en squash. Fusionner ensuite volontairement la Release PR créée par Release Please.

- [ ] **Step 3: Prepare and validate Garage before cutover**

Déployer Garage dans Coolify, initialiser le layout, créer le bucket et sa clé limitée, copier les objets depuis MinIO, comparer le nombre d'objets et la somme des tailles, puis lire un échantillon depuis Garage.

- [ ] **Step 4: Cut over and verify production**

Renseigner `AWS_ENDPOINT`, `AWS_DEFAULT_REGION`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` et `AWS_BUCKET`, redéployer, puis vérifier `/up`, lecture/écriture/suppression S3 via Laravel et affichage d'un avatar existant. Garder MinIO arrêté ou isolé avec son volume intact.

- [ ] **Step 5: Close the issue**

Fermer #134 seulement après les preuves de santé, d'intégrité et d'accès applicatif en production.
