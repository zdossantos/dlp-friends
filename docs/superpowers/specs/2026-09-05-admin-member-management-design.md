# Administration des membres et identification des administrateurs

## Contexte

L'issue #122 ajoute un tableau d'administration des membres, leurs statistiques
d'usage et leur suppression. Les décisions produit prises pendant la conception
élargissent ce périmètre à l'ouverture d'une conversation classique entre un
administrateur et un membre, à la protection des comptes administrateurs contre
le blocage et la suppression, ainsi qu'à leur identification visuelle sur les
cartes et fiches de profil.

Cette conception conserve la confidentialité des conversations : seuls leurs
deux participants accèdent à leur contenu. Le tableau d'administration expose
des agrégats, jamais le texte des messages privés.

## Périmètre fonctionnel

### Catalogue des membres

Une page protégée par le rôle administrateur liste les comptes par pages de 20.
La recherche, conservée lors de la pagination, porte sur le nom d'affichage et
l'adresse e-mail. Chaque ligne affiche :

- le nom d'affichage et l'adresse e-mail ;
- le rôle administrateur, le cas échéant ;
- le statut du compte, sa visibilité et les dates d'inscription et de
  vérification ;
- les likes envoyés et reçus ;
- les passes envoyées et reçues ;
- le nombre de matchs auxquels le compte participe ;
- le nombre de messages envoyés ;
- le nombre de personnes bloquées ;
- le nombre de fois où le compte a été bloqué.

Les compteurs sont calculés dans la requête paginée par des agrégats SQL. Ils ne
chargent ni les relations ligne par ligne ni le contenu des messages.

Les lignes d'un compte administrateur sont consultables, mais ne proposent ni
suppression ni ouverture de conversation administrative.

### Suppression immédiate

Un administrateur peut supprimer immédiatement un compte ordinaire après une
confirmation explicite dans l'interface. Annuler la confirmation ne déclenche
aucune requête et ne modifie aucune donnée.

La règle est appliquée côté serveur par une autorisation dédiée : aucun
administrateur ne peut supprimer un autre administrateur, et cette route ne
permet donc pas non plus l'auto-suppression d'un administrateur.

La suppression est transactionnelle. Elle révoque les sessions persistantes du
membre puis supprime immédiatement son compte et ses données actives liées par
les cascades prévues par le schéma. Elle ne met pas le compte en attente de
suppression. Les sauvegardes suivent leur politique de rétention séparée, avec
expiration sous 30 jours telle que documentée par le produit.

