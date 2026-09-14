# Discussion de groupe des événements — conception

## Contexte et objectif

Les événements amicaux et leurs inscriptions sont déjà implémentés. L’issue
#191 ajoute à chaque événement un espace textuel commun sans transformer les
conversations privées liées aux matches en conversations de groupe.

La discussion doit permettre à l’organisateur et aux participants acceptés de
préparer la rencontre, recevoir les nouveaux messages en temps réel et retrouver
un compteur fiable après reconnexion. Elle reste ouverte pendant l’événement et
les sept jours suivants, puis devient définitivement accessible en lecture
seule. Une annulation la rend immédiatement accessible en lecture seule.

## Décisions produit

- La discussion apparaît dans un onglet « Discussion » du panneau adaptatif de
  l’événement, aux côtés de « Détails » et « Participants ».
- Une URL profonde ouvre l’espace Événements sur le bon contexte et sélectionne
  directement cet onglet. La discussion n’apparaît pas dans la messagerie privée.
- L’organisateur et les inscriptions actuellement acceptées ont accès au chat.
- Une désinscription, un retrait ou un blocage révoque immédiatement et
  définitivement l’accès de la personne concernée, en HTTP comme en temps réel.
- Après archivage, l’organisateur et les participants encore acceptés conservent
  la lecture de tout l’historique. Les personnes dont l’accès avait déjà été
  révoqué ne le récupèrent pas.
- Aucun accusé de lecture individuel, présence ou indicateur de saisie n’est
  ajouté au chat de groupe.

## Architecture et modèle de données

Le chat de groupe constitue un agrégat distinct de la messagerie privée. Trois
tables sont ajoutées :

- `event_chats`, avec une clé étrangère `event_id` unique et supprimée en
  cascade avec l’événement ;
- `event_chat_messages`, avec le chat, l’auteur, le contenu texte et les
  horodatages ;
- `event_chat_reads`, unique par couple chat/utilisateur, avec l’identifiant du
  dernier message lu et les horodatages.

La clé unique de `event_chats.event_id` garantit un seul espace par événement.
La création de l’événement et celle du chat s’exécutent dans la même transaction
avec une opération idempotente. La migration crée également, de manière
idempotente, le chat des événements qui existent avant son exécution. Un chat ne
peut donc pas survivre sans son événement et un événement utilisable possède
toujours son chat.

Les modèles `EventChat`, `EventChatMessage` et `EventChatRead` exposent des
relations explicites. `Event` expose une relation `chat`. Ils ne réutilisent ni
`Conversation` ni `Message`, dont les invariants restent limités à un match et à
deux membres.

Un message conserve son auteur par clé étrangère. La purge définitive d’un
compte supprime les messages de chat écrits par ce compte ainsi que ses états de
lecture. La suppression d’un événement supprime son chat, ses messages et ses
états de lecture. L’export personnel inclut les messages de chat écrits par le
membre uniquement pour les événements auxquels il est encore autorisé, sans
exporter les messages des autres participants.

## Autorisations et cycle de vie

Une `EventChatPolicy` centralise les règles utilisées par les contrôleurs et
les canaux Reverb.

La consultation est autorisée si le membre est l’organisateur ou possède une
inscription `accepted`, et si aucune relation de blocage applicable ne révoque
son inscription. Une inscription `pending`, `refused`, `withdrawn`, `removed`
ou `blocked` ne permet ni de découvrir le chat, ni d’en charger les messages, ni
de rejoindre son canal.

L’envoi exige en plus que l’événement ne soit pas annulé et que l’heure courante
soit strictement antérieure à `starts_at + 7 jours`. L’événement ne possède pas
de durée de fin distincte : conformément au modèle existant, `starts_at` est le
repère du début et de la fin métier pour calculer cette fenêtre. À l’instant
exact `starts_at + 7 jours`, le chat est en lecture seule.

L’archivage est calculé depuis les données de l’événement plutôt que persisté
par une tâche planifiée. Il reste donc correct même si un worker ou le scheduler
est interrompu. Toute tentative d’envoi devenue invalide entre l’affichage du
formulaire et la requête reçoit une erreur métier localisée et ne crée ni ne
diffuse de message.

Les actions existantes d’acceptation, désinscription, retrait, blocage et
annulation diffusent après validation de leur transaction un événement personnel
minimal aux membres concernés. Le client recharge alors les seules données de
l’événement utiles et rejoint ou quitte le canal de chat selon les nouveaux
droits. L’autorisation Reverb reste la barrière de sécurité ; la mise à jour Vue
ne remplace jamais le contrôle serveur.

## Messages, historique et temps réel

L’envoi passe par une `SendEventChatMessage` et une Form Request dédiées. Le
contenu suit la même validation que la messagerie privée : texte obligatoire,
normalisé selon les conventions existantes et limité à 2 000 caractères. Le
serveur choisit toujours l’auteur depuis la session authentifiée.

L’action verrouille les données nécessaires, revérifie le droit d’envoi,
persiste le message puis diffuse `EventChatMessageSent` après commit sur le
canal privé `event-chat.{chatId}`. Sa charge utile contient uniquement les
données nécessaires à l’affichage du message. Aucun lieu privé ni donnée
d’inscription n’est diffusé.

L’historique reprend la pagination de la messagerie privée : les messages les
plus récents sont chargés au premier affichage, les pages anciennes sont
remises dans l’ordre chronologique et l’ancre visuelle est préservée. Le client
fusionne réponse HTTP et événement Reverb par identifiant de message afin que
l’auteur comme les destinataires ne voient chaque message qu’une fois.

