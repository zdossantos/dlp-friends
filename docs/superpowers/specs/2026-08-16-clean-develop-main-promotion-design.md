# Promotion propre de `develop` vers `main` — Design

## Objectif

Conserver `develop` comme passage obligatoire pour toute modification, maintenir
les protections fortes de `develop` et `main`, et éliminer définitivement le
blocage des pull requests de promotion causé par la divergence normale de leurs
historiques.

Release Please reste responsable du calcul SemVer, de `CHANGELOG.md`, des tags
et des GitHub Releases. Il intervient sur `main`, conformément à son modèle
natif : il analyse une branche cible et ouvre sa pull request de release vers
cette même branche. La promotion entre deux branches longues reste la
responsabilité d'un workflow GitHub dédié.

## Cause à corriger

Le workflow actuel ouvre une pull request permanente `develop → main`. Après la
première publication, `main` contient des commits absents de `develop` : commits
de merge des promotions et commits de release produits par Release Please.

La protection de `main` exige que la branche source soit à jour. Une mise à jour
de `develop` par merge violerait son historique linéaire ; une mise à jour par
rebase nécessiterait un force-push interdit. Le bouton **Update branch** ne peut
donc satisfaire simultanément les règles existantes.

## Architecture retenue

Le workflow de promotion possède une branche technique unique nommée
`automation/promote-develop`. Cette branche n'est jamais utilisée pour le
développement et n'est pas protégée comme une branche longue.

À chaque push sur `develop`, ainsi qu'à chaque changement pertinent de `main`, le
workflow :

1. récupère les derniers commits de `main` et `develop` ;
2. reconstruit `automation/promote-develop` depuis le dernier `main` ;
3. fusionne le dernier `develop` avec un merge commit explicite ;
4. publie cette branche technique avec `--force-with-lease` ;
5. crée une pull request `automation/promote-develop → main` si elle n'existe
   pas, ou laisse GitHub actualiser la pull request existante ;
6. ne fusionne jamais automatiquement la pull request.

Le force-push est limité à la branche technique détenue par l'automatisation. Il
n'est jamais exécuté sur `develop` ou `main`. Comme la branche technique repart
du dernier `main`, la pull request satisfait toujours la règle exigeant une
branche à jour.

## Flux de livraison

```text
feature/*
    ↓ pull request + CI
develop
    ↓ workflow de promotion
automation/promote-develop (dernier main + merge de develop)
    ↓ pull request + CI + fusion manuelle par merge commit
main
    ├── Coolify déploie le nouveau commit de production
    ↓ Release Please
release-please--branches--main (version + changelog)
    ↓ pull request + CI + fusion manuelle
tag vX.Y.Z + GitHub Release
```

Toutes les branches fonctionnelles et Dependabot ciblent `develop`. Une pull
request applicative directe vers `main` est hors workflow. Les seules pull
requests attendues vers `main` sont la promotion technique et la pull request de
release créée par Release Please.

## Déclenchement et concurrence

Le workflow est déclenché :

- après chaque push sur `develop`, pour proposer les nouveaux changements ;
- manuellement avec `workflow_dispatch`, pour permettre une réparation
  contrôlée ;
- après un push sur `main`, uniquement si `develop` contient de nouveau des
  changements non promus, afin de réaligner une promotion encore ouverte après
  une release ou une autre évolution autorisée de `main`.

Une concurrence unique `promote-develop` sérialise les exécutions. Une exécution
plus ancienne ne doit jamais écraser la branche calculée par une exécution plus
récente.

## Construction sûre de la branche

Le job utilise un checkout avec l'historique complet et des références distantes
explicites. Avant toute écriture, il vérifie les invariants suivants :

- l'événement concerne bien `develop`, `main` ou un déclenchement manuel ;
- `origin/main` et `origin/develop` existent ;
- le dépôt n'est pas dans un état de shallow clone ;
- le jeton d'automatisation peut pousser uniquement la branche technique et
  gérer ses pull requests.

La branche est créée à partir de `origin/main`, puis `origin/develop` est fusionné
avec `--no-ff`. Si la fusion produit un conflit, le workflow échoue sans pousser
de branche partielle et explique qu'un conflit réel entre les deux branches doit
être corrigé par une pull request normale. Aucun `git push --force` non protégé
par lease n'est autorisé.

Lorsque `main` contient déjà tout `develop`, le workflow ne crée pas de promotion
vide. S'il existe encore une ancienne pull request de promotion, il la ferme avec
un commentaire indiquant que la promotion est devenue inutile.

