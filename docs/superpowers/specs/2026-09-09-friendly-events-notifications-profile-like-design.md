# Événements amicaux, notifications persistantes et like depuis un profil

## Contexte

L’issue #128 introduit un sous-système d’événements amicaux dans DLP Friends.
Les échanges de conception ont également validé deux évolutions liées : un
centre de notifications persistant qui absorbe les alertes de match et de
message déjà présentes, et la possibilité de liker un membre depuis sa page de
profil.

Les événements restent réservés à l’espace membre. Ils ne sont ni accessibles
sans connexion ni indexés. Les conversations de groupe restent hors périmètre ;
le like depuis un profil prépare seulement un point d’entrée utilisable dans de
futurs parcours.

Cette conception remplace deux limites du texte initial de l’issue : les
changements de date ou de lieu sont permis jusqu’à 24 heures avant l’événement,
et ces changements produisent une notification dans l’application.

## Objectifs

- Permettre à un membre socialement éligible de créer et gérer un événement
  strictement amical.
- Protéger la capacité contre les inscriptions et acceptations concurrentes.
- Réserver le lieu détaillé et l’identité des participants à l’organisateur et
  aux membres acceptés.
- Offrir un mode d’inscription automatique et un mode soumis à validation.
- Conserver les décisions définitives pour empêcher leur contournement.
- Centraliser les notifications importantes dans un historique persistant,
  filtrable et navigable.
- Réutiliser la logique de swipe pour permettre un like depuis un profil.

## Hors périmètre

- Accès public ou référencement des événements.
- Géolocalisation, carte et recherche par distance.
- Conversations de groupe ou messagerie attachée à un événement.
- Liste d’attente automatique.
- Événements récurrents ou comportant plusieurs dates.
- Notifications par e-mail ou push système.
- Reconstruction rétroactive de notifications antérieures au déploiement.
- Annulation ou remplacement d’une décision de swipe existante.

## Architecture retenue

La fonctionnalité suit les conventions Laravel/Inertia/Vue existantes. Les
règles sont portées par des Actions métier dédiées, les autorisations par des
Policies et la validation HTTP par des Form Requests. Les contrôleurs restent
minces et rendent des pages Inertia.

Chaque transition sensible verrouille la ligne de l’événement dans une
transaction. Cette approche couvre directement les invariants de capacité et
évite une dépendance de machine à états générique.

Les notifications utilisent le système de notifications Laravel avec les
canaux `database` et `broadcast`. La base fournit l’historique persistant ; la
diffusion privée conserve l’immédiateté des alertes actuelles. Une notification
ne contient que les identifiants et métadonnées minimales nécessaires à son
affichage et à sa destination.

## Modèle de données

### Événements

La table `events` contient :

- l’organisateur (`organizer_user_id`) ;
- le titre et la description ;
- une zone générale visible à tous les membres éligibles ;
- un lieu détaillé privé ;
- la date et l’heure de début stockées en UTC ;
- la capacité totale, organisateur inclus ;
- le mode d’inscription `automatic` ou `manual` ;
- `cancelled_at` pour conserver une annulation sans supprimer l’historique ;
- les horodatages Laravel.

La saisie, les validations métier et l’affichage utilisent toujours le fuseau
`Europe/Paris`. Le stockage UTC évite les ambiguïtés des changements d’heure.

L’organisateur occupe implicitement une place et ne possède pas de ligne dans
`event_registrations`. La capacité minimale est donc un. Le nombre de places
occupées vaut un plus le nombre d’inscriptions acceptées.

### Inscriptions

La table `event_registrations` contient une ligne unique par paire
`(event_id, user_id)` et un état parmi :

- `pending` : demande manuelle en attente, sans consommation de place ;
- `accepted` : participation confirmée qui consomme une place ;
- `refused` : refus définitif par l’organisateur ;
- `withdrawn` : désinscription volontaire ;
- `removed` : retrait définitif par l’organisateur ;
- `blocked` : fin interne et définitive causée par un blocage.

Une inscription `withdrawn` peut redevenir `pending` ou `accepted` si
l’événement est encore ouvert et dispose d’une place. Les états `refused`,
`removed` et `blocked` sont terminaux. L’unicité de la ligne empêche les
demandes répétées de contourner ces décisions.

### Notifications

La migration Laravel standard crée la table `notifications`. Les données
applicatives stockées comportent :

- une catégorie `conversations` ou `events` ;
- une clé de traduction et des paramètres d’affichage minimaux ;
- une destination interne typée, conversation ou événement ;
- aucun corps de message, lieu détaillé, e-mail ou secret.

