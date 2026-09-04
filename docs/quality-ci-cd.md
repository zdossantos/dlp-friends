# Qualité, CI et livraison continue

Ce document est la source détaillée du processus de qualité et de livraison.
Le guide contributeur pas à pas reste dans
[`CONTRIBUTING.md`](../CONTRIBUTING.md).

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
3. `Pest tests` installe Chromium, construit les assets et exécute avec MySQL
   les suites Pest unitaires, fonctionnelles et navigateur ;
4. `Frontend quality` génère Wayfinder puis exécute ESLint, Prettier et
   TypeScript ;
5. `Vite build` compile les assets de production ;
6. `Docker build` construit l’image runtime `linux/amd64` sans la publier, puis
   démarre un conteneur et vérifie `/up` et la présence des assets compilés.
   Ce contrôle s’applique aussi à la Release PR, via `RELEASE_PLEASE_TOKEN`.

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
d’écriture nécessaires aux contenus, issues et pull requests. Les jobs de
publication et de déploiement utilisent le `GITHUB_TOKEN` éphémère avec les
permissions décrites ci-dessous. Aucun autre mécanisme ne crée de tag ou de
release.

## Production : image de release sur GHCR

Le workflow `Release Please` conserve une concurrence unique et n’annule pas
une livraison en cours. Il enchaîne :

1. Release Please crée ou actualise la Release PR ; seul `release_created ==
   'true'` autorise la publication de l’image.
2. `Publish release image` extrait le **SHA retourné par Release Please**,
   vérifie que le tag et le manifeste de version désignent ce commit, puis
   construit et publie le runtime `linux/amd64` dans GHCR. L’architecture du VPS
   Ubuntu a été vérifiée dans Coolify : `x86_64`.
3. Le tag `ghcr.io/zdossantos/dlp-friends:vX.Y.Z-<SHA complet>` et les labels OCI
   identifient version et commit. Le workflow démarre l’image **publiée par son
   digest** et vérifie `/up` ainsi que les assets. Le digest, contrairement au
   tag, constitue la référence immuable utilisée en production.
4. Après succès complet de ce job, `Record image and deploy to Coolify` joint
   `container-image.json` à la GitHub Release : version, commit, plateforme et
   référence `ghcr.io/zdossantos/dlp-friends@sha256:…`.
5. Le script `.github/scripts/deploy-release.sh` met à jour uniquement
   `APP_IMAGE` dans les variables Coolify, fixe `git_commit_sha` au commit de
   release pour charger le bon Compose, puis appelle `POST /api/v1/deploy`.
   Il exige un digest et un SHA complets ; chaque appel doit réussir avant le
   suivant. L’acceptation de la requête n’atteste pas encore la santé du service.

Coolify garde le dépôt Git et le build pack **Docker Compose**, avec
`compose.production.yaml`. Les quatre services `web`, `worker`, `scheduler` et
`reverb` héritent de `APP_IMAGE` ; aucun ne contient de section `build`.
Désactiver **Auto Deploy** dans Coolify avant de fusionner ce changement : un
push de feature ou le merge ordinaire dans `main` ne doit pas lancer la stack.
Ne pas configurer de commande personnalisée de build.

Coolify 4.3.14 lance encore une phase `docker compose build` pour préparer la
stack ; sans section `build`, aucune image applicative n’est compilée. Il doit
néanmoins résoudre les variables du Compose pendant cette phase. `APP_IMAGE`
est donc disponible au build **et** à l’exécution dans Coolify. Conserver aussi
la disponibilité des autres variables nécessaires à cette interpolation sur
le VPS ; cela ne les transmet pas au build GitHub ni à une couche de l’image.

### Configuration et droits

Dans GitHub, **Settings → Secrets and variables → Actions → Variables** :

| Variable | Valeur |
| --- | --- |
| `VITE_REVERB_APP_KEY` | Clé publique, identique à `REVERB_APP_KEY` dans Coolify. |
| `VITE_REVERB_HOST` | Hôte WebSocket public, sans protocole ni chemin. |
| `VITE_REVERB_PORT` | `443` en production. |
| `VITE_REVERB_SCHEME` | `https` en production. |
| `COOLIFY_API_URL` | `https://coolify.zdossantos.fr/api/v1`. |
| `COOLIFY_APPLICATION_UUID` | Identifiant de l’application Docker Compose DLP Friends, visible dans son URL. |

