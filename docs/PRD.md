# PRD — DLP Friends

## Statut et usage du document

Ce document est la source de vérité du produit DLP Friends. Il définit la
vision, la cible du MVP, les règles métier, les critères de succès et les
évolutions envisagées. Il distingue volontairement le produit attendu de ce
qui est déjà livré.

La matrice d’implémentation est un instantané du dépôt au 30 août 2026. Elle
doit être mise à jour dès qu’une capacité change de statut. Les détails de
stockage, d’architecture, de sécurité et d’interface appartiennent aux
références spécialisées liées en fin de document.

## Vision et proposition de valeur

DLP Friends est une application de rencontres strictement **amicales** pour les
fans majeurs de Disneyland Paris et de ses univers associés. Elle aide des
personnes partageant des intérêts à se découvrir, à exprimer réciproquement
leur envie d’échanger, puis à discuter en privé.

Le produit évite de chercher des contacts dans des communautés généralistes :
il présente en priorité les profils ayant le plus d’intérêts en commun, tout en
laissant chaque membre contrôler sa visibilité et ses interactions.

DLP Friends est un projet indépendant, non affilié à Disney ni à Disneyland
Paris.

## Public et principes non négociables

- Le service est réservé aux personnes âgées de 18 ans ou plus.
- L’âge vérifie la majorité et apparaît sur le profil ; il ne sert pas à
  filtrer ou à classer les suggestions.
- Le swipe est une interaction de découverte amicale, jamais une mécanique de
  rencontre romantique.
- Un match exige deux likes réciproques, à l’exception explicite d’un échange
  d’assistance initié par un administrateur depuis la gestion des membres.
- La vie privée prime : aucune ville ou région n’est demandée, la photo
  personnelle n’est pas obligatoire et le membre garde le contrôle de son
  profil et de son compte.
- L’expérience est mobile first, accessible et disponible en français et en
  anglais.
- Aucun personnage, logo ou visuel Disney ne peut être livré sans droit
  d’utilisation démontré.

## Critères de succès du MVP

- Un nouveau membre majeur peut créer un compte vérifié, compléter son profil,
  sélectionner ses intérêts et comprendre l’application grâce au tutoriel.
- Les suggestions favorisent des affinités explicables sans critère
  géographique, romantique ou fondé sur l’âge.
- Deux likes réciproques créent un match et une conversation sans intervention
  manuelle.
- Les membres d’un match peuvent échanger des messages texte en temps réel.
- Un membre peut bloquer une personne et reprendre immédiatement le contrôle
  de son expérience.
- Un membre peut modifier ou masquer son profil et supprimer son compte.
- Le projet peut être déployé de manière reproductible sur Coolify et toutes
  les pull requests vers `main` sont contrôlées automatiquement.

## Cible fonctionnelle du MVP

### Compte et accès

- Inscription et connexion par e-mail et mot de passe, vérification de
  l’adresse, réinitialisation du mot de passe, protection contre les tentatives
  abusives et gestion des sessions.
- Date de naissance obligatoire et refus de l’inscription avant 18 ans.
- Connexion simplifiée via Google et Apple.
- Après vérification, profil obligatoire puis tutoriel produit obligatoire
  avant l’accès à l’espace membre.
- Le tutoriel enseigne successivement le refus d’une carte, le like, le match,
  puis l’ouverture et l’usage d’une conversation. Ses interactions restent
  locales et ne créent aucune donnée sociale.

### Profil, intérêts et représentation

- Nom d’affichage public non unique, âge calculé depuis le compte, bio courte,
  fréquence de visite et visibilité. Aucun prénom n’est stocké.
- Avatar actif obligatoire, choisi dans un catalogue administrable. Chaque
  avatar associe une image à un fond dégradé défini par deux couleurs.
- Photo personnelle facultative, distincte de l’avatar obligatoire. Les images
  sont validées, redimensionnées, privées et débarrassées de leurs métadonnées
  EXIF.
- Sélection multiple d’intérêts actifs. La limite par défaut est de cinq et un
  administrateur peut la régler entre 1 et 100.
- Les catégories d’intérêts restent un regroupement technique interne, ni
  visible ni administrable dans le MVP.
- Un intérêt archivé disparaît des sélecteurs, profils publics et calculs
  d’affinité. Son historique est suspendu sans consommer de capacité ; une
  réactivation ne restaure que les sélections disposant encore de capacité.

