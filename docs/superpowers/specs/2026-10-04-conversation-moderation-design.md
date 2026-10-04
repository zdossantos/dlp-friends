# Signalement des échanges et bannissement — issue 256

## Objectif et périmètre

Permettre à un participant de signaler un échange privé, à un administrateur
d'examiner cet échange en lecture seule et de bannir un compte sans le supprimer.
Le service reste strictement amical, réservé aux adultes. La modération
partenaire demeure un parcours distinct.

Hors périmètre : signalement de profils ou de discussions d'événement,
modération automatique, accès administratif aux échanges non signalés,
modification des messages et reconnaissance d'une nouvelle identité.

## Décisions produit recueillies

Les corrections de l'utilisateur des 4 octobre 2026 priment sur la proposition
initiale de conservation limitée et de consultation jusqu'au signalement.

- L'administration voit toute la conversation signalée : historique ancien
  et messages ultérieurs. Blocage, masquage, archivage, bannissement et clôture
  du signalement ne retirent pas cet accès.
- Le formulaire propose de bloquer simultanément l'autre participant. Le
  choix « Oui » est sélectionné par défaut ; le membre peut choisir « Non ».
  Le blocage autonome reste disponible.
- Le bannissement est un état banni/non banni, sans durée ni expiration.
- Toutes les données du compte banni sont conservées sans échéance automatique.
  Le compte banni ne peut pas demander sa suppression dans l'application.
- La suppression administrative explicite reste possible. Seule la purge
  automatique des données du compte banni est désactivée.

Les autres paramètres proposés restent : motifs harcèlement, propos
discriminatoires, contenu sexuel, menace, spam/arnaque et autre ; précision
facultative de 1 000 caractères ; un seul signalement ouvert par auteur et
conversation ; motif obligatoire pour une sanction ; pas de bannissement
d'un administrateur ; nouvelle version des textes légaux avec réacceptation
des CGU par les comptes existants.

## Signalement par un membre

Réutiliser `useResponsiveModal` pour le drawer mobile et la modale ordinateur.
Le formulaire est accessible au clavier et contient le motif obligatoire,
la précision facultative, le choix explicite du blocage et une confirmation.

Avant la confirmation, annoncer que les administrateurs pourront lire
l'intégralité de l'échange, y compris les messages futurs, même après
blocage ou clôture du signalement. Annuler ne transmet aucune donnée et ne
bloque personne.

Le serveur exige un compte membre actif, vérifié et majeur et vérifie
l'appartenance au match. Un tiers ne peut signaler par substitution d'identifiant.
Le signalement est autorisé sur un échange auquel le membre appartient, y
compris lorsque le blocage ou l'archivage empêche déjà la messagerie.

Le serveur crée le signalement et applique le blocage choisi dans une même
transaction. Réutiliser `BlockUser` et ses effets sur les événements. Lorsqu'un
administrateur est l'autre participant, son signalement reste possible mais
le blocage reste interdit ; le formulaire explique cette exception et le
serveur refuse toute demande de blocage de cet administrateur.

Une répétition pendant qu'un signalement de cet auteur est ouvert ne crée pas
de doublon. Les contraintes et verrous protègent aussi les demandes simultanées.
Après clôture, un nouveau signalement est possible. Un signalement sans blocage
ne restreint pas la conversation et ne notifie pas l'autre participant.

## Consultation et décision administratives

Ajouter une file paginée de signalements ouverts et clôturés dans l'espace
administratif et une fiche dédiée avec participants, motif, précision,
horodatages et décision. Réutiliser le langage visuel et la navigation admin.

Une Policy exige un administrateur actif, vérifié et majeur. L'accès au contenu
passe exclusivement par un signalement existant ; aucune route ne permet
d'ouvrir une conversation arbitraire avec son identifiant.

La fiche lit les messages originaux dans un fil paginé et chronologique,
sans filtre sur leur date ni sur la visibilité des profils. Elle ne modifie
aucun curseur de lecture et n'inscrit pas l'administrateur au match ou au
canal de participants. Elle n'offre ni envoi, ni réaction, ni modification,
ni suppression de message. La Policy de conversation ne reçoit pas de droit
général supplémentaire pour les administrateurs.

Une décision textuelle obligatoire, limitée à 1 000 caractères, clôture le
signalement et conserve acteur et date. La clôture ne retire pas l'accès à
l'échange. Chaque consultation du contenu, décision et changement de sanction
produit un audit minimal : acteur, cible, opération et date ; aucune copie
de message, de secret ou de précision libre dans les journaux techniques.

L'accès persiste tant que les données existent. Une suppression administrative
explicite peut les retirer ; elle ne doit pas être présentée comme réversible.
Les suppressions des comptes non bannis gardent leur parcours existant ;
les cascades doivent être adaptées pour ne pas purger indirectement les données
du compte banni. Les références aux comptes effectivement supprimés doivent
être neutralisées sans conserver inutilement leurs données personnelles.

## Bannissement

Ajouter `banned` à `UserStatus`, distinct de `pending_deletion`, avec acteur,
date et motif dans un audit de sanction. Ne pas ajouter de date de fin.
L'administration peut modifier l'état banni/non banni avec confirmation et
motif, sans restauration automatique des publications, inscriptions ou sessions.

Une Action verrouille le compte ciblé, revérifie qu'il n'est pas administrateur,
change son état et révoque ses sessions et accès privés. Le bannissement peut
viser un compte déjà en attente de suppression : son ancienne purge ne doit
plus s'appliquer. Conserver ses identifiants de connexion, liens Google et clés
d'accès afin de reconnaître une identité authentifiée et empêcher son retour.
Ne conserver aucun jeton OAuth nouveau.

