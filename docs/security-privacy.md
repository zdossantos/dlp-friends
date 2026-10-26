# Sécurité, confidentialité et données personnelles

Ce document définit les exigences de sécurité et de confidentialité, qu’elles
soient déjà implémentées ou nécessaires avant la mise en production. Leur état
de livraison est suivi dans le [`PRD.md`](PRD.md).

## Publication légale

Zacharie Dos Santos, entrepreneur individuel (SIREN 104 531 819), est l’éditeur
et responsable du traitement. L’adresse publique de contact est fournie par
`LEGAL_CONTACT_EMAIL`; elle est obligatoire en production. Les CGU version
`2026-10-04` sont acceptées explicitement à l’inscription et la preuve conserve
uniquement l’utilisateur, la version et l’heure serveur.

La demande de suppression d’un compte actif révoque immédiatement l’accès, puis retire les
données des systèmes actifs après 30 jours. Elles peuvent ensuite subsister
dans les sauvegardes quotidiennes chiffrées MySQL et fichiers jusqu’à leur
expiration automatique, au plus 30 jours après leur création.

## Majorité et accès

- La date de naissance est obligatoire à l'inscription.
- Refuser la création de compte si la personne a moins de 18 ans à cette date.
- L'inscription par mot de passe exige le lien de vérification envoyé par l'application. Pour une nouvelle inscription Google, l'adresse déclarée vérifiée par le fournisseur tient lieu de cette vérification ; une identité sociale déjà liée utilise ensuite ce lien enregistré.
- Une adresse déjà associée à un compte n'est jamais reliée automatiquement à une nouvelle identité sociale.
- Limiter les tentatives de connexion et protéger les formulaires contre les abus usuels.

## Images

- Accepter uniquement les formats et tailles explicitement autorisés.
- Vérifier le contenu technique des fichiers côté serveur, limiter les dimensions et créer des variantes optimisées.
- Retirer les métadonnées EXIF avant stockage ou diffusion.
- Stocker les images dans le bucket MinIO privé, jamais dans le répertoire public de l'application, et les servir avec une URL contrôlée ou temporaire.
- Aucun avatar Disney ou asset non autorisé n'est livré. La validation préalable des images ajoutées au catalogue relève de l'administrateur et n'est pas matérialisée par des champs juridiques dans l'application.

## Données et contrôle utilisateur

L’historique des profils passés est personnel : liste, consultation et conversion
exigent un refus du membre connecté. Les profils masqués, incomplets,
indisponibles ou bloqués dans l’un des deux sens n’y sont pas exposés. La
conversion recontrôle disponibilité et blocages sous verrou côté serveur ;
aucun nouveau journal historique n’est conservé.

- Réglages : édition des données visibles et des intérêts actifs, dans la limite configurée.
- Un intérêt archivé est retiré des sélections visibles et du matching. La sélection historique est conservée comme suspendue et ne consomme plus de capacité ; elle ne peut être restaurée à la réactivation que si le profil a alors une capacité disponible.
- Masquage : suspend les nouvelles suggestions et retire les conversations du
  profil masqué des listes et de leurs compteurs chez les autres membres, sans
  supprimer le compte. Il ne révoque pas l’accès direct, l’envoi de messages ou
  les notifications d’une conversation existante ; ces échanges réapparaissent
  dans les listes lorsque le profil redevient visible. Le blocage reste le
  mécanisme qui interdit les nouveaux messages, réactions et signaux privés ;
  l’historique reste lisible par les deux participants via son URL directe.
- Suppression d’un compte actif : après confirmation explicite, le compte devient immédiatement inaccessible et invisible. Un compte disposant d’un mot de passe utilisable doit fournir son mot de passe actuel ; un compte créé exclusivement avec un fournisseur social doit accepter explicitement les conséquences irréversibles de la demande. Ce mode est déterminé côté serveur. Les sessions et tous les liens sociaux sont révoqués immédiatement. Un job asynchrone gardé par le statut et l’horodatage supprime le profil, les intérêts, swipes, matches, conversations, messages et autres données liées 30 jours après la demande. Une tâche horaire redispatche les purges échues manquées ; les reprises sont idempotentes. Aucun parcours de restauration n’est proposé.
- Documenter, avant mise en production, les durées de conservation et la politique de confidentialité applicable.
- L’export JSON des données de compte, profil, intérêts, matches et messages est généré à la demande dans une réponse authentifiée téléchargée directement. Aucun fichier d’export n’est conservé côté serveur. Les messages exportés sont tous ceux envoyés par le demandeur et encore conservés, indépendamment du masquage, du blocage ou du statut du pair. Aucun message purgé n’est rétabli et aucun message d’autrui n’est exporté. Il exclut mots de passe, secrets, jetons et données inutiles sur les autres membres.
- L’export inclut les événements organisés, les inscriptions du membre, les messages de discussion d’événement qu’il a lui-même envoyés et ses notifications persistantes, sans exposer les inscriptions privées ni les messages d’autrui. Les annonces système concernant le membre sont incluses avec leur type ; elles suivent les mêmes règles de purge et de conservation que ses messages, sans instantané supplémentaire de son nom.
- L'export inclut toutes les données partenaires propres au compte : consentement,
  historique de rôle ciblé, fiche et révisions possédées, annonces et agrégats,
  ainsi que les annonces qu'il a lui-même reçues et ses interactions. Il exclut
  les chemins de stockage, jetons de clic, identifiants de notification ou de
  destinataire, erreurs techniques, identité des acteurs et toute interaction
  individuelle d'un autre membre.
