# Trajets entre membres — conception de l’issue 304

Date : 21 octobre 2026. Statut : conception écrite validée par l’utilisateur le 21 octobre 2026 ; pas une fonctionnalité livrée.

Références : [issue 304](https://github.com/zdossantos/dlp-friends/issues/304), [PR 312](https://github.com/zdossantos/dlp-friends/pull/312), maquettes liées à l’issue. Ce document rassemble les règles approuvées, puis l’exclusion et le signalement d’un désistement tardif. L’utilisateur a ensuite validé cette conception écrite, y compris la portée des motifs obligatoires.

## Intention et limites

Permettre à des adultes de coordonner un aller vers Disneyland Paris ou un retour distinct, en voiture, train, RER ou bus. L’organisateur choisit les participants. Les rencontres restent amicales ; aucun paiement, billet, itinéraire, GPS, ville de résidence ou contrôle de présence physique n’est ajouté.

Les objets Trajet, Événement et Visite restent distincts. Sorties partage sa navigation et les groupes partagent leur messagerie. La fonctionnalité sera livrée dans une PR séparée de l’issue 305, déjà fusionnée.

## Proposition et audience

Une proposition contient transport, sens, départ général, destination, date et heure, rendez-vous précis et audience. La date initiale correspond au jour courant à Paris ; le départ doit rester futur et dans les douze prochains mois calendaires. Stockage des instants en UTC, saisie et affichage en Europe/Paris.

L’audience initiale est Mes matchs. Chaque élargissement réel vers Tous les membres exige une confirmation explicite, contrôlée côté serveur, y compris à la création. Annuler ou fermer la confirmation ne change rien. Le bouton de partage est rouge. Une réduction d’audience ne supprime pas une participation déjà acceptée ; elle limite les nouvelles consultations et demandes. Les règles de blocage et d’indisponibilité priment toujours.

Les membres de l’audience voient seulement les informations générales et le nombre de places disponibles. L’organisateur peut être présenté publiquement. Le rendez-vous précis, l’identité des autres participants et la discussion ne sont transmis qu’à l’organisateur et aux participants acceptés, jamais cachés seulement par CSS. Un demandeur voit son propre état sans recevoir les données privées des autres membres. Les dates de visite personnelles conservent leur audience indépendante.

## Participation et capacité

La demande est manuelle, sans accès privé avant acceptation. L’organisateur accepte ou refuse ; le demandeur retire une demande en attente ou quitte une participation acceptée. Un nouveau passage après refus, retrait ou départ exige une nouvelle demande et une nouvelle décision ; une ancienne acceptation n’est jamais restaurée automatiquement.

Les demandes et décisions cessent au départ ou à l’annulation. Une sortie reste possible tant que le groupe existe. L’organisateur ne peut pas quitter son propre trajet : il doit l’annuler. Il peut exclure un participant accepté, avec confirmation rouge et motif obligatoire. L’exclusion retire immédiatement l’accès privé et libère la capacité ; elle n’est pas un bannissement de l’application. Une nouvelle demande après exclusion n’est possible que si les autorisations habituelles le permettent et reste soumise à une nouvelle décision.

Les places voiture excluent le conducteur. La capacité train/RER/bus est de cinquante personnes, organisateur compris ; ce plafond ne réserve ni ne garantit une place chez le transporteur. Un trajet complet reste visible mais n’accepte pas de nouvelle demande. Les demandes déjà en attente ne sont pas annulées automatiquement ; leur acceptation attend une place libre.

L’ordre des verrous est commun aux transitions, blocages et suppressions : comptes concernés par identifiant croissant, puis trajets par identifiant croissant, puis participations. Une transaction verrouille le trajet avant toute transition ou variation de capacité. L’acceptation recontrôle les deux comptes, les blocages, la demande et la dernière place disponible. Les doubles requêtes ne créent pas de doublons et deux acceptations concurrentes ne peuvent pas dépasser la capacité. Les appels répétés sur un état terminal ne répètent pas les notifications.

La règle validée exige un motif lors d’un refus, d’une sortie volontaire, d’une exclusion et d’une annulation. Le retrait d’une demande non acceptée n’exige pas d’explication. Les motifs sont limités à 1 000 caractères, validés et échappés. Un refus ou une exclusion est expliqué uniquement à la personne concernée ; une sortie à l’organisateur ; une annulation aux participants et demandeurs concernés. Aucun motif libre ne figure dans une notification Push, un journal technique ou une annonce publique du groupe. Un retrait automatique pour blocage ou inactivation ne demande aucun motif et ne révèle pas sa cause.

## Modification et annulation

Avant toute acceptation, tous les champs restent modifiables avant le départ. Dès la première acceptation, transport, sens, départ général, destination et instant du départ sont verrouillés, même si le groupe se vide ensuite. Pour changer ces éléments, annuler puis recréer le trajet.

Le rendez-vous précis reste modifiable, avec notification des participants acceptés. Les places voiture restent ajustables sans descendre sous le nombre de participants acceptés. L’audience suit les confirmations décrites plus haut.

L’annulation par l’organisateur est possible avant le départ et confirmée en rouge, conserve le contexte pour l’historique, arrête demandes et décisions et met la discussion en lecture seule. Le motif ne remplace pas la confirmation. La liste courante présente les trajets futurs actifs ; Mes trajets inclut les trajets organisés ou demandés/rejoints, y compris passés ou annulés jusqu’à leur purge. Un ancien participant conserve uniquement son propre historique minimal de participation, sans rendez-vous précis, autres identités privées ou messages.

## Désistement dans les douze heures et signalement

L’organisateur dispose d’une action Signaler le désistement sur une participation acceptée qui a été quittée volontairement dans l’intervalle allant de douze heures avant le départ incluses jusqu’au départ exclu. Le calcul utilise l’heure serveur et l’horaire du trajet verrouillé. Il ne se fonde pas sur une date envoyée par le navigateur.

Retirer une demande en attente, être exclu, être retiré par blocage/inactivation ou quitter après le départ n’ouvre pas ce signalement. Le serveur conserve l’événement de sortie nécessaire : participation, état antérieur accepté et heure. Une nouvelle demande ne réécrit pas cette preuve historique.

Le formulaire demande une explication obligatoire, limitée à 1 000 caractères, puis une confirmation. Un seul dossier ouvert est permis pour cette sortie et cet organisateur ; une reprise réseau ne crée pas de doublon. L’action reste disponible depuis l’historique de gestion jusqu’à la purge du trajet. Le membre ciblé n’est pas notifié de l’identité de l’auteur du signalement. Un signalement n’entraîne ni sanction ni score de fiabilité automatique.

### Réutilisation de la modération et confidentialité

L’existant signale une conversation privée et donne accès à tous ses messages. Ce droit ne convient pas à un désistement : ne pas créer une fausse conversation et ne pas ouvrir le chat de groupe à l’administration par cet intermédiaire.

Ajouter un dossier de désistement lié à la sortie, avec auteur, cible, heure prévue, heure de sortie, explication, décision, décideur et dates. Réutiliser les composants de confirmation, les contrôles administrateur actif, l’audit minimal et l’Action de sanction existante ; ajouter le contexte trajet aux listes et à l’historique administratif des signalements reçus. Garder un formulaire et une Policy propres au désistement. La fiche administrative lit uniquement ce dossier, sans messages, rendez-vous précis ni autres participants. Consultation et clôture sont auditées ; clôture avec décision obligatoire de 1 000 caractères maximum.

La purge normale du trajet détache le dossier et conserve uniquement ces éléments nécessaires à la modération, selon le cycle existant des signalements. Elle ne conserve pas le chat. Suppression de compte et gel des données bannies suivent la politique existante. L’export personnel exclut les identités d’auteurs de signalements reçus et les identités administratives. La documentation distingue cette extension du signalement général de profils, toujours hors périmètre.

## Blocages et inactivation

Un blocage entre organisateur et participant retire le participant, quel que soit son auteur. Entre deux participants, la personne qui bloque quitte le groupe après une confirmation expliquant la conséquence. Cela ne révèle pas à l’autre membre qu’il a été bloqué. Le déblocage ne restaure aucune participation.

La mise hors service du compte organisateur annule ses trajets futurs et ferme les accès privés ; celle d’un participant retire sa participation. Les effets du masquage doivent respecter le contrat existant de confidentialité : retrait des nouvelles surfaces de découverte, sans assimiler silencieusement masquage et bannissement. Toutes les Actions recontrôlent les autorisations réelles côté Laravel.

## Une messagerie de groupe commune

Deux stockages séparés de messages, lectures et réactions multiplieraient les requêtes de non-lus, les notifications et les règles de purge. Transformer un trajet en événement confondrait capacité et autorisations. Le choix retenu pour cette conception est un groupe commun à deux contextes concrets, événement ou trajet, avec exactement une relation renseignée. Aucun moteur polymorphe extensible n’est ajouté.

Faire évoluer le stockage événement existant vers les tables de groupe communes en préservant identifiants, messages, réactions, curseurs de lecture et dates. La migration impose l’exclusivité événement/trajet et leur unicité. Les URLs événements existantes restent utilisables ; leurs contrôleurs délèguent aux mêmes Actions de groupe avec leur Policy de contexte. Un test de migration sur données existantes précède cette évolution. Les règles de capacité, participation et archivage restent dans leur domaine ; le délai événement existant n’est pas modifié.

Côté Vue, réutiliser MessageTimeline, MessageComposer, RealtimeStatus, les réactions et la mémorisation du brouillon. Extraire l’adaptateur commun utile à partir d’EventChat ; conserver des entrées événement/trajet explicites pour les informations et endpoints. Le groupe trajet figure dans la messagerie Groupes avec ses non-lus. Ni la discussion événement ni celle du trajet ne conserve la barre inférieure ou son espace réservé.

Pour un trajet, lecture seule à l’annulation ou vingt-quatre heures après le départ. Les anciens participants n’ont aucun accès à l’historique du chat après sortie/exclusion. La consultation d’un groupe en lecture seule reste réservée aux membres encore autorisés.

### Révocation pendant une connexion existante

Le canal partagé événement actuel ne suffit pas à protéger un abonnement déjà ouvert après exclusion. Les messages, réactions et autres données privées passent par les canaux personnels des destinataires autorisés, calculés à nouveau à la diffusion après commit. La liste n’est pas figée lors de l’envoi ni lors de l’abonnement. Les événements de révocation ne contiennent pas de données privées.

Recontrôler également l’auteur, l’organisateur et les droits au moment de la livraison des notifications en attente, y compris leur événement Broadcast et Push. Le client filtre par identifiant de groupe, déduplique avec la réponse HTTP, recharge les compteurs et ferme un groupe devenu inaccessible. Une déconnexion forcée ou une fermeture visuelle seule ne constitue pas une garantie. Les événements existants bénéficient de cette protection commune, sans changer leurs règles métier.

## Notifications

Notifications persistantes dans l’application et Push selon les préférences, sans nouvel e-mail. Les messages reprennent la préférence Messages de groupe. Les demandes, décisions, exclusions, changements du rendez-vous et annulations utilisent une préférence Trajets dédiée afin de ne pas dépendre d’un réglage Événements trompeur ; les composants existants de préférence et de centre de notifications sont conservés.

Une demande alerte l’organisateur ; une décision la personne concernée ; un changement de rendez-vous les participants acceptés ; une annulation les participants et demandeurs concernés ; une sortie l’organisateur. Les alertes de gestion ne contiennent ni rendez-vous précis ni motif libre. La cible interne est résolue côté serveur et revérifiée lors de l’ouverture ; un membre exclu peut consulter son seul état de décision, pas le groupe. Pas de notification de message après perte de droit.

## Stockage du domaine et rétention

Tables distinctes pour trajets, demandes/participations et sorties historiques minimales. Une nouvelle demande après départ conserve la sortie utilisée par un éventuel signalement ; ne pas transformer toutes les mutations en journal métier générique. Les textes de motif restent attachés à leur transition et n’apparaissent pas dans les traces techniques.

Purger les trajets et leur discussion trente jours après le départ, ou trente jours après l’annulation si elle a lieu avant le départ. Le signalement détaché conserve son dossier minimal comme indiqué plus haut. La purge est idempotente, recontrôle le statut banni sous verrou et ne détruit pas indirectement des données protégées par la suppression d’un autre compte.

BuildUserDataExport inclut les trajets propres, ses participations, ses messages et ses motifs/dossiers autorisés ; jamais les messages ou participations privés d’autrui. PrepareMemberDataForDeletion inclut les nouvelles relations et respecte le gel existant des données bannies. Pas de fichier d’export stocké.

## Interface et navigation

Onglet Trajets actif dans OutingsNavigation. Liste mobile lisible, détail et gestion via les panneaux adaptatifs et confirmations partagés. Cliquer un membre ouvre le profil public existant, sans modifier un filtre. BackLink revient dans l’historique en conservant filtres, défilement et brouillon ; entrée directe avec secours interne.

Réutiliser les Select de l’application et le traitement Safari des champs date/mois. Aucun texte utilisateur en dur : catalogues FR/EN, erreurs Laravel comprises. Vérifier 320 px et ordinateur, clavier/focus, chargements et états vides, clair/sombre. Les maquettes ne sont pas une preuve de livraison ; aucun raccourci Aperçu après acceptation n’existe dans le produit.

## Validation avant livraison

Suivre rouge, vert, refactorisation pour les règles et régressions. La couverture doit démontrer :

- Audience et chaque élargissement confirmé, annulation sans écriture, indépendance du calendrier.
- Absence des données privées dans les props Inertia, réponses et notifications d’un tiers ou demandeur.
- Transitions, raisons, organisateur seul décisionnaire, demandes répétées et deux acceptations concurrentes réelles MySQL pour la dernière place.
- Frontières du signalement à exactement douze heures, avant/après, au départ, sortie forcée, nouvelle demande et doublon concurrent ; aucune lecture administrative de chat accordée.
- Verrouillage des champs après première acceptation et notification d’une modification du rendez-vous.
- Sortie/exclusion/blocage/inactivation pendant une connexion ouverte, messages et réactions mis en file avant révocation, Push différé et accès intercontexte refusé.
- Migration préservant l’historique événements, non-lus et réactions ; parcours événements/visites inchangés.
- Lecture seule aux échéances, export propre, purge à trente jours, dossiers de modération détachés et relations bannies préservées.
- Parcours Pest Browser Chromium proposition/demande/décision/groupe, exclusion motivée, désistement/signalement/admin, annulation de confirmation, retour/avance, clavier et mobile FR/EN clair/sombre.

Après contrôles ciblés, lancer les contrôles PHP/frontend pertinents complets, Wayfinder, Vite et build Docker. Aucun succès annoncé sans sortie récente. Déploiement Docker local sans reseed destructeur, avec migration explicitement exécutée ; aucun lancement automatique de migration ajouté à Docker. Mise à jour du PRD et des documents spécialisés seulement pour décrire le comportement réellement livré.
