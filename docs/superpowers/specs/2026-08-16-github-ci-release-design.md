# CI GitHub et automatisation des releases — Design

## Périmètre

Cette étape met en place la tâche 2 de `docs/implementation-plan.md` et applique les décisions de `docs/quality-ci-cd.md`. Elle couvre les contrôles applicatifs versionnés, la promotion de `develop` vers `main`, Release Please, Dependabot et les réglages distants du dépôt GitHub.

Coolify reste hors de GitHub Actions. Il surveille uniquement `main` et déploie les commits qui y sont fusionnés. Aucun secret, webhook ou identifiant Coolify n'est ajouté au dépôt.

## Branches et flux de livraison

`develop` est la branche d'intégration et la branche par défaut. Les branches de fonctionnalité arrivent sur `develop` par pull request.

Chaque push sur `develop` déclenche un workflow de promotion. Celui-ci
reconstruit `automation/promote-develop` depuis le dernier `main`, y fusionne
`develop`, puis maintient une unique pull request de cette branche technique
vers `main`. Un push sur `main` ou un déclenchement manuel réaligne également une
promotion ouverte. Le workflow ne fusionne jamais cette pull request.

`main` est la branche de production. Après la fusion manuelle de la pull request de promotion, Release Please analyse les Conventional Commits de `main`. Il maintient une pull request de release, met à jour `CHANGELOG.md`, puis crée le tag SemVer et la GitHub Release quand cette pull request est fusionnée. Le type de release est `simple` : aucun package npm ou Composer n'est publié.

## CI des pull requests

Un workflow CI unique s'exécute pour les pull requests vers `develop` et `main`. Le ciblage de `main` est nécessaire pour vérifier la promotion avant la mise en production, même si l'exigence documentaire minimale porte sur `develop`.

Les responsabilités sont séparées en checks stables et parallèles :

- `php-quality` installe Composer de manière verrouillée, puis exécute Laravel Pint en mode vérification et Larastan/PHPStan ;
- `backend-tests` démarre un service MySQL, prépare une configuration Laravel de test sans e-mail réel, puis exécute Pest ;
- `frontend-quality` installe npm avec `npm ci`, puis exécute ESLint, Prettier, la vérification TypeScript et Vitest ;
- `build` installe les dépendances PHP et Node nécessaires puis compile les assets Vite ;
- `docker-build` construit l'image applicative depuis le `Dockerfile` sans la publier.

Les jobs utilisent les versions PHP et Node attendues par le dépôt, des installations verrouillées et des caches natifs fondés sur les lockfiles. Une concurrence par workflow et par pull request annule les exécutions devenues obsolètes après un nouveau push.

## Scripts et tests frontend

Les scripts Composer et npm fournissent des commandes explicites et non mutantes pour la CI : tests, tests unitaires, lint, formatage, analyse statique, vérification des types et build. Les commandes locales et celles des workflows partagent les mêmes scripts afin d'éviter deux définitions divergentes de la qualité.

Vitest est configuré pour découvrir les futurs tests Vue. Comme le dépôt ne contient pas encore de test frontend, son exécution accepte temporairement l'absence de fichiers de test. Dès qu'un test existe, il est exécuté normalement et son échec bloque la CI.

## Release Please

Release Please utilise un fichier de configuration à la racine et un manifeste de version. La version initiale reflète un produit encore pré-MVP afin que les Conventional Commits futurs produisent des versions SemVer cohérentes. Les tags portent le préfixe `v`.

Les sections du changelog mettent en avant les fonctionnalités, corrections et changements incompatibles. Les commits `docs`, `test`, `chore`, `ci` et `refactor` ne déclenchent pas seuls une nouvelle version, conformément à `docs/quality-ci-cd.md`.

## Dependabot et fichiers communautaires

Dependabot surveille chaque semaine Composer, npm et GitHub Actions depuis la racine. Toutes ses pull requests ciblent `develop`. Les mises à jour d'un même écosystème peuvent être regroupées de manière prudente pour limiter le bruit sans mélanger Composer, npm et Actions.