- La suppression d'un partenaire dépublie immédiatement sa fiche, refuse ses
  révisions en attente, annule ses annonces actives et neutralise les livraisons
  encore en attente. Sa purge retire ses préférences et ses propres livraisons,
  mais ne retire pas l'historique des autres destinataires. Celui-ci conserve
  seulement l'instantané public reçu, sans identité de compte expéditeur ni
  secret, jusqu'au propre cycle de suppression du destinataire.
- Les sauvegardes ne sont pas modifiées rétroactivement lors d'une suppression ; leur rotation automatique est limitée à 30 jours.

## Autorisation et protection applicative

- Toute route sociale exige un utilisateur authentifié, e-mail vérifié, majeur et dont le statut est `active`.
- Les contrôleurs délèguent le contrôle d'accès aux Policies Laravel; ne jamais faire confiance à un identifiant de profil transmis par le navigateur.
- Les contenus texte sont validés, échappés à l'affichage et protégés contre l'injection HTML.
- Les cookies de session sont sécurisés en HTTPS et les protections CSRF natives de Laravel restent actives.
- Le bannissement, sa levée et la demande de suppression incrémentent une version de session sur le compte dans leur transaction et renouvellent le jeton « se souvenir de moi ». Chaque connexion enregistre cette version ; les requêtes web rejettent les sessions d’une version antérieure, quel que soit le stockage utilisé. Une session antérieure à ce mécanisme appartient à la version initiale zéro : elle reste utilisable jusqu’à la première révocation du compte.
- La validation de l'état OAuth par Socialite reste obligatoire sur le callback Google.
- Une identité Google liée exige le challenge Fortify lorsque la 2FA locale est
  activée, avant connexion complète ou divulgation d’un bannissement. Les
  passkeys conservent leur parcours WebAuthn distinct sans challenge TOTP ajouté.
- Tous les rôles actifs, majeurs, vérifiés et ayant accepté les conditions
  accèdent à leurs réglages personnels, sans profil social ni tutoriel.
  Suppression et changement de mot de passe conservent leurs confirmations.
- Aucun jeton d'accès, jeton de renouvellement ou contenu brut de réponse Google n'est stocké ou journalisé. Seul l'identifiant stable nécessaire au lien de compte est conservé.
- Les canaux Reverb de conversation sont privés et leur autorisation vérifie l'appartenance au match ainsi que l'absence de blocage.
- Les pages événements exigent le même accès membre protégé. Le lieu précis et la liste des participants ne sont transmis qu’à l’organisateur et aux inscriptions acceptées ; une demande en attente ou refusée ne reçoit que le lieu général.
- Les discussions d’événement et leurs canaux Reverb privés appliquent la même autorisation. Le curseur de lecture reste individuel et aucun accusé de lecture nominatif n’est diffusé.
- Les notifications persistantes contiennent une clé de traduction, des paramètres minimaux et une cible interne. Elles ne recopient ni message privé ni lieu précis et leur route cible est résolue côté serveur.
- Les alertes de nouveaux membres ne stockent que leur identifiant cible,
  leur catégorie et une clé de traduction sans paramètres personnels. Le Push
  utilise un titre et un corps génériques. L’accès à la fiche est revérifié
  côté serveur ; suppression, inactivation ou retrait du rôle admin ne donnent
  aucun accès via l’ancienne alerte. Le réglage individuel figure dans l’export
  et disparaît avec le compte ; le désactiver préserve les alertes déjà reçues.
- La présence est facultative et visible uniquement par les interlocuteurs
  encore autorisés. Redis conserve seulement un état temporaire avec expiration,
  `last_active_at` est limité en fréquence et seule une activité relative est
  affichée. Les signaux de saisie ne contiennent jamais le brouillon et ne sont
  ni persistés ni journalisés.
