# Notifications membres en temps réel — Conception

## Objectif

Informer immédiatement les deux membres lorsqu’un nouvel univers croisé crée
effectivement un échange, et informer le destinataire d’un nouveau message
depuis toutes les pages de l’espace membre. Enrichir le dialogue d’univers
croisés avec l’identité visuelle de l’autre membre et permettre de filtrer la
liste des échanges par nom public.

Le périmètre reste celui d’une application déjà ouverte. Il n’ajoute ni
notification push, ni e-mail, ni historique persistant de notifications, ni
recherche dans le contenu des messages.

## Architecture

Le serveur diffuse les événements métier après validation de la transaction.
Les événements personnels passent par le canal privé Laravel existant
`App.Models.User.{id}`, dont l’autorisation exige que l’utilisateur authentifié
corresponde à l’identifiant du canal.

`MemberLayout` porte un abonnement personnel unique pendant toute la durée de
vie de l’espace membre. Un composable ciblé traduit les événements reçus en
annonces d’interface : dialogue d’univers croisés ou toast de nouveau message.
Les pages de conversation conservent leur abonnement au canal de conversation
pour le fil et les reçus de lecture. Elles n’assument plus la responsabilité
des notifications transversales.

La recherche d’échanges reste locale à `Conversations/Index.vue`, car le
contrôleur fournit déjà la totalité des échanges visibles. Elle filtre la
collection réactive par nom public sans effectuer de requête serveur.

## Création et diffusion d’un univers croisé

`CreateSwipe` distingue un match nouvellement créé d’un match préexistant. Deux
instances destinées de l’événement `MatchCreated`, une par participant, ne sont
déclenchées que lorsque le match et sa conversation viennent tous deux d’être
créés ou garantis dans la transaction. L’événement implémente la diffusion
après commit afin qu’aucun client ne puisse annoncer une conversation
inexistante ou une transaction annulée.

Les deux participants reçoivent l’événement sur leur canal privé. La charge
utile commune contient l’identifiant du match et celui de la conversation. Les
données du membre rencontré sont adaptées à chaque destinataire et contiennent
uniquement son identifiant public, son nom public et son avatar actif : URL de
l’image, nom accessible et couleurs du fond.

La réponse Inertia du swipe conserve son retour immédiat pour le membre qui
déclenche la réciprocité. Le client déduplique la réponse et l’événement Reverb
avec la clé stable `match:{matchId}`. La première annonce reçue gagne ; la
seconde est ignorée. Une navigation ne doit pas réannoncer le même match.

## Dialogue « Vos univers se croisent »

Le composant `MatchDialog` accepte une identité de membre comprenant le nom et
l’avatar. Il affiche un petit `AvatarPortrait` centré au-dessus du titre, puis
le nom du membre rencontré et la description canonique existante. Il conserve
les actions permettant d’ouvrir l’échange ou de continuer à explorer, ainsi
que son comportement accessible de dialogue et la réduction des animations.

Le même rendu sert au match déclenché depuis Explorer, au match reçu en temps
réel et à l’échange d’assistance créé par un administrateur. Les chemins qui
ouvrent ce dialogue doivent donc fournir la même forme de données.

## Toast de nouveau message

`MessageSent` continue d’être envoyé sur le canal de la conversation pour le
fil ouvert et sur les canaux privés des deux participants pour les surfaces
globales. Sa charge utile personnelle expose les informations minimales de
l’auteur nécessaires à l’interface : identifiant, nom public et avatar si la
structure partagée l’exige. Le contenu du message est déjà nécessaire à la
prévisualisation.

Le gestionnaire global ignore :

- un message dont l’auteur est l’utilisateur courant ;
- un message destiné à la conversation actuellement ouverte ;
- un identifiant de message déjà traité pendant la session du layout.

Pour tout autre message, il affiche un toast cliquable dont le titre est le nom
public de l’auteur et dont la description est le début du message. La
description est échappée par Vue, limitée visuellement à une ligne et tronquée
avec une ellipse. L’action et le clic ouvrent `/conversations/{id}` via Inertia.
Le toast possède un libellé d’action traduit et reste utilisable au clavier.

## Abonnements, déduplication et reconnexion

Un seul composable global s’abonne au canal privé de l’utilisateur. Son cycle
de vie suit celui de `MemberLayout`; les écouteurs et le canal sont libérés à
son démontage. Les pages ne créent aucun second abonnement personnel pour les
mêmes événements.

