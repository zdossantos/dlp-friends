# Inventaire et migration de la documentation

## Usage

Cet inventaire décrit le corpus Markdown au début de la consolidation liée à
l’issue 89. Il sert de matrice de migration : une suppression n’est autorisée
que lorsque la destination de toute information encore applicable est
explicite.

Les documents sous `docs/superpowers/` sont des traces historiques utiles à la
compréhension des décisions passées. Ils ne sont pas normatifs, ne font pas
partie du parcours de lecture courant et restent strictement inchangés.

## Références racine et courantes

| Source | Audience | Rôle | Statut | Doublons ou liens entrants | Décision | Destination ou justification |
| --- | --- | --- | --- | --- | --- | --- |
| `AGENTS.md` | Agents de développement | Instructions opérationnelles du dépôt | Normatif | Aucun lien entrant explicite ; recoupe certains guides techniques | Conserver et raccourcir le parcours métier | `avatar.md`, `docs/PRD.md`, puis référence spécialisée |
| `CHANGELOG.md` | Utilisateurs et mainteneurs | Historique des versions publiées | Normatif pour les releases | Aucun lien entrant explicite | Conserver sans fusion | Alimenté par Release Please |
| `CONTRIBUTING.md` | Contributeurs | Workflow Git, contrôles et releases | Normatif | Lié depuis `README.md` ; recoupe `quality-ci-cd.md` | Conserver et remplacer les détails répétés par des liens | `AGENTS.md` et `docs/quality-ci-cd.md` |
| `README.md` | Nouveaux contributeurs | Installation et index documentaire | Normatif pour le démarrage | Lié depuis `CONTRIBUTING.md` | Conserver et actualiser l’index | Nouvelles références canoniques |
| `docs/data-model.md` | Développeurs backend | Entités, contraintes et matching | Normatif | Lié depuis `README.md` ; règles produit aussi présentes dans `mvp-v1.md` | Conserver et limiter au modèle | `docs/PRD.md` pour le contrat produit |
| `docs/documentation-consolidation-design.md` | Mainteneurs | Design validé de l’issue 89 | Historique de décision | Aucun lien entrant explicite | Conserver | Traçabilité de la consolidation |
| `docs/documentation-consolidation-plan.md` | Mainteneurs et agents | Plan d’exécution de l’issue 89 | Historique d’exécution | Aucun lien entrant explicite | Conserver | Traçabilité de la consolidation |
| `docs/documentation-inventory.md` | Mainteneurs | Inventaire et matrice de migration | Traçabilité ponctuelle | Aucun lien entrant avant migration | Conserver | Preuve des destinations et décisions de l’issue 89 |
| `docs/engineering-principles.md` | Développeurs et reviewers | Principes d’implémentation | Normatif | Lié depuis `README.md` ; repris partiellement dans `AGENTS.md` | Conserver et dédupliquer | `AGENTS.md` garde seulement les instructions opérationnelles |
| `docs/mvp-v1.md` | Produit et développement | Périmètre fonctionnel cible du MVP | Normatif avant migration | Lié depuis `README.md`, `AGENTS.md`, `roadmap.md` et `technical-architecture.md` | Fusionner puis supprimer | `docs/PRD.md`, avec détails dans les références spécialisées |
| `docs/operations.md` | Exploitants | Exploitation, sauvegardes et incidents | Normatif | Lié depuis `README.md` | Conserver | Procédures opérationnelles uniques |
| `docs/product-vision.md` | Produit et développement | Vision et principes produit | Normatif avant migration | Lié depuis `README.md` et `AGENTS.md` | Fusionner puis supprimer | `docs/PRD.md` |
| `docs/quality-ci-cd.md` | Contributeurs et exploitants | Branches, CI, déploiement et releases | Normatif | Lié depuis `README.md` et `CONTRIBUTING.md` | Conserver et dédupliquer | Source détaillée liée par les guides racine |
| `docs/roadmap.md` | Produit et développement | Évolutions après le MVP | Normatif avant migration | Lié depuis `README.md` et `technical-architecture.md` | Fusionner puis supprimer | `docs/PRD.md` |
| `docs/security-privacy.md` | Produit, sécurité et développement | Sécurité, confidentialité et contrôle des données | Normatif | Lié depuis `README.md` et référencé par `data-model.md` | Conserver et distinguer cible et existant | Exigences de sécurité ; statut de livraison dans `docs/PRD.md` |
| `docs/technical-architecture.md` | Développeurs et exploitants | Architecture technique actuelle | Normatif | Lié depuis `README.md` ; renvoie vers MVP, roadmap et ingénierie | Conserver et actualiser les liens | Architecture implémentée uniquement |
| `docs/ux-design.md` | Produit et frontend | Parcours et direction visuelle | Normatif avant migration | Lié depuis `README.md` ; recoupe `mvp-v1.md` | Fusionner puis supprimer | Parcours dans `docs/PRD.md`, règles visuelles dans `docs/design-system.md` |