Une perte de connexion conserve l’historique déjà chargé. L’interface signale
la coupure et permet une resynchronisation partielle qui fusionne les messages
persistés manquants sans doublon.

## État de lecture et compteurs

`event_chat_reads.last_read_message_id` représente seulement le dernier message
vu par un membre dans un chat. Aucun état n’est exposé aux autres membres.

Le compteur d’un membre correspond aux messages postérieurs à ce curseur dont
il n’est pas l’auteur. En l’absence de curseur, tous les messages écrits par
d’autres membres sont non lus. Ouvrir l’onglet Discussion et rendre les messages
récents visibles marque, par une requête idempotente, le dernier message chargé
comme lu. Le serveur refuse d’avancer un curseur vers un message d’un autre chat
ou à un identifiant antérieur.

Les compteurs sont calculés depuis les messages persistés et les curseurs, puis
transmis dans les résumés d’événement autorisés. Ils restent ainsi cohérents
après rechargement ou reconnexion. Les événements temps réel peuvent les mettre
à jour de façon optimiste, mais la persistance serveur reste la source de vérité.
Le badge apparaît sur l’onglet Discussion et sur les cartes des événements dans
« Mes événements » ; aucune carte publique ne révèle l’existence ou l’activité
du chat à un membre non autorisé.

## Interface et accessibilité

Le panneau actuel conserve son comportement drawer sur mobile et modale sur
ordinateur. L’onglet Discussion utilise les primitives de la messagerie privée :
chronologie, messages groupés par auteur, chargement de l’historique et
compositeur multiligne. `Entrée` envoie et `Maj+Entrée` insère une nouvelle
ligne. Le contenu texte est échappé et les mots longs ne causent aucun
débordement à 320 px.

Lorsque le chat est accessible en lecture seule, le compositeur est remplacé
par un message expliquant soit l’annulation, soit l’archivage sept jours après
l’événement. Une révocation d’accès ferme la discussion et ramène vers une
surface encore autorisée sans révéler de contenu supplémentaire.

Les nouveaux messages sont annoncés par une région `aria-live="polite"` sans
déplacer le focus. Les onglets, l’historique, le chargement des pages et le
compositeur sont utilisables au clavier. Les badges ne reposent pas uniquement
sur la couleur. Tous les libellés, erreurs, textes de lecture seule et noms
accessibles sont fournis dans les catalogues français et anglais et vérifiés en
thèmes clair et sombre.

## Routes et intégration

Les routes authentifiées protégées couvrent :

- l’ouverture paginée de l’historique d’un chat d’événement ;
- l’envoi d’un message ;
- l’avancement du curseur de lecture.

L’URL profonde du panneau utilise l’état de navigation déjà employé par les
événements et ajoute la sélection `chat`. Elle reconstruit « Mes événements »
pour un organisateur ou un participant accepté. Les réponses Inertia ne
transmettent le chat, ses messages ou son compteur que si la Policy l’autorise.

Le canal `event-chat.{chatId}` est privé. Son callback d’autorisation charge le
chat et délègue à la même Policy que l’HTTP. Les événements personnels de
transition d’inscription ne contiennent que les identifiants et le type de
transition nécessaires à une actualisation ciblée.

## Gestion des erreurs

- Une URL ou un canal étranger reçoit un refus sans distinguer chat inexistant
  et chat non autorisé.
- Une révocation concurrente avec un envoi gagne dès qu’elle est persistée :
  l’action d’envoi revérifie les droits dans sa transaction.
- Une double soumission ne crée qu’un message par requête acceptée et la
  déduplication d’affichage repose sur l’identifiant persistant.
- Une panne Reverb ne remet pas en cause un message accepté par HTTP ; la
  resynchronisation récupère ensuite le message persisté.
- Une erreur d’envoi conserve le brouillon et expose un message localisé associé
  au compositeur.

## Tests et vérifications

Les tests Pest et frontend suivent un cycle rouge, vert, refactorisation et
couvrent au minimum :

- le schéma, les cascades, l’unicité et le rattrapage idempotent des chats ;
- la création atomique et idempotente du chat avec l’événement ;
- les autorisations HTTP pour organisateur, accepté, attente, refus, retrait,
  désinscription, blocage et membre étranger ;
- les mêmes autorisations sur le canal privé Reverb ;
- l’accès acquis après acceptation et révoqué après désinscription, retrait ou
  blocage sans rechargement manuel ;
- la lecture seule immédiate après annulation et à `starts_at + 7 jours`, avec
  tests aux frontières temporelles ;
- la validation à 2 000 caractères, l’auteur imposé par le serveur, la
  persistance avant diffusion et l’absence de diffusion après refus ;
- la diffusion après commit et la déduplication entre réponse HTTP et Reverb ;
- les curseurs de lecture, les compteurs par membre et leur recalcul après
  reconnexion, sans exposition d’un état de lecture individuel ;
- la purge de compte, l’export personnel et la suppression en cascade ;
- les traductions françaises et anglaises ;
- un parcours Chromium clavier et responsive à 320 px, en thèmes clair et
  sombre, ainsi qu’un échange temps réel entre deux sessions.

Les contrôles ciblés sont suivis des analyses, linters, vérifications de types,
build frontend et suites de tests complètes concernés par le changement.

## Hors périmètre

Les pièces jointes, GIF, réactions, sondages, édition ou suppression de message,
reçus individuels, présence, saisie, rôles de modération, administrateurs
délégués et accès public ne font pas partie de cette évolution.
