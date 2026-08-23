# Contribuer à DLP Friends

## Préparer le projet

Installer PHP 8.4, Composer 2, Node.js 22 avec npm et Docker Desktop. Le
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

Exécuter les contrôles concernés avant d'ouvrir une pull request :

```sh
composer lint:check
composer analyse
php artisan test
php artisan wayfinder:generate --with-form
npm run lint:check
npm run format:check
npm run types:check
npm test
npm run build
docker build --target runtime --tag dlp-friends:ci .
```

## Ouvrir et fusionner la pull request

Ouvrir la pull request directement vers `main`. Son titre doit suivre
Conventional Commits, car il devient le message du commit final lors du squash :

- `feat: ajouter une fonctionnalité` ;
- `fix(auth): corriger la session` ;
- `feat!: modifier un contrat public` ;
- `chore:`, `docs:`, `refactor:`, `test:`, `build:`, `ci:` ou `revert:` lorsque
  ces types décrivent mieux le changement.

Attendre la réussite de `Conventional PR title`, `PHP quality`, `Backend tests`,
`Frontend quality`, `Vite build` et `Docker build`, puis résoudre toutes les
conversations. Utiliser uniquement **Squash & Merge**.

## Publier une version

Le merge d'une feature ou d'un fix dans `main` ne publie pas immédiatement de
version. Release Please accumule les Conventional Commits dans une Release PR et
prépare `CHANGELOG.md` ainsi que la prochaine version SemVer.

La publication est volontaire : merger la Release PR avec **Squash & Merge**
finalise le changelog, crée le tag `vX.Y.Z` et la GitHub Release correspondante.
Aucun package npm ou Composer n'est publié.
