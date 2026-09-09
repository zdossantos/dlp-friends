# Export et suppression différée des données personnelles

## Contexte et objectifs

Les issues 25 et 26 complètent le contrôle des données du membre. Le membre
doit pouvoir obtenir une copie portable de ses données, puis demander une
suppression qui coupe immédiatement tout accès avant une purge définitive au
plus tard trente jours après la demande.

Cette livraison couvre deux cycles de vie liés :

- produire, mettre à disposition et supprimer une archive JSON privée ;
- désactiver immédiatement un compte puis purger ses données de façon
  asynchrone, idempotente et rejouable.

La suppression administrative existante reste immédiate. La restauration d'un
compte en attente de purge, les exports CSV ou PDF et la modification
rétroactive des sauvegardes restent hors périmètre.

## Décisions structurantes

### Stockage des états

Une table `user_data_exports` conserve un enregistrement par demande avec
l'utilisateur propriétaire, un statut (`pending`, `processing`, `ready` ou
`failed`), le chemin privé éventuel, la date d'expiration et les horodatages.
L'enregistrement est supprimé avec son propriétaire. L'archive reste sur le
disque privé configuré pour les exports et n'est jamais exposée par une URL
publique. Le livrable est un fichier JSON UTF-8 unique, téléchargé avec une
extension `.json` ; aucun conteneur ZIP n'est ajouté.

La table `users` reçoit `deletion_requested_at`. Le statut existant
`pending_deletion` exprime l'inaccessibilité immédiate ; la date est la source
de vérité pour la planification et l'éligibilité à la purge. La purge est
prévue exactement trente jours après cette date.

### Jobs et planification

`ExportUserData` reçoit uniquement l'identifiant de la demande. Au démarrage,
il recharge la demande et son propriétaire, verrouille la transition de statut
et abandonne proprement si la demande, le membre ou l'archive n'est plus
éligible. Il construit le document à partir des relations appartenant au
membre, écrit l'archive sur le disque privé, puis la marque prête avec une
expiration. Une reprise remplace l'archive de cette demande sans en créer une
seconde.

`PurgeDeletedUser` reçoit uniquement l'identifiant du membre et la date de
demande attendue. Il ne purge que si le compte existe encore, porte toujours
le statut `pending_deletion`, possède la même date de demande et a atteint son
échéance. Ces gardes empêchent un job ancien ou rejoué de viser un autre état.
La suppression du compte exploite les cascades relationnelles existantes ; les
fichiers privés connus sont supprimés avant la ligne utilisateur. Une absence
de fichier ou de compte est un succès idempotent.

Une tâche planifiée quotidienne redispatche également les purges arrivées à
échéance. Elle constitue le mécanisme de reprise si le job différé initial a
été perdu. Une autre tâche supprime les archives expirées et les
enregistrements associés.

## Parcours d'export

La page des réglages de compte affiche l'état de la dernière demande : aucune
demande, préparation, disponible jusqu'à une date, échec ou expiration. Le
membre peut demander un export avec une limite raisonnable d'une demande par
vingt-quatre heures. Une demande encore active n'est jamais dupliquée.

La création passe par une route authentifiée soumise aux middlewares sociaux
existants et à un rate limiter serveur. Le téléchargement passe par une route
authentifiée et signée temporairement. Le contrôleur vérifie en plus que
l'archive appartient au membre connecté, qu'elle est prête, non expirée et
présente sur le disque privé. La réponse est un téléchargement, pas une
redirection vers le stockage.

Le JSON contient une version de format, la date de génération et les sections
suivantes :

- `account` : e-mail, locale, date de naissance, statut, dates de création et
  de mise à jour ;
- `profile` : nom d'affichage, bio, fréquence de visite, visibilité et avatar
  actif ;
- `interests` : identifiant, libellés français et anglais, et état actif de
  chaque intérêt sélectionné ;
- `matches` : identifiant du match, dates et identité publique minimale de
  l'autre membre ;
- `messages` : conversation, auteur identifié comme `self` ou `other`, contenu,
  état de lecture et dates.

Les mots de passe, secrets à deux facteurs, codes de récupération, jetons de
session ou de réinitialisation, identifiants OAuth stables, rôles internes,
adresses IP et agents utilisateurs sont exclus. Les messages inclus sont
uniquement ceux des conversations auxquelles le membre appartient. Les
données de l'autre membre sont limitées à ce qui rend le match ou le message
compréhensible ; son e-mail, sa date de naissance et ses autres données privées
ne sont jamais exportés.

