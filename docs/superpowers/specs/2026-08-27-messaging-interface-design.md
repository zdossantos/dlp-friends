# Interface de messagerie — design

## Contexte

L’issue #22 doit permettre à un membre de retrouver ses conversations et
d’échanger dans une interface mobile accessible. Elle s’appuie sur l’issue #21,
qui fournit déjà la persistance des messages, l’action d’envoi, le point d’entrée
HTTP, l’événement `MessageSent` et le canal privé Reverb d’une conversation.

Le produit reste strictement amical. Une conversation appartient à un match et
ne peut être consultée que par ses deux membres. Une conversation archivée reste
lisible, mais aucun nouveau message ne peut y être envoyé.

## Objectifs

- Afficher uniquement les conversations du membre connecté.
- Présenter les dix derniers messages d’une conversation au premier chargement.
- Charger l’historique plus ancien par lots de dix lors du défilement vers le
  haut, sans saut de lecture.
- Ajouter les nouveaux messages sans rechargement grâce à Echo/Reverb.
- Conserver les messages déjà affichés lors d’une coupure temps réel.
- Rendre les erreurs visibles, compréhensibles et récupérables.
- Fournir un compositeur utilisable au clavier, avec un lecteur d’écran et sur
  un petit écran avec clavier virtuel.

La saisie en cours distante, la présence, les notifications, les pièces jointes,
les réactions, l’édition et la suppression restent hors périmètre.

## Routes et autorisations

Une route `GET /conversations` nommée `conversations.index` affiche la liste. Sa
requête part de l’utilisateur connecté et filtre les matches dont il est l’un
des deux membres. Elle ne doit jamais charger une conversation étrangère, y
compris pour un administrateur.

La route existante `GET /conversations/{conversation}` nommée
`conversations.show` devient une réponse Inertia. Elle conserve l’autorisation
`ConversationPolicy::view`. L’envoi continue d’utiliser
`POST /conversations/{conversation}/messages` et
`ConversationPolicy::send`, qui refuse une conversation archivée.

Le canal `private-conversation.{id}` reste autorisé avec la même règle d’envoi.
Ainsi, une conversation archivée ne reçoit plus de nouveaux événements, tout en
restant consultable par ses membres.

## Contrats Inertia

La page `Conversations/Index` reçoit une collection `conversations`. Chaque
entrée contient :

- l’identifiant de la conversation ;
- l’interlocuteur (`id`, nom d’affichage et avatar rendu par le catalogue) ;
- l’état d’archivage ;
- le dernier message éventuel (`content`, auteur et date) ;
- la date d’activité utilisée pour le tri.

Les conversations sont triées par date du dernier message décroissante, puis
par date de création décroissante lorsqu’aucun message n’existe. La page rend un
état vide explicite lorsque le membre n’a encore aucune conversation.

La page `Conversations/Show` reçoit :

- `conversation` avec son identifiant et son état d’archivage ;
- `participant` avec les informations publiques nécessaires à l’en-tête ;
- `messages`, un paginator Inertia dont chaque élément contient `id`,
  `conversation_id`, `author_user_id`, `content` et `created_at` ;
- `currentUserId`, utilisé uniquement pour distinguer les bulles envoyées et
  reçues.

Le backend sélectionne d’abord les dix messages les plus récents, puis remet
chaque page en ordre chronologique avant sérialisation. `Inertia::scroll()`
configure la fusion des pages. Le composant `InfiniteScroll` utilise
`only-previous` et `preserve-url` afin de charger uniquement les pages plus
anciennes en haut de l’historique sans polluer l’URL.

Les contenus restent du texte brut. Vue les interpole avec `{{ }}` ; aucun
`v-html` n’est utilisé.

## Interface

La navigation membre gagne une entrée « Échanges » entre « Découvrir » et
« Profil ». Elle mène à la liste des conversations et reste active sur les
pages de conversation.

La liste occupe la largeur disponible, privilégie des cibles tactiles d’au
moins 44 px et affiche l’avatar, le nom, un aperçu tronqué et une date concise.
Une conversation archivée est identifiable sans reposer uniquement sur la
couleur.

