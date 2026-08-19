# Qualité, CI et livraison continue

## Stratégie de branches

- `develop` : branche d'intégration. Toute modification arrive par PR.
- `main` : branche de production. Aucun push direct ; elle reçoit les PR de
  promotion qui intègrent `develop` et les PR de version de Release Please.
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

## PR automatique de promotion vers main

À chaque push sur `develop` :

- réchauffer le cache Docker partagé ;
- reconstruire `automation/promote-develop` depuis le dernier `main` ;
- fusionner `develop` dans cette branche technique avec un merge commit ;
- publier uniquement la branche technique avec `--force-with-lease` ;
- créer une PR `automation/promote-develop → main` si elle n'existe pas ;
- ne jamais fusionner automatiquement cette PR.

Le workflow se déclenche aussi après un push sur `main` et manuellement. Il ferme
une promotion devenue vide lorsque `main` contient déjà tout `develop`. Il ne
force-push jamais `develop` ou `main` et ne diminue aucune protection.

Dans l'interface GitHub, sélectionner explicitement **Create a merge commit**
pour la PR `automation/promote-develop → main`. Ne pas utiliser **Update
branch**, **Squash and merge** ou rebase : la branche technique contient déjà le
dernier `main`, et Release Please doit voir les Conventional Commits de
`develop` devenus ancêtres de `main`.

## Production

Coolify surveille uniquement `main` et déploie automatiquement chaque nouveau commit.

GitHub Actions ne gère pas le déploiement Coolify et ne contient aucun webhook ou identifiant de déploiement.

Ne jamais déployer `develop` ou une branche de fonctionnalité en production.

## Protection GitHub

- Interdire les pushes directs sur `develop` et `main`.
- Conserver un historique linéaire sur `develop` pour les PR de fonctionnalité.
- Conserver l'exigence de branche à jour sur `main` : la branche technique part
  toujours du dernier `main`.
- Autoriser les merge commits sur `main` pour les promotions qui intègrent
  `develop`.
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
automation/promote-develop (dernier main + develop)
    ↓
PR automatique → main
    ↓
Revue + CI + action Promote to production
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
