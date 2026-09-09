# Sécurité, confidentialité et données personnelles

Ce document définit les exigences de sécurité et de confidentialité, qu’elles
soient déjà implémentées ou nécessaires avant la mise en production. Leur état
de livraison est suivi dans le [`PRD.md`](PRD.md).

## Publication légale

Zacharie Dos Santos, entrepreneur individuel (SIREN 104 531 819), est l’éditeur
et responsable du traitement. L’adresse publique de contact est fournie par
`LEGAL_CONTACT_EMAIL`; elle est obligatoire en production. Les CGU version
`2026-09-01` sont acceptées explicitement à l’inscription et la preuve conserve
uniquement l’utilisateur, la version et l’heure serveur.

La demande de suppression révoque immédiatement l’accès, puis retire les
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

- Réglages : édition des données visibles et des intérêts actifs, dans la limite configurée.
- Un intérêt archivé est retiré des sélections visibles et du matching. La sélection historique est conservée comme suspendue et ne consomme plus de capacité ; elle ne peut être restaurée à la réactivation que si le profil a alors une capacité disponible.
- Masquage : suspend les nouvelles suggestions et retire les conversations du
  profil masqué des listes et de leurs compteurs chez les autres membres, sans
  supprimer le compte. Il ne révoque pas l’accès direct, l’envoi de messages ou
  les notifications d’une conversation existante ; ces échanges réapparaissent
  dans les listes lorsque le profil redevient visible. Le blocage reste le
  mécanisme qui interdit réellement l’accès et la messagerie.
- Suppression : après confirmation explicite, le compte devient immédiatement inaccessible et invisible. Les sessions et liens sociaux sont révoqués immédiatement. Un job asynchrone gardé par le statut et l’horodatage supprime le profil, les intérêts, swipes, matches, conversations, messages et autres données liées 30 jours après la demande. Une tâche horaire redispatche les purges échues manquées ; les reprises sont idempotentes. Aucun parcours de restauration n’est proposé.
- Documenter, avant mise en production, les durées de conservation et la politique de confidentialité applicable.
- L’export JSON des données de compte, profil, intérêts, matches et messages est généré à la demande dans une réponse authentifiée téléchargée directement. Aucun fichier d’export n’est conservé côté serveur. Les messages exportés sont uniquement ceux envoyés par le membre dans les conversations visibles dans sa liste ; les profils masqués et toute relation bloquée dans un sens ou dans l’autre en sont exclus. Il exclut mots de passe, secrets, jetons et données inutiles sur les autres membres.
- Les sauvegardes ne sont pas modifiées rétroactivement lors d'une suppression ; leur rotation automatique est limitée à 30 jours.

## Autorisation et protection applicative

- Toute route sociale exige un utilisateur authentifié, e-mail vérifié, majeur et dont le statut est `active`.
- Les contrôleurs délèguent le contrôle d'accès aux Policies Laravel; ne jamais faire confiance à un identifiant de profil transmis par le navigateur.
- Les contenus texte sont validés, échappés à l'affichage et protégés contre l'injection HTML.
- Les cookies de session sont sécurisés en HTTPS et les protections CSRF natives de Laravel restent actives.
- La validation de l'état OAuth par Socialite reste obligatoire sur le callback Google.
- Aucun jeton d'accès, jeton de renouvellement ou contenu brut de réponse Google n'est stocké ou journalisé. Seul l'identifiant stable nécessaire au lien de compte est conservé.
- Les canaux Reverb de conversation sont privés et leur autorisation vérifie l'appartenance au match ainsi que l'absence de blocage.
- La gestion des membres est réservée au rôle `admin`. Ses statistiques ne
  contiennent aucun corps de message. La suppression d’un membre révoque ses
  sessions, supprime immédiatement ses données actives en cascade, puis met en
  file un e-mail localisé construit à partir d’un instantané minimal ; une
  panne d’envoi ne restaure pas le compte.
- Un administrateur ne peut ni supprimer un autre administrateur ni ouvrir un
  échange d’assistance avec lui.

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

Le bouton « Gérer les cookies » reste disponible sur chaque page. Il permet de
modifier le choix ou de retirer l’accord ; le retrait remplace le choix par un
refus, supprime les cookies GA4 accessibles au site et recharge le document afin
d’arrêter toute mesure suivante. Le Consent Mode v2 est utilisé en mode basic :
`analytics_storage` n’est accordé qu’après consentement et les finalités
publicitaires restent refusées.

## Blocage

- Le blocage doit être disponible depuis un profil et une conversation.
- Il a effet immédiat sur suggestions, match et messagerie pour les deux membres; la conversation est archivée et aucun nouveau message n'est accepté.
- Ne pas informer l'autre membre de manière explicite qu'il a été bloqué.
- Refuser côté serveur tout blocage visant un administrateur et masquer le
  contrôle correspondant dans l’interface.

## Différé

Le signalement de profils/messages, la console de modération et les processus d'équipe sont prévus en V2. Leur absence du MVP ne dispense pas de sécuriser les accès, les fichiers et la suppression de compte.
