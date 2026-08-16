# Modèle métier et logique de matching

## Entités principales

| Entité | Rôle |
| --- | --- |
| `users` | Identité, authentification, date de naissance et statut de compte |
| `social_accounts` | Lien unique entre un utilisateur et Google ou Apple |
| `profiles` | Données publiques : nom d'affichage, bio, fréquence de visite, image et visibilité |
| `passion_categories` | Regroupements administrables de passions |
| `passions` | Entrées administrables du catalogue, activables/désactivables |
| `passion_profile` | Association multiple entre profil et passion |
| `swipes` | Décision d'un membre sur un autre : like ou refus |
| `matches` | Paire unique créée après deux likes |
| `conversations` | Conversation liée à un match |
| `messages` | Messages d'une conversation |
| `blocks` | Blocage unidirectionnel entre deux membres |
| `avatars` | Catalogue administrable d'avatars et métadonnées de droits |
| `roles` / `user_roles` | Attribution du rôle d'administration sans le mélanger aux profils membres |

## États et contraintes de stockage

- `users.status` vaut `active` ou `pending_deletion`. Un compte en suppression n'est jamais découvrable ni connectable.
- `users` contient l'identité de connexion et la date de naissance, mais aucun `username` ni `first_name`.
- `profiles.display_name` est obligatoire une fois l'onboarding terminé et n'est volontairement pas unique.
- `profiles.onboarding_completed_at` indique qu'un membre a terminé le profil minimal requis.
- `profiles.visibility` vaut `visible` ou `hidden`. Seul un profil `visible` appartenant à un compte `active` est découvrable.
- `profiles.image_type` vaut `upload`, `avatar` ou `null`. Une image téléversée est référencée par une clé de stockage, jamais par un chemin local public.
- `swipes.decision` vaut `like` ou `pass`; son unicité est `(actor_user_id, target_user_id)`.
- `matches` est unique pour une paire non ordonnée : stocker les deux identifiants dans un ordre canonique (`user_low_id < user_high_id`).
- `messages` porte un identifiant séquentiel, l'auteur, le contenu texte validé et les horodatages. Aucun champ de lecture n'est requis en V1.
- `blocks` est unique pour `(blocker_user_id, blocked_user_id)` et doit être vérifié dans chaque autorisation de conversation ou de matching.

## Règles essentielles

- Un profil appartient à un seul utilisateur.
- Le nom d'affichage public n'est pas unique : plusieurs membres peuvent choisir le même libellé.
- Un profil masqué ne peut pas être proposé à de nouveaux membres.
- Un utilisateur ne peut pas swiper son propre profil.
- Une paire de profils n'a qu'un swipe par sens, un match et une conversation au maximum.
- Un blocage est prioritaire sur un match ou une conversation existante.
- La suppression de compte doit anonymiser ou supprimer les données conformément à la politique de conservation définie dans `security-privacy.md`.

## Score de proposition V1

Le score est volontairement simple et explicable :

```text
score = nombre de passions communes
      + 0,25 si fréquence de visite identique
```

Les résultats sont triés par score décroissant. Le bonus de fréquence ne peut donc jamais faire passer un profil avec moins de passions communes devant un autre. À score égal, appliquer un tirage aléatoire contrôlé pour éviter de toujours favoriser les mêmes comptes. Ce score ne crée jamais un match : il détermine seulement l'ordre des profils présentés.

## Décisions V1 explicites

- Il n'y a ni limite quotidienne de swipes, ni annulation d'un swipe dans le MVP.
- Un profil passé ou liké n'est plus reproposé au même membre.
- La messagerie accepte uniquement du texte brut, limité à 2 000 caractères. Les pièces jointes, GIF, réactions, édition et suppression de message sont hors V1.
- Un membre ne peut lire ou envoyer un message que dans une conversation liée à son match et non affectée par un blocage.
- Chaque compte reçoit le rôle `user`; `admin` est un rôle additionnel attribué explicitement.
- Le rôle `admin` donne accès au dashboard et pourra gérer les catégories, passions et avatars; il ne donne pas de droit de lecture des messages privés dans le MVP.