Avant la suppression, l'action capture uniquement les valeurs nécessaires à la
notification (adresse, langue et nom d'affichage). Après validation de la
transaction, elle programme une seule notification localisée en français ou en
anglais, sans conserver ni sérialiser le modèle supprimé. Une erreur de mise en
file ou d'envoi est signalée techniquement, mais ne restaure jamais le compte et
ne relance pas l'action de suppression.

Le message informe le membre que l'administration a supprimé son compte, que
l'accès est révoqué et que les données ont été retirées des systèmes actifs. Il
indique le canal de contact en cas d'erreur.

### Conversation initiée par un administrateur

Pour un membre ordinaire actif disposant d'un profil exploitable dans la
messagerie, l'administrateur peut créer ou ouvrir une conversation depuis le
tableau. Cette conversation utilise exactement la messagerie classique et reste
strictement limitée à l'administrateur qui a cliqué et au membre concerné. Un
autre administrateur forme une autre paire avec ce membre.

L'action crée, de manière transactionnelle et idempotente, le match canonique de
la paire puis sa conversation unique. Si la paire existe déjà, elle réutilise
les enregistrements existants. Une contrainte d'unicité et des insertions sûres
face à la concurrence empêchent les doublons.

Il s'agit d'une exception explicite à la règle habituelle du match par likes
réciproques. Aucun swipe artificiel n'est créé : les statistiques de likes et de
passes restent donc fidèles aux décisions réellement prises par les membres.
L'action est refusée si la paire est actuellement bloquée ou si la cible n'est
plus éligible au moment du verrouillage transactionnel.

Lorsqu'une conversation vient d'être créée, la page d'administration affiche le
composant existant de célébration de match avec le membre et son bouton vers la
conversation. Quand la conversation existait déjà, le bouton l'ouvre directement.

### Administrateurs impossibles à bloquer

Un membre ne peut pas créer de blocage visant un administrateur. La policy et
l'action métier appliquent cette règle côté serveur, y compris si une requête est
fabriquée manuellement. L'interface d'un profil administrateur ne montre ni le
bouton de blocage ni le bouton de déblocage.

Aucune migration de nettoyage des blocages existants n'est prévue, car le
produit confirme qu'il n'existe actuellement aucun blocage en production. Un
administrateur conserve la faculté de bloquer un membre ordinaire.

### Identification visuelle des administrateurs

Les vraies cartes et fiches de profil d'un administrateur sont reconnaissables
dans l'application par :

- une bordure dorée ;
- un badge « Administrateur » ou « Administrator » avec une icône, placé en
  haut à droite de la carte ou de la fiche.

Le badge rend l'information accessible sans dépendre uniquement de la couleur.
Il apparaît sur les cartes de découverte, sur la fiche publique d'un membre et
sur la propre fiche de profil d'un administrateur. Les profils de démonstration
du tutoriel restent ordinaires. Les lignes compactes et l'en-tête de la
messagerie ne sont pas modifiés.

## Architecture et autorisations

- Les routes de catalogue, suppression et conversation restent dans le groupe
  web authentifié, profil complété, onboarding complété et rôle administrateur.
- Une policy sur les comptes contrôle la consultation du catalogue, la
  suppression et l'ouverture de conversation. Les contrôleurs ne se reposent
  jamais sur les boutons masqués par Vue.
- Une action métier dédiée réalise la suppression transactionnelle et prépare
  la notification avec des valeurs scalaires.
- Une action métier dédiée crée ou retrouve le match et la conversation de la
  paire administrateur-membre sans passer par le service de swipe.
- Les données Inertia des profils et de la découverte exposent un booléen
  `is_admin`; les permissions de blocage restent calculées côté serveur.
- La page Vue du catalogue réutilise les primitives existantes de tableau,
  dialogue, pagination et le composant `MatchDialog`.
- Tous les textes visibles, e-mails, confirmations, retours de succès et erreurs
  sont ajoutés aux catalogues français et anglais.

## Cohérence, erreurs et confidentialité

- Une suppression refusée ne modifie rien et ne programme aucun e-mail.
- Après une suppression réussie, une erreur de notification ne rend pas le
  compte de nouveau accessible.
- Une cible devenue administratrice, inactive, incomplète ou bloquée entre
  l'affichage et le clic est refusée côté serveur.
- La liste administrative ne sélectionne jamais le corps des messages et ne
  permet pas d'ouvrir une conversation à laquelle l'administrateur n'appartient
  pas.
- Les conversations administratives suivent ensuite toutes les règles normales
  de la messagerie, notamment la participation, l'archivage et l'unicité par
  paire.

## Documentation à aligner

Le PRD, la documentation de sécurité et les documents spécialisés concernés
seront mis à jour pour distinguer clairement :

- la suppression administrative immédiate des données actives et la rétention
  technique limitée des sauvegardes ;
- le match social créé par likes réciproques et l'exception administrative
  explicite, sans faux swipes ;
- la capacité d'administrer des agrégats sans accès au contenu privé ;
- l'impossibilité de bloquer ou de supprimer un compte administrateur.

## Stratégie de test

Le développement suit un cycle rouge, vert, refactorisation. Les tests Pest et
Pest Browser couvrent au minimum :

- l'accès au catalogue réservé aux administrateurs ;
- la recherche, la pagination et les statistiques dans chaque direction ;
- l'absence de contenu de message dans les données Inertia ;
- le refus de supprimer un administrateur et l'absence d'effet en cas
  d'annulation ;
- la suppression immédiate, les cascades, la révocation des sessions et la
  notification localisée construite sans modèle supprimé ;
- la création idempotente d'un match et d'une conversation sans swipe, leur
  unicité et leurs deux seuls participants ;
- l'affichage du composant de match uniquement après une création et l'ouverture
  directe d'une conversation existante ;
- le refus de bloquer un administrateur au niveau HTTP et métier ;
- les propriétés Inertia et le badge administrateur sur les cartes de découverte
  et les fiches de profil ;
- les actions et confirmations principales du tableau dans Chromium.

Les contrôles ciblés sont suivis des vérifications PHP et frontend pertinentes :
formatage, analyse statique, types, lint, build et suite de tests complète.
