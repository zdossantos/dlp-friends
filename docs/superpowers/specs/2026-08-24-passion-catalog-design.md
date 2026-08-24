# Catalogue administrable des passions — conception

## Contexte

L’issue 14 demande de gérer les centres d’intérêt sans redéploiement et de
permettre leur sélection multiple par les membres. Le vocabulaire produit du
projet est « passion » : l’implémentation conserve donc les tables et modèles
existants `passions`, `passion_categories` et `passion_profile` plutôt que de
créer les tables `intereset` et `profile_interest` mentionnées dans l’ancien
texte de l’issue.

Les rôles `user` et `admin`, leur pivot `user_roles`, le middleware de rôle et
la base du dashboard d’administration existent déjà. Les catégories restent
une structure technique dans cette issue : elles ne disposent d’aucun écran
d’administration.

## Objectifs

- permettre aux administrateurs de créer, modifier, activer, archiver,
  supprimer et réordonner les passions ;
- permettre aux administrateurs de configurer le nombre maximal de passions
  actives sélectionnables par membre, avec une valeur initiale de 5 ;
- fournir aux membres une sélection multiple sous forme de tags cliquables ;
- retirer immédiatement une passion archivée de l’expérience membre et du
  calcul d’affinité, sans perdre son historique d’utilisation ;
- garantir que le rôle `admin` reste limité au back-office et ne confère aucun
  accès implicite aux messages privés.

La gestion des catégories, la modération et l’import/export massif restent hors
périmètre.

## Modèle de données

### Réglage du catalogue

Une table dédiée `passion_settings` contient une ligne singleton avec
`max_selections`, initialisé à 5. Une contrainte applicative impose une valeur
entière positive. Une table générique clé/valeur n’est pas introduite : aucun
autre réglage dynamique ne la justifie aujourd’hui.

La diminution de la limite ne supprime aucune sélection existante. Un profil
qui dépasse temporairement la nouvelle limite conserve ses passions actives,
mais ne peut en ajouter aucune tant qu’il n’est pas revenu sous la limite. Ce
comportement évite une perte silencieuse de données.

### Historique des sélections

Le pivot `passion_profile` reçoit un booléen `is_selected`, vrai par défaut pour
préserver le comportement et les données existants.

- `is_selected = true` représente une sélection membre effective ;
- `is_selected = false` conserve une association historique suspendue ;
- une passion effective doit également avoir `passions.is_active = true`.

Le modèle `Profile` expose séparément les passions sélectionnées et l’historique
non filtré. Les requêtes de profil et de découverte utilisent uniquement les
associations effectives et les passions actives.

Lorsqu’un membre retire volontairement une passion active, son association est
supprimée. Une association suspendue n’existe donc que parce qu’une passion
précédemment sélectionnée a été archivée ou n’a pas pu être restaurée.

### Catégorie technique et seeding

Le seeder crée de façon idempotente une catégorie technique unique, non
administrable dans cette issue, puis les passions suivantes dans l’ordre :

1. Chill
2. Attractions à sensations
3. Attractions calmes
4. Collection / merch
5. Pins
6. Rencontres personnages
7. Spectacles
8. Food
9. Secrets / anecdotes
10. Événements

Le seeding ne réactive pas une passion archivée et n’écrase pas un ordre déjà
administré lors d’une exécution ultérieure.

## Administration

### Navigation et écran

Une entrée « Passions » est ajoutée à la navigation d’administration et ouvre
`/admin/passions`. La page responsive affiche :

- le réglage de limite globale ;
- les passions dans leur ordre courant ;
- pour chaque passion, son nom, son état, son nombre d’associations historiques
  et les actions disponibles.

La création et l’édition portent uniquement sur le nom. Une nouvelle passion
est rattachée à la catégorie technique et placée à la fin de la liste.

Le réordonnancement utilise des actions « Monter » et « Descendre ». Elles sont
utilisables au clavier, évitent la complexité d’un glisser-déposer et
normalisent les `sort_order` dans une transaction.

### Activation, archivage et restauration

Archiver une passion réalise atomiquement les opérations suivantes :

1. définir `passions.is_active` à faux ;
2. passer à faux `is_selected` sur ses associations effectives.

La passion disparaît immédiatement de tous les écrans membre, ne compte plus
dans leur limite et ne participe plus au score de découverte. Un membre qui
avait cinq passions dont une est archivée voit donc quatre passions et peut
immédiatement choisir un autre tag.