### Découverte et matching

- Les cartes de découverte permettent le like ou le refus.
- Le classement favorise d’abord le nombre d’intérêts actifs communs, puis la
  fréquence de visite commune lorsque ce bonus est activé.
- Les profils déjà évalués, bloqués, masqués, incomplets ou indisponibles sont
  exclus.
- Un profil passé ou liké n’est plus reproposé au même membre.
- Il n’existe ni limite quotidienne ni annulation de swipe dans le MVP.
- Le détail du score et les contraintes de paire sont définis dans
  [`data-model.md`](data-model.md).

### Messagerie et blocage

- Chaque match possède une conversation privée textuelle.
- Les messages sont limités à 2 000 caractères. Pièces jointes, GIF, réactions,
  édition et suppression de message sont hors MVP.
- Seuls les deux membres du match peuvent lire et envoyer des messages.
- Le blocage est disponible depuis un profil ou une conversation. Il retire les
  suggestions, interdit les nouveaux contacts et rend la conversation
  inaccessible aux deux membres, sans notifier explicitement la personne
  bloquée.
- Un administrateur ne peut pas être bloqué.
- Le signalement et les outils de modération ne font pas partie du MVP.

### Administration

- Le rôle `admin` donne accès à un tableau de bord et aux catalogues d’intérêts
  et d’avatars, jamais aux messages privés.
- Le catalogue d’intérêts permet création, traduction, renommage,
  réordonnancement, archivage, réactivation et suppression selon les règles
  d’usage historique.
- Le catalogue d’avatars permet création, modification, réordonnancement,
  archivage et suppression selon les contraintes de sélection.
- L’administration choisit deux avatars actifs et distincts pour le tutoriel,
  puis consulte ses statistiques et la progression des membres éligibles.
- La gestion des membres recherche par nom d’affichage ou e-mail et présente,
  par compte, les likes et refus envoyés/reçus, matches, messages envoyés,
  personnes bloquées et blocages reçus. Elle n’expose jamais le contenu des
  messages privés.
- Un administrateur peut supprimer immédiatement un compte membre après une
  confirmation explicite. Il ne peut jamais supprimer un autre administrateur.
- Un administrateur peut créer ou rouvrir un échange privé classique avec un
  membre. La paire reste unique, limitée à ces deux participants et ne crée
  aucun swipe artificiel. Le dialogue existant « Vos univers se croisent » est
  présenté lors de la première création.
- Les profils administrateurs sont identifiés dans l’application par le badge
  « Administrateur » et une bordure dorée sur leurs cartes et pages profil.

### Contrôle des données

- Le membre peut modifier son profil et ses intérêts actifs.
- Il peut masquer temporairement son profil des suggestions.
- Il peut demander la suppression définitive de son compte. L’accès et la
  visibilité cessent immédiatement ; la cible prévoit la purge asynchrone des
  données associées sous 30 jours.
- Il peut exporter ses données de profil, intérêts, matches et messages.
- Les exigences détaillées de suppression, conservation et sauvegarde sont
  définies dans [`security-privacy.md`](security-privacy.md).

### Langues et interface

- Le français est la langue par défaut et de repli ; l’anglais est également
  pris en charge.
- Les thèmes clair, sombre et système sont persistés.
- Les parcours sont responsive, utilisables au clavier et compréhensibles sans
  dépendre uniquement de la couleur.
- Le langage visuel et les règles de composants sont définis dans
  [`design-system.md`](design-system.md).

## État d’implémentation

