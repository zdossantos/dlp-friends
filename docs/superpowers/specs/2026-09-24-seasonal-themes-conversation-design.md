# Design — Thèmes saisonniers et ambiance des conversations

## Contexte et objectifs

Ce document couvre les issues GitHub #185 et #189. Il poursuit deux objectifs
complémentaires : donner aux conversations privées une ambiance visuelle plus
chaleureuse et permettre à l'administration d'activer des ambiances Halloween
et Noël sans redéploiement.

Le résultat doit préserver les règles métier existantes, le choix d'apparence
`light`, `dark` ou `system`, l'accessibilité à partir de 320 px et l'identité
indépendante de DLP Friends. Aucun asset, personnage, logo, attraction ou motif
Disney n'est utilisé.

## Décisions structurantes

### Modèle de configuration

La table `seasonal_themes` contient une ligne par thème supporté. Le thème est
identifié par un enum PHP fermé aux valeurs `halloween` et `christmas`. Chaque
ligne stocke :

- `theme`, unique ;
- `is_manually_active` ;
- `starts_at` et `ends_at`, tous deux facultatifs mais toujours fournis
  ensemble ;
- les timestamps Laravel.

La migration crée les deux lignes afin que la configuration soit disponible en
production sans dépendre d'un seeder. Les dates sont enregistrées en UTC comme
les autres dates applicatives, puis saisies et présentées dans le fuseau
`config('app.timezone')`.

Un modèle `SeasonalTheme` porte les casts et les scopes de lecture. Un enum
`SeasonalThemeName` constitue la liste canonique partagée par le modèle, le
résolveur, la validation et la présentation admin.

### Résolution du thème actif

`ResolveActiveSeasonalTheme` reçoit un instant testable et retourne le thème
actif ainsi que le prochain instant auquel le résultat peut changer.

La priorité est déterministe :

1. un thème activé manuellement gagne sur toute programmation et reste actif
   jusqu'à sa désactivation explicite ;
2. l'action d'activation manuelle désactive les autres lignes dans une
   transaction, de sorte qu'une seule activation manuelle existe ;
3. sans activation manuelle, les périodes telles que
   `starts_at <= now < ends_at` sont éligibles ;
4. si plusieurs périodes se chevauchent, celle dont `starts_at` est le plus
   récent gagne ;
5. en cas d'égalité stricte, `christmas` gagne sur `halloween` selon un ordre
   explicite de l'enum.

La borne de fin est exclusive : à `ends_at`, le thème standard revient ou une
autre période éligible prend le relais. Le prochain changement possible est le
plus proche début ou fin futur. Aucun scheduler serveur n'est nécessaire : la
résolution est effectuée à la requête et le navigateur programme un
rafraîchissement de la seule prop Inertia concernée au prochain changement.

### Administration et autorisation

Une page `Admin/SeasonalThemes/Index` est ajoutée à la navigation
d'administration. Elle affiche deux cartes, une par thème, comprenant :

- un aperçu clair et sombre ;
- l'état courant (standard, programmé, actif par période ou actif
  manuellement) ;
- les champs locaux de début et de fin ;
- une action d'enregistrement de la période ;
- une action d'activation ou de désactivation manuelle avec conséquence
  explicitée.

Les routes restent sous le groupe `role:admin`. Une Policy protège les actions
du domaine et une Form Request valide les entrées : thème connu, deux dates
présentes ensemble et fin strictement postérieure au début. Les mises à jour
manuelles passent par une Action transactionnelle qui verrouille les lignes et
désactive l'autre thème avant activation.

Les réponses utilisent les flash toasts existants. Tous les textes visibles,
placeholders et noms accessibles sont fournis en français et en anglais dans
les catalogues existants.

## Application des thèmes saisonniers

### Distribution au frontend

Le middleware Inertia partage une prop légère :

```text
seasonalTheme: {
  active: "halloween" | "christmas" | null,
  nextTransitionAt: ISO-8601 | null
}
```

La vue Blade applique également la classe résolue à `<html>` pour éviter un
flash de palette au premier rendu. Le composable frontend
`useSeasonalTheme` :

- synchronise exactement une classe `seasonal-halloween` ou
  `seasonal-christmas` sur `<html>` ;
- conserve indépendamment la classe `.dark` pilotée par l'apparence membre ;
- programme un `router.reload({ only: ['seasonalTheme'] })` au prochain
  changement, avec un délai borné puis reprogrammé pour les dates lointaines ;
- resynchronise le timer après chaque navigation Inertia ;
- nettoie timers et anciennes classes pour éviter les doublons.

Les pages publiques reçoivent la même ambiance. La préférence membre n'est ni
modifiée ni persistée par ce mécanisme.

### Palette et décorations

Les thèmes saisonniers peuvent changer sensiblement les tokens sémantiques,
mais chaque combinaison conserve les contrastes et usages documentés.

- Halloween emploie des fonds prune/charbon, un primaire orange citrouille,
  des accents mauves et des surfaces chaudes. Les décorations abstraites
  utilisent lunes, étoiles, feuilles, chauves-souris géométriques et citrouilles
  simplifiées originales.
- Noël emploie des fonds sapin/crème, un primaire rouge canneberge, des accents
  verts et dorés modérés. Les décorations utilisent flocons, étoiles, branches
  géométriques et boules abstraites originales.

