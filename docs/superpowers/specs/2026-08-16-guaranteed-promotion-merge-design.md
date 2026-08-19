# Merge garanti des promotions vers `main` — Design

## Objectif

Conserver une validation humaine avant chaque mise en production tout en
garantissant que la pull request `automation/promote-develop → main` est
fusionnée avec un vrai merge commit. Cette garantie doit empêcher la recréation
en boucle des pull requests de promotion et permettre à Release Please de voir
les Conventional Commits provenant de `develop`.

## Incident et cause racine

Les pull requests de promotion #34 et #35 ont été fusionnées avec **Squash and
merge**. Les commits créés sur `main` n'ont qu'un seul parent. L'ascendance de
`develop` n'est donc jamais entrée dans `main`, même si le contenu des fichiers
y a été copié.

Deux effets en découlent :

1. le workflow de promotion constate toujours que `develop` n'est pas un
   ancêtre de `main` et recrée une pull request après chaque fusion ;
2. Release Please ne voit qu'un commit `chore: promote develop to main`, et non
   les commits `feat:` et `fix:` de `develop`. Il conclut qu'aucun changement
   destiné aux utilisateurs n'existe et ne prépare aucune nouvelle version.

Le texte de la pull request demandant de choisir **Create a merge commit** est
un conseil, pas un garde-fou technique. Il ne suffit pas.

## Architecture retenue

La création et la mise à jour de la pull request de promotion restent gérées par
le workflow `Promote develop`. La validation humaine est déplacée vers une
action GitHub manuelle nommée **Promote to production**.

L'opérateur examine la pull request et ses checks, puis lance cette action au
lieu d'utiliser le bouton de fusion standard de GitHub. Le workflow retrouve la
pull request ouverte par son couple exact de branches, valide ses invariants et
exécute :

```text
gh pr merge <numéro> --merge --match-head-commit <sha>
```

L'option `--merge` impose le merge commit. L'option `--match-head-commit`
empêche de fusionner une révision différente de celle qui a été vérifiée.

## Validation avant fusion

Le workflow manuel échoue sans modifier `main` si l'un des invariants suivants
n'est pas satisfait :

- il n'existe pas exactement une pull request ouverte dont la source est
  `automation/promote-develop` et la cible `main` ;
- la pull request est un brouillon ;
- son état de fusion n'est pas `CLEAN` ;
- son commit de tête change entre la validation et la commande de fusion ;
- un check requis est en attente, a échoué ou a été annulé.

Les protections de `main` restent la dernière autorité. Le workflow n'utilise
ni droits administrateur, ni contournement des protections, ni force-push.

Le jeton `RELEASE_PLEASE_TOKEN` est utilisé, comme dans les workflows actuels,
afin que le push résultant sur `main` déclenche Release Please et les autres
automatisations du dépôt.

## Expérience opérateur

La pull request automatique indique explicitement :

1. vérifier les changements et attendre les cinq checks requis ;
2. ouvrir l'onglet **Actions** ;
3. lancer **Promote to production** ;
4. ne pas utiliser **Squash and merge** ou le bouton de fusion standard sur
   cette pull request.

Le workflow manuel publie un résumé contenant le numéro de la pull request, le
SHA vérifié et le résultat de la fusion. Un échec reste visible dans GitHub
Actions avec une cause exploitable ; il ne ferme pas la pull request.

## Récupération de l'incident actuel

La pull request #36 ne doit pas être fusionnée avec le bouton standard. Après
déploiement du nouveau workflow sur `develop`, elle peut être fusionnée par
**Promote to production**, à condition que sa tête ait été reconstruite depuis
le dernier `develop` et que tous ses checks soient réussis.

Le merge commit résultant réintroduit l'ascendance complète de `develop` dans
`main`. Lors du push sur `main` :

- le workflow de promotion constate que `develop` est déjà inclus et cesse de
  recréer une pull request ;
- Release Please retrouve les commits `feat:` et `fix:` postérieurs à `v1.0.0`
  et ouvre ou actualise sa pull request de version.

Si la tête de #36 change pendant l'opération, le workflow refuse la fusion. Une
nouvelle exécution est alors nécessaire après le retour au vert des checks.

## Portée et choix écartés

La configuration globale des méthodes de fusion du dépôt n'est pas modifiée.
Désactiver le squash globalement dégraderait le workflow linéaire de `develop`,
car GitHub ne permet pas de choisir des méthodes différentes par branche.

Le workflow de promotion ne fabrique pas non plus un commit squash
`feat:` artificiel. Cela pourrait déclencher une version, mais détruirait la
granularité du changelog et ne restaurerait pas l'ascendance de `develop`.

Release Please reste exclusivement responsable du SemVer, du changelog, des
tags et des GitHub Releases. La fusion de sa propre pull request n'est pas
modifiée par ce changement.

## Tests et critères d'acceptation

Le changement est accepté lorsque :

1. le workflow manuel ne sélectionne que la pull request exacte
   `automation/promote-develop → main` ;
2. les validations sont testables hors GitHub par un script shell avec des
   réponses `gh` simulées ou des fonctions unitaires isolées ;
3. une pull request absente, multiple, en brouillon, non `CLEAN` ou avec checks
   incomplets provoque un échec avant toute commande de fusion ;
4. le chemin nominal invoque `gh pr merge --merge --match-head-commit` avec le
   numéro et le SHA validés ;
5. le YAML et les scripts shell passent leurs contrôles syntaxiques ;
6. le corps de la pull request et la documentation décrivent l'action manuelle
   et interdisent le bouton standard ;
7. aucune protection de `develop` ou `main` n'est affaiblie ;
8. après la fusion de récupération, `git merge-base --is-ancestor develop main`
   réussit, aucune nouvelle promotion vide n'est créée et Release Please crée
   ou met à jour sa pull request de version.
