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

La promotion et Release Please utilisent le secret GitHub Actions
`RELEASE_PLEASE_TOKEN`, contenant un jeton fin limité à ce dépôt. Ce jeton est
nécessaire pour que leurs PR déclenchent la CI ; il ne sert à aucun déploiement ni
publication de package.

La première version stable est amorcée en `v1.0.0` avec le pied de commit
`Release-As: 1.0.0`. Les versions suivantes sont calculées automatiquement à
partir des Conventional Commits publiés sur `main`.

## Dépendances — Dependabot

Dependabot surveille et met à jour automatiquement :

- Composer ;
- npm ;
- GitHub Actions.

Les mises à jour sont proposées sous forme de PR vers `develop` et doivent passer la CI avant fusion.

## CI — PR vers develop et main

Déclencheurs : ouverture, synchronisation, réouverture ou passage en revue d'une
PR vers `develop` ou `main`.

Checks obligatoires :

1. `PHP quality` : Laravel Pint et PHPStan/Larastan.
2. `Backend tests` : tests Pest avec MySQL.
3. `Frontend quality` : ESLint, Prettier, TypeScript et Vitest.
4. `Vite build` : compilation des assets frontend.
5. `Docker build` : construction de l'image applicative sans publication.

Le cache BuildKit de l'image runtime est conservé par GitHub Actions. Il est
réchauffé depuis `develop` avant la promotion afin que toutes les pull requests
puissent réutiliser les extensions PHP natives déjà compilées.

Aucun merge n'est autorisé si les checks requis échouent.

## PR automatique develop → main

À chaque push sur `develop` :

- réchauffer le cache Docker partagé ;
- vérifier si une PR `develop → main` existe ;
- la créer automatiquement si elle n'existe pas ;
- si elle existe, la laisser se mettre à jour avec les nouveaux commits ;
- ne jamais fusionner automatiquement cette PR.

La fusion vers `main` reste manuelle après revue et validation de la CI.
Elle utilise un merge commit : le squash masquerait les Conventional Commits de
`develop` à Release Please, tandis qu'un rebase répété réécrirait l'historique de
la branche d'intégration.

Dans l'interface GitHub, sélectionner explicitement **Create a merge commit**
pour la PR `develop → main`. Ne pas utiliser **Squash and merge** : cette méthode
ferait diverger les historiques et provoquerait une proposition inverse
`main → develop` ainsi que des conflits lors de la promotion suivante.

## Production

Coolify surveille uniquement `main` et déploie automatiquement chaque nouveau commit.

GitHub Actions ne gère pas le déploiement Coolify et ne contient aucun webhook ou identifiant de déploiement.

Ne jamais déployer `develop` ou une branche de fonctionnalité en production.

## Protection GitHub

- Interdire les pushes directs sur `develop` et `main`.
- Conserver un historique linéaire sur `develop` pour les PR de fonctionnalité.
- Autoriser les merge commits sur `main` pour les promotions depuis `develop`.
- Les futurs identifiants SMTP de production sont des secrets Coolify ; la CI ne doit jamais envoyer d'e-mail réel.
- Exiger les checks CI requis avant merge.
- Tant que le dépôt n'a qu'un seul contributeur, ne demander aucune approbation
  sur `main` afin de ne pas bloquer les publications. Passer à une approbation
  obligatoire dès qu'un second reviewer est disponible.
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
```
