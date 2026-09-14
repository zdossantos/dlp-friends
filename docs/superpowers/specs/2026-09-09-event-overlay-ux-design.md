# Expérience événements à deux écrans et panneaux adaptatifs

## Contexte

La première version des événements amicaux utilise plusieurs pages Inertia
distinctes pour découvrir, consulter, créer, modifier et gérer un événement.
Les essais sur mobile ont révélé quatre limites : certains contrastes sont trop
faibles en thème sombre, les événements complets encombrent la découverte, les
rôles d'organisateur et de participant sont insuffisamment différenciés, et les
parcours secondaires font perdre le contexte de la liste.

Cette conception conserve seulement deux écrans complets : **Découvrir les
événements** et **Mes événements**. Toute autre interaction s'affiche dans un
panneau adaptatif au-dessus de l'un de ces écrans.

## Objectifs

- Rendre l'interface lisible et cohérente en thèmes clair et sombre.
- Retirer les événements complets de la découverte sans les masquer aux membres
  qui les organisent ou y participent dans « Mes événements ».
- Distinguer immédiatement les événements organisés de ceux rejoints.
- Afficher détail, formulaires, participants, profils et gestion sans quitter
  l'écran de liste courant.
- Garantir qu'une URL directe ou une notification reconstruise exactement la
  même interface qu'une navigation manuelle.
- Mutualiser les composants, les règles d'autorisation et les données entre les
  différents points d'entrée.

## Écrans principaux et navigation

Les deux seuls écrans de premier niveau sont :

1. `/events`, qui affiche la découverte ;
2. `/events/mine`, qui affiche les événements du membre.

Les routes secondaires restent adressables pour les notifications, les liens
partagés dans l'application, l'historique et le rechargement du navigateur,
mais elles ne rendent plus une page autonome. Elles reconstruisent un écran
principal puis ouvrent le panneau correspondant :

- le détail d'un événement ouvre le panneau de détail ;
- la création ouvre le formulaire vide ;
- la modification ouvre le formulaire prérempli ;
- les participants, un profil et les demandes d'inscription utilisent des
  sous-vues du même panneau.

Une navigation initiée depuis « Mes événements » conserve cet écran comme
arrière-plan. Une URL directe ou une notification utilise « Découvrir » comme
arrière-plan par défaut. L'origine est portée dans l'état de navigation interne
et n'est jamais acceptée comme URL de redirection externe.

Fermer le panneau retire uniquement la route secondaire. Le bouton précédent
du navigateur revient d'abord à la sous-vue précédente, puis ferme le panneau.
La liste, ses filtres et sa position de défilement sont préservés.

## Panneau adaptatif mutualisé

Un composant métier unique orchestre le contenu secondaire :

- sur mobile, il compose les primitives `Sheet` en panneau occupant la majeure
  partie de l'écran et respectant les zones sûres ;
- à partir du palier `sm`, il compose les primitives `Dialog` en modale centrée,
  avec hauteur maximale et défilement interne ;
- il partage le même titre, la même hiérarchie, les mêmes actions, la même
  gestion du focus et le même état métier dans les deux variantes.

Le composant reçoit un état de vue typé (`detail`, `create`, `edit`,
`participants`, `participant-profile`, `registrations`) et ne duplique pas les
contenus pour mobile et ordinateur. Les écrans principaux, les URLs directes et
les notifications alimentent tous ce même état.

La fermeture rend le focus au déclencheur lorsque celui-ci existe. Les titres,
descriptions accessibles et actions de fermeture sont présents dans chaque
variante. Les transitions respectent `prefers-reduced-motion`.

## Découverte et événements complets

La requête de découverte élimine côté serveur tout événement dont le nombre de
places occupées atteint la capacité. Cette règle ne repose pas sur un masquage
Vue et s'applique avant la sérialisation.

Un événement complet reste accessible :

- dans « Mes événements » pour son organisateur ;
- dans « Mes événements » pour un membre ayant une inscription acceptée ;
- depuis une notification ou une URL autorisée pour ces mêmes membres.

Le comportement concurrent de la capacité reste inchangé : le serveur demeure
la source de vérité si la dernière place est prise après le chargement d'une
liste.

## Présentation de « Mes événements »

La liste est séparée en deux sections :

- **J'organise**, avec un pictogramme d'organisation et un libellé explicite ;
- **Je participe**, réservée aux inscriptions en attente ou acceptées, avec un
  pictogramme de participation et le statut textuel correspondant.

Les états annulé et passé restent visibles lorsqu'ils appartiennent au membre,
mais sont visuellement atténués. La distinction de rôle repose sur le texte,
l'icône et la structure, jamais uniquement sur la couleur.

Les cartes de découverte et de gestion partagent un socle commun. Des variantes
de rôle ajoutent seulement les informations et actions nécessaires.

## Détail et participants

Le détail d'un événement s'affiche dans le panneau adaptatif. Il utilise les
tokens `card`, `foreground`, `muted`, `secondary`, `border` et `destructive`
sans texte clair forcé sur une surface claire. Le lieu privé emploie une surface
sémantique garantissant son contraste dans les deux thèmes.

