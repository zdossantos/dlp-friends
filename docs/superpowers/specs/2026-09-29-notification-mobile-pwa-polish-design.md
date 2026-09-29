# Design — Finitions notifications, affichage mobile et identité PWA

## Contexte et résultat attendu

Ce document couvre les issues GitHub #222, #223 et #224. Il apporte trois
finitions cohérentes à l’expérience mobile installable : l’état des
notifications de conversation suit réellement la consultation d’une
conversation, les listes ne déplacent plus le document horizontalement et
l’identité visuelle reste lisible dans les thèmes standard et saisonniers.

Le résultat doit préserver les autorisations, la confidentialité des
conversations, les préférences de notification et les règles PWA existantes.
Il ne crée ni nouvelle catégorie de notification, ni suppression de message,
de conversation, de match ou d’événement.

## Principes communs

- Laravel reste la source de vérité pour la lecture et la suppression des
  notifications ; une mise à jour optimiste ne remplace jamais l’action
  serveur.
- Les changements frontend suivent l’amélioration progressive : toutes les
  actions tactiles possèdent une alternative visible, clavier et technologie
  d’assistance.
- Les composants corrigent leur propre largeur. Le shell ne masque pas une
  erreur de dimensionnement avec un `overflow-x: hidden` global.
- Tous les textes visibles et noms accessibles sont traduits en français et en
  anglais.
- Les thèmes utilisent les tokens sémantiques existants et aucun asset Disney.
- Le périmètre minimum reste 320 px, en modes clair, sombre, Halloween et Noël.

## Lot 1 — Notifications liées aux conversations

### Identité d’une conversation

Les notifications de match et de nouveau message contiennent déjà leur cible
de conversation dans leurs données persistées sous la forme canonique
`target_type: conversation` et `target_id`. Le serveur utilise cet identifiant,
et non une comparaison d’URL, de texte traduit ou de type de classe, pour
retrouver les notifications appartenant à la conversation ouverte.

Une Action métier dédiée reçoit le membre et la conversation autorisée. Elle
marque comme lues toutes les notifications non lues de catégories
conversationnelles qui portent le même `conversation_id`. Elle est idempotente
et ne modifie aucune notification sans cet identifiant ni aucune notification
d’une autre conversation.

### Déclenchement de la lecture

La synchronisation est exécutée côté serveur dans le parcours canonique
d’affichage d’une conversation, après son autorisation. Elle fonctionne donc
quelle que soit la source : centre de notifications, liste des conversations,
profil membre, notification Web Push ou URL directe.

Lorsqu’une notification temps réel concernant la conversation actuellement
ouverte arrive après le rendu initial, le frontend appelle la même action de
lecture ciblée. Le compteur partagé, la liste éventuelle et le badge PWA sont
ensuite resynchronisés sans rechargement complet de la page. Une notification
concernant une autre conversation reste non lue.

### Actions d’une notification

Le centre de notifications expose deux actions distinctes :

- « Marquer comme lue » pour une notification non lue, sans navigation ;
- « Supprimer » pour retirer uniquement la notification persistée du membre.

Chaque route charge la notification à travers la relation du membre connecté,
ce qui interdit l’accès à la notification d’un autre compte. La suppression ne
supprime jamais la ressource métier ciblée. Les lectures et suppressions de
notifications partenaires continuent d’enregistrer les métriques existantes
avant de modifier ou retirer la notification.

Sur écran tactile, un glissement horizontal intentionnel révèle les actions de
la ligne. Le déplacement est borné à la largeur des actions, un seuil distingue
le geste d’un tap et le mouvement horizontal n’intercepte le défilement vertical
qu’après détermination claire de l’axe. Une seule ligne reste ouverte. Un tap
hors de la ligne ou l’ouverture d’une autre ligne la referme.

Les mêmes boutons restent disponibles sans geste et portent des libellés
accessibles. Le focus, `Escape`, les états de chargement et les erreurs réseau
laissent la ligne dans un état compréhensible. La préférence de mouvement réduit
supprime les transitions non indispensables.

## Lot 2 — Défilement horizontal parasite

### Périmètre

Les surfaces mobiles contrôlées sont : liste des conversations, centre de
notifications, listes d’événements et panneau de participants. Pour chacune,
la largeur du document doit rester inférieure ou égale à celle du viewport à
partir de 320 px, en français et en anglais et dans les quatre combinaisons de
thème pertinentes.

### Stratégie de correction

