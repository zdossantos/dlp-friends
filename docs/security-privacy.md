# Sécurité, confidentialité et données personnelles

Ce document définit les exigences de sécurité et de confidentialité, qu’elles
soient déjà implémentées ou nécessaires avant la mise en production. Leur état
de livraison est suivi dans le [`PRD.md`](PRD.md).

## Majorité et accès

- La date de naissance est obligatoire à l'inscription.
- Refuser la création de compte si la personne a moins de 18 ans à cette date.
- La vérification d'e-mail est requise avant l'accès aux fonctionnalités sociales.
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
- Masquage : suspend les nouvelles suggestions sans supprimer le compte.
- Suppression cible : après confirmation explicite, le compte devient immédiatement inaccessible et invisible. Un job asynchrone doit supprimer les images, tokens sociaux, profil, swipes, matches, conversations et messages dans un délai maximal de 30 jours ; les sessions sont révoquées immédiatement. Le code actuel supprime directement le compte et ne livre pas encore cette purge différée.
- Documenter, avant mise en production, les durées de conservation et la politique de confidentialité applicable.
- Prévoir l'export des données de profil, intérêts, matches et messages dans les réglages. Cet export attendu au MVP n’est pas encore implémenté.
- Les sauvegardes ne sont pas modifiées rétroactivement lors d'une suppression ; leur durée de rétention doit être documentée et limitée.

## Autorisation et protection applicative

- Toute route sociale exige un utilisateur authentifié, e-mail vérifié, majeur et dont le statut est `active`.
- Les contrôleurs délèguent le contrôle d'accès aux Policies Laravel; ne jamais faire confiance à un identifiant de profil transmis par le navigateur.
- Les contenus texte sont validés, échappés à l'affichage et protégés contre l'injection HTML.
- Les cookies de session sont sécurisés en HTTPS et les protections CSRF natives de Laravel restent actives.
- Les canaux Reverb de conversation sont privés et leur autorisation vérifie l'appartenance au match ainsi que l'absence de blocage.

## Blocage

- Le blocage doit être disponible depuis un profil et une conversation.
- Il a effet immédiat sur suggestions, match et messagerie pour les deux membres; la conversation est archivée et aucun nouveau message n'est accepté.
- Ne pas informer l'autre membre de manière explicite qu'il a été bloqué.

## Différé

Le signalement de profils/messages, la console de modération et les processus d'équipe sont prévus en V2. Leur absence du MVP ne dispense pas de sécuriser les accès, les fichiers et la suppression de compte.