Les notifications sont créées uniquement après le déploiement de la
fonctionnalité. Aucun backfill n’est effectué.

## Règles métier des événements

### Création et visibilité

Les routes exigent les middlewares sociaux existants : authentification,
adresse vérifiée, compte actif, majorité, profil complet et onboarding terminé.
Le créateur devient l’organisateur.

La liste des événements à venir expose à chaque membre éligible le titre, la
description, la zone générale, la date et l’heure, l’organisateur, le mode et
le compteur de participants. Elle n’expose jamais le lieu détaillé ni
l’identité des participants.

L’organisateur et les membres acceptés voient le lieu détaillé et la liste des
participants acceptés. Un membre en attente ne voit que les informations
générales et son propre état.

### Inscription automatique

Une inscription automatique verrouille l’événement, vérifie qu’il n’est ni
annulé ni commencé, contrôle l’absence de blocage dans les deux sens et réserve
une place uniquement si la capacité le permet. La ligne est alors `accepted`.
Une demande simultanée qui arrive après la dernière place reçoit une erreur
localisée et ne crée pas d’inscription active.

### Inscription manuelle

Une demande manuelle valide crée ou réactive une ligne en `pending`. Elle ne
consomme pas de place. L’acceptation par l’organisateur verrouille l’événement
et ne passe à `accepted` que si une place est encore disponible. Le refus passe
à l’état terminal `refused`.

Seul l’organisateur peut accepter, refuser ou retirer une inscription de son
événement. Un retrait passe à `removed` et libère immédiatement une place.
Aucune demande en attente n’est promue automatiquement.

### Désinscription

Un membre accepté peut se désinscrire jusqu’au début de l’événement. La ligne
passe à `withdrawn` et la place est immédiatement libérée. Il peut se
réinscrire plus tard selon le mode courant si l’événement reste ouvert.

### Modification

Le titre et la description peuvent être corrigés tant que l’événement n’a pas
commencé. La date, l’heure, la zone générale, le lieu détaillé et la capacité
peuvent être modifiés uniquement tant que la date actuellement enregistrée est
à au moins 24 heures. La limite est calculée avant la modification pour empêcher
le report tardif d’un événement imminent.

Le mode d’inscription devient immuable dès qu’une première inscription existe.
Une capacité peut être augmentée jusqu’à la limite de 24 heures. Elle ne peut
jamais être réduite sous le nombre de places occupées, organisateur inclus.

Un changement de date, d’heure, de zone générale ou de lieu détaillé notifie
les membres acceptés et en attente. La notification indique qu’une information
a changé mais ne copie pas le lieu privé ; le membre ouvre l’événement pour
voir les données auxquelles il a droit.

### Annulation

Seul l’organisateur peut annuler. L’annulation empêche immédiatement toute
nouvelle inscription ou transition vers `accepted` et notifie les membres
acceptés et en attente. L’événement disparaît de la liste ouverte mais reste
visible avec son statut dans « Mes événements » pour l’organisateur et les
membres concernés.

### Blocage

La création d’un blocage dans un sens ou l’autre passe toute inscription active
entre l’organisateur et le membre à `blocked` dans la même transaction métier,
et libère immédiatement une éventuelle place. Aucune notification n’est créée
et aucun écran ne révèle la cause. Une relation bloquée interdit une nouvelle
inscription et protège aussi les données privées lors de l’affichage.

## Centre de notifications

La barre mobile fixe comporte cinq destinations : Découvrir, Événements,
Messages, Notifications et Profil. L’entrée Notifications utilise une icône de
cloche et un badge accessible indiquant le nombre non lu.

La page Notifications présente :

- le filtre de catégorie `Toutes`, `Conversations` ou `Événements` ;
- un filtre cumulable `Non lues` ;
- une action « Tout marquer comme lu » ;
- une liste paginée du plus récent au plus ancien.

Le clic sur une notification la marque comme lue puis navigue vers sa cible :

- un match ou un message ouvre la conversation ;
- une transition ou modification d’événement ouvre l’événement.

Les alertes de match et de message déjà diffusées en temps réel sont aussi
persistées. Un nouveau match produit une notification pour chacun des deux
membres avec un lien vers la conversation. Un nouveau message produit une
notification pour le destinataire, sans stocker le corps du message dans la
notification. Les présentations temps réel existantes restent disponibles et
les doublons entre réponse Inertia et diffusion sont dédupliqués.

