# MVP V1 — Affinités et messagerie

## Inclus

### Compte

- Inscription et connexion par e-mail/mot de passe, avec vérification d'e-mail.
- Connexion simplifiée via Google et Apple.
- Date de naissance obligatoire ; l'inscription est refusée avant 18 ans.
- Après vérification de l'e-mail, un onboarding de profil est obligatoire avant l'accès à l'espace membre.
- Réinitialisation de mot de passe, limites de tentatives et gestion des sessions.

### Profil

- Nom d'affichage non unique, âge calculé depuis le compte, bio courte, fréquence de visite et visibilité. Aucun prénom n'est stocké.
- Image facultative : téléversement d'une photo personnelle ou sélection d'un avatar depuis un catalogue.
- Les images doivent être stockées, validées, redimensionnées et dépourvues de métadonnées EXIF.
- Sélection multiple d’intérêts actifs depuis un catalogue administrable. La limite par défaut est de cinq intérêts par profil ; un administrateur peut la régler entre 1 et 100.

### Intérêts

Le catalogue est stocké en base et gérable par l'administration : création, renommage, réordonnancement, archivage/réactivation et suppression des intérêts jamais utilisés. Les noms sont uniques après normalisation.

Les catégories constituent un regroupement technique interne (la catégorie initiale est « Général ») : elles ne sont ni affichées ni administrables dans le MVP. Il n’y a donc pas de gestion de catégories dans le périmètre implémenté.

Un intérêt archivé disparaît des sélecteurs de profil, des profils publics et du calcul des intérêts communs. Ses sélections sont suspendues, mais leur historique est conservé et elles ne consomment plus de capacité. Lors de sa réactivation, seules les sélections historiques des profils qui disposent encore de capacité au regard de la limite alors applicable sont restaurées ; les autres restent suspendues.

Un dashboard protégé par le rôle `admin` expose des agrégats de comptes, les inscriptions récentes et la gestion du catalogue d’intérêts, sans donner accès aux messages privés.

### Découverte et matching

- Cartes de profils à swiper : like ou refus.
- Le classement favorise le nombre d’intérêts communs, puis la fréquence de visite commune si cette préférence est activée dans l'algorithme.
- Les profils déjà évalués, bloqués, masqués ou indisponibles sont exclus.
- Un match existe uniquement quand les deux membres se sont likés.

### Messagerie et sécurité sociale minimale

- Une conversation privée est créée à chaque match.
- La messagerie V1 est textuelle uniquement : pas de pièce jointe, GIF, réaction, modification ou suppression de message.
- Un membre peut bloquer un autre membre. Le blocage retire les suggestions, interdit les nouveaux contacts et archive la conversation pour les deux membres.
- Aucun signalement ni back-office de modération n'est requis pour le MVP.

### Contrôle des données

- Modifier le profil et les intérêts.
- Masquer temporairement son profil des suggestions.
- Supprimer définitivement son compte et les données associées depuis les réglages.

### Interface

- Thèmes clair, sombre et système persistés, sur des surfaces neutres chaudes.
- Univers doux et magique : violet et rose comme couleurs principales, touches dorées limitées aux accents.
- Composants accessibles et responsive.

## Hors périmètre V1

- Compagnons pour une date de visite précise.
- Groupes, fil communautaire, collections publiques et événements.
- Recherche par ville, distance ou tranche d'âge.
- Limites quotidiennes, annulation de swipe et filtres avancés de découverte.
- Signalement de contenu, équipe de modération et outils de modération avancés.
- Paiement, abonnement ou publicité.