- La gestion des membres est réservée au rôle `admin`. Ses statistiques ne
  contiennent aucun corps de message. La suppression d’un membre révoque ses
  sessions, supprime immédiatement ses données actives en cascade, puis met en
  file un e-mail localisé construit à partir d’un instantané minimal ; une
  panne d’envoi ne restaure pas le compte.
- Un administrateur ne peut ni supprimer un autre administrateur ni ouvrir un
  échange d’assistance avec lui.

## Partenaires : accès, consentement et conservation

- Les routes partenaires exigent le rôle `partner`, un compte actif, majeur et
  vérifié, mais pas le profil social ni son tutoriel. Les routes sociales exigent
  `user` et leurs prérequis existants ; l’administration exige `admin`.
  Les Policies et Actions vérifient propriétaire, rôle et transition côté serveur.
  Après authentification, la redirection est calculée avec les mêmes rôles afin
  qu’un compte partenaire ou admin sans `user` ne soit pas envoyé vers `/app`.
- Les modifications de rôles exigent une confirmation et créent un audit minimal
  immuable. Le retrait de `partner` bloque immédiatement les routes privées
  partenaires ; l’UI ne peut pas accorder `admin`. La dépublication est une
  action administrative distincte (elle est automatique à la suppression du compte).
- Les images de fiche sont JPEG, PNG ou WebP, de 640 × 360 à 6000 × 6000 pixels,
  au plus 5 Mo. Le serveur les réencode sans métadonnées et les garde privées.
  Une route contrôlée ne rend public que le fichier de la révision publiée ; les
  brouillons restent réservés au propriétaire et aux administrateurs.
- Les liens d’annonce exigent HTTPS et refusent notamment identifiants intégrés,
  hôtes locaux et adresses IP non publiques. Aucune récupération distante ni
  prévisualisation serveur de l’URL n’est effectuée.
- La préférence partenaire est indépendante du consentement analytique. Elle est
  active en l’absence de choix, révocable dans les réglages et vérifiée à chaque livraison,
  avec l’éligibilité actuelle du membre. Pas de ciblage ni d’accès aux identités
  des destinataires dans les vues statistiques, seulement des agrégats.
- Le lien de clic utilise un jeton opaque propre à la livraison. Lecture/retrait
  restent authentifiés et réservés au destinataire ; le lien opaque permet la
  redirection et le comptage sans exposer d’identifiant utilisateur dans l’URL.
- Les données historiques expirables (révisions non actives, annonces terminales,
  agrégats et audits) sont purgées après deux ans. Une version actuellement
  publiée n’expire pas. Les annonces reçues sont des instantanés distincts : elles
  restent dans l’historique du destinataire après suppression de l’expéditeur ou
  expiration de la source, jusqu’au cycle de suppression du destinataire.
  Le centre présente le contenu de cet instantané par interpolation texte
  échappée ; il n’interprète aucun HTML et n’ajoute aucune donnée personnelle.

## Mesure d’audience

Lorsque `GOOGLE_ANALYTICS_ID` est défini, Google Analytics 4 mesure les pages
vues sur les surfaces publiques et privées. Les chemins dynamiques sont
normalisés avant envoi et les paramètres de requête sont supprimés. Ne jamais
envoyer à GA4 un nom, une adresse e-mail, un identifiant de membre, une bio, un
message ou toute autre donnée permettant d’identifier directement une
personne.

Le tag Google reste totalement bloqué avant acceptation : aucun appel, ping ou
événement n’est envoyé à Google en l’absence d’accord. L’acceptation et le refus
sont proposés au même niveau, puis mémorisés six mois dans le cookie fonctionnel
`analytics_consent`. Un refus ne modifie aucune fonction essentielle.

Avant le choix, le bouton « Gérer les cookies » accompagne la bannière. Après
acceptation ou refus, il disparaît des pages et reste accessible depuis le profil
du membre. Il permet de modifier le choix ou de retirer l’accord ; le retrait
remplace le choix par un refus, supprime les cookies GA4 accessibles au site et
recharge le document afin d’arrêter toute mesure suivante. Le Consent Mode v2 est utilisé en mode basic :
`analytics_storage` n’est accordé qu’après consentement et les finalités
publicitaires restent refusées.

## Blocage

- Le blocage doit être disponible depuis un profil et une conversation.
- Il a effet immédiat sur suggestions, match et messagerie pour les deux membres; la conversation est archivée et aucun nouveau message n'est accepté.
- Ne pas informer l'autre membre de manière explicite qu'il a été bloqué.
- Refuser côté serveur tout blocage visant un administrateur et masquer le
  contrôle correspondant dans l’interface.

