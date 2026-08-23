# Shell membre mobile-first de type application

**Date :** 23 août 2026

**Issue :** #60

## Objectif

Donner aux parcours publics et membres l'apparence et l'ergonomie d'une
application mobile responsive, sans transformer le site en application native
ou PWA. L'espace membre utilise un shell sans en-tête global et une navigation
principale fixe en bas ; l'administration conserve seule un layout
desktop-first à sidebar.

## Périmètre

Le changement comprend :

- une résolution centrale et explicite des layouts public, authentification,
  membre et administration ;
- un shell membre sans header, avec contenu protégé de la navigation basse et
  des zones sûres mobiles ;
- un dock inférieur à icônes seules, accessible, piloté par les routes membres
  réellement disponibles ;
- l'adaptation mobile-first des pages de découverte, profil, onboarding et
  réglages actuellement livrées ;
- le maintien d'un shell d'administration distinct à sidebar et utilisable sur
  petit écran ;
- le remplacement de l'accueil Laravel générique par une page publique DLP
  Friends mobile-first ;
- la suppression des actions visibles « Passer » et « J'aime » de la carte de
  découverte au profit du swipe gauche ou droite ;
- les tests frontend et la vérification visuelle responsive des deux thèmes.

Les conversations, une page de liste des matches, les notifications, la PWA et
les fonctionnalités natives restent hors périmètre. Une destination n'apparaît
dans le dock que lorsque sa page et sa route existent réellement.

## Résolution centrale des layouts

La sélection du layout est extraite de l'initialisation Inertia dans une
fonction testable qui associe les familles de pages aux composants suivants :

- `Welcome` ne reçoit aucun layout imbriqué et porte sa propre surface publique ;
- les pages `auth/*` utilisent `AuthLayout` ;
- `Dashboard` utilise `AdminLayout` ;
- les pages `settings/*` utilisent successivement `MemberLayout` et le layout
  secondaire des réglages ;
- les autres pages authentifiées utilisent `MemberLayout`.

Cette décision reste fondée sur les noms de composants Inertia déjà stables.
Elle n'ajoute pas de prop serveur ni de conditions de layout dans les pages.
Les breadcrumbs restent disponibles dans le shell admin, mais ne créent aucun
header dans le shell membre.

## Shell et navigation membres

`MemberLayout` est une surface `min-height: 100svh` sans header global. Son
contenu reste fluide sur mobile, tablette et ordinateur, avec une largeur
maximale qui conserve une lecture de type application sur grand écran. Le fond
utilise les tokens existants et de légers accents violet, rose et doré qui
fonctionnent en thèmes clair et sombre.

Le dock est fixé en bas de la fenêtre, dimensionné par ses icônes plutôt que sur
toute la largeur disponible, et centré sur les écrans larges. Son
espacement inférieur inclut `env(safe-area-inset-bottom)`. Le contenu principal
réserve la hauteur du dock plus cette zone sûre afin qu'aucune action, erreur ou
fin de formulaire ne soit masquée.

Le dock n'affiche que des icônes visibles. Chaque lien conserve un nom
accessible avec `aria-label`, une cible tactile d'au moins 48 par 48 pixels, un
focus visible et `aria-current="page"` quand il est actif. L'état actif combine
fond, forme et contraste ; la couleur seule ne porte jamais l'information.

Les destinations disponibles à la date de cette spécification sont :

- « Découvrir », vers `discovery.index` ;
- « Profil », vers `member-profile.show`.

La page de matches n'existant pas encore, aucune icône correspondante n'est
affichée. Les réglages ne sont pas un onglet principal. Tant que le profil n'est
pas complet, le dock est masqué parce que ses destinations sont protégées par
le middleware `profile.complete`.

## Profil et réglages

La page Profil devient le point d'entrée des actions personnelles. Sa zone
d'actions, propre à la page et non au shell, se trouve en haut à droite :

- réglages du compte pour tous les membres complets ;
- administration uniquement pour un utilisateur ayant le rôle `admin` ;
- déconnexion pour tous les membres, soumise en `POST` après purge de l'état
  client mis en cache ;
- modification du profil comme action principale de la page.

Chaque action possède une icône, un libellé accessible et une cible tactile
adaptée. Les sous-pages Compte, Sécurité et Apparence restent regroupées dans le
layout secondaire des réglages. Leur navigation est horizontale ou compacte
sur petit écran, puis peut s'élargir sur desktop sans devenir une sidebar
d'application globale. Sur toutes ces routes, le dock reste affiché et son
entrée Profil conserve l'état actif.

La création et la modification du profil réutilisent les formulaires existants.
Leur largeur, leurs espacements et leurs actions sont ajustés afin de rester
entièrement manipulables au-dessus du dock. L'onboarding conserve le langage
visuel membre, mais sans dock avant la complétion du profil.

## Découverte et swipe

La page Découvrir reprend la hiérarchie de la maquette validée : titre local,
carte de profil dominante, affinités explicables et contenu lisible au pouce.
Elle n'ajoute aucun header de shell.

Le serveur précharge une pile ordonnée d'au plus cinq profils. Les cartes sont
rendues les unes derrière les autres ; seule la première est interactive et les
suivantes sont retirées de l'ordre de tabulation et de l'arbre d'accessibilité.
La carte suivante est ainsi déjà visible pendant la sortie de la carte active,
sans attendre la réponse du swipe.