## Pull request de promotion

La pull request porte le titre stable `chore: promote develop to main`. Son corps
explique qu'elle est reconstruite automatiquement, qu'elle doit être fusionnée
avec **Create a merge commit**, et qu'il ne faut pas utiliser **Update branch**.

Le workflow identifie la pull request par le couple exact
`automation/promote-develop → main`, ce qui garantit l'idempotence. Il n'ouvre
jamais plusieurs promotions simultanées.

Les cinq checks existants restent obligatoires :

1. `PHP quality` ;
2. `Backend tests` ;
3. `Frontend quality` ;
4. `Vite build` ;
5. `Docker build`.

La fusion reste manuelle. L'automatisation ne contourne ni les checks, ni la
résolution des conversations, ni les protections administrateur.

## Release Please

Release Please continue à écouter les pushs sur `main` avec
`target-branch: main`. Après une promotion, il analyse les Conventional Commits
devenus ancêtres de `main` et ouvre ou actualise sa pull request de version.

La fusion de cette pull request met à jour la version et `CHANGELOG.md`, puis
Release Please crée le tag `vX.Y.Z` et la GitHub Release. Le workflow de
promotion ne calcule jamais de version et ne modifie jamais le changelog, afin
de garder une seule source de vérité.

Coolify continue à déployer chaque nouveau commit de `main`. La promotion est
donc déployée avant la fusion de la pull request de version ; la GitHub Release
documente ensuite exactement le code déjà présent sur `main`. La pull request de
Release Please peut provoquer un second déploiement sans changement applicatif,
limité aux fichiers de version et de changelog. Le passage à un déploiement par
tag constituerait un projet distinct et reste hors de ce changement.

## Protections et permissions

Les protections existantes sont conservées :

- `develop` exige une pull request, les cinq checks, un historique linéaire et
  interdit les force-pushs ;
- `main` exige une pull request à jour, les cinq checks, autorise les merge
  commits et interdit les force-pushs ;
- aucune protection n'est affaiblie pour résoudre le problème de promotion.

Le workflow requiert `contents: write` pour publier uniquement la branche
technique et `pull-requests: write` pour créer, commenter ou fermer la pull
request. Le jeton `RELEASE_PLEASE_TOKEN`, déjà limité au dépôt, est utilisé afin
que les pushs et pull requests de l'automatisation déclenchent la CI. Aucun
secret de production n'est exposé.

## Migration de l'état actuel

La pull request directe `develop → main` existante est remplacée, pas mise à
jour. La migration suit cet ordre :

1. fusionner le changement de workflow dans `develop` par une pull request ;
2. laisser le workflow créer `automation/promote-develop → main` ;
3. vérifier que les cinq checks passent sur cette nouvelle pull request ;
4. fermer l'ancienne pull request directe avec une explication et un lien vers
   la nouvelle ;
5. fusionner la nouvelle promotion avec un merge commit ;
6. vérifier que Release Please ouvre ou actualise sa pull request sur `main`.

L'ancienne pull request n'est pas fermée avant que sa remplaçante existe, afin de
ne perdre aucune visibilité sur les changements en attente.

## Documentation et garde-fous

`docs/quality-ci-cd.md`, le design CI existant et le corps de la pull request
automatique sont mis à jour. Ils ne doivent plus recommander une pull request
directe `develop → main` ni l'utilisation du bouton **Update branch**.

Le dépôt conserve les payloads JSON de protection tels quels : la correction ne
nécessite pas de désactiver `strict` sur `main`.

## Vérification et critères d'acceptation

Le changement est accepté lorsque :

1. le YAML du workflow est syntaxiquement valide ;
2. les scripts shell sont vérifiés par ShellCheck ou par une extraction suivie
   d'un contrôle `bash -n` ;
3. une simulation Git locale prouve que la branche technique contient à la fois
   le dernier `main` et le dernier `develop` ;
4. une seconde simulation après ajout d'un commit propre à `main` prouve que la
   branche reconstruite reste à jour sans modifier `develop` ;
5. une exécution sans changement à promouvoir ne crée pas de pull request vide ;
6. le workflow recherche exactement une pull request
   `automation/promote-develop → main` ;
7. aucune commande ne force-push `develop` ou `main` ;
8. les protections versionnées de `develop` et `main` restent strictes ;
9. Release Please reste configuré sur `main` et demeure la seule automatisation
   qui modifie la version, le changelog, les tags et les GitHub Releases ;
10. la documentation décrit le nouveau flux et la procédure de récupération en
    cas de conflit.
