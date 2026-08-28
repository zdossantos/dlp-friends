# Parcours d’onboarding interactif après la complétion du profil

## Objectif

Apprendre aux nouveaux membres à utiliser la découverte, les matches et la
messagerie dans un parcours guidé, accessible et sans interaction avec de vrais
profils. Le parcours démarre après la première complétion du profil, peut être
ignoré sans bloquer le membre et reste relançable depuis les réglages.

Ce travail implémente l’issue GitHub #39. Il complète l’onboarding de profil
existant sans le remplacer : `profiles.onboarding_completed_at` continue de
représenter la complétion du profil, tandis qu’un état séparé représente le
tutoriel produit.

## Parcours validé

Le tutoriel est une expérience linéaire sur une page dédiée :

1. une première carte fictive, marquée « Démonstration », explique le refus ;
   seul un Pass, par geste, bouton ou clavier, permet d’avancer ;
2. une seconde carte fictive, elle aussi explicitement démonstrative, explique
   le Like ; seul un Like permet d’avancer ;
3. ce Like affiche un faux match sans créer de ligne en base ;
4. le membre doit activer « Ouvrir la conversation » pour poursuivre ;
5. une conversation fictive guidée explique la chronologie, la zone de saisie
   et l’envoi d’un message ;
6. la fin marque le tutoriel comme terminé et conduit à la découverte réelle.

Une action incorrecte reste disponible afin d’enseigner les deux interactions,
mais elle ne modifie ni l’étape ni les tables sociales. Un retour visuel et
annoncé aux technologies d’assistance indique l’action attendue.

Le contenu utilise une identité fictive neutre, des assets internes autorisés
et un badge textuel persistant « Démonstration ». Aucun nom, avatar, intérêt ou
message ne provient d’un membre réel. Le vocabulaire reste strictement amical.

## Persistance

Une table `product_onboardings` contient une ligne au plus par utilisateur :

- `user_id`, clé étrangère unique avec suppression en cascade ;
- `status`, enum applicatif et valeur de stockage parmi `not_started`,
  `in_progress`, `completed` et `skipped` ;
- `step`, enum applicatif représentant `pass_demo`, `like_demo`, `match_demo`
  ou `conversation_demo` ;
- les timestamps Laravel.

L’absence de ligne équivaut à `not_started` à l’entrée du parcours. La première
ouverture crée ou met à jour atomiquement l’état vers `in_progress` et conserve
l’étape attendue. Chaque transition est validée côté serveur : le client ne peut
pas sauter une étape en forgeant une requête.

Un parcours interrompu affiche un écran court permettant de reprendre l’étape
persistée ou de recommencer à `pass_demo`. Recommencer et relancer depuis les
réglages remettent le statut à `in_progress` et l’étape à `pass_demo`. Ignorer
marque `skipped`; terminer marque `completed`. Ces deux états empêchent tout
redémarrage automatique, mais pas une relance volontaire.

Une table singleton `product_onboarding_settings` configure les deux cartes de
démonstration :

- `pass_avatar_id`, clé étrangère vers l’avatar de la première carte ;
- `like_avatar_id`, clé étrangère vers l’avatar de la seconde carte ;
- les timestamps Laravel.

Les deux avatars doivent être actifs et distincts. La configuration référence
le catalogue existant et ne duplique ni image ni métadonnée d’avatar. Une valeur
initiale déterministe est créée depuis les deux premiers avatars actifs lors du
seeding local ; en production, l’administration doit enregistrer une
configuration valide avant que le tutoriel ne puisse démarrer.

## Déclenchement et navigation

La première complétion du profil détecte la transition d’un profil incomplet
vers un profil complet. Elle redirige vers la route du tutoriel au lieu de la
route d’atterrissage ordinaire. Modifier ultérieurement un profil complet ne
redéclenche rien.

Le contrôleur d’atterrissage dirige un membre au profil complet vers le
tutoriel seulement si son état est `not_started` ou `in_progress`. Un état
`completed` ou `skipped` conserve la destination normale. Les routes du
tutoriel utilisent les middlewares membre existants et exigent un profil
complet.

La page utilise le layout membre en mode focalisé : la navigation principale
et la navigation inférieure sont entièrement masquées pendant toutes les pages
du parcours, mais les actions explicites « Quitter » et « Ignorer le tutoriel »
restent disponibles. Quitter revient à l’espace membre sans changer un statut
`in_progress`. Une nouvelle page « Tutoriel » dans les réglages décrit l’état
actuel et propose de relancer le parcours.

## Architecture backend

Un modèle `ProductOnboarding` et deux enums typent l’état. Une action métier
unique applique les transitions autorisées dans une transaction avec
verrouillage de la ligne. Les contrôleurs restent minces : afficher le parcours,
faire avancer une étape, ignorer, recommencer et relancer depuis les réglages.

Les transitions ne dépendent jamais de `CreateSwipe`, `MemberMatch`,
`Conversation`, `MessageController`, de Reverb ou des événements de messagerie.
Elles ne reçoivent aucun identifiant de profil, match ou conversation. Cette
séparation structurelle garantit que la démonstration ne peut pas écrire dans
`swipes`, `matches`, `conversations` ou `messages`.

Les propriétés Inertia contiennent uniquement le statut, l’étape et du contenu
de démonstration localisé. Les données fictives sont définies côté application,
pas dans les tables membres.