Pour les membres autorisés, les participants sont représentés par un bouton en
forme de pile d'avatars :

- trois avatars au maximum sont visibles et se chevauchent ;
- un dernier cercle affiche `+N` lorsque la liste dépasse trois personnes ;
- une bordure fondée sur `card` sépare les images en thèmes clair et sombre ;
- le bouton possède un libellé accessible indiquant le nombre de participants.

Un clic remplace le contenu du panneau par la liste complète. Chaque ligne
affiche l'avatar, le nom et une action ouvrant le profil. L'identité des
participants reste limitée à l'organisateur et aux inscriptions acceptées,
conformément au contrat existant.

## Profil imbriqué

Le clic sur un participant charge son profil public dans le même panneau. Le
profil réutilise `ProfilePresentation` et les mêmes règles de visibilité,
d'autorisation, de blocage et de like que la page de profil existante. Les
données détaillées ne sont chargées qu'au clic afin de ne pas enrichir
inutilement la charge du détail de l'événement.

Un bouton retour placé en haut à droite ramène à la liste des participants. La
fermeture globale reste distincte et possède son propre libellé accessible.
Lorsque le profil ne peut plus être consulté, le panneau revient à la liste et
affiche un message localisé sans exposer de données supplémentaires.

## Création, modification et gestion

`EventForm` reste l'unique formulaire de création et de modification. Il est
hébergé dans le panneau adaptatif et conserve les valeurs saisies lorsque la
validation serveur échoue. Après création, l'URL et le contenu du panneau
basculent vers le détail du nouvel événement. Après modification, le détail est
rafraîchi dans le même panneau.

Les demandes d'inscription de l'organisateur sont une sous-vue du panneau de
détail. Une décision met à jour le détail et les compteurs sans renvoyer vers
une page autonome.

Les actions sensibles suivantes utilisent une confirmation applicative fondée
sur `Dialog`, et jamais `window.confirm` :

- se désinscrire ;
- annuler un événement ;
- retirer un participant ;
- refuser une demande lorsque la décision est définitive.

Chaque confirmation nomme l'action, décrit sa conséquence, propose Annuler et
Confirmer, bloque la double soumission et rend le focus au déclencheur.

## Données et autorisations

Le résumé d'un participant contient uniquement son identifiant, son nom
d'affichage et les données publiques nécessaires à son avatar. Le profil complet
est obtenu par un contrôleur dédié réutilisant la présentation et les policies
de profil existantes.

Les contrôleurs Inertia fournissent un modèle commun d'écran événement : liste
principale, origine autorisée et panneau optionnel. Les routes ne possèdent pas
de seconde implémentation visuelle. Les décisions métier et les contrôles
d'accès restent côté Laravel.

## Erreurs et états transitoires

- Le contenu du panneau affiche un squelette ou un état de chargement pendant
  une navigation différée.
- Une erreur de formulaire reste dans la sous-vue courante.
- Une ressource supprimée, annulée ou devenue inaccessible ferme la sous-vue
  invalide et présente un retour localisé sur l'écran principal.
- Une inscription refusée pour capacité atteinte rafraîchit la liste et retire
  l'événement désormais complet de la découverte.
- Les boutons asynchrones exposent un état occupé et empêchent la répétition.

## Accessibilité et thème sombre

- Toutes les surfaces et tous les textes utilisent les tokens sémantiques du
  design system.
- Les combinaisons critiques sont vérifiées dans les thèmes clair et sombre.
- Le panneau piège correctement le focus et le rend à la fermeture.
- Les piles d'avatars, boutons d'icône et actions retour possèdent un nom
  accessible.
- La liste des participants reste utilisable au clavier et avec un lecteur
  d'écran ; l'ordre visuel correspond à l'ordre du DOM.
- Les cibles tactiles principales mesurent au moins 44 px sur mobile.

## Tests et critères d'acceptation

Les tests automatisés couvrent au minimum :

- l'exclusion serveur des événements complets dans la découverte ;
- leur maintien dans « Mes événements » pour les personnes concernées ;
- la séparation « J'organise » / « Je participe » ;
- l'ouverture identique du détail depuis une carte, une URL directe et une
  notification ;
- la création et la modification dans le panneau, succès et erreurs compris ;
- le rendu `Sheet` mobile et `Dialog` desktop ;
- la pile d'avatars et le compteur `+N` ;
- la navigation participants → profil → retour dans le même panneau ;
- les restrictions d'accès aux participants et aux profils ;
- les confirmations applicatives et leur annulation ;
- le contraste visuel et les contrôles essentiels en thèmes clair et sombre ;
- l'historique navigateur, la fermeture et la préservation du défilement.

Un parcours navigateur multi-utilisateur vérifie enfin les interactions entre
organisateur, participants acceptés, demande en attente et membre extérieur,
notamment lorsque l'événement atteint sa capacité.