Chaque thème définit ses variantes claire et sombre dans `app.css` via les
classes combinées `.seasonal-*` et `.dark.seasonal-*`. Les composants continuent
d'utiliser `background`, `foreground`, `card`, `primary`, `secondary`,
`accent`, `muted`, `border`, `input`, `ring` et leurs premiers plans plutôt que
des couleurs brutes.

Un composant purement décoratif `SeasonalDecorations` rend de petits SVG inline
originaux, `aria-hidden`, non focalisables et sans texte. Il apparaît sur les
surfaces de marque et la célébration de match sans gêner les contrôles ni créer
de défilement horizontal. Il ne charge aucun asset tiers.

### Célébration de match

`MatchDialog` conserve son contenu, ses actions, son focus et sa durée
fonctionnelle. Seule sa couche décorative varie :

- standard : halos, feux et joyaux actuels ;
- Halloween : lune/halos ambrés, silhouettes géométriques et particules en
  feuilles ;
- Noël : étoile centrale, flocons et scintillements rouges/verts/dorés.

Le DOM expose un identifiant de variante testable. Les animations n'utilisent
que `transform`, `opacity` et ombres. Avec `prefers-reduced-motion: reduce`,
elles disparaissent tandis que la composition statique et le dialogue restent
visibles et utilisables.

## Ambiance dédiée des conversations

La page de conversation garde les composables temps réel, l'état de lecture,
la saisie, le blocage et les routes existants.

### Fond original

`MessageTimeline` reçoit une couche de fond doodle originale, légère et sans
asset externe. Le motif est construit avec un petit SVG CSS encodé et des
formes génériques liées à l'amitié et à l'échange : bulles, étincelles, mains
stylisées et enveloppes abstraites. Il n'emploie aucune forme propriétaire.

Le motif utilise un nouveau token local `--conversation-pattern`, avec une
opacité suffisamment faible pour que les bulles restent la surface dominante.
Les variantes standard, sombre, Halloween et Noël sont définies explicitement.

### Hiérarchie et identification des messages

Les messages envoyés et reçus restent alignés différemment, mais ne dépendent
plus de ce seul indice ni de la couleur :

- chaque groupe affiche un libellé textuel « Vous » ou le nom du participant ;
- les bulles ont des coins directionnels distincts et une bordure/ombre
  différente ;
- le dernier message envoyé conserve son état de lecture textuel ;
- les contenus longs utilisent le retour à la ligne et ne dépassent jamais la
  largeur disponible.

Des séparateurs de jour localisés apparaissent entre deux jours calendaires,
avec « Aujourd'hui » et « Hier » lorsque pertinent, puis une date courte. Ils
sont textuels et ne reposent pas sur la couleur. L'en-tête, la zone de messages
et le compositeur partagent une surface cohérente, tandis que les erreurs,
l'envoi en cours, le chargement de l'historique et l'état vide restent lisibles.

À 320 px, les marges diminuent et la largeur maximale des bulles laisse assez
d'espace aux libellés sans défilement horizontal. Les nouveaux éléments ne
modifient pas l'ordre du focus. Les entrées de message conservent l'animation
existante, annulée par la préférence de réduction des mouvements.

## Tests et vérification

Le développement suit un cycle rouge, vert, refactorisation.

### Backend

- test de schéma et des deux lignes initiales ;
- tests unitaires du résolveur : aucun thème, manuel prioritaire, début inclus,
  fin exclue, chevauchement, égalité et prochain changement ;
- tests Feature : accès admin, refus membre, validation des périodes,
  activation exclusive, désactivation et rendu de la prop Inertia ;
- test du fuseau applicatif avec des dates locales non UTC.

### Frontend et navigateur

- tests des transformations pures nécessaires aux séparateurs de date ;
- page admin : édition, activation, désactivation, libellés et état courant ;
- apparence : coexistence `light`/`dark`/`system` avec chaque classe
  saisonnière, contrastes principaux et changement automatique simulé ;
- match : variante standard/Halloween/Noël et suppression des animations sous
  réduction des mouvements ;
- conversation : libellés d'émetteur, séparateurs temporels, motif, messages
  longs, erreur, envoi en cours et état vide ;
- revues navigateur à 320 px et ordinateur, clair et sombre, sans erreur
  JavaScript ni défilement horizontal.

Les vérifications finales comprennent les tests ciblés pendant le
développement, puis les contrôles backend et frontend concernés, le build Vite
et la suite Pest complète conformément à `AGENTS.md`.

## Documentation

`docs/design-system.md` documente les deux palettes, les règles de décorations,
le motif des conversations et les variantes de célébration. `docs/PRD.md` et
`docs/documentation-inventory.md` reflètent l'état effectivement livré. La
documentation technique décrit le résolveur et la synchronisation Inertia sans
présenter un scheduler inexistant.

## Hors périmètre

- éditeur libre de thèmes ou de couleurs ;
- thème saisonnier choisi individuellement par un membre ;
- nouveaux types de messages, pièces jointes, GIF, audio ou appels ;
- modification des règles de match, lecture, blocage ou temps réel ;
- assets distants ou éléments protégés liés à Disney ou Disneyland Paris.
