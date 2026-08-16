# MVP V1 — Affinités et messagerie

## Inclus

### Compte

- Inscription et connexion par e-mail/mot de passe, avec vérification d'e-mail.
- Connexion simplifiée via Google et Apple.
- Date de naissance obligatoire ; l'inscription est refusée avant 18 ans.
- Réinitialisation de mot de passe, limites de tentatives et gestion des sessions.

### Profil

- Prénom, pseudo, âge, bio courte et fréquence de visite.
- Image facultative : téléversement d'une photo personnelle ou sélection d'un avatar depuis un catalogue.
- Les images doivent être stockées, validées, redimensionnées et dépourvues de métadonnées EXIF.
- Sélection multiple de passions depuis un catalogue administrable.

### Passions

Le catalogue est stocké en base et gérable par l'administration. Exemples de catégories :

- Resort : attractions, spectacles, hôtels, restaurants, saisons et expériences.
- Univers fan : personnages, Disneybound, pins, Lorcana, collection, merchandising, jeux et autres centres d'intérêt associés.

Un espace d'administration protégé permet d'ajouter, modifier, désactiver et réordonner catégories, passions et avatars, sans redéploiement. Il ne donne aucun accès aux messages privés.

### Découverte et matching

- Cartes de profils à swiper : like ou refus.
- Le classement favorise le nombre de passions communes, puis la fréquence de visite commune si cette préférence est activée dans l'algorithme.
- Les profils déjà évalués, bloqués, masqués ou indisponibles sont exclus.
- Un match existe uniquement quand les deux membres se sont likés.

### Messagerie et sécurité sociale minimale

- Une conversation privée est créée à chaque match.
- La messagerie V1 est textuelle uniquement : pas de pièce jointe, GIF, réaction, modification ou suppression de message.
- Un membre peut bloquer un autre membre. Le blocage retire les suggestions, interdit les nouveaux contacts et archive la conversation pour les deux membres.
- Aucun signalement ni back-office de modération n'est requis pour le MVP.

### Contrôle des données

- Modifier le profil et les passions.
- Masquer temporairement son profil des suggestions.
- Supprimer définitivement son compte et les données associées depuis les réglages.

### Interface

- Thème clair par défaut sur fond blanc ; thème sombre facultatif.
- Univers doux et magique : violet et rose comme couleurs principales, touches dorées limitées aux accents.
- Composants accessibles et responsive.

## Hors périmètre V1

- Compagnons pour une date de visite précise.
- Groupes, fil communautaire, collections publiques et événements.
- Recherche par ville, distance ou tranche d'âge.
- Limites quotidiennes, annulation de swipe et filtres avancés de découverte.
- Signalement de contenu, équipe de modération et outils de modération avancés.
- Paiement, abonnement ou publicité.
