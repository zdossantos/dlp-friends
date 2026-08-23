# Découverte, swipes et matches réciproques

**Date :** 23 août 2026  
**Issues :** #17, #18 et #19

## Objectif

Livrer un parcours de découverte mobile-first complet : sélectionner les profils éligibles selon une affinité explicable, enregistrer une décision unique et créer exactement un match après deux likes réciproques. Le parcours reste entièrement utilisable sans geste tactile et rend clairement ses états de chargement, d’erreur, d’épuisement et de match.

## Périmètre

Le changement comprend :

- le socle de données minimal des passions et des blocages requis par le moteur de découverte ;
- le classement des profils par passions communes puis fréquence de visite ;
- la persistance transactionnelle des décisions et des matches ;
- la page Inertia de découverte et son composant métier `SwipeCard` ;
- l’accès à la découverte depuis la navigation membre ;
- les tests Pest et Vitest nécessaires aux critères des trois issues.

L’administration du catalogue de passions, l’action de bloquer un membre, les filtres avancés, le retour arrière, les limites quotidiennes, les conversations et les animations complexes restent hors périmètre. Le schéma minimal des passions et blocages prépare ces fonctionnalités sans les exposer dans l’interface.

## Modèle de données

Une migration ajoute :

- `passion_categories`, avec un nom et un ordre d’affichage ;
- `passions`, rattachées à une catégorie, nommées, ordonnées et activables ;
- `passion_profile`, paire unique profil–passion ;
- `swipes`, avec acteur, cible et décision limitée à `like` ou `pass`, unique par acteur et cible ;
- `matches`, avec les deux utilisateurs dans l’ordre canonique `user_low_id < user_high_id`, paire unique ;
- `blocks`, paire unique bloqueur–bloqué, utilisée uniquement pour l’exclusion du moteur à ce stade.

Les clés étrangères sont supprimées en cascade avec leurs propriétaires. Les contraintes de contrôle disponibles dans Laravel garantissent les valeurs de décision, l’absence de paire réflexive et l’ordre canonique des matches. Les modèles Eloquent et relations nécessaires sont ajoutés à `User` et `Profile`.

Le catalogue n’est pas rempli avec de fausses données de production. Les factories permettent de construire des jeux de test représentatifs.

## Moteur de découverte

`DiscoveryService::for(User $user)` retourne des objets de résultat dédiés plutôt que des modèles bruts. Chaque résultat contient uniquement les données publiques nécessaires à la page, le nombre de passions communes, leurs libellés, le bonus de fréquence et le score total.

La requête exclut :

- l’utilisateur courant ;
- les comptes qui ne sont pas actifs ;
- les profils incomplets ou masqués ;
- les profils déjà évalués par l’utilisateur courant ;
- toute paire bloquée dans un sens ou dans l’autre.

Les passions inactives ne participent ni au score ni à l’explication. Le score vaut le nombre de passions communes, augmenté de `0,25` lorsque les fréquences de visite non nulles sont identiques. Le tri compare d’abord le nombre de passions communes, puis le bonus de fréquence. Un départage aléatoire injectable départage seulement les profils ayant ces deux valeurs identiques ; une implémentation déterministe est utilisée dans les tests.

Le service charge les relations nécessaires en nombre borné de requêtes et ne déclenche aucune requête supplémentaire pendant la transformation des résultats.

## Enregistrement d’un swipe et création d’un match

`CreateSwipe::handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch` porte la règle métier. Le nom `MemberMatch` évite le mot-clé PHP réservé `match`. Un enum partagé limite les décisions à `like` et `pass`.

Avant l’écriture, l’action refuse l’utilisateur lui-même, une cible inactive, incomplète ou masquée, une paire bloquée, ainsi qu’une décision déjà enregistrée. Le contrôleur transforme ces refus en erreur de validation compréhensible sans exposer d’information privée.

L’écriture s’effectue dans une transaction :

1. insertion unique du swipe ;
2. arrêt immédiat pour un `pass` ;
3. recherche du like inverse ;
4. insertion idempotente du match avec identifiants canoniques ;
5. lecture et retour du match créé ou existant.

Les contraintes uniques restent la protection définitive contre les doubles requêtes et la concurrence. Une répétition ne crée jamais un deuxième swipe ou match. Une fois la décision acceptée, le profil est exclu de la prochaine réponse de découverte.