## Administration du tutoriel

Une page protégée par le rôle `admin`, accessible depuis la navigation
d’administration sous le libellé « Onboarding », réunit configuration et suivi.

La section de configuration présente deux sélecteurs limités aux avatars actifs
et enregistre les avatars des cartes Pass et Like. Une Form Request valide leur
présence, leur activité et leur différence. L’autorisation et la validation
restent côté Laravel.

Un avatar référencé par l’une des deux cartes ne peut être ni archivé ni
supprimé. Les actions existantes du catalogue vérifient la configuration dans
la même opération serveur et renvoient une erreur explicite indiquant que
l’avatar doit d’abord être remplacé dans la configuration du tutoriel. Masquer
le bouton dans Vue ne constitue qu’une aide visuelle ; la protection backend
reste définitive.

Au-dessus du tableau de suivi, cinq indicateurs affichent :

- le nombre de membres `not_started` ;
- le nombre de membres `in_progress` ;
- le nombre de membres `completed` ;
- le nombre de membres `skipped` ;
- le taux de complétion, calculé comme `completed / total des membres éligibles`
  et affiché à zéro lorsque le dénominateur est nul.

Un membre éligible est un compte membre actif, majeur, vérifié et doté d’un
profil complet. L’absence de ligne `product_onboardings` est agrégée et affichée
comme `not_started`.

Le tableau paginé affiche, pour chaque membre éligible, le nom d’affichage,
l’e-mail, le statut, l’étape courante lorsqu’elle existe et la date de dernière
activité du tutoriel. Il est trié par activité décroissante, puis par identifiant
utilisateur décroissant afin de rester stable. Il n’expose aucun swipe, match,
conversation, message privé ou autre contenu social.

## Architecture frontend et accessibilité

La page `Onboarding/Show.vue` orchestre les quatre étapes persistées. Des
composants ciblés rendent la carte de démonstration, la boîte de dialogue de faux
match et la conversation guidée. Ils réutilisent les primitives et styles
existants, tout en conservant un contrat séparé des composants connectés aux
routes sociales réelles.

Les cartes acceptent :

- gestes gauche et droite avec seuil et retour visuel ;
- boutons Pass et Like avec libellés accessibles ;
- touches Flèche gauche et Flèche droite quand la carte a le focus.

Le focus est déplacé vers le titre de chaque nouvelle étape, puis vers le titre
du faux match. La conversation place ensuite le focus sur son introduction. Les
instructions et erreurs d’action utilisent une zone `aria-live`. Le badge
« Démonstration » et une description textuelle rendent la nature fictive du
contenu compréhensible sans dépendre de la couleur.

La fausse zone de saisie explique la composition et l’envoi sans appeler le
serveur social. L’envoi simulé reste en mémoire dans la page et termine la visite
guidée. Les textes visibles et accessibles passent par les catalogues français
et anglais existants.

## Gestion des erreurs

Une transition refusée par le serveur conserve l’étape courante et affiche un
message réessayable. Une erreur réseau ne simule jamais une réussite. Les appels
sont verrouillés pendant leur traitement pour empêcher les doubles transitions.
Les écritures sont idempotentes : répéter la transition attendue conserve le
même état final.

Si l’état stocké est incohérent ou ancien, le serveur le ramène à
`pass_demo` avec le statut `in_progress`; il n’en déduit jamais une interaction
sociale. L’absence de contenu de démonstration localisé utilise le fallback
anglais ou français configuré par l’application.

## Tests

Les tests Pest couvrent :

- la migration, les enums, la relation utilisateur et l’unicité ;
- le déclenchement uniquement lors de la première complétion du profil ;
- les redirections selon les quatre statuts ;
- chaque transition valide, le refus des transitions hors ordre,
  l’idempotence, l’interruption, la reprise, le recommencement, l’ignorance et
  la relance depuis les réglages ;
- l’absence de toute ligne nouvelle dans `swipes`, `matches`, `conversations`
  et `messages` pendant l’ensemble du parcours ;
- les propriétés Inertia limitées au contenu fictif ;
- la configuration administrative, les avatars actifs et distincts, ainsi que
  l’interdiction d’archiver ou supprimer un avatar utilisé ;
- les agrégats, le taux de complétion, le traitement des absences de ligne et la
  pagination stable du tableau de suivi ;
- l’accès strictement administrateur à la configuration et aux statistiques.

Les tests Pest Browser remplacent les tests Vitest demandés par l’issue, car le
dépôt a migré ses parcours frontend vers Pest Browser. Ils couvrent Pass
obligatoire, Like obligatoire, faux match, bouton d’ouverture, conversation
guidée, reprise et relance, en tactile simulé, souris et clavier, avec les
libellés accessibles et le focus attendu. Ils vérifient aussi l’absence de toute
navigation inférieure pendant le parcours. Le parcours navigateur admin couvre
la sélection des deux avatars, les erreurs de validation et le rendu des
statistiques et du tableau.

Les contrôles finaux comprennent les tests ciblés, `composer test`, les
contrôles frontend, le build Vite et la génération Wayfinder.

## Hors périmètre

Le parcours n’ajoute ni vidéo, récompense, succès, personnalisation
comportementale, analytics événementielles, édition administrative des noms,
bios ou messages fictifs, vraie interaction sociale, pièce jointe, réaction ou
fonctionnalité de messagerie supplémentaire.