La carte n'affiche ni croix, ni cœur, ni boutons visibles « Passer » ou
« J'aime ». Une translation horizontale gauche ou droite au-delà du seuil
existant déclenche respectivement `pass` ou `like`. Les mouvements courts,
verticaux, diagonaux ou annulés ne prennent aucune décision, et la carte reste
verrouillée pendant la soumission.

Pendant le geste, la carte suit directement le pointeur et s'incline dans la
direction du déplacement. Un geste trop court revient au centre avec une
transition souple. Une décision validée accélère la carte hors de l'écran avant
d'émettre l'action, afin que le résultat du geste soit immédiatement visible,
sans ajouter d'icône ni de libellé romantique. La préférence système de
réduction des animations est respectée.

Le classement continue d'utiliser le score côté serveur, mais sa valeur et le
mot « Score » ne sont plus affichés. L'explication visible se limite aux
passions communes, à la fréquence de visite et à l'éventuelle fréquence
identique.

L'absence de boutons visibles ne retire pas l'accessibilité : la carte reste
focusable, son nom accessible explique les commandes, les flèches gauche et
droite déclenchent les mêmes décisions, et deux contrôles sémantiques
visuellement masqués restent disponibles aux lecteurs d'écran. Les libellés
utilisent « Passer ce profil » et « Aimer ce profil ». Les erreurs continuent
d'être annoncées et offrent un bouton visible « Réessayer », puisqu'il s'agit
d'une action de récupération et non d'une décision de swipe.

Les états chargement, épuisement, erreur et match existants sont conservés. La
confirmation de match reste dorée et amicale ; elle ne bloque pas la poursuite
de la découverte.

## Administration

`AdminLayout` reprend les composants `AppShell`, `AppSidebar`, `AppContent` et
les breadcrumbs existants. La sidebar contient l'administration comme
destination principale et un retour vers le profil membre. Elle n'est jamais
rendue autour d'une page membre.

Sur petit écran, le déclencheur et le panneau responsive existants rendent la
navigation admin utilisable sans en faire le modèle visuel du produit. Les
cartes et listes du dashboard continuent de se réorganiser sans défilement
horizontal imposé.

## Accueil public et authentification

L'accueil générique du starter Laravel est remplacé par une page DLP Friends
en français. Elle présente le service comme un espace de rencontres strictement
amicales pour adultes fans de Disneyland Paris, rappelle son indépendance et
propose les appels à l'inscription, à la connexion ou à l'espace membre selon
l'état de session. Aucun logo, personnage ou visuel Disney n'est ajouté.

L'accueil et les pages d'authentification restent sans dock. Ils sont conçus à
partir des petits écrans, respectent les zones sûres et conservent le sélecteur
de thème existant. Les cartes d'authentification actuelles sont réutilisées
plutôt que remplacées par une nouvelle primitive.

## Gestion des erreurs et accessibilité

Les shells ne modifient aucune règle métier ni autorisation Laravel. Les liens
du dock pointent uniquement vers des routes accessibles après complétion du
profil. L'accès admin reste protégé côté serveur ; sa visibilité conditionnelle
dans Vue n'est qu'une adaptation d'interface.

Le contenu principal garde un élément sémantique identifiable, les liens et
boutons suivent l'ordre naturel du DOM et tous les contrôles conservent un
focus visible. Les icônes décoratives sont masquées aux technologies
d'assistance. Les textes accessibles compensent l'absence de libellés visibles
dans le dock.

## Tests

### Vitest

- la résolution centrale retourne le layout public, auth, membre, réglages ou
  admin attendu pour chaque famille de pages ;
- le shell membre ne rend aucun header, masque le dock pendant l'onboarding et
  réserve l'espace inférieur lorsque le profil est complet ;
- le dock rend uniquement Découvrir et Profil, marque correctement la route
  active et expose des noms accessibles ;
- la page Profil affiche Réglages pour tous et Administration seulement pour un
  admin ;
- la sidebar admin ne dépend plus d'un rendu conditionnel membre et expose le
  retour vers le profil ;
- `SwipeCard` ne rend plus d'actions visibles de décision, tout en conservant
  swipe, clavier, contrôles lecteurs d'écran, verrouillage et seuil ;
- les principales pages conservent leurs états observables après adaptation.

### Vérification globale

Les tests frontend ciblés sont exécutés en rouge puis en vert. Ils sont suivis
des contrôles TypeScript, ESLint, Prettier, Vitest et du build Vite. Les tests
backend concernés confirment que la séparation de layout n'a modifié aucune
route ou autorisation.

### Vérification visuelle

Les pages Accueil, Connexion, Découvrir, Profil, Réglages et Administration
sont vérifiées en clair et sombre aux dimensions suivantes :

- mobile : 375 × 812 ;
- tablette : 768 × 1024 ;
- ordinateur : 1440 × 900.

La vérification contrôle l'absence de recouvrement par le dock, les zones sûres,
les cibles tactiles, le focus clavier, le comportement responsive de la sidebar
admin et la cohérence visuelle de type application sur les trois largeurs.