## Différé

Le signalement de profils et les processus d’équipe avancés restent prévus en V2.
Le signalement des échanges privés, leur revue et les sanctions sont livrés.
La modération des fiches et annonces partenaires conserve son parcours distinct.

### Disneyland Pass et Cast Member

Ces informations sont facultatives et autodéclarées, sans justificatif collecté.
Le membre peut les modifier ou les retirer ; elles ne donnent aucun rôle ni droit.
Le serveur valide le type exact de pass et le booléen Cast Member, puis met à jour
uniquement le profil du membre authentifié. Un ancien client qui omet ces champs
préserve les choix courants. Les réponses de profils détaillés et de découverte autorisés incluent
ces statuts, sauf en cas de blocage dans un sens ou l’autre. Les profils masqués
ou indisponibles ne deviennent pas accessibles via ces informations.
L’export et la purge suivent le profil.

### Liens externes du profil

Les liens saisis par le membre sont distincts des identités OAuth de connexion.
Le contrôle Laravel retire complètement `social_links` des réponses publiques
non autorisées : visibilité cachée, absence de match avec le réglage par défaut,
blocage dans un sens ou l’autre, profil indisponible. Le propriétaire conserve
l’accès à ses propres liens. Les cartes de découverte et listes de participants
n’exposent jamais ces données.

La validation impose HTTPS et les domaines exacts du réseau sélectionné, refuse
les identifiants embarqués, domaines trompeurs et ports non standard. Aucun appel
aux services externes, aperçu distant ou widget n’est effectué. Les liens utilisent
`target="_blank"` et `rel="noopener noreferrer"` avec une indication accessible.
Le choix « Tous les membres » avertit du contact hors application avant un match.
Les liens et leur visibilité figurent dans l’export personnel ; le profil devient
inaccessible à la demande de suppression et est supprimé avec eux lors de la purge.

## Échanges signalés et comptes bannis

La confirmation préalable informe l’auteur que les administrateurs actifs
peuvent lire l’intégralité de l’échange et ses futurs messages, même après
clôture, blocage, masquage ou bannissement. Le rapport ne duplique aucun message ;
chaque consultation et clôture produit un audit réservé à l’administration.
L’historique reçu depuis la fiche administrative ou le profil ordinaire est
également réservé aux administrateurs actifs, avec contrôle Laravel. Son accès
n’est pas transmis aux autres membres et ses listes ne contiennent aucun message
privé. L’auteur du signalement reste visible uniquement dans l’administration.
Le blocage reste facultatif, présélectionné sauf pour une cible administratrice.

`banned` est un état sans expiration. La sanction n’est révélée qu’après
vérification complète d’identité, y compris le second facteur. Sessions, remember,
tokens de réinitialisation, Push, présence et canaux privés sont révoqués.
Chaque abonnement Reverb exige une identité signée et un compte actif ; chaque
diffusion revérifie les destinataires même si la terminaison HTTP échoue.
Aucun nouveau journal technique n’expose message, motif libre, identité ou secret.

Les données d’un compte actuellement banni ne sont soumises à aucune purge
automatique, y compris l’historique partenaire et les audits de rôles. Une purge
de compte déjà planifiée recontrôle le statut sous verrou. La suppression d’un
autre compte conserve les relations nécessaires au compte banni avec extrémités
nulles, retire les messages du compte supprimé et garde les réactions bannies
détachées de ces messages. Les événements nécessaires sont conservés annulés
sans organisateur ; leur accès privé reste fermé. La suppression administrative
explicite reste effective, y compris sur un compte banni.

La demande de suppression en application est refusée aux comptes bannis par
la route et l’Action métier. Cette règle décrit le logiciel ; elle ne modifie
pas les droits légaux ni le contact public. Les délais usuels de 30 jours et
deux ans restent applicables aux comptes non bannis. La rotation des sauvegardes
ne change pas. L’export contient les rapports soumis par le membre et ses
sanctions, sans identité des autres auteurs, cibles ou décideurs, ni messages
d’autrui. La version des CGU `2026-10-04` doit être réacceptée explicitement par
tous les rôles avant l’accès privé ; déconnexion et textes publics restent ouverts.

## Préférences privées de découverte (issue 271)

Les deux bornes d’âge et le minimum d’univers communs sont enregistrés sur le
compte et accessibles seulement à leur propriétaire dans ses paramètres et
son export. Aucune préférence ne figure dans les cartes publiques. Elles sont
supprimées avec le compte lors de sa purge. Leur modification requiert un
membre actif, majeur, vérifié et autorisé ; les exclusions de sécurité et les
blocages dans les deux sens restent prioritaires sur ces filtres.

