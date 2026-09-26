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
| `seasonal_themes` | Plages administrables et activation manuelle des ambiances Halloween et Noël |
| `events` | Événement amical organisé par un membre, avec horaire, lieux, capacité, mode et annulation |
| `event_registrations` | Demande et état d’inscription d’un membre à un événement |
| `notifications` | Notification persistante catégorisée, localisée et reliée à une cible applicative |
| `blocks` | Blocage unidirectionnel entre deux membres |
| `avatars` | Catalogue administrable : nom, image privée, deux couleurs de dégradé, activation et ordre |
| `roles` / `user_roles` | Rôles cumulables `user`, `partner`, `admin`, distincts des profils |
| `partner_profiles` / `partner_profile_revisions` | Fiche partenaire, version publique et historique modéré bilingue |
| `partner_announcements` / `partner_announcement_metrics` | Contenu d'annonce et statistiques agrégées sans identité de destinataire |
| `partner_announcement_deliveries` | Historique individuel du destinataire avec instantané immuable du contenu reçu |
| `partner_notification_preferences` | Consentement explicite et révocable aux annonces partenaires |
| `partner_settings` | Délai global entre deux envois d’un même partenaire, 30 jours par défaut |
| `role_audits` | Trace minimale et temporaire des changements de rôle |

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
- `seasonal_themes.theme` est limité à `halloween` et `christmas`. Les bornes
  sont toutes deux nulles ou forment une plage ordonnée ; au plus une ligne est
  activée manuellement, et cette activation est prioritaire sur les plages.
- `blocks` est unique pour `(blocker_user_id, blocked_user_id)` et doit être vérifié dans chaque autorisation de conversation ou de matching.
- `events.registration_mode` vaut `automatic` ou `manual`; `cancelled_at` conserve l’événement annulé dans l’historique.
- `event_registrations` est unique pour `(event_id, user_id)`. Son état évolue entre `pending`, `accepted`, `refused`, `withdrawn`, `removed` et `blocked`. Seul `withdrawn` autorise une nouvelle inscription.
- L’organisateur compte dans `events.capacity` sans ligne d’inscription. Les transitions qui occupent une place verrouillent l’événement en base afin de ne jamais dépasser cette capacité.
- `partner_profiles.user_id` devient nul à la suppression du compte. Toute
  révision en attente est alors refusée et aucune révision ne peut être publiée
  sans propriétaire existant, actif et hors suppression.
- `partner_announcement_deliveries.partner_announcement_id` devient nul lorsque
  l'annonce source expire. `source_announcement_id`, le titre, le contenu et
  l'URL de destination sont figés à la préparation ; ils ne contiennent aucune
  identité d'expéditeur ou de destinataire. La livraison reste attachée au seul
  destinataire et disparaît avec son compte.
- Une révision de fiche encore publiée n'est jamais éligible à la purge de
  rétention. Les historiques non actifs, annonces terminales, métriques et
  audits expirent par lots de 500 lorsque `expires_at <= now()`.

## Règles essentielles

### Partenaires

- Une fiche appartient à un compte partenaire ; `published_revision_id` désigne
  la révision publique, distincte du brouillon mutable unique (`draft_key`).
  Les révisions passent de `draft` à `pending_approval`, puis `approved` ou
  `rejected`. Les champs FR/EN sont obligatoires et `position` règle l’ordre public.
- Les annonces évoluent entre `draft`, `pending_approval`, `approved`, `sending`,
  `sent`, `rejected` et `cancelled`. Le contenu soumis est figé ; la révision d’une
  annonce refusée crée une nouvelle annonce brouillon. `run_uuid` identifie le
  lancement et `audience_prepared_at` la fin de préparation.
- La paire annonce/destinataire est unique. Les livraisons sont `pending`,
  `delivered`, `failed` ou `skipped` ; une reprise ne recrée pas une notification
  livrée. Les horodatages de lecture, retrait et premier clic sont individuels,
  le compteur total de clics est cumulatif, le jeton de clic est opaque et unique.
- Une métrique unique par annonce stocke préparation, livraison, lecture,
  retrait, clics uniques et clics totaux. Les taux utilisent les livraisons
  effectives comme dénominateur, et valent zéro sans livraison.
- L’absence de préférence signifie refus. Le consentement est unique par
  utilisateur et revérifié au moment de la livraison.
- `role_audits` conserve acteur, cible, rôle, opération et date, sans modification
  après création. Les références de compte sont détachées à leur suppression ;
  les audits et historiques expirables ont une échéance de deux ans.

### Membres et relations sociales

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
- Un blocage transforme en `blocked` toute inscription active entre les deux membres. Une suppression organisateur annule puis purge ses événements ; une suppression participante purge ses inscriptions.

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

- Il n'y a ni limite quotidienne de swipes, ni annulation libre dans le MVP. Un
  `pass` peut uniquement être remplacé par un `like` depuis une surface membre
  qui propose cette action ; le `like` obtenu reste irréversible.
- Un profil passé ou liké n'est plus reproposé au même membre.
- La messagerie accepte uniquement du texte brut, limité à 2 000 caractères. Les pièces jointes, GIF, réactions, édition et suppression de message sont hors V1.
- Un membre ne peut lire ou envoyer un message que dans une conversation liée à son match et non affectée par un blocage.
- L’inscription donne initialement `user`. L’administration peut ensuite modifier
  `user` et `partner` avec confirmation ; `admin` reste géré par console. Le rôle
  partenaire ne confère aucun droit social ni administrateur implicite.
- Le rôle `admin` donne accès au dashboard, à la gestion des membres et aux catalogues d’intérêts et d’avatars. La gestion des membres agrège des compteurs directionnels sans charger le contenu des messages. Elle permet la suppression immédiate d’un membre et la création d’un échange privé avec lui, mais jamais ces actions sur un autre administrateur. La gestion des avatars reste accessible avant la complétion du profil afin de permettre l’ajout initial au catalogue. Ce rôle ne donne pas de droit de lecture des messages privés dans le MVP. Les catégories d’intérêts restent techniques et ne sont pas gérées dans cette interface.
