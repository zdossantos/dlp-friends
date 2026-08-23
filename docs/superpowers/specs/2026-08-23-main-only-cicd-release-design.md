# CI/CD et releases depuis `main` uniquement — Design

## Périmètre

Cette migration remplace entièrement le flux `develop → main` par un flux
standard dans lequel toutes les branches de travail ouvrent une pull request
directement vers `main`. Elle couvre les workflows GitHub Actions, Release
Please, Dependabot, les réglages GitHub versionnés et distants, la documentation
et le nettoyage des branches devenues obsolètes.

L'application reste déployée par Coolify en dehors de GitHub Actions. Aucun
workflow ne publie de package npm ou Composer et aucun second mécanisme ne crée
de tag ou de GitHub Release.

## État de départ vérifié

Le 23 août 2026, GitHub utilise encore `develop` comme branche par défaut. Les
branches distantes `main`, `develop` et `automation/promote-develop` ont le même
contenu, aucune pull request n'est ouverte et les deux worktrees locaux
existants sont propres. `main` et `develop` exigent actuellement les cinq mêmes
checks applicatifs.

Cet état permet de supprimer les branches obsolètes sans perdre de contenu,
mais uniquement après le merge de la migration, le changement de branche par
défaut et le retrait de la protection de `develop`.

## Flux de contribution cible

Une modification part d'une branche dédiée telle que `feature/*`, `fix/*`,
`chore/*`, `docs/*` ou `refactor/*`. Elle ouvre une pull request vers `main`,
passe tous les checks obligatoires, résout les conversations puis est intégrée
avec Squash & Merge.

Le titre de la pull request devient le message du commit squash. Il doit donc
respecter Conventional Commits, avec un type autorisé, un scope facultatif et
un marqueur `!` facultatif pour un changement incompatible. Les exemples
valides incluent `feat: ajouter les favoris`, `fix(auth): corriger la session`
et `feat!: modifier le contrat public`.

GitHub interdit les pushs directs, les force-pushs et la suppression de `main`,
y compris pour les administrateurs. Le dépôt ne requiert aucune approbation
tant qu'il ne dispose que d'un contributeur capable de relire : l'auteur ne
peut pas approuver sa propre pull request. Les checks et la résolution des
conversations restent obligatoires.

## CI des pull requests

Le workflow `CI` se déclenche uniquement pour les pull requests vers `main`, y
compris lors de leur ouverture, réouverture, passage hors brouillon et mise à
jour. Une concurrence propre à chaque pull request annule les runs obsolètes.

Les six checks indépendants s'exécutent en parallèle :

- `Conventional PR title` valide le titre sans dépendance externe ;
- `PHP quality` exécute Pint et PHPStan ;
- `Backend tests` exécute Pest avec MySQL ;
- `Frontend quality` génère les modules Wayfinder puis exécute ESLint,
  Prettier, TypeScript et Vitest ;
- `Vite build` compile les assets de production ;
- `Docker build` construit l'image runtime sans la publier.

Les installations utilisent les lockfiles. Node utilise le cache npm natif de
`actions/setup-node`. Composer utilise le cache de téléchargement calculé à
partir de `composer.lock` dans les jobs qui l'installent. Le cache Docker GHA
reste partagé par le job de build, sans workflow séparé de préchauffage.

Le validateur de titre est couvert par un test shell écrit avant son
implémentation. Les fichiers YAML, JSON et Markdown bénéficient de l'exception
TDD approuvée pour les changements de configuration et de documentation ; ils
sont vérifiés par parsing, recherche structurelle et exécution des commandes
qu'ils déclarent.

## Release Please

Le workflow `Release Please` reste déclenché par chaque push sur `main` et
utilise explicitement `main` comme branche cible. Sa configuration `simple`
maintient une unique Release PR et `CHANGELOG.md`, avec des tags préfixés par
`v` et une version SemVer suivie dans `.release-please-manifest.json`.

Le secret `RELEASE_PLEASE_TOKEN` reste nécessaire pour que la pull request
créée par l'automatisation déclenche la même CI que les autres pull requests.
Le workflow conserve uniquement les permissions d'écriture nécessaires aux
contenus, issues et pull requests. Il ne publie aucun package.

Le merge d'une feature ou d'un fix dans `main` met à jour ou crée la Release PR
mais ne publie pas immédiatement une version. La publication volontaire a lieu
uniquement quand la Release PR est mergée : Release Please finalise alors le
changelog, crée le tag `vX.Y.Z` et la GitHub Release correspondante.

## Dependabot