Les quatre `VITE_REVERB_*` sont obligatoires à la publication. La CI des PR
emploie des valeurs publiques de test et ne reçoit aucun secret de production.
Une modification des valeurs Vite exige une nouvelle image ; changer les
variables Coolify seules ne modifie pas les assets déjà compilés. Le build
installe les dépendances et compile les assets sans joindre MySQL, Redis,
MinIO ou Resend. Le réseau sortant vers les registres de dépendances est requis.

Les seuls secrets GitHub nécessaires sont `RELEASE_PLEASE_TOKEN` et
`COOLIFY_TOKEN`. L’ancien `COOLIFY_WEBHOOK` n’est plus utilisé : l’URL de l’API
et l’UUID remplacent le webhook opaque, afin de modifier puis déployer la même
application. `COOLIFY_TOKEN` doit autoriser la modification de l’application,
la gestion de ses variables et son déploiement (droits **write** et **deploy**),
sur l’équipe et les ressources nécessaires uniquement. Il n’a pas besoin de
lire ou de divulguer les valeurs des autres variables.

Le package GHCR reste **privé**. Le job de publication reçoit seulement
`contents: read` et `packages: write` via `GITHUB_TOKEN` ; le job de déploiement
reçoit `contents: write` pour joindre la référence à la release, sans droit
sur les packages. Aucun PAT de publication supplémentaire n’est nécessaire.
Si le package existait déjà, lui donner accès au dépôt depuis les réglages du
package. Vérifier sa visibilité privée après la première publication.

Le VPS s’authentifie à GHCR avec un PAT classic dédié **`read:packages`**,
appartenant à un compte autorisé à lire ce package. Pas de `write:packages`,
`delete:packages` ni de droit `repo` supplémentaire pour ce besoin. Configurer
`docker login ghcr.io --username <compte>` sur le serveur, avec l’utilisateur
SSH employé par Coolify (`root` sur le VPS actuel), puis saisir le PAT à
l’invite masquée. Ne pas placer ce PAT dans le Compose, dans les arguments de
build ou dans les variables de l’application. Tester ensuite un `docker pull`
de la référence par digest fournie dans `container-image.json`.

Les secrets Laravel, Reverb, MySQL, MinIO/S3 et Resend restent dans Coolify et
ne sont fournis qu’aux conteneurs au démarrage. `.dockerignore` exclut les
fichiers `.env` locaux. Les migrations restent une opération explicite.

Références : [flux GitHub Actions de Coolify](https://coolify.io/docs/applications/ci-cd/github/actions/),
[API des variables](https://coolify.io/docs/api-reference/api/applications/update-envs-by-application-uuid),
[API de configuration](https://coolify.io/docs/api-reference/api/applications/update-application-by-uuid),
[accès et digests GHCR](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry).

## Gestion des échecs

Un échec de CI bloque la PR. Sans nouvelle release, aucun job de publication ou
de déploiement ne s’exécute. Un échec de build, de push, de test de démarrage
ou d’enregistrement de la référence empêche tout appel de déploiement Coolify.
Un échec de mise à jour de l’image ou du commit arrête également le script
avant l’appel de déploiement. Les corps des réponses API ne sont pas journalisés.

Après correction de la configuration, utiliser **Re-run failed jobs** du run
initial pour conserver les sorties de Release Please. Ne pas relancer tous
les jobs : une release déjà créée ne produira plus `release_created == true`.
Si le problème nécessite une correction du code de release, passer par une
nouvelle PR puis une nouvelle release ; ne pas déplacer son tag.

Les appels de mutation Coolify ne sont pas retentés automatiquement. Après un
timeout, consulter les déploiements dans Coolify avant de relancer : la requête
peut avoir été acceptée. Si une mise à jour de configuration a réussi puis la
suivante a échoué, les conteneurs actuels continuent de tourner mais les champs
peuvent être partiellement modifiés. Vérifier le couple `APP_IMAGE` /
`git_commit_sha` avant toute action manuelle. Une procédure de retour arrière
est décrite dans [`operations.md`](operations.md).

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
main stable (aucun déploiement automatique)
      ↓
Release Please maintient sa Release PR
      ↓
merge volontaire de la Release PR
      ↓
CHANGELOG + tag vX.Y.Z + GitHub Release
      ↓
checkout du SHA de release → build AMD64 → publication GHCR
      ↓
test de démarrage par digest → référence jointe à la release
      ↓
Coolify : image + commit épinglés → déploiement sans build

Dependabot (Composer, Bun, Actions)
      ↓
  PR vers main → même CI
```

Le guide pas à pas pour contribuer et publier se trouve dans
[`CONTRIBUTING.md`](../CONTRIBUTING.md).