## Documents historiques Superpowers

Chaque fichier ci-dessous est conservé sans modification. Il est consultable
pour la traçabilité, mais n’est pas une source de vérité courante. Aucun ne
possède de lien entrant explicite depuis la documentation de référence.

| Source | Audience | Rôle | Statut | Doublons ou liens entrants | Décision | Destination ou justification |
| --- | --- | --- | --- | --- | --- | --- |
| `docs/superpowers/plans/2026-08-16-adult-active-accounts.md` | Mainteneurs | Plan historique comptes adultes | Historique non normatif | Recoupe PRD, sécurité et code ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-16-laravel-bootstrap.md` | Mainteneurs | Plan historique bootstrap Laravel | Historique non normatif | Recoupe architecture et opérations ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-16-profile-onboarding-admin-dashboard.md` | Mainteneurs | Plan historique profil et administration | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-23-bun-migration.md` | Mainteneurs | Plan historique migration Bun | Historique non normatif | Recoupe architecture et qualité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-23-discovery-swipes-matches.md` | Mainteneurs | Plan historique découverte et matching | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-23-main-only-cicd-release.md` | Mainteneurs | Plan historique CI/CD | Historique non normatif | Recoupe qualité et contribution ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-23-mobile-first-member-shell.md` | Mainteneurs | Plan historique interface membre | Historique non normatif | Recoupe design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-24-interest-catalog.md` | Mainteneurs | Plan historique catalogue d’intérêts | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-25-avatar-first-profile-ux.md` | Mainteneurs | Plan historique avatar de profil | Historique non normatif | Recoupe PRD et design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-25-pest-browser-migration.md` | Mainteneurs | Plan historique Pest Browser | Historique non normatif | Recoupe qualité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-26-i18n-fr-en.md` | Mainteneurs | Plan historique internationalisation | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-26-realtime-messages.md` | Mainteneurs | Plan historique temps réel | Historique non normatif | Recoupe architecture et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-27-messaging-interface.md` | Mainteneurs | Plan historique interface de messagerie | Historique non normatif | Recoupe PRD et design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-28-interactive-product-onboarding.md` | Mainteneurs | Plan historique tutoriel interactif | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-28-mandatory-registration-onboarding.md` | Mainteneurs | Plan historique onboarding obligatoire | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/plans/2026-08-28-member-blocking.md` | Mainteneurs | Plan historique blocage membre | Historique non normatif | Recoupe PRD et sécurité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-16-adult-active-accounts-design.md` | Mainteneurs | Spécification historique comptes adultes | Historique non normatif | Recoupe PRD et sécurité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-16-laravel-bootstrap-design.md` | Mainteneurs | Spécification historique bootstrap | Historique non normatif | Recoupe architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-16-profile-onboarding-admin-dashboard-design.md` | Mainteneurs | Spécification historique profil et administration | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-23-bun-migration-design.md` | Mainteneurs | Spécification historique Bun | Historique non normatif | Recoupe architecture et qualité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-23-discovery-swipes-matches-design.md` | Mainteneurs | Spécification historique découverte | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-23-main-only-cicd-release-design.md` | Mainteneurs | Spécification historique CI/CD | Historique non normatif | Recoupe qualité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-23-mobile-first-member-shell-design.md` | Mainteneurs | Spécification historique interface membre | Historique non normatif | Recoupe design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-24-interest-catalog-design.md` | Mainteneurs | Spécification historique intérêts | Historique non normatif | Recoupe PRD et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-25-avatar-first-profile-ux-design.md` | Mainteneurs | Spécification historique avatar de profil | Historique non normatif | Recoupe PRD et design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-25-pest-browser-migration-design.md` | Mainteneurs | Spécification historique Pest Browser | Historique non normatif | Recoupe qualité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-26-i18n-fr-en-design.md` | Mainteneurs | Spécification historique internationalisation | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-26-realtime-messages-design.md` | Mainteneurs | Spécification historique temps réel | Historique non normatif | Recoupe architecture et modèle ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-27-messaging-interface-design.md` | Mainteneurs | Spécification historique messagerie | Historique non normatif | Recoupe PRD et design system ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-28-interactive-product-onboarding-design.md` | Mainteneurs | Spécification historique tutoriel interactif | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-28-mandatory-registration-onboarding-design.md` | Mainteneurs | Spécification historique onboarding obligatoire | Historique non normatif | Recoupe PRD et architecture ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |
| `docs/superpowers/specs/2026-08-28-member-blocking-design.md` | Mainteneurs | Spécification historique blocage membre | Historique non normatif | Recoupe PRD et sécurité ; aucun lien entrant explicite | Conserver inchangé | Traçabilité Git et conception |