Dependabot surveille chaque semaine Composer, npm et GitHub Actions à la racine
du dépôt. Chaque écosystème conserve son groupe indépendant, son cooldown et sa
limite de pull requests, mais toutes les pull requests ciblent désormais
`main`. Elles passent les six checks obligatoires comme toute autre pull
request et leur titre conventionnel est accepté par le validateur.

## Réglages GitHub

Les fichiers sous `.github/settings/` restent les entrées reproductibles de
l'API GitHub. `repository.json` configure `main` comme branche par défaut,
active la suppression des branches après merge, autorise uniquement le squash
et impose le titre de pull request comme titre du commit squash.

`main-protection.json` impose une branche à jour, les six checks, une pull
request, la résolution des conversations et un historique linéaire. Il interdit
les force-pushs et suppressions, applique les règles aux administrateurs et
conserve zéro approbation requise pour le dépôt mono-contributeur. Le fichier de
protection de `develop` est supprimé.

L'ordre d'application distant est transactionnel autant que le permet GitHub :

1. merger la PR de migration dans `main` avec les cinq checks actuellement
   requis ;
2. confirmer que le nouveau workflow et le check de titre existent ;
3. basculer la branche par défaut sur `main` et configurer Squash & Merge ;
4. appliquer la nouvelle protection de `main` avec les six checks ;
5. retirer la protection de `develop` ;
6. supprimer `develop` et `automation/promote-develop` ;
7. auditer à nouveau les workflows, permissions, branches et protections.

Cette séquence évite de supprimer la branche par défaut, de référencer un check
inconnu ou de créer une fenêtre où `main` accepte les pushs directs.

## Documentation et nettoyage versionné

`CONTRIBUTING.md` devient le guide concret de contribution : branche de
travail, PR vers `main`, titres Conventional Commits, CI, Squash & Merge et
Release Please. Le README résume ce flux et renvoie au guide.

Les documents d'architecture, de vision, d'implémentation et de qualité CI/CD
sont mis à jour pour décrire `main` comme unique branche principale. Les anciens
workflows de promotion, leurs scripts et tests, leurs réglages et leurs anciens
documents de conception ou plans sont supprimés. La recherche finale ne doit
laisser aucune référence fonctionnelle à l'ancienne branche ; l'URL
`ralouphie/getallheaders/tree/develop` dans `composer.lock` est une métadonnée
externe verrouillée et n'est pas une dépendance au workflow du dépôt.

Après la migration distante, les worktrees locaux propres associés à des PR
déjà mergées sont retirés, puis les branches locales obsolètes sont supprimées.
Une branche ou un worktree devenu sale entre l'audit et le nettoyage est
conservé et signalé au lieu d'être forcé.

## Gestion des échecs

Un check en échec bloque le merge sans modifier de branche protégée. Une erreur
Release Please laisse `main` intact et sera retentée au prochain push. Une
erreur lors de l'application des réglages GitHub arrête la séquence avant toute
suppression de branche et produit un nouvel audit pour identifier précisément
l'état partiellement appliqué.

La suppression distante ne commence qu'après vérification que `main`,
`develop` et la branche d'automatisation ont toujours le même arbre Git. La
suppression locale ne commence qu'après un nouveau contrôle de propreté des
worktrees.

## Vérification et critères d'acceptation

La migration est terminée lorsque :

1. les contrôles PHP, backend, frontend, Vite et Docker réussissent localement
   et dans la PR ;
2. le test du validateur de titre couvre les titres valides, invalides et les
   changements incompatibles ;
3. les YAML et JSON modifiés sont syntaxiquement valides ;
4. `main` est la branche par défaut, la seule branche principale et la seule
   cible de CI, Dependabot et Release Please ;
5. les six checks sont exigés par la protection de `main` ;
6. GitHub n'autorise que Squash & Merge et utilise le titre de PR pour le commit
   final ;
7. `develop` et `automation/promote-develop` n'existent plus sur GitHub ;
8. aucun workflow autre que Release Please ne peut créer de tag ou de GitHub
   Release ;
9. une Release PR peut passer la CI et être mergée malgré la protection ;
10. les secrets et variables Actions restants sont utilisés et les permissions
    respectent le moindre privilège ;
11. la documentation explique qu'un merge applicatif ne publie pas
    immédiatement une version ;
12. aucune référence technique ou documentaire au flux supprimé ne subsiste,
    hors métadonnée externe verrouillée explicitement identifiée ;
13. le dépôt distant et les worktrees locaux ne conservent aucune branche
    obsolète dont la suppression a été vérifiée comme sûre.