| Capacité | Statut | État observé |
| --- | --- | --- |
| Inscription e-mail, vérification et récupération | **Implémenté** | Fortify, pages et tests couvrent le parcours. |
| Majorité, compte actif et contrôle d’accès social | **Implémenté** | Stockage, middlewares et tests sont présents. |
| Profil, avatar obligatoire et intérêts | **Implémenté** | Parcours membre et catalogues administrables sont livrés. |
| Découverte, swipes et match réciproque | **Implémenté** | Service de classement, actions et tests sont présents. |
| Conversations, messages temps réel et état de lecture | **Implémenté** | Stockage, diffusion privée, interfaces et tests sont présents. |
| Blocage et déblocage | **Implémenté** | Effet immédiat sur découverte et conversation. |
| Tutoriel produit obligatoire | **Implémenté** | Progression persistée et statistiques admin sont livrées. |
| Gestion administrative des membres | **Implémenté** | Recherche et compteurs, suppression confirmée, échange privé admin/membre et identification visuelle des admins sont livrés sans accès au contenu des messages. |
| Français et anglais | **Implémenté** | Résolution de locale et catalogues backend/frontend sont présents. |
| Univers éditorial | **Implémenté** | Tutoiement, vocabulaire canonique et catalogues par feature sont contrôlés automatiquement. |
| Thèmes clair, sombre et système | **Implémenté** | Préférence persistée et interface correspondante sont présentes. |
| Accueil public et référencement bilingue | **Implémenté** | Landing pages françaises et anglaises, métadonnées SEO, données structurées, sitemap public et exclusion des parcours privés sont livrés. |
| Explication publique du classement et du matching | **Implémenté** | Pages françaises et anglaises indexables, liées depuis l’accueil, décrivant l’éligibilité, les priorités, le bonus de fréquence, le départage et la réciprocité. |
| Mesure d’audience et suivi d’indexation | **Implémenté** | GA4 conditionnel couvre les pages publiques et les navigations Inertia avec chemins normalisés ; Search Console s’appuie sur une validation configurable, le sitemap et robots.txt. |
| Retours d’interaction et mouvement accessible | **Implémenté** | Décisions de carte optimistes avec rollback, célébration de match, états occupés, feedback de messagerie, navigation et réduction des animations sont couverts. |
| Connexion Google et Apple | **Planifié** | Aucun flux OAuth ou stockage de compte social n’existe. |
| Photo personnelle facultative | **Planifié** | Aucun flux de téléversement membre n’existe. |
| Export des données | **Planifié** | Aucun parcours d’export n’existe. |
| Suppression différée sous 30 jours | **Partiel** | La suppression de compte existe, mais elle est immédiate et sans job de purge différée. |
| Signalement et console de modération | **Planifié après le MVP** | Le blocage existe ; aucun signalement ou workflow de modération n’est livré. |

Les preuves détaillées de cet instantané sont consignées dans
[`documentation-inventory.md`](documentation-inventory.md).

## Hors périmètre du MVP

- Compagnons pour une date de visite précise.
- Groupes, fil communautaire, collections publiques et événements.
- Recherche par ville, distance ou tranche d’âge.
- Limites quotidiennes, annulation de swipe et filtres avancés.
- Signalement, équipe de modération et outils de modération avancés.
- Paiement, abonnement et publicité.

## Évolutions envisagées

### V2 — Compagnons de visite

Permettre de trouver des amis pour une prochaine visite sans transformer le
produit en réseau social généraliste : intention ou fenêtre de visite,
découverte de membres compatibles sur une période proche, contrôle fin de la
visibilité avant le match, puis signalement et traitement par une équipe de
modération.

### Après V2 — À évaluer sur usage réel

- Notifications de match et de nouveau message.
- Options de matching plus fines, sans critère romantique.
- Administration enrichie au-delà des catalogues et du tutoriel.
- Fonctions communautaires uniquement si un besoin réel est validé.

## Références spécialisées

- [`data-model.md`](data-model.md) : modèle métier, stockage et matching ;
- [`technical-architecture.md`](technical-architecture.md) : architecture
  réellement implémentée ;
- [`design-system.md`](design-system.md) : langage visuel, composants et
  accessibilité ;
- [`editorial-guidelines.md`](editorial-guidelines.md) : voix, vocabulaire et
  règles de rédaction ;
- [`security-privacy.md`](security-privacy.md) : sécurité, confidentialité et
  contrôle des données ;
- [`operations.md`](operations.md) : exploitation et fiabilité ;
- [`quality-ci-cd.md`](quality-ci-cd.md) : qualité, CI et livraison ;
- [`engineering-principles.md`](engineering-principles.md) : principes de
  développement.

## Pages légales publiques livrées

Les issues 42 et 43 sont implémentées par quatre documents SSR indexables :
`/fr/conditions-generales-utilisation`, `/en/terms-of-use`,
`/fr/politique-confidentialite` et `/en/privacy-policy`. L’inscription exige une
case non précochée et conserve la version `2026-09-01` avec l’heure serveur.
Cette livraison ne bloque pas les comptes existants et n’ajoute pas de parcours
de réacceptation.
