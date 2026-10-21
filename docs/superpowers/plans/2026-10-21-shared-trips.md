# Issue 304 — préparation de la proposition de trajets

Statut : inventaire préparatoire conservé pour traçabilité. Les décisions ont reçu une validation conversationnelle ; la conception consolidée, incluant les ajouts exclusion et désistement tardif, se trouve dans [la spécification du 21 octobre](../specs/2026-10-21-shared-trips-design.md). Ce document préparatoire ne constitue pas un plan d’exécution approuvé et ne décrit aucune fonctionnalité déjà livrée.

Référence : [issue #304](https://github.com/zdossantos/dlp-friends/issues/304), section « Points à trancher avant implémentation ». La branche part de `main` après la fusion de la [PR #310](https://github.com/zdossantos/dlp-friends/pull/310), commit `baaf948`. En cas de différence, la spécification consolidée prévaut sur les propositions initiales ci-dessous.

## Résultat attendu

Un membre majeur propose un trajet amical en voiture, train, RER ou bus, pour un aller au parc ou un retour distinct. Les demandes sont acceptées par l’organisateur. Seuls l’organisateur et les participants acceptés voient le rendez-vous précis, les identités privées du groupe et la discussion.

## Engagements déjà établis

- Onglet Trajets dans Sorties, aux côtés d’Événements et Visites ; mêmes panneaux responsive et confirmations existantes.
- Audience par trajet, initialement Mes matchs. Tout élargissement vers Tous les membres exige une confirmation explicite côté serveur ; fermeture ou annulation conserve l’ancienne valeur. Bouton de confirmation rouge.
- Visibilité du calendrier personnel indépendante du trajet. Blocages et indisponibilité restent prioritaires.
- Transport, sens, départ général, destination, date/heure et rendez-vous précis. Places voiture hors conducteur ; ne pas reprendre la capacité événement qui inclut l’organisateur.
- Demande, décision, retrait, sortie, exclusion et annulation protégés par Policy, Form Request et Actions transactionnelles. Une double acceptation concurrente ne sur-réserve jamais le trajet.
- Profils ouverts dans la présentation publique existante depuis chaque liste ; aucun clic sur un nom ne pose implicitement un filtre.
- Retour par l’historique avec filtres, brouillon et défilement conservés ; secours interne pour une entrée directe.
- Même fil, compositeur, reconnexion et non-lus que les groupes existants. Pas de barre de navigation inférieure dans la discussion.
- Composants Select existants, champs date/mois centrés sur Safari ; valeur initiale à la date du jour à Paris. FR/EN et thèmes clair/sombre, sans débordement à 320 px.

## Propositions initiales, remplacées par la spécification consolidée

- Modification jusqu’au départ ; date, lieux et transport verrouillés dès qu’un participant est accepté. Places modifiables sans descendre sous l’occupation.
- Départ dans les douze prochains mois. Trajets complets visibles ; trajets passés et annulés dans Mes trajets.
- Discussion en lecture seule dès annulation ou vingt-quatre heures après le départ. Purge après trente jours, avec conservation des données nécessaires aux comptes bannis conformément à la politique existante.
- Blocage entre participants : retrait de la personne qui bloque. Blocage avec l’organisateur : retrait du participant. Pas de rétablissement automatique après déblocage.
- Train, RER et bus : cinquante personnes maximum dans le groupe, sans garantie de place chez le transporteur.
- Notifications dans l’application et Push selon les préférences ; aucun nouvel e-mail. Messages de groupe rattachés à la préférence existante ; alertes de gestion sans rendez-vous précis ni contenu privé.

## Inventaire et limites vérifiées

`OutingsNavigation` contient actuellement Événements/Visites. Les panneaux responsive, confirmations, `ProfilePresentation`, `UserAvatar` et `BackLink` sont réutilisables.

`EventChat` utilise déjà `MessageTimeline`, `MessageComposer`, `RealtimeStatus` et le brouillon mémorisé ; son adaptateur dépend cependant des routes, identifiants et types d’événement. Extraire le comportement partagé lors du second usage, sans deuxième messagerie ni composant universel conditionnel.

Le stockage courant comprend `event_chats`, messages, lectures et réactions, avec Policy et Actions dédiées. Le choix du stockage commun doit préserver les données et les URLs existantes ; les objets Trajet et Événement restent distincts. Comparer une généralisation ciblée du stockage de groupe et un stockage trajet dédié avant de figer la migration, sans architecture polymorphe générale.

Point sensible : le canal partagé actuel conserve des abonnements déjà autorisés. Pour les trajets, une vérification à l’abonnement seule est insuffisante après révocation. Les destinataires de toute diffusion contenant un message doivent être recalculés côté serveur après commit ; un canal personnel par destinataire autorisé permet de ne pas envoyer aux anciens participants. Vérifier également les notifications en attente et les réactions. Une simple fermeture du panneau côté client ne constitue pas une protection.

`BuildUserDataExport`, `PrepareMemberDataForDeletion`, la purge différée et le gel des données bannies doivent inclure les objets du trajet sans exporter les messages ou inscriptions d’autrui.

## Découpage de mise en œuvre après validation

1. Documenter les décisions approuvées et arrêter le stockage minimal après comparaison avec le chat existant.
2. Tests Pest rouges sur audience, données privées, création et confirmation, puis migrations et domaine trajet.
3. Tests rouges des transitions, propriété et concurrence ; Actions sous verrou du trajet et de ses participations.
4. Chat commun : non-lus, historique, envoi, réactions et révocation HTTP/temps réel ; conserver les parcours événements.
5. Liste et gestion Trajets dans le shell Sorties, profils dans les panneaux existants, formulaires et confirmations traduits.
6. Notifications, export, suppression et rétention ; tests des comptes bannis et des révocations en attente de diffusion.
7. Parcours navigateur proposition/demande/décision/discussion, annulation d’une confirmation, retour/avance, mobile et clavier FR/EN clair/sombre.
8. Contrôles PHP/frontend complets, build Docker, revue du diff et PR distincte vers main. Aucune migration automatique dans Docker, aucun reseed destructeur des données de démonstration.

## Preuves attendues

Tests de concurrence réels MySQL ; aucune donnée privée dans les props Inertia d’un demandeur ; accès coupé pour une connexion déjà ouverte après retrait, blocage ou inactivation ; absence d’accès croisé événement/trajet. Régression des événements, visites, profils et navigation. Les tests se concentrent sur les comportements observables.

## Comparaison du stockage (proposition technique, à confirmer dans le design)

- Tables Trajet dédiées au groupe : migration additive simple, mais deuxième ensemble de messages/lectures/réactions et duplication des requêtes, non-lus, notifications, export et purge. Cette option exige une mutualisation substantielle des services pour respecter l’issue.
- Groupe commun avec deux contextes explicites Événement/Trajet : réutilise un seul stockage de messages/lectures/réactions et leurs comportements. La migration doit conserver les identifiants, messages, réactions et dernières lectures existants ; les routes publiques événements restent compatibles. Le contexte doit appartenir à exactement un événement ou un trajet, sans moteur polymorphe extensible.
- Recommandation de travail : groupe commun à deux contextes concrets, avec règles de participation et d’archivage déléguées aux domaines. Ne pas convertir un trajet en faux événement. Avant de retenir la migration, écrire un test d’évolution sur les données événements existantes et vérifier les contraintes MySQL.

## Matrice des régressions à écrire avant chaque implémentation

| Sujet | Cas observables prioritaires |
| --- | --- |
| Création/audience | Match initial ; membre sans match masqué ; partage confirmé puis nouvelle confirmation après réduction ; annulation/fermeture de la pop-up sans écriture ; calendrier inchangé. |
| Confidentialité | Listes, détail Inertia, recherche et compteurs sans rendez-vous précis, identités privées ni message avant acceptation ; tentatives par identifiant direct refusées. |
| Transitions | Demande répétée sans doublon ; acceptation/refus par propriétaire seulement ; retrait et sortie avec récupération de capacité ; exclusion et annulation irréversibles dans l’état concerné. |
| Concurrence | Deux processus MySQL acceptent la dernière place : un seul succès ; pas de dépassement ; verrouillage et ordre de verrous cohérents avec blocage/suppression. |
| Révocation | Droits retirés après retrait/exclusion/blocage/inactivation : HTTP, historique, réaction, envoi, autorisation de canal et diffusion vers un abonnement déjà ouvert ; tâche de notification en attente recontrôlée. |
| Chat commun | Brouillon, pagination sans doublon, ancrage de lecture, réaction, reconnexion, non-lus, retour/avance et absence de navigation inférieure ; événements existants conservés. |
| Cycle des données | Export limité aux données propres ; suppression et purge après délai retenu ; relations de comptes bannis conservées selon les règles existantes ; données d’autrui exclues de l’export. |
| Interface | 320 px et desktop ; clavier, focus et fermeture des panneaux ; Safari date/mois ; FR/EN et clair/sombre ; clic membre ouvre son profil sans modifier les filtres. |

La spécification consolidée fixe modification, blocage, archivage, anticipation et plafond non automobile. La portée exacte des motifs y est présentée pour relecture ; aucun test ne doit entériner une interprétation encore discutée.