## Confidentialité et conservation des visites (issue 305)

Les visites sont privées par défaut. L’accord de partage avec tous les membres
est demandé à chaque transition effective depuis une audience plus restreinte ;
un formulaire ancien ne contourne pas ce contrôle serveur. Modifier un séjour
ne modifie pas son audience. Les compteurs, aperçus, recherches et pages de
participants utilisent les mêmes autorisations avant de produire les résultats.
La réponse n’expose jamais les identifiants de séjours des autres membres.

Un compte actif, vérifié, majeur, au profil complet et au rôle membre est requis.
Le masquage du propriétaire, l’inactivité d’un avatar, la perte du match ou un
blocage dans l’un des deux sens retirent l’accès correspondant dès la requête
suivante. Le propriétaire garde ses propres visites même si son profil est
masqué. Un membre privé recherché produit une liste vide sans révéler ses dates.

L’export personnel comprend `visits_visibility` et tous les séjours encore
stockés du seul propriétaire, avec bornes et horodatages. La demande de
suppression retire immédiatement l’accès social ; la purge du compte et la
suppression administrative retirent ses séjours par cascade. Les séjours
terminés disparaissent immédiatement de l’interface et sont supprimés de la
base active après 30 jours calendaires à Paris. Les comptes bannis gardent le
gel de toutes les purges automatiques ; une suppression explicite reste
effective. La rotation et les délais existants des sauvegardes restent
applicables : la purge de la base active ne réécrit pas les sauvegardes.

## Confidentialité et conservation des trajets (issue 304)

Les Policies et Actions vérifient comptes éligibles et blocages avant de
transmettre les informations. L’audience publique ne donne accès ni au
rendez-vous précis, ni aux participants, ni au chat. Un ancien participant
conserve uniquement ses propres décisions et motifs. Le blocage d’un autre
participant accepté provoque la sortie de celui qui bloque après confirmation ;
un blocage avec l’organisateur retire le participant. Le déblocage ne réinscrit
personne. Le masquage ne se confond pas avec le bannissement.

Messages et réactions sont diffusés sur les canaux personnels, avec droits
recalculés à la livraison, y compris pour Broadcast, notifications persistantes
et Push différés. Aucun motif libre ou rendez-vous précis n’est transmis dans
les alertes de gestion. Le dossier de désistement tardif ne donne jamais un
droit de lecture du chat à l’administration. Consultation et clôture sont auditées.

`trips:purge-expired`, à 04:00 Europe/Paris, supprime le trajet et sa discussion
trente jours après le départ, ou après l’annulation si elle précède le départ.
Le dossier de modération est détaché et garde son seul contenu minimal.
Les comptes liés sont verrouillés avant suppression ; un bannissement concurrent
est recontrôlé par une lecture actuelle verrouillée. La suppression d’un autre
compte préserve les relations nécessaires aux données bannies en détachant les
références du compte supprimé. Le gel prime sur la purge automatique.

L’export en mémoire comprend les trajets organisés, inscriptions, sorties,
messages propres et rapports soumis autorisés. Il exclut les messages et
participations privés d’autrui, l’auteur d’un signalement reçu et les décideurs.
Aucun nouveau fichier d’export persistant n’est ajouté.


## Annonces de l’application

La rédaction, l’aperçu, les destinataires et les reprises sont réservés aux
administrateurs actifs et vérifiés. L’audience globale comprend tous les rôles,
mais exclut les comptes non vérifiés, inactifs, en suppression et ceux ayant
désactivé `application_announcements`. Ces conditions sont revérifiées avant
livraison et avant le Push optionnel. Les préférences sont activées par défaut.

Le contenu FR/EN est du texte brut échappé dans Vue. Le lien facultatif utilise
la validation HTTPS existante (sans identifiants, hôtes locaux ni IP privées).
Une nouvelle version utilise uniquement le changelog localisé. Le Push reste
générique et ouvre le centre de notifications adapté au rôle du destinataire.

Les notifications et leur export individuel ne révèlent pas les autres
utilisateurs ciblés. L’export inclut l’historique de livraison propre au compte,
y compris après suppression individuelle d’une notification.
Les livraisons sont purgées avec le destinataire, l’auteur
est anonymisé par la clé étrangère et la suppression d’une notification reçue
ne permet pas de la recréer lors d’une reprise. Aucun message privé ni donnée
de destinataire n’est écrit dans les erreurs des jobs.