## État d’implémentation vérifié

Ce tableau est fondé sur le code courant, pas sur les documents historiques.
Il sert de preuve pour la matrice de statut du futur PRD.

| Capacité | Statut | Preuves dans le dépôt |
| --- | --- | --- |
| Inscription e-mail/mot de passe et vérification | Implémenté | `app/Actions/Fortify/CreateNewUser.php`, `app/Providers/FortifyServiceProvider.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/Auth/EmailVerificationTest.php` |
| Majorité et compte actif | Implémenté | `database/migrations/2026_08_16_000000_add_eligibility_fields_to_users_table.php`, `app/Http/Middleware/EnsureUserCanAccessSocialFeatures.php`, tests d’authentification et d’inscription |
| Profil et avatar de catalogue obligatoire | Implémenté | `app/Models/Profile.php`, `app/Models/Avatar.php`, contrôleurs de profil et d’avatars, `tests/Feature/MemberProfileTest.php`, `tests/Feature/Admin/ManageAvatarCatalogTest.php` |
| Catalogue d’intérêts et limite configurable | Implémenté | migrations des intérêts, contrôleurs `Admin/Interest*`, `tests/Feature/Admin/ManageInterestCatalogTest.php` |
| Découverte, swipes et match réciproque | Implémenté | `app/Actions/CreateSwipe.php`, `app/Services/DiscoveryService.php`, routes sociales et tests `CreateSwipe*`/`Discovery*` |
| Conversations, messagerie temps réel et lecture | Implémenté | migrations `conversations`/`messages`, `app/Actions/SendMessage.php`, événements de messages, contrôleurs et tests de conversation/message |
| Blocage immédiat d’un membre | Implémenté | `app/Actions/BlockUser.php`, `app/Actions/UnblockUser.php`, routes de blocage et tests `Block*`/`MemberBlockingTest.php` |
| Tutoriel produit obligatoire | Implémenté | migrations `product_onboarding`, action de progression, contrôleurs/pages et tests `ProductOnboarding*`/`OnboardingTest.php` |
| Français et anglais | Implémenté | `app/Http/Middleware/SetLocale.php`, catalogues `lang/`, composable de traduction et tests `Localization/*` |
| Connexion Google et Apple | Planifié | aucune migration `social_accounts`, aucune route ou action OAuth et aucun test de connexion sociale |
| Photo personnelle facultative | Planifié | aucun champ de photo dans les migrations de profil et aucun flux HTTP de téléversement membre |
| Export des données du compte | Planifié | aucune route, aucun contrôleur et aucun test d’export |
| Masquage du profil | Implémenté | `profiles.visibility`, contrôleurs de profil, filtrage de découverte et tests de profil/découverte |
| Suppression différée sous 30 jours | Partiel | `AccountController::destroy()` et le réglage existent, mais suppriment immédiatement l’utilisateur ; aucun job de purge différée n’est présent |
| Signalement et modération | Planifié après le MVP | aucune entité, route, interface ou suite de tests dédiée ; le blocage immédiat existe séparément |

## Règle de maintenance

Après la migration, toute modification de périmètre met à jour `docs/PRD.md` et
toute modification d’une responsabilité spécialisée met à jour son unique
document canonique. Cet inventaire reste la preuve ponctuelle de la migration
de l’issue 89 ; il n’a pas vocation à devenir un second index normatif.