La sanction s'applique à tous les rôles cumulés. Les événements futurs organisés
sont annulés, les inscriptions futures retirées, les publications partenaires
retirées et les envois encore en attente arrêtés. Les données correspondantes
restent stockées. Les abonnements Push sont révoqués sans supprimer leurs
enregistrements ; aucune notification privée n'est ensuite délivrée.

Le statut actif doit être vérifié pour les deux participants dans les actions
sociales, Policies, listes, compteurs et canaux privés. Les sessions déjà ouvertes
et connexions Reverb déjà autorisées doivent perdre leur accès immédiatement :
refuser un nouvel abonnement ne suffit pas. Vérifier les capacités présentes
de Reverb et protéger également chaque émission privée contre les destinataires
désormais sanctionnés.

## Authentification et protection des identifiants

Couvrir mot de passe, Google, passkeys, second facteur et cookies de connexion
persistante. Aucun de ces parcours ne doit laisser une session utilisable à un
compte banni, y compris si le bannissement intervient pendant l'authentification.

Après vérification de l'identité, afficher un message FR/EN expliquant le
bannissement pour comportement dans l'application et le contact fourni par
`LEGAL_CONTACT_EMAIL`. Une simple adresse saisie ne révèle pas la sanction.
Une authentification avec second facteur exige sa réussite avant ce message.

L'e-mail et l'identifiant Google conservés empêchent une nouvelle inscription
avec ces mêmes identifiants. La protection ne prétend pas reconnaître une
personne revenant avec une nouvelle identité et n'utilise aucun suivi d'appareil.

## Conservation, suppression et export

Aucun scheduler ni job de purge automatique ne retire les données d'un compte
actuellement banni, même si un job avait été planifié avant la sanction.
Inclure les données partenaires et leurs traitements de rétention existants.
Les messages et données du compte banni ne doivent pas être supprimés
indirectement par une cascade de suppression d'un autre compte.

La demande de suppression est refusée côté serveur, dans la route et dans
l'Action métier, pour un compte banni. La suppression administrative explicite
garde ses autorisations et sa confirmation ; elle supprime effectivement les
données et met fin à la protection liée aux identifiants conservés.

Les comptes non bannis conservent le délai de suppression de 30 jours.
L'export d'un compte actif inclut ses signalements et les décisions le concernant
avec des données minimales, sans identité de l'auteur d'un signalement reçu,
identité des admins, messages d'autrui ni accès administratif. Le compte banni
n'a accès à aucun parcours privé d'export en application.

Conserver les signalements permettant l'accès administratif après clôture.
Ne pas dupliquer le contenu des messages dans les audits ou un instantané.
La suppression administrative reste l'exception explicite à la conservation.

## Textes et documentation

Toutes les traductions, erreurs, confirmations, placeholders et libellés
accessibles sont ajoutés aux catalogues Laravel/frontend FR/EN existants.
Les CGU et la confidentialité expliquent le signalement, l'accès permanent
à l'échange signalé, le blocage facultatif, le bannissement sans expiration,
la conservation sans échéance et le refus de suppression en application
pour les comptes bannis. Les documents décrivent le comportement logiciel
et ne prétendent pas déterminer les droits légaux de la personne.

Versionner les textes au `2026-10-04`. La réacceptation explicite des CGU
précède l'accès aux fonctionnalités privées des comptes existants, sans
contourner les contrôles de bannissement. Les routes nécessaires à la
réacceptation et à la déconnexion restent accessibles aux comptes éligibles.

Mettre à jour PRD, sécurité/confidentialité, modèle de données, architecture,
design system et règles éditoriales selon les changements réellement livrés.

## Réutilisation et organisation

- Policy et Form Request dédiées aux signalements et aux sanctions.
- Actions de signalement, clôture et changement de sanction ; réutilisation
  des Actions de blocage et de cycle d'événement lorsque leurs effets conviennent.
- Stockage des signalements lié à la conversation, avec statut, auteur,
  motif, précision, décision, décideur et dates ; audit séparé sans messages.
- Réponses d'authentification existantes et middleware de compte actif renforcés.
- Formulaire membre adaptatif ; file et fiche admin dédiées à la lecture seule.
- Aucune API séparée, dépendance ou abstraction générique nouvelle.

## Vérification attendue

Suivre rouge/vert/refactorisation pour chaque règle métier. Tests Pest ciblés :
participant/tiers, confirmation/annulation, défaut du blocage et exception admin,
doublons simultanés, consultation après clôture/blocage/masquage, messages futurs,
substitution d'identifiant, lecture sans modification des curseurs, sanctions
et tous les parcours d'authentification, réinscription, sessions et temps réel,
cumul des rôles, anciens jobs de purge, cascades, refus de suppression,
suppression admin et export minimal.

Tests Pest Browser Chromium sur mobile et ordinateur : signalement et annulation,
choix de blocage, examen en lecture seule, clôture, sanction confirmée, navigation
au clavier et réacceptation. Contrôler les catalogues FR/EN et pages légales.

Après les tests ciblés, exécuter les contrôles PHP et frontend pertinents,
Wayfinder, build Vite et suite Pest complète. Le build Docker reste séparé.
Les migrations restent une action explicite et ne sont jamais ajoutées aux
points d'entrée Docker.