La page de conversation utilise trois zones stables :

1. un en-tête avec retour vers la liste, avatar, nom et état d’archivage ;
2. un historique défilable marqué comme région de journal accessible ;
3. un compositeur fixé dans le flux du layout, au-dessus de la zone sûre et du
   clavier virtuel.

Au premier affichage, l’historique se positionne sur le dernier message. Le
chargement de pages plus anciennes restaure l’ancre visuelle. Un nouveau message
fait défiler vers le bas seulement si le membre était déjà proche du bas ; il ne
doit pas arracher la lecture d’un message ancien.

Le compositeur contient un libellé accessible, une zone de texte limitée à
2 000 caractères, un compteur et un bouton d’envoi. Il est désactivé pendant la
requête et lorsque la conversation est archivée. `Enter` envoie le message,
tandis que `Shift+Enter` insère un retour à la ligne. Après succès, la saisie est
vidée et le focus reste dans le champ. Après échec, le texte et le focus sont
conservés et une erreur associée au champ permet de réessayer.

## Temps réel et résilience

Un composable dédié rejoint le canal privé de la conversation et expose un état
de connexion : `connecting`, `connected` ou `disconnected`. Il écoute
`.message.sent`, normalise la charge utile et ajoute le message à la collection
locale seulement si son identifiant n’est pas déjà présent. Cette déduplication
évite le doublon entre la réponse HTTP de l’auteur et l’événement diffusé.

La reconnexion native de Pusher/Echo reste active. Lors d’une perte de connexion,
un bandeau `role="status"` annoncé poliment indique que le temps réel est
interrompu. Les messages persistés et déjà chargés restent intacts. Un bouton
« Réessayer » force une nouvelle tentative de connexion. Une reconnexion réussie
masque le bandeau et recharge partiellement la première page des messages afin
de récupérer d’éventuels messages persistés pendant la coupure, avec fusion et
déduplication par identifiant.

Une erreur d’envoi est distincte d’une erreur temps réel : le message peut avoir
été persisté même si la diffusion échoue, conformément à l’issue #21. La réponse
HTTP réussie ajoute donc immédiatement le message localement ; l’état du socket
ne conditionne jamais la persistance.

## Accessibilité et mobile

- Les états vide, archivé, déconnecté et erreur utilisent du texte explicite.
- Les nouveaux messages sont annoncés par une région `aria-live="polite"` sans
  relire tout l’historique.
- Le journal et le compositeur conservent un ordre de tabulation naturel.
- Les boutons ont un nom accessible et un indicateur de focus visible.
- La page utilise `100svh`, les zones sûres et des conteneurs `min-h-0` pour ne
  pas masquer le compositeur derrière le clavier virtuel ou la navigation.
- Les messages longs reviennent à la ligne et ne créent aucun débordement
  horizontal à 320 px.
- Les animations respectent `prefers-reduced-motion`.

## Tests

Les tests Pest fonctionnels couvrent :

- la liste limitée aux conversations du membre et son ordre ;
- les props de la liste et de la page de conversation ;
- le refus d’un identifiant deviné ;
- les dix derniers messages au premier chargement ;
- la pagination vers les messages plus anciens, sans doublon et dans l’ordre ;
- la lecture d’une conversation archivée et l’impossibilité d’envoyer.

Les tests Pest Browser couvrent :

- l’état vide et la navigation mobile vers « Échanges » ;
- le compositeur, son état pendant l’envoi, `Enter` et `Shift+Enter` ;
- l’ajout et la déduplication d’un événement temps réel ;
- la conservation de l’historique lors d’une déconnexion et le bouton de
  reconnexion ;
- l’erreur d’envoi récupérable avec conservation du texte ;
- l’état archivé ;
- l’absence de débordement horizontal et d’erreurs JavaScript sur mobile ;
- les libellés, régions dynamiques et comportements de focus essentiels.

Le dépôt ayant remplacé Vitest par Pest Browser, les attentes frontend de
l’issue sont transposées dans les parcours Chromium existants. Les contrôles
ciblés précèdent `composer test`, les vérifications frontend et le build final.
