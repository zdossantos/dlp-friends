# Qualité, CI et livraison continue

## Stratégie de branches

`main` est l'unique branche principale, stable et déployable. Toute
modification part d'une branche dédiée (`feature/*`, `fix/*`, `chore/*`,
`docs/*` ou `refactor/*`) et ouvre une pull request directement vers `main`.

Les pushs directs, force-pushs et suppressions de `main` sont interdits, y
compris aux administrateurs. Une pull request doit être à jour, résoudre ses
conversations et réussir les six checks obligatoires. Le dépôt utilise
uniquement **Squash & Merge** et conserve ainsi un historique linéaire.

Tant qu'un seul contributeur peut relire le dépôt, aucune approbation n'est
requise : l'auteur ne peut pas approuver sa propre pull request. Cette valeur
doit passer à une approbation dès qu'un second reviewer est disponible.

## Conventional Commits

Le titre de chaque pull request respecte Conventional Commits, car GitHub
l'utilise comme titre du commit squash final :

- `feat:` produit normalement une version mineure ;
- `fix:` produit normalement une version patch ;
- `feat!:` ou un pied `BREAKING CHANGE:` produit une version majeure ;
- `perf:`, `refactor:`, `docs:`, `test:`, `build:`, `ci:`, `chore:` et
  `revert:` décrivent les autres changements.

Un scope facultatif précise le domaine, par exemple `fix(auth): préserver la
session`. Le check `Conventional PR title` bloque les titres non conformes.

## CI des pull requests

Le workflow `CI` se déclenche à l'ouverture, la synchronisation, la réouverture
ou le passage hors brouillon d'une pull request vers `main`. Une concurrence par
pull request annule les runs devenus obsolètes. Il exécute les cinq checks
applicatifs sur le commit proposé.

Le workflow `PR title` revalide aussi le titre après chaque modification de la
pull request. Ce workflow de métadonnées est en lecture seule, extrait le
validateur depuis la branche par défaut et n'exécute jamais le code proposé.

Les six checks indépendants sont :

1. `Conventional PR title` valide le futur message du commit squash ;
2. `PHP quality` exécute Laravel Pint et PHPStan/Larastan ;
3. `Backend tests` exécute Pest avec MySQL ;
4. `Frontend quality` génère Wayfinder puis exécute ESLint, Prettier,
   TypeScript et Vitest ;
5. `Vite build` compile les assets de production ;
6. `Docker build` construit l'image runtime sans la publier.

Les dépendances sont installées depuis `composer.lock` et `bun.lock` avec Bun
1.3.14. GitHub Actions résout dynamiquement le répertoire de cache Composer,
et met aussi en cache les téléchargements Bun et les couches Docker BuildKit.
Aucun merge n'est possible tant qu'un check requis échoue.

## Dependabot

Dependabot surveille chaque semaine Composer, Bun et GitHub Actions. Chaque
écosystème conserve son propre groupe de mises à jour, son cooldown de cinq
jours et sa limite de pull requests. Toutes les pull requests Dependabot ciblent
`main` et passent les six checks comme les autres contributions.

## Release Please et SemVer

Release Please est le seul mécanisme autorisé à créer une version. À chaque
push sur `main`, il analyse les Conventional Commits depuis le dernier tag,
maintient une Release PR et prépare :

- la prochaine version SemVer ;
- la mise à jour de `CHANGELOG.md` ;
- le tag préfixé par `v` ;
- la GitHub Release correspondante.

Le merge d'une feature ou d'un fix dans `main` ne publie donc pas immédiatement
de version. La publication est volontaire et intervient uniquement lors du
merge de la Release PR. Aucun package npm ou Composer n'est publié : DLP Friends
est une application web.

Le workflow utilise `RELEASE_PLEASE_TOKEN`, jeton limité à ce dépôt, afin que sa
pull request déclenche la CI normale. Il dispose seulement des permissions
d'écriture nécessaires aux contenus, issues et pull requests. Les autres
workflows restent en lecture et aucun workflow ne crée directement de tag ou de
release.

## Production

Coolify surveille uniquement `main` et déploie automatiquement chaque commit
validé qui y est fusionné. GitHub Actions ne déclenche pas Coolify et ne stocke
aucun webhook ou identifiant de déploiement. Les futurs secrets SMTP de
production appartiennent à Coolify ; la CI n'envoie jamais d'e-mail réel.

## Gestion des échecs

Un job en échec bloque uniquement la pull request concernée. Une exécution
annulée après un nouveau push est remplacée par le run le plus récent. Une
erreur Release Please laisse `main` intact et sera retentée au prochain push.
Les réglages distants sont reproductibles depuis `.github/settings/` et ne sont
appliqués qu'après la réussite des checks connus par GitHub.

## Flux

```text
branche de travail
      ↓
  PR vers main
      ↓
 six checks CI
      ↓
 Squash & Merge
      ↓
main stable ───────────────→ Coolify
      ↓
Release Please maintient sa Release PR
      ↓
merge volontaire de la Release PR
      ↓
CHANGELOG + tag vX.Y.Z + GitHub Release

Dependabot (Composer, Bun, Actions)
      ↓
  PR vers main → même CI
```

Le guide pas à pas pour contribuer et publier se trouve dans
[`CONTRIBUTING.md`](../CONTRIBUTING.md).