Les événements produisent des notifications pour l’acceptation, le refus, le
retrait, l’annulation et les changements de date, heure ou lieu. Une
désinscription volontaire et un blocage n’en produisent pas.

## Like depuis un profil

La page publique interne d’un membre affiche une action localisée « Ajouter à
mes amis » uniquement si :

- le visiteur et la cible sont éligibles ;
- il ne s’agit pas du même compte ;
- aucune relation de blocage n’existe ;
- aucune décision de swipe du visiteur vers la cible n’existe déjà.

L’action appelle la logique `CreateSwipe` existante avec la décision `like`.
Elle respecte donc l’unicité, la concurrence et la création de match déjà
implémentées. Un like réciproque crée la conversation et les notifications de
match. Une décision antérieure, `like` ou `pass`, n’est ni remplacée ni annulée.

## Autorisation et confidentialité

Une `EventPolicy` protège la lecture privée, la modification, l’annulation et
les décisions d’inscription. Les contrôleurs ne transmettent au frontend que
des représentations déjà filtrées : masquer un bloc dans Vue ne constitue
jamais la protection principale.

Le compte public des participants inclut l’organisateur et les inscriptions
acceptées. Les identités sont absentes des listes générales, des données
analytiques et des notifications. La suppression différée d’un compte traite
les événements organisés selon une annulation explicite avant purge, puis
supprime ou détache les données selon les clés étrangères définies. Les
inscriptions et notifications du membre sont supprimées avec le compte.

L’export personnel est étendu aux événements organisés, aux inscriptions du
membre et à ses propres notifications, sans exporter les données privées
d’autres participants ni le contenu des messages.

## Interface et accessibilité

La rubrique Événements contient :

- une liste des événements à venir ;
- une page « Mes événements » pour les organisations, demandes en attente,
  participations et annulations concernées ;
- un formulaire de création et d’édition ;
- une page de détail adaptée au rôle du visiteur ;
- les contrôles organisateur pour décider, retirer, modifier et annuler.

Les formulaires conservent leurs valeurs après erreur. Les états ne reposent
pas uniquement sur la couleur, les compteurs ont un libellé accessible, le
focus est visible et toutes les actions sont utilisables au clavier. Aucun
texte visible n’est codé en dur : les catalogues français et anglais couvrent
libellés, validations, confirmations, notifications, toasts et textes
d’accessibilité.

## Erreurs et cohérence

Les Actions lèvent des erreurs métier localisées pour les cas attendus :
événement complet, annulé ou commencé, délai de modification dépassé,
inscription terminale, relation indisponible, capacité invalide et action non
autorisée. Les Policies répondent par une interdiction sans exposer de donnée
privée.

Les écritures de notification sont déclenchées après la réussite de la
transaction qui modifie l’état. Une transaction annulée ne laisse donc pas de
notification trompeuse. Les cibles supprimées ou devenues inaccessibles
affichent une erreur générique et la notification peut toujours être marquée
comme lue.

## Tests et vérification

Les tests Pest couvrent :

- création, validation, fuseau et capacité organisateur incluse ;
- autorisations de lecture et d’action ;
- transitions automatiques et manuelles ;
- refus, retrait et blocage terminaux ;
- désinscription et réinscription ;
- doublons et inscriptions ou acceptations concurrentes ;
- modification à la frontière des 24 heures et prévention du contournement ;
- augmentation et réduction de capacité ;
- confidentialité du lieu et des participants ;
- annulation et visibilité historique ;
- création, lecture, filtrage, ciblage et suppression des notifications ;
- persistance des notifications de match et de message sans corps de message ;
- like depuis un profil et réutilisation de `CreateSwipe` ;
- effets du blocage sur une inscription ;
- export et suppression de compte.

Les tests frontend isolent les filtres, le badge non lu, le ciblage et la
déduplication temps réel. Les parcours Pest Browser dans Chromium vérifient la
création, les deux modes d’inscription, la gestion organisateur, la
confidentialité, l’annulation, le centre de notifications et le like depuis un
profil.

Avant livraison, les contrôles ciblés sont suivis de `composer ci:check`, puis
du build Docker de production conformément aux règles du dépôt.

## Documentation produit

La livraison met à jour `docs/PRD.md`, `docs/data-model.md`,
`docs/security-privacy.md` et `docs/technical-architecture.md`. Les événements
quittent le hors-périmètre du MVP et sont présentés comme une évolution livrée,
sans changer le positionnement strictement amical du produit.