Deux ensembles bornés aux identifiants rencontrés pendant la vie du layout
dédupliquent séparément les matchs et les messages. Ils empêchent les doublons
de livraison ou de réponse Inertia, sans persister de notification ni rejouer
un historique après reconnexion. Le canal de conversation reste responsable
de fusionner le message dans un échange ouvert et de gérer les reçus.

## Recherche dans la liste des échanges

Un champ de type recherche est affiché sous l’en-tête de la liste. Son libellé,
son placeholder et l’action d’effacement sont traduits en français et en
anglais. La valeur est locale à la page et ne modifie ni l’URL ni le serveur.

La comparaison porte uniquement sur `participant.display_name`. Elle est
insensible à la casse et aux accents grâce à une normalisation Unicode
partagée, puis à la suppression des marques diacritiques. Les espaces autour
de la requête sont ignorés.

La liste temps réel reste la source de vérité : chaque message reçu met d’abord
à jour et réordonne cette liste, puis le résultat visible est recalculé selon
la recherche active. Lorsque la liste source est vide, l’état vide historique
est affiché. Lorsqu’elle contient des échanges mais qu’aucun nom ne correspond,
un état « aucun résultat » distinct est affiché avec une action pour effacer la
recherche.

## Autorisation, blocage et profils masqués

Les canaux personnels n’autorisent que leur propriétaire. Les canaux de
conversation continuent de déléguer à la Policy et exigent un échange actif et
non bloqué pour l’envoi et la réception interactive.

Un blocage empêche l’envoi avant la création du message, donc aucun événement
de message ne peut être diffusé après ce refus. Un profil masqué reste conforme
au contrat produit : son échange peut être ouvert par URL directe, accepter des
messages et produire un toast, même s’il n’apparaît pas dans la liste. Le clic
du toast ouvre donc directement la conversation autorisée.

Les charges utiles n’exposent ni e-mail, ni données privées de profil, ni
contenu autre que le message explicitement envoyé aux deux participants.

## Traductions et accessibilité

Tous les textes visibles sont ajoutés aux catalogues français et anglais
existants. Cela inclut la recherche, son effacement, l’état sans résultat,
l’action du toast et les libellés accessibles de l’avatar et des annonces.

Le dialogue conserve titre, description, gestion du focus et fermeture. Les
toasts utilisent la région accessible de Sonner sans multiplier les annonces.
Le champ de recherche possède un libellé associé, un bouton d’effacement nommé
et un focus visible. Le sens des retours ne dépend ni de la couleur ni du
mouvement.

## Tests et vérification

Le développement suit rouge, vert, refactorisation.

Les tests Pest couvrent :

- aucune diffusion avant la création effective du match et de sa conversation ;
- diffusion après commit vers les deux canaux privés avec l’identité opposée ;
- autorisation du canal personnel et refus d’un tiers ;
- absence d’événement lors d’un swipe non réciproque, d’un doublon ou d’un
  refus lié au blocage ;
- événement message après commit et absence de création/diffusion lorsqu’un
  membre n’est pas autorisé à envoyer.

Les tests navigateur couvrent :

- deux membres connectés recevant chacun le dialogue de match sans rechargement ;
- absence de double dialogue pour le membre qui déclenche la réciprocité ;
- avatar et nom visibles dans le dialogue ;
- toast reçu depuis une autre page, prévisualisation sur une ligne et
  navigation vers le bon échange ;
- absence de toast pour l’auteur et dans l’échange déjà ouvert ;
- absence de doublons après livraison répétée ou navigation ;
- recherche par nom insensible à la casse et aux accents, état sans résultat
  et conservation des mises à jour temps réel sous filtre.

Les contrôles ciblés sont suivis des lint, analyse statique, types, formatage,
build et suites Pest concernées. Les tests d’intégration Reverb à deux sessions
restent conditionnés par l’environnement temps réel documenté du dépôt.

## Documentation

`docs/PRD.md` classe les notifications de match et de message dans le MVP
implémenté une fois la livraison vérifiée et les retire des évolutions futures.
`docs/technical-architecture.md` décrit l’abonnement personnel global, les deux
catégories d’événements et leurs règles de canal, de commit et de
déduplication.

L’indicateur de saisie de l’issue #127 reste hors de cette livraison. Il pourra
réutiliser le canal de conversation et la discipline de nettoyage, sans ajouter
un second abonnement global concurrent.
