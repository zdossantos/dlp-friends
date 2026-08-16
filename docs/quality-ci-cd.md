# Qualité, CI et livraison continue

## Stratégie de branches

- `develop` : branche d'intégration. Toute modification arrive par PR.
- `main` : branche de production. Aucun push direct ; elle reçoit uniquement les PR de publication depuis `develop`.
- Les branches `develop` et `main` sont protégées et les checks CI requis doivent passer avant fusion.

## Conventional Commits

Tous les commits doivent respecter Conventional Commits :

- `feat:` → version mineure.
- `fix:` → version patch.
- `feat!:` ou `BREAKING CHANGE:` → version majeure.
- `docs:`, `refactor:`, `test:`, `chore:`, `ci:` → aucune nouvelle version par défaut.

Exemples : `feat: add matching filters`, `fix: prevent duplicate matches`, `chore(deps): update laravel`.

## Versioning — Release Please

Release Please gère automatiquement le versioning SemVer de l'application :

- analyse les Conventional Commits ;
- génère/met à jour `CHANGELOG.md` ;
- crée les PR de release ;
- crée les tags (`v1.0.0`, `v1.1.0`, `v1.1.1`, etc.) ;
- crée les GitHub Releases.

Release Please ne publie aucun package npm : l'application est un site web.

Le versioning est effectué à partir de `main` après publication des changements.

## Dépendances — Dependabot

Dependabot surveille et met à jour automatiquement :

- Composer ;
- npm ;
- GitHub Actions.

Les mises à jour sont proposées sous forme de PR vers `develop` et doivent passer la CI avant fusion.

## CI — PR vers develop

Déclencheurs : ouverture, synchronisation ou réouverture d'une PR vers `develop`.

Checks obligatoires :

1. Checkout et installation PHP/Node.
2. Installation verrouillée des dépendances Composer/npm.
3. Laravel Pint, PHPStan/Larastan, lint et formatage.
4. Tests Pest backend avec MySQL.
5. Tests frontend, type-check TypeScript et build Vite.
6. Build Docker.

Aucun merge n'est autorisé si les checks requis échouent.

## PR automatique develop → main

À chaque push sur `develop` :

- vérifier si une PR `develop → main` existe ;
- la créer automatiquement si elle n'existe pas ;
- si elle existe, la laisser se mettre à jour avec les nouveaux commits ;
- ne jamais fusionner automatiquement cette PR.

La fusion vers `main` reste manuelle après revue et validation de la CI.

## Production

Coolify surveille uniquement `main` et déploie automatiquement chaque nouveau commit.

GitHub Actions ne gère pas le déploiement Coolify et ne contient aucun webhook ou identifiant de déploiement.

Ne jamais déployer `develop` ou une branche de fonctionnalité en production.

## Protection GitHub

- Interdire les pushes directs sur `develop` et `main`.
- Les futurs identifiants SMTP de production sont des secrets Coolify ; la CI ne doit jamais envoyer d'e-mail réel.
- Exiger les checks CI requis avant merge.
- Exiger au minimum une approbation sur `main`.
- Annuler les validations obsolètes après nouveau push.
- Utiliser le principe du moindre privilège pour les permissions GitHub Actions.
- Autoriser uniquement les workflows nécessaires à créer/modifier les PR et releases.
- Ne jamais stocker de secrets ou `.env` de production dans Git.

## Flux

```text
Feature branch
    ↓
PR → develop
    ↓
CI + tests
    ↓
develop
    ↓
PR automatique → main
    ↓
Revue + CI + merge manuel
    ↓
main
    ├── Release Please → SemVer + CHANGELOG + tag + GitHub Release
    └── Coolify → Production

Dependabot
    ├── Composer
    ├── npm
    └── GitHub Actions
          ↓
       PR → develop
