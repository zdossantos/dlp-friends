# Modèle métier et logique de matching

Ce document décrit le modèle réellement implémenté. Le périmètre fonctionnel
et son état de livraison sont définis dans le [`PRD.md`](PRD.md).

## Entités principales

| Entité | Rôle |
| --- | --- |
| `users` | Identité, authentification, date de naissance et statut de compte |
| `social_accounts` | Lien unique entre un utilisateur et Google |
| `profiles` | Données publiques : nom d'affichage, bio, fréquence de visite, image et visibilité |
| `interest_categories` | Regroupement technique interne des intérêts ; non administrable dans le MVP |
| `interests` | Entrées administrables du catalogue, actives ou archivées |
| `interest_profile` | Association multiple entre profil et intérêt, avec l’état de sélection courant ou suspendu |
| `interest_settings` | Réglage unique de la limite de sélections, à 5 par défaut et configurable de 1 à 100 |
| `swipes` | Décision d'un membre sur un autre : like ou refus |
| `matches` | Paire unique créée après deux likes |
| `conversations` | Conversation liée à un match |
| `messages` | Messages d'une conversation |
| `blocks` | Blocage unidirectionnel entre deux membres |
| `avatars` | Catalogue administrable : nom, image privée, deux couleurs de dégradé, activation et ordre |
| `roles` / `user_roles` | Attribution du rôle d'administration sans le mélanger aux profils membres |

## États et contraintes de stockage

- `users.status` vaut `active` ou `pending_deletion`. Un compte en suppression n'est jamais découvrable ni connectable.
- `users` contient l'identité de connexion et la date de naissance, mais aucun `username` ni `first_name`.
- `social_accounts` contient `user_id`, `provider` et `provider_user_id`. La paire `(provider, provider_user_id)` est unique, le lien est supprimé en cascade avec l'utilisateur et aucun jeton OAuth n'est conservé.
- `profiles.display_name` est obligatoire une fois l'onboarding terminé et n'est volontairement pas unique.
- `profiles.onboarding_completed_at` indique qu'un membre a terminé le profil minimal requis.
- `profiles.avatar_id` référence l'avatar choisi. Le profil n'est complet que si cette référence désigne un avatar actif.
- `profiles.visibility` vaut `visible` ou `hidden`. Seul un profil `visible` appartenant à un compte `active` est découvrable.
- `avatars.image_path` référence un fichier du stockage privé. `primary_color` et `secondary_color` sont des couleurs hexadécimales utilisées pour générer le fond dégradé à l'affichage.
- `interest_categories` sert uniquement à rattacher techniquement les intérêts ; aucune gestion de catégories n’est exposée dans le MVP.
- `interests.is_active` distingue un intérêt actif d’un intérêt archivé. Un intérêt archivé n’est pas proposé dans les sélecteurs, n’est pas affiché dans les profils publics et ne participe pas au matching.
- `interest_profile.is_selected` vaut `true` pour une sélection active et `false` pour une sélection suspendue conservée dans l’historique. Les sélections suspendues ne consomment pas la limite.
- `interest_settings.max_selections` est initialisé à 5 et doit rester compris entre 1 et 100. Réduire cette limite ne supprime pas les sélections existantes ; elle s’applique aux nouvelles sélections et aux restaurations.
- `swipes.decision` vaut `like` ou `pass`; son unicité est `(actor_user_id, target_user_id)`.
- `matches` est unique pour une paire non ordonnée : stocker les deux identifiants dans un ordre canonique (`user_low_id < user_high_id`).
- `messages` porte un identifiant séquentiel, l'auteur, le contenu texte validé,
  `read_at` pour l’état de lecture et les horodatages.
- `blocks` est unique pour `(blocker_user_id, blocked_user_id)` et doit être vérifié dans chaque autorisation de conversation ou de matching.

## Règles essentielles

- Un profil appartient à un seul utilisateur.
- Un profil complet doit sélectionner un avatar actif. Archiver cet avatar conserve la sélection mais rend le profil incomplet jusqu'à sa réactivation ou son remplacement.
- Le nom d'affichage public n'est pas unique : plusieurs membres peuvent choisir le même libellé.
- Un profil masqué ne peut pas être proposé à de nouveaux membres.
- Un profil ne peut sélectionner que des intérêts actifs, dans la limite configurée.
- Archiver un intérêt suspend toutes ses sélections actives sans supprimer leur historique et libère immédiatement la capacité correspondante pour chaque profil.
- Réactiver un intérêt restaure ses sélections historiques dans l’ordre des profils uniquement lorsque la capacité est disponible au regard de la limite courante ; les sélections sans capacité restent suspendues.
- Un intérêt actif ayant déjà été utilisé ne peut pas être supprimé. Après archivage, sa suppression est autorisée et retire en cascade toutes ses associations historiques.
- Un utilisateur ne peut pas swiper son propre profil.
- Une paire de profils n'a qu'un swipe par sens, un match et une conversation au maximum. L’échange d’assistance initié par un administrateur réutilise cette même paire et peut créer directement le match et la conversation sans swipe artificiel.
- Un blocage est prioritaire sur un match ou une conversation existante.
- Un administrateur ne peut pas être la cible d’un blocage.
- La suppression de compte doit anonymiser ou supprimer les données conformément à la politique de conservation définie dans [`security-privacy.md`](security-privacy.md).

## Score de proposition V1

Le score est volontairement simple et explicable :

```text
score = nombre d’intérêts communs
      + 0,25 si fréquence de visite identique
```

Les résultats sont triés par score décroissant. Le bonus de fréquence ne peut donc jamais faire passer un profil avec moins d’intérêts communs devant un autre. À score égal, appliquer un tirage aléatoire contrôlé pour éviter de toujours favoriser les mêmes comptes. Les intérêts archivés sont exclus de ce score. Ce score ne crée jamais un match : il détermine seulement l'ordre des profils présentés.

Les pages publiques `/fr/matching` et `/en/matching` traduisent ces règles en
langage courant. Toute évolution de l’éligibilité, de l’ordre de classement, du
bonus ou de la réciprocité doit mettre à jour ces pages et leurs tests dans le
même changement.

## Décisions V1 explicites

- Il n'y a ni limite quotidienne de swipes, ni annulation d'un swipe dans le MVP.
- Un profil passé ou liké n'est plus reproposé au même membre.
- La messagerie accepte uniquement du texte brut, limité à 2 000 caractères. Les pièces jointes, GIF, réactions, édition et suppression de message sont hors V1.
- Un membre ne peut lire ou envoyer un message que dans une conversation liée à son match et non affectée par un blocage.
- Chaque compte reçoit le rôle `user`; `admin` est un rôle additionnel attribué explicitement.
- Le rôle `admin` donne accès au dashboard, à la gestion des membres et aux catalogues d’intérêts et d’avatars. La gestion des membres agrège des compteurs directionnels sans charger le contenu des messages. Elle permet la suppression immédiate d’un membre et la création d’un échange privé avec lui, mais jamais ces actions sur un autre administrateur. La gestion des avatars reste accessible avant la complétion du profil afin de permettre l’ajout initial au catalogue. Ce rôle ne donne pas de droit de lecture des messages privés dans le MVP. Les catégories d’intérêts restent techniques et ne sont pas gérées dans cette interface.
