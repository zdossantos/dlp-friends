# Blocage bilatéral immédiat d’un membre

**Date :** 28 août 2026  
**Issue :** #23

## Objectif

Permettre à un membre de bloquer un autre membre depuis son profil public ou
leur conversation. Le blocage redonne immédiatement le contrôle à son auteur :
les deux profils disparaissent réciproquement de la découverte, leur éventuelle
conversation est archivée et aucun nouveau message ne peut être envoyé dans un
sens comme dans l’autre.

L’interface et les réponses HTTP ne révèlent jamais lequel des deux membres a
bloqué l’autre. Le signalement, la modération et le déblocage restent hors
périmètre.

## Modèle de données et invariants

La table `blocks` et le modèle `Block` existent déjà. Une ligne conserve
`blocker_user_id` et `blocked_user_id`, avec la contrainte unique existante sur
cette paire orientée. Deux membres peuvent donc prendre indépendamment la même
décision, mais répéter le même blocage ne crée jamais de doublon.

Un membre ne peut pas se bloquer lui-même. Une paire est considérée bloquée dès
qu’une ligne existe dans l’un ou l’autre sens. Cette règle bilatérale est
centralisée sur `User` sous une méthode ou un scope explicite réutilisable par
les Policies, la découverte, la messagerie et les canaux de diffusion.

## Action transactionnelle

`BlockUser::handle(User $blocker, User $blocked): Block` porte le cas d’usage.
L’action refuse une paire réflexive puis ouvre une transaction. Elle verrouille
les deux utilisateurs dans l’ordre croissant des identifiants afin que deux
actions concurrentes sur la même paire ne s’interbloquent pas. Elle crée ou
retrouve ensuite le blocage orienté et archive avec le même timestamp toute
conversation active rattachée au match canonique de la paire.

L’action est idempotente : un second appel par le même auteur retourne la ligne
existante et laisse la conversation archivée. La contrainte unique reste la
protection définitive contre deux insertions concurrentes.

## Profil public d’un membre

Une route membre `GET /members/{member}` affiche un profil public dédié. Elle
est accessible depuis la carte de découverte, la confirmation de match et
l’en-tête de conversation. Elle ne sérialise que les données déjà publiques en
découverte : nom d’affichage, âge, avatar, bio, fréquence de visite et intérêts
actifs.

Une `UserPolicy` protège cette page. L’utilisateur cible doit être distinct du
membre connecté, actif, majeur, avoir terminé son profil, posséder un avatar
actif et être visible. Une paire bloquée dans l’un ou l’autre sens produit la
même réponse introuvable qu’un profil absent ou indisponible. Le profil personnel
existant reste accessible par sa route actuelle.

Le profil public propose « Bloquer ce membre » dans une zone secondaire. La
conversation propose la même action dans son en-tête. Les deux surfaces ouvrent
une boîte de dialogue qui explique uniquement les effets bilatéraux : profil
retiré des suggestions, conversation archivée et nouveaux messages impossibles.
Le libellé de confirmation est explicite et le focus revient correctement en
cas d’annulation.

## Route de blocage et navigation

Une route membre `POST /members/{member}/block` appelle `BlockUser`. Elle exige
les middlewares sociaux et de profil complet existants. Le contrôleur résout la
cible sans exposer son état, autorise l’action côté serveur, puis redirige vers
la découverte avec un message flash neutre : « Ce profil n’est plus accessible. »

Après succès, l’auteur quitte donc immédiatement le profil ou la conversation.
Une répétition du POST reçoit le même résultat de succès. Une cible absente,
l’auto-blocage ou une cible non autorisée emploie une réponse générique qui ne
permet pas de déduire un blocage antérieur.

Tous les nouveaux textes système sont fournis par les catalogues Laravel
français et anglais puis exposés par le mécanisme de traduction frontend
existant.

## Découverte, conversations et messagerie

`DiscoveryService` conserve ses exclusions actuelles dans les deux sens et ses
tests les rendent explicites après un blocage créé par l’action.

`ConversationPolicy::view` permet toujours aux deux participants de consulter
l’historique archivé. Cela évite que la visibilité de la conversation révèle
qui a bloqué. `ConversationPolicy::send` exige en revanche simultanément :

- que l’utilisateur appartienne au match ;
- que la conversation ne soit pas archivée ;
- qu’aucun blocage n’existe dans un sens ou dans l’autre.

`SendMessage` reverrouille la conversation et les deux membres dans sa
transaction, recharge l’état pertinent puis réévalue l’autorisation avant
l’insertion. Un blocage concurrent ne peut ainsi pas laisser passer un message
après l’archivage.

Le canal privé Reverb `conversation.{conversation}` réutilise la Policy
d’envoi. Un membre d’une paire bloquée ne peut donc plus s’abonner ou se
réabonner, quel que soit l’auteur du blocage. Les connexions déjà ouvertes ne
reçoivent aucun nouveau message puisque l’action d’envoi est interdite à la
source.

## État d’interface et confidentialité

Le bouton est désactivé pendant la requête afin d’éviter les doubles actions.
Une erreur réseau ou serveur conserve la boîte de dialogue et affiche un message
réessayable sans prétendre que le blocage a réussi. Après succès, le message
flash ne mentionne ni l’autre membre ni l’auteur de la décision.

Une conversation archivée conserve l’état de lecture existant et son historique,
mais le composeur affiche l’état indisponible générique déjà prévu. Aucun texte
ne distingue un archivage causé par un blocage d’un autre archivage futur.

## Tests

### Pest

- schéma et relations existantes du blocage ;
- refus de l’auto-blocage, création unique et répétition idempotente ;
- deux appels concurrents sans doublon ni interblocage ;
- archivage immédiat de la conversation existante et succès sans conversation ;
- exclusion réciproque des suggestions ;
- autorisation du profil public et réponse identique pour un profil bloqué ou
  indisponible ;
- consultation de l’historique archivé par les deux membres ;
- refus de tout nouveau message dans les deux sens, y compris face à une course
  blocage/envoi ;
- refus du canal Reverb dans les deux sens après blocage ;
- route de blocage, redirection et message flash neutre.

### Interface

Les tests frontend existants utilisent Pest Browser et les tests JavaScript
ciblés du dépôt. Ils couvrent :

- le lien vers le profil public depuis découverte et conversation ;
- la présence de l’action sur profil et conversation ;
- l’ouverture, l’annulation, le verrouillage pendant soumission et la
  confirmation de la boîte de dialogue ;
- le maintien de l’état en cas d’échec ;
- les libellés français et anglais sans révélation de l’auteur du blocage.

### Vérification finale

Exécuter les tests ciblés pendant le cycle rouge–vert–refactorisation, puis les
contrôles PHP, frontend, analyse statique, formatage et build concernés avant de
considérer l’issue terminée.

