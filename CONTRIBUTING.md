# Contribuer à DLP Friends

## Préparer le projet

Installer PHP 8.4, Composer 2, Bun 1.3.14 et Docker Desktop. Le
[`README.md`](README.md) décrit le démarrage local et les commandes de contrôle.

## Créer une branche de travail

Partir d'un `main` à jour et créer une branche dédiée :

```sh
git switch main
git pull --ff-only
git switch -c feature/nom-court
```

Utiliser selon le changement `feature/*`, `fix/*`, `chore/*`, `docs/*` ou
`refactor/*`. Ne jamais pousser directement sur `main`.

## Vérifier le changement

La suite backend s'exécute sur une instance MySQL 8.4 dédiée et éphémère. La
préparer avant les contrôles PHP :

```sh
docker compose --profile test up -d --wait mysql-test
bunx playwright install chromium
php tests/Support/verify-test-database.php
```

Le service écoute sur `127.0.0.1:3307` et utilise la configuration versionnée
dans `.env.testing`. Il ne partage ni données ni volume avec la base de
développement. Il n'existe aucun repli vers SQLite : une base indisponible ou un
pilote différent fait échouer immédiatement la commande.

Exécuter ensuite les contrôles concernés avant d'ouvrir une pull request :

```sh
composer lint:check
composer analyse
composer test
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
docker build --target runtime --tag dlp-friends:ci .
```

`composer test` et `composer ci:check` utilisent cette même base MySQL et
exécutent les suites Pest unitaires, fonctionnelles et navigateur. PHPUnit
traite également tout nouvel avertissement comme un échec. Une fois les
contrôles terminés, supprimer le service et ses données éphémères avec
`docker compose --profile test rm -sf mysql-test`.

## Ouvrir et fusionner la pull request

Ouvrir la pull request directement vers `main`. Son titre doit suivre
Conventional Commits, car il devient le message du commit final lors du squash :

- `feat: ajouter une fonctionnalité` ;
- `fix(auth): corriger la session` ;
- `feat!: modifier un contrat public` ;
- `chore:`, `docs:`, `refactor:`, `test:`, `build:`, `ci:` ou `revert:` lorsque
  ces types décrivent mieux le changement.

Attendre la réussite de `Conventional PR title`, `PHP quality`, `Pest tests`,
`Frontend quality`, `Vite build` et `Docker build`, puis résoudre toutes les
conversations. Utiliser uniquement **Squash & Merge**.

## Publier une version

Le merge d'une feature ou d'un fix dans `main` ne publie pas immédiatement de
version. Release Please accumule les Conventional Commits dans une Release PR et
prépare `CHANGELOG.md` ainsi que la prochaine version SemVer.

La publication est volontaire : merger la Release PR avec **Squash & Merge**
finalise le changelog, crée le tag `vX.Y.Z` et la GitHub Release correspondante.
Aucun package npm ou Composer n'est publié.