Le diagnostic part du premier enfant dont la largeur calculée dépasse son
contenant. La correction est appliquée au composant responsable avec les outils
adaptés : `min-w-0` sur les enfants flex/grid, `max-w-full`, retour à la ligne ou
troncature accessible pour les contenus longs, et largeur locale bornée pour
les surfaces animées.

Les régions dont le défilement horizontal est fonctionnel, notamment une table
large explicitement documentée, conservent leur propre conteneur focalisable et
nommé. Elles ne doivent jamais élargir le document. Le balayage d’une
notification déplace uniquement son contenu à l’intérieur d’une ligne
`overflow-hidden` et ne change pas la largeur de mise en page.

Les corrections ne doivent pas altérer le défilement vertical, les panneaux
adaptatifs, les zones sûres, les taps ni les gestes de découverte existants.

## Lot 3 — Logo et icônes PWA

### Logo dans l’application

Un composant de marque unique choisit la variante du logo depuis le thème
saisonnier actif et l’apparence claire/sombre déjà disponibles. Les layouts et
surfaces qui affichent aujourd’hui directement un SVG réutilisent ce composant.
La boîte, les dimensions intrinsèques et l’alignement restent identiques lors
d’un changement de thème afin d’éviter tout saut de mise en page.

Le thème standard conserve les variantes actuelles. Halloween utilise une
variante dont le rose incompatible est remplacé par les couleurs sémantiques de
l’ambiance, avec contraste suffisant sur surface claire et sombre. Noël ne
reçoit une variante propre que si les variantes standard échouent aux mêmes
critères ; aucune multiplication décorative d’assets n’est requise.

Le composant réagit immédiatement aux changements de classe saisonnière et
d’apparence sans rechargement. Le texte alternatif ou le nom accessible reste
« DLP Friends » et le SVG décoratif interne ne duplique pas cette annonce.

### Icônes installables

Les systèmes d’exploitation pouvant mettre les icônes installées en cache et
le manifeste ne garantissant pas une sélection dynamique par thème, les icônes
PWA restent un jeu fixe. Les quatre PNG 192/512 et `maskable` utilisent le fond
de la palette sombre DLP Friends plutôt qu’un noir pur. Le symbole conserve son
contraste et les versions `maskable` respectent la zone sûre centrale.

Le manifeste, l’icône Apple et les métadonnées de tête continuent de référencer
les bons formats. Les changements n’introduisent aucun chargement distant ni
dépendance d’exécution.

## Données, sécurité et erreurs

Aucune migration n’est nécessaire : les notifications de conversation
persistantes possèdent déjà `target_type` et `target_id`. Une notification
historique ou malformée qui ne les possède pas est ignorée par la lecture
groupée et reste traitable individuellement.

Toutes les routes de mutation utilisent l’authentification, la relation
`notifications()` du membre et la protection CSRF Laravel. Une ressource absente
ou étrangère renvoie 404 sans révéler son existence. Une erreur réseau conserve
la notification affichée, annule le déplacement visuel et fournit le retour
d’erreur Inertia habituel.

## Validation

Les tests fonctionnels prouvent :

- l’ouverture d’une conversation marque uniquement ses notifications de match
  et de message comme lues ;
- l’action est idempotente et ignore une autre conversation ainsi que les
  notifications sans `conversation_id` ;
- un membre peut marquer ou supprimer sa propre notification, mais pas celle
  d’un autre membre ;
- une suppression ne touche aucune conversation, aucun message, événement ou
  match ;
- les métriques partenaires existantes restent cohérentes.

Les tests frontend et navigateur prouvent :

- le seuil du geste, la distinction horizontal/vertical et les alternatives
  clavier ;
- la mise à jour des compteurs lors d’une lecture et d’une notification temps
  réel dans la conversation ouverte ;
- `document.documentElement.scrollWidth <= window.innerWidth` sur chaque liste
  ciblée à 320 px, avec contenu long ;
- l’absence de régression sur le défilement vertical et les gestes intentionnels ;
- la sélection immédiate du logo pour chaque thème sans changement de boîte ;
- la présence, les dimensions, le fond sombre et la zone sûre des icônes PWA,
  ainsi que les références du manifeste.

Les contrôles finaux comprennent les tests ciblés rouge/vert, les suites PHP et
frontend concernées, PHPStan, lint, format, types et build Vite.

## Hors périmètre

- Modifier le contenu ou les préférences des notifications Web Push.
- Supprimer une conversation, un message, un match, un événement ou une
  inscription depuis le centre de notifications.
- Fournir une icône installée différente pour chaque thème saisonnier.
- Reconcevoir les layouts, la navigation ou la palette globale.
- Masquer globalement tout débordement horizontal sans en corriger la cause.