## Routes et contrat Inertia

Sous les middlewares membre existants (`auth`, `verified`, `social`, `profile.complete`) :

- `GET /discover` affiche `Discovery/Index` avec la première suggestion ou `null` ;
- `POST /discover/{target}/swipe` valide la décision, appelle l’action métier et redirige vers la page de découverte.

Le contrôleur de page transforme le résultat en propriété sérialisable stable. Le retour du POST place en session une propriété de match minimale quand la réciprocité vient d’être satisfaite. La page suivante consomme cette propriété pour afficher la confirmation. Les erreurs de validation empruntent le mécanisme Inertia standard et conservent la possibilité de réessayer.

La navigation principale ajoute « Découvrir » pour les membres éligibles.

## Interface mobile-first

`SwipeCard` reçoit un profil de découverte et un état verrouillé. Son contrat public n’émet que `like` ou `pass`. La carte présente :

- nom d’affichage et âge ;
- initiales comme solution de repli lorsque aucune image n’est disponible ;
- bio et fréquence de visite ;
- score expliqué en texte, bonus éventuel et passions communes sous forme de badges.

Les boutons « Passer » et « J’aime » sont de vrais boutons accessibles, avec libellés explicites, focus visible et état désactivé pendant une soumission. Les touches fléchées gauche et droite déclenchent les mêmes actions lorsque la carte a le focus. Pointer Events ajoute un geste horizontal avec seuil ; un mouvement inférieur au seuil annule l’action. Le geste reste une amélioration et ne remplace jamais les contrôles visibles.

La page verrouille la carte dès la première action, soumet via le routeur Inertia et ignore toute action supplémentaire jusqu’à `onFinish`. Une erreur serveur affiche un message relié à une zone d’annonce accessible et déverrouille la carte. Le profil suivant est obtenu après le redirect Inertia réussi.

La page distingue quatre états :

- chargement initial avec squelette et texte non ambigu ;
- erreur avec message et bouton pour réessayer ;
- liste vide avec explication et lien vers le profil ;
- match confirmé dans une boîte de dialogue titrée, descriptive et refermable.

La largeur de carte est fluide jusqu’à une largeur maximale raisonnable ; les actions ne débordent pas aux largeurs mobiles usuelles. La palette existante violet/rose est utilisée pour l’action principale, avec une mise en valeur dorée réservée au match. La couleur n’est jamais le seul indicateur.

## Gestion des erreurs et sécurité

Le serveur reste la source de vérité. Une cible absente ou devenue inéligible entre l’affichage et l’action est refusée côté serveur. Le client ne reçoit que les champs publics du profil et aucun e-mail ou identifiant métier inutile.

Les erreurs attendues sont rendues comme validation Inertia. Les erreurs inattendues conservent la carte et proposent de réessayer. Les contraintes de base couvrent les courses que le verrouillage du client ne peut pas empêcher.

## Tests

### Pest

- formule, ordre, bonus inférieur à une passion et égalités déterministes ;
- exclusion de soi, profils masqués ou incomplets, comptes inactifs, profils évalués et paires bloquées ;
- nombre borné de requêtes pour prévenir les N+1 ;
- validation de `like`/`pass`, refus de l’auto-swipe et des cibles inéligibles ;
- premier like sans match, like inverse créant une paire canonique, pass sans match ;
- répétitions et simulation de concurrence ne créant aucun doublon ;
- rendu Inertia, propriétés publiques, état vide, redirection et confirmation de match.

### Vitest

- `SwipeCard` ne peut émettre que `like` ou `pass` ;
- boutons, clavier et geste horizontal déclenchent les décisions attendues ;
- seuil du geste, verrouillage et doubles actions ;
- rendu du score et des passions communes ;
- chargement, erreur, liste vide et boîte de dialogue de match.

### Vérification globale

Exécuter les tests ciblés pendant le développement, puis les suites PHP et Vue, l’analyse statique, la vérification TypeScript, ESLint, Prettier et le build avant livraison.

## Compatibilité et évolution

Les migrations sont additives. Les services métier ne dépendent pas de l’interface Inertia, ce qui permettra à l’administration des passions, au blocage et à la messagerie de les réutiliser. Le contrat sérialisé de découverte reste volontairement minimal afin de ne pas figer prématurément les futurs filtres ou médias de profil.