Réactiver une passion la rend à nouveau disponible dans le catalogue. Pour
chaque association historique suspendue, l’application la restaure seulement
si le profil possède strictement moins de sélections actives que la limite
courante. Les profils déjà à la limite conservent une association historique
suspendue ; la passion n’y réapparaît pas automatiquement. Ils peuvent la
choisir manuellement plus tard après avoir libéré une place.

### Suppression

Une passion ne peut être supprimée que si elle n’a aucune association,
effective ou historique. Lorsqu’elle a déjà été utilisée, l’interface demande
de l’archiver. Cette règle préserve l’historique demandé par l’issue.

### Validation et autorisation

Des Form Requests valident les noms normalisés et uniques, la limite entière
positive et les paramètres d’ordre. Les actions métier qui modifient plusieurs
lignes — archivage, réactivation et déplacement — sont transactionnelles.

Une `PassionPolicy` exige le rôle `admin` pour consulter et modifier le
catalogue. Les routes emploient également le middleware `role:admin`, mais la
Policy reste l’autorité pour chaque action sensible. Aucun `Gate::before` ni
permission globale n’est ajouté : le rôle admin ne reçoit donc aucun droit sur
les conversations ou messages.

## Parcours membre

Les pages de création et de modification du profil reçoivent :

- les passions actives ordonnées ;
- les identifiants des sélections effectives du profil ;
- la limite globale courante.

Le composant `PassionTagSelector` présente une grille de tags cliquables. Chaque
tag est un bouton avec `aria-pressed`, un état visuel actif/inactif et une
interaction clavier native. Un compteur indique `x / limite`.

Cliquer sur un tag inactif l’ajoute ; cliquer sur un tag actif le retire. Quand
la limite est atteinte, les tags non choisis deviennent indisponibles, tandis
que les tags choisis restent actionnables afin de libérer une place. Des champs
`passion_ids[]` transmettent les identifiants avec le formulaire Inertia.

Les passions archivées et les associations suspendues ne sont jamais envoyées
aux pages membre et ne sont donc ni visibles ni comptabilisées.

Le serveur valide que les identifiants soumis sont distincts, correspondent à
des passions actives et ne dépassent pas la limite lue en base au moment de la
requête. Une désactivation ou une réduction de limite concurrente produit une
erreur de validation explicite et conserve les autres données du formulaire.

La mise à jour synchronise seulement les passions actives : elle crée ou
réactive les choix soumis, supprime les sélections actives retirées et laisse
intactes les associations historiques suspendues.

Les passions effectives apparaissent sur le profil membre. Les profils et le
moteur de découverte continuent à filtrer toute passion inactive.

## Gestion des erreurs et concurrence

- Les changements de statut, d’ordre et de réglage sont exécutés dans des
  transactions courtes.
- La validation membre relit les passions actives et la limite côté serveur ;
  le navigateur n’est jamais considéré comme source d’autorité.
- Une passion supprimée ou archivée entre l’affichage et l’envoi est rejetée
  comme choix invalide.
- Une réactivation verrouille les lignes nécessaires pendant le recalcul des
  places afin de ne jamais dépasser la limite par restauration automatique.
- Les erreurs métier sont présentées comme erreurs de validation ou réponses
  403/404 appropriées, sans journaliser de donnée personnelle inutile.

## Stratégie de tests

Les tests Pest suivent le cycle rouge, vert, refactorisation et couvrent :

- la migration et les valeurs par défaut ;
- le seeder idempotent et l’ordre initial des dix passions ;
- le CRUD, l’unicité, l’ordre, l’activation, l’archivage et la suppression ;
- la mise à jour de la limite et le comportement sans perte lors d’une baisse ;
- l’accès administrateur et le refus des membres ordinaires par middleware et
  Policy ;
- l’absence de permission générale donnant accès aux messages privés ;
- la sélection multiple, la validation dynamique et la concurrence ;
- la suspension des associations à l’archivage, la place immédiatement libérée
  et la restauration conditionnelle à la réactivation ;
- l’exclusion des passions inactives des profils et de la découverte.

Les tests Vitest et Vue Test Utils couvrent :

- l’affichage des tags actifs dans l’ordre ;
- l’activation et la désactivation à la souris et au clavier ;
- `aria-pressed`, le compteur et le blocage des nouveaux choix à la limite ;
- la possibilité de retirer un tag lorsque la limite est atteinte ;
- la soumission des identifiants et l’affichage des erreurs ;
- l’absence totale des passions archivées dans l’interface membre ;
- les principaux états de la page d’administration.

Après les tests ciblés, la vérification exécute les contrôles PHP et frontend
pertinents : formatage, lint, analyse statique, suite Pest, suite Vitest,
vérification TypeScript, génération Wayfinder et build Vite.