Une nouvelle demande supprime l'archive expirée précédente du membre. Une
demande de suppression de compte supprime immédiatement toutes ses archives et
empêche tout export supplémentaire.

## Parcours de suppression

Le formulaire existant conserve la confirmation explicite par mot de passe.
Après validation, une action transactionnelle :

1. verrouille la ligne utilisateur et refuse un compte qui n'est plus actif ;
2. renseigne `pending_deletion` et `deletion_requested_at` ;
3. supprime toutes les sessions persistées du membre ;
4. supprime ses identités sociales afin d'empêcher leur réutilisation pour se
   connecter à ce compte ;
5. supprime ses archives d'export privées et leurs enregistrements ;
6. programme `PurgeDeletedUser` après trente jours.

Après la transaction, la session courante est invalidée et le membre revient
à l'accueil public. Le middleware social existant refuse déjà les comptes dont
le statut n'est pas actif. Les requêtes de découverte et les autres surfaces
membres continuent d'utiliser le statut actif comme frontière ; des tests
vérifient explicitement que le compte n'est plus connectable ni découvrable.

La purge définitive retire le compte et, par cascades, son profil, ses intérêts,
ses rôles, son tutoriel, ses preuves de consentement, ses passkeys, ses swipes,
ses blocages, ses matches, ses conversations et tous les messages associés.
La photo personnelle n'étant pas encore implémentée, aucun chemin de photo de
membre n'existe aujourd'hui ; l'action de purge centralise néanmoins la
suppression des fichiers privés connus afin que l'ajout futur de cette photo
dispose d'un point d'intégration explicite.

## Erreurs, concurrence et reprise

- La création d'export est transactionnelle et protégée par verrouillage afin
  que deux requêtes simultanées ne créent pas deux exports actifs.
- Un échec de génération marque la demande `failed`, supprime un éventuel
  fichier partiel et peut être retenté selon la politique Laravel du job.
- Une archive absente, expirée ou appartenant à un autre membre produit une
  réponse non révélatrice et ne divulgue aucun chemin de stockage.
- La demande de suppression est idempotente au niveau métier : une requête
  concurrente ne peut pas repousser l'échéance ni programmer une nouvelle date.
- La purge ne supprime jamais un utilisateur actif et vérifie l'identifiant et
  la date attendue avant tout effet.
- Une erreur de suppression de fichier fait échouer le job avant la suppression
  relationnelle afin qu'un retry puisse reprendre sans laisser un fichier
  privé orphelin.

## Interface et internationalisation

Les contrôles d'export et de suppression restent dans les réglages du compte,
avec composants accessibles, états occupés et messages français/anglais issus
des catalogues Laravel. Aucun texte utilisateur n'est écrit en dur. Le dialogue
de suppression explique que l'accès cesse immédiatement et que la purge est
définitive au plus tard sous trente jours ; il ne suggère aucune restauration.

## Tests

Le développement suit un cycle rouge, vert, refactorisation. Les tests Pest
couvrent au minimum :

- le contenu littéral des cinq catégories de l'export et l'identification des
  auteurs de messages ;
- l'absence de mots de passe, secrets, jetons et données privées de l'autre
  membre ;
- la propriété, l'authentification, la signature, l'expiration et la disparition
  physique du fichier ;
- la limitation et la non-duplication des demandes d'export ;
- le passage immédiat à `pending_deletion`, l'horodatage, la révocation de toutes
  les sessions et des identités sociales, et la programmation à trente jours ;
- le refus de connexion et l'exclusion de la découverte avant la purge ;
- la suppression de toutes les données relationnelles et des fichiers ;
- l'idempotence, la reprise par le scheduler et les gardes contre une purge
  prématurée ou visant un compte actif ;
- le maintien de la suppression administrative immédiate ;
- le rendu bilingue et les contrôles principaux du parcours dans les réglages.

Les contrôles ciblés sont suivis des analyses PHP, vérifications frontend, build
et tests complets concernés par les deux issues.

## Exploitation et documentation

Les workers exécutent la génération et la purge ; le scheduler quotidien
assure le nettoyage des archives expirées et la reprise des purges dues. Les
durées et disques sont configurables avec des valeurs par défaut documentées :
archive disponible vingt-quatre heures, nouvelle demande après vingt-quatre
heures, purge du compte après trente jours.

La politique de sauvegarde reste inchangée : la purge retire les données des
systèmes actifs, tandis que les sauvegardes quotidiennes chiffrées peuvent les
conserver jusqu'à leur rotation automatique, limitée à trente jours. Le PRD,
la documentation de sécurité, l'architecture technique et les opérations sont
mis à jour pour refléter le comportement réellement livré.