Un modèle de pull request rappelle les contrôles utiles et l'usage de Conventional Commits. `CODEOWNERS` attribue par défaut la revue au propriétaire du dépôt. Aucun formulaire d'issue ou automatisme supplémentaire n'est ajouté sans besoin documenté.

## Permissions et sécurité

Les workflows ont `contents: read` par défaut. Le workflow de promotion reçoit
`contents: write` pour publier uniquement `automation/promote-develop` et
`pull-requests: write` pour maintenir sa pull request. Il utilise un
force-with-lease lié au SHA distant observé et ne pousse jamais `develop` ou
`main`. Release Please reçoit les permissions d'écriture strictement requises
pour sa pull request, ses tags et ses releases. Les actions tierces sont
référencées par commit immuable lorsque cela est compatible avec Dependabot.

Aucun workflow provenant d'une pull request non fiable n'accède à un secret de production. Les tests utilisent uniquement des valeurs éphémères et un service MySQL isolé. La CI ne contacte aucun transport SMTP réel.

## Réglages GitHub distants

La branche par défaut est `develop`. GitHub supprime automatiquement les branches après fusion et autorise GitHub Actions à créer les pull requests nécessaires aux deux automatisations.

Les règles de `develop` et `main` imposent une pull request, la réussite des checks CI, la résolution des conversations, et interdisent les force-pushs ainsi que la suppression des branches. `develop` conserve un historique linéaire pour les branches de fonctionnalité.

`main` autorise les merge commits pour les promotions qui intègrent `develop`.
La branche technique part toujours du dernier `main`, ce qui permet de conserver
l'exigence de branche à jour malgré les commits propres à `main`. Le merge commit
préserve l'ascendance de la branche d'intégration et rend ses Conventional
Commits visibles par Release Please. Un squash les masquerait derrière le seul
titre de la pull request.

Le dépôt n'a actuellement qu'un seul contributeur. Par exception explicite à la recommandation générale de `docs/quality-ci-cd.md`, `main` ne requiert aucune approbation tant qu'aucun second reviewer n'est disponible. Exiger une approbation rendrait toute publication impossible puisque l'auteur d'une pull request ne peut pas approuver sa propre modification. Tous les autres contrôles de `main` restent obligatoires.

Les protections distantes sont appliquées après que les workflows et leurs noms de checks existent sur GitHub, afin que les règles ciblent des checks valides.

## Comportement en échec

Un échec dans un job bloque uniquement le merge concerné et reste attribuable à une responsabilité claire. Un ancien run annulé n'empêche pas le run le plus récent de devenir la source de vérité.

Le workflow de promotion est idempotent : une erreur de construction, de push,
de recherche ou de création rend le run rouge, mais ne modifie aucune branche
protégée et ne crée jamais plusieurs pull requests volontairement. Un conflit
entre `main` et `develop` échoue avant tout push. Release Please conserve sa
propre pull request et reprend au prochain push sur `main` après une erreur
temporaire.

Si les permissions GitHub ou l'authentification empêchent l'application d'un réglage distant, les fichiers versionnés restent valides et le réglage non appliqué est signalé précisément ; aucune protection existante n'est supprimée pour contourner le problème.

## Vérification et critères d'acceptation

L'étape est acceptée lorsque :

1. les fichiers YAML et JSON ajoutés sont syntaxiquement valides ;
2. chaque script PHP et Node utilisé par la CI termine avec le code zéro localement ;
3. Pest utilise MySQL dans le job backend ;
4. le build Vite réussit ;
5. le build Docker réussit ;
6. Dependabot couvre Composer, npm et GitHub Actions vers `develop` ;
7. la promotion crée au plus une pull request
   `automation/promote-develop` vers `main`, toujours basée sur le dernier
   `main`, et ne la fusionne pas ;
8. Release Please est configuré pour une application sans publication de package ;
9. les permissions de chaque workflow respectent le moindre privilège ;
10. les protections et réglages GitHub distants correspondent à ce document, à l'exception d'une limitation explicitement signalée par GitHub ou l'authentification disponible.
