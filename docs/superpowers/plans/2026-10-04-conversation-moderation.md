# Signalement des échanges et bannissement — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Livrer l'issue 256 avec signalement confirmé, examen administratif en lecture seule et bannissement permanent conservant les données.

**Architecture:** Les règles restent dans Laravel, avec Policies, Form Requests et Actions transactionnelles. Vue réutilise les panneaux adaptatifs et l'espace administratif. Les canaux Reverb privés reçoivent une identité signée permettant une révocation serveur, sans publier de liste de présence.

**Tech Stack:** PHP 8.4, Laravel 13, Fortify, Socialite, Laravel Passkeys, Reverb, MySQL 8.4, Inertia 3, Vue 3, TypeScript, Bun 1.3.14, Pest et Chromium.

**Spec:** `docs/superpowers/specs/2026-10-04-conversation-moderation-design.md`.

## Global Constraints

- Conversation complète, messages ultérieurs inclus ; clôture, blocage, masquage et bannissement ne retirent pas l'accès administratif.
- Blocage facultatif proposé avec « Oui » sélectionné par défaut ; blocage d'un administrateur interdit.
- Bannissement banni/non banni sans expiration ; comptes administrateurs non sanctionnables.
- Conservation intégrale sans échéance des données des comptes actuellement bannis ; suppression administrative explicite maintenue.
- Suppression en application interdite aux comptes bannis, y compris dans l'Action métier.
- Motifs : harcèlement, propos discriminatoires, contenu sexuel, menace, spam/arnaque, autre. Précision et décision : 1 000 caractères maximum.
- Un seul signalement ouvert par auteur et conversation. Nouveau signalement possible après clôture.
- Textes et libellés accessibles dans les catalogues FR/EN ; version légale `2026-10-04` et réacceptation explicite des CGU.
- Aucun message privé, secret ou précision libre dans les journaux techniques ; aucun instantané dupliquant les messages.
- Pas de nouvelle dépendance, API séparée, migration automatique au démarrage Docker ou refactorisation sans rapport.
- Tests de règles métier en rouge/vert/refactorisation, MySQL de test exclusivement, parcours navigateur dans Chromium.

## Review Focus

- Identité correctement vérifiée : mot de passe correct sans second facteur ne révèle pas la sanction ; une mauvaise identité conserve l'erreur générique.
- Ancienne autorisation WebSocket rejouée après sanction : aucun réabonnement ni contenu privé ne passe, même si la révocation HTTP échoue momentanément.
- Suppression d'un autre membre : les cascades ne retirent pas les données du compte banni et l'identité du compte supprimé n'est pas conservée inutilement.
- Signalement d'un administrateur : signaler reste possible sans autoriser son blocage ni rendre l'ensemble de l'opération impossible par défaut.
- Sanction et suppression concurrentes : un job de purge déjà planifié ne peut pas supprimer un compte devenu banni sous verrou.

## Ordre et préparation

La branche `feature/256-conversation-moderation` part de `main` synchronisé.
Préserver les dossiers non suivis `.superpowers/` et `artifacts/`.
Avant chaque tâche, lire les usages des composants et Actions cités.
Préparer `mysql-test` selon `CONTRIBUTING.md`, vérifier la base avec
`php tests/Support/verify-test-database.php` et relever les résultats des tests
rouges avant d'écrire l'implémentation. Ne jamais migrer la base de développement
pour les tests. Chaque tâche se termine par son contrôle ciblé et un commit
Conventional Commits portant uniquement sur ses fichiers.

### Tâche 1 : signalement transactionnel et accès administratif

**Files:**
- Create: `database/migrations/2026_10_04_100000_create_conversation_reports_and_moderation_audits_tables.php`.
- Create: `app/Enums/ConversationReportReason.php`, `app/Models/ConversationReport.php`, `app/Models/ModerationAudit.php`, `database/factories/ConversationReportFactory.php`.
- Create: `app/Policies/ConversationReportPolicy.php`, `app/Http/Requests/StoreConversationReportRequest.php`, `app/Http/Requests/Admin/CloseConversationReportRequest.php`.
- Create: `app/Actions/ReportConversation.php`, `app/Actions/CloseConversationReport.php`.
- Create: `app/Http/Controllers/ConversationReportController.php`, `app/Http/Controllers/Admin/ConversationReportController.php`.
- Modify: `routes/web.php`, `app/Policies/ConversationPolicy.php`.
- Test: `tests/Feature/ConversationReportTest.php`, `tests/Feature/Admin/ConversationReportReviewTest.php`.

**Interfaces:**
- `ReportConversation::handle(User $reporter, Conversation $conversation, ConversationReportReason $reason, ?string $details, bool $block): ConversationReport`.
- `CloseConversationReport::handle(User $admin, ConversationReport $report, string $decision): void`.
- `ConversationReportPolicy::viewAny(User $user): bool`, `view(User $user, ConversationReport $report): bool`, `close(User $user, ConversationReport $report): bool`.
- Member POST `conversations/{conversation}/reports`, name `conversations.reports.store`.
- Admin GET `admin/conversation-reports`, GET/PATCH `admin/conversation-reports/{report}`, names `admin.conversation-reports.index`, `.show`, `.close`.

- [x] Écrire les tests de participant/tiers, motif obligatoire, limite 1 000, annulation sans requête, blocage choisi, admin non bloquable, répétition et clôture. Assertions : un seul rapport ouvert, aucun blocage avec `block=false`, refus 403 pour un tiers, 422 pour motif invalide et texte de 1 001 caractères.
- [x] Exécuter `php artisan test tests/Feature/ConversationReportTest.php tests/Feature/Admin/ConversationReportReviewTest.php` et confirmer les échecs attendus.
- [x] Créer les tables. Rapport : conversation, auteur, membre visé, motif, précision, décideur nullable, décision nullable, date de clôture nullable, timestamps. Audit : acteur nullable, cible nullable, rapport nullable, opération, date ; aucun corps de message. Protéger les doublons ouverts avec une clé unique nullable dérivée de l'auteur et de la conversation, remise à null à la clôture, et un verrou transactionnel.
- [x] Implémenter Policy, requêtes et Actions avec compte actif/majeur/vérifié et appartenance au match recontrôlés sous verrou. Réutiliser `BlockUser`; rendre signalement et blocage atomiques. Ne pas appliquer les restrictions de blocage/archivage à l'éligibilité au signalement.
- [x] Implémenter la file paginée, filtre ouvert/clôturé, fiche et clôture. Lire les messages originaux par pagination stable `(created_at,id)` et exposer uniquement les données nécessaires. Journaliser chaque consultation et clôture dans l'audit, sans changer `read_at` ni les droits de participant.
- [x] Ajouter les tests : échange non signalé inaccessible par substitution, message ancien et ultérieur visibles, accès après clôture/blocage/masquage, compte admin inactif refusé, absence de droits d'envoi/réaction et curseur de lecture inchangé. Rejouer les tests ciblés et commiter.

### Tâche 2 : état de sanction et révocation des accès web

**Files:**
- Modify: `app/Enums/UserStatus.php`, `app/Policies/UserPolicy.php`, `app/Http/Middleware/EnsureUserCanAccessSocialFeatures.php`, `app/Actions/RequestAccountDeletion.php`.
- Create: `app/Actions/SetMemberBan.php`, `app/Http/Requests/Admin/SetMemberBanRequest.php`, `app/Http/Controllers/Admin/MemberBanController.php`, `app/Support/BannedAuthentication.php`.
- Modify: `app/Http/Responses/LoginResponse.php`, `app/Http/Responses/PasskeyLoginResponse.php`, `app/Http/Responses/TwoFactorLoginResponse.php`, `app/Http/Controllers/Auth/SocialAuthController.php`, `app/Providers/FortifyServiceProvider.php`, `routes/web.php`.
- Modify: actions de messages, réactions, matching, présence et événements et leurs Policies lorsque leur contrôle actuel omet le statut d'un participant ; `Conversation` scopes et notifications privées.
- Test: `tests/Feature/Admin/MemberBanTest.php`, `tests/Feature/Auth/BannedAuthenticationTest.php`, `tests/Feature/Settings/AccountDeletionTest.php`.

**Interfaces:**
- `SetMemberBan::handle(User $admin, User $member, bool $banned, string $reason): void`.
- `UserPolicy::ban(User $admin, User $member): bool` : admin actif autorisé, cible non admin.
- `BannedAuthentication::rejectIfBanned(Request $request, User $user): void` : invalider toute session de connexion, puis erreur localisée après identité vérifiée.
- PATCH `admin/members/{member}/ban`, name `admin.members.ban.update`, payload `banned`, `reason`, `confirmed=true`.

- [x] Écrire les tests : admin seulement, confirmation et motif obligatoires, impossibilité de bannir un admin, conservation du compte/identifiants, destruction des sessions, refus des routes privées et de `RequestAccountDeletion::handle`, état sans expiration, levée sans restauration des anciennes sessions.
- [x] Exécuter `php artisan test tests/Feature/Admin/MemberBanTest.php tests/Feature/Auth/BannedAuthenticationTest.php tests/Feature/Settings/AccountDeletionTest.php` et constater le rouge.
- [x] Ajouter `UserStatus::Banned = 'banned'`. Dans `SetMemberBan`, verrouiller les utilisateurs dans l'ordre des IDs, revérifier le rôle cible, invalider sessions, cookie remember et tokens de réinitialisation, révoquer Push, conserver liens sociaux, passkeys, données et rôles. Auditer état, motif et acteur dans la base ; pas dans les logs.
- [x] Annuler les événements futurs organisés, retirer les inscriptions futures et dépublier/arrêter les contenus partenaires en réutilisant les Actions adaptées sans suppression de données. Les transitions utilisent les statuts existants et sont idempotentes.
- [x] Brancher le contrôle de sanction après vérification complète d'identité dans les trois réponses Fortify/passkey et le callback Google. Recontrôler le statut frais, invalider la session et retourner le message FR/EN avec `LEGAL_CONTACT_EMAIL` sans divulgation par simple e-mail. Refuser aussi remember-me et accès privés de sessions concurrentes.
- [x] Ajouter les tests : mauvais mot de passe générique, bon mot de passe sanctionné, second facteur incorrect/correct, passkey valide/invalide, Google lié, réinscription e-mail/Google, compte multi-rôles, interlocuteur actif tentant d'écrire au compte banni, absence de notifications privées. Rejouer tests ciblés et commiter.

### Tâche 3 : révocation serveur des connexions Reverb

**Files:**
- Create: `app/Broadcasting/IdentityAwareReverbBroadcaster.php`, `app/Reverb/IdentityAwarePrivateChannel.php`, `app/Reverb/IdentityAwareChannelManager.php`, `app/Actions/RevokeMemberRealtimeAccess.php`.
- Modify: `app/Providers/AppServiceProvider.php`, `routes/channels.php`, `app/Actions/SetMemberBan.php`, `app/Events/MessageSent.php`, `app/Events/MessagesRead.php`, `app/Events/MessageReactionUpdated.php`, événements de groupe/présence et notifications utilisant un destinataire privé.
- Test: `tests/Feature/BannedRealtimeAccessTest.php`, `tests/Feature/ConversationTest.php`, tests existants de diffusion de groupe et de présence.

**Interfaces:**
- `RevokeMemberRealtimeAccess::handle(User $user): void` appelle l'API Pusher/Reverb signée `terminate_connections` pour cet utilisateur, sans exposer les secrets.
- `IdentityAwarePrivateChannel::subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void` exige une identité signée et un compte encore actif.
- `IdentityAwarePrivateChannel::broadcast(array $payload, ?Connection $except = null): void` et `broadcastToAll(array $payload): void` revérifient les destinataires et déconnectent les comptes inactifs avant d'envoyer.
- `IdentityAwareChannelManager::findOrCreate(string $channelName): Channel` conserve le manager Reverb existant et substitue seulement les canaux privés applicatifs sensibles.

- [x] Écrire les tests avec connexions de protocole Reverb : une connexion déjà abonnée reçoit avant sanction, ne reçoit plus après, et une ancienne autorisation ne peut pas être rejouée. Test sans abonnement au canal utilisateur : l'identité doit être attachée à chaque canal privé.
- [x] Exécuter `php artisan test tests/Feature/BannedRealtimeAccessTest.php` et constater le rouge.
- [x] Étendre le broadcaster Reverb existant pour signer `socket_id:channel_name:channel_data`, où `channel_data` ne contient que `user_id`. La bibliothèque Pusher transmet déjà ce champ pour les canaux privés ; Reverb vérifie déjà la signature incluant ce champ et stocke ces données dans `ChannelConnection`. Ne pas activer de roster de présence ni `pusher:signin`, absent du serveur installé.
- [x] Enregistrer le broadcaster et le manager dans le conteneur sans modifier `vendor/`. Pour chaque canal applicatif privé, refuser l'absence d'identité signée, l'identité incohérente, le statut inactif et les autorisations invalides. Garder les noms des canaux et les usages Echo existants.
- [x] Révoquer les connexions après sanction et garantir aussi le filtrage au moment de la diffusion si l'API de terminaison échoue. Tester les deux chemins de diffusion, les clients malveillants sans `channel_data`, signatures altérées, messages queued avant sanction, multi-canaux et mode Reverb distribué. Aucun payload privé dans les nouveaux logs.
- [x] Renforcer `routes/channels.php` et les événements avec vérification fraîche des participants/destinataires. Rejouer les tests de conversation, présence, groupes et notifications, puis commiter. Documenter le redémarrage requis de Reverb lors du déploiement.

### Tâche 4 : conservation intégrale et suppression explicite

**Files:**
- Create: `database/migrations/2026_10_04_110000_preserve_banned_account_related_records.php`, `app/Actions/PrepareMemberDataForDeletion.php`.
- Modify: `app/Actions/DeleteMember.php`, `app/Jobs/PurgeDeletedUser.php`, `app/Console/Commands/DispatchDueAccountPurges.php`, `app/Console/Commands/PurgeExpiredPartnerRecords.php`, `app/Actions/PurgeDeletedPartnerData.php`, `app/Actions/BuildUserDataExport.php`.
- Modify: modèles/Policies/Data dépendant des références utilisateur concernées, notamment `MemberMatch`, `Conversation`, `Swipe`, `Event` et sérialisation administrative.
- Test: `tests/Feature/BannedAccountRetentionTest.php`, `tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php`, `tests/Feature/Settings/DirectUserDataExportTest.php`, tests de suppression membres/partenaires.

**Interfaces:**
- `PrepareMemberDataForDeletion::handle(User $member): void`, appelée sous verrou avant la suppression effective, protège les données des autres comptes bannis et nettoie les graphes non protégés.
- Export : section `moderation` contenant signalements émis et décisions concernant le compte, sans auteur tiers ni acteur admin.

- [x] Écrire les tests : job ancien ignoré après bannissement, données conservées après avance de dix ans, demandes de suppression bannies refusées, suppression administrative effective, conservation partenaires malgré `expires_at`, suppression d'un interlocuteur et d'un organisateur d'événement laissant les données du compte banni intactes.
- [x] Exécuter `php artisan test tests/Feature/BannedAccountRetentionTest.php tests/Feature/Console/PurgeExpiredPartnerRecordsTest.php` et constater le rouge.
- [x] Faire l'inventaire des FK et graphes sociaux/événements/partenaires. Rendre nullables les références à un compte supprimé qui doivent conserver une donnée du compte banni (`matches.user_low_id/user_high_id`, références de swipes, organisateur d'événement et références d'audit) et utiliser `nullOnDelete` pour ces liens. Garder les auteurs de messages existants supprimables : la suppression de l'autre membre retire ses propres messages, conserve ceux du compte banni et neutralise son identité.
- [x] Dans `PrepareMemberDataForDeletion`, conserver seulement les graphes liés à un compte banni encore présent : match/conversation, swipes, événements avec inscription/message du compte banni et audits pertinents. Supprimer explicitement les graphes non protégés afin de conserver le comportement de purge ordinaire. Archiver les échanges et événements détachés ; exclure ces graphes des parcours sociaux ordinaires, afficher les références supprimées par un libellé FR/EN dans la revue admin.
- [x] Recontrôler sous verrou avant chaque purge automatique le statut des propriétaires/cibles protégés. Couvrir aussi les cascades d'événements, les historiques partenaires et les suppressions de rôles audités : aucune purge indirecte des données du compte banni. La suppression administrative du compte banni retire ses données et les références de modération devenues inutiles.
- [x] Étendre l'export minimal et tester les exclusions de messages d'autrui, identité des signalants/admins et secrets. Tester la levée du bannissement : ancienne demande de suppression neutralisée, aucune purge surprise à la réactivation. Rejouer toutes les suites de suppression/export et commiter.

### Tâche 5 : parcours membre et administration FR/EN

**Files:**
- Create: `resources/js/components/members/ReportConversationDialog.vue`, `resources/js/components/admin/SetMemberBanDialog.vue`, `resources/js/pages/Admin/ConversationReports/Index.vue`, `resources/js/pages/Admin/ConversationReports/Show.vue`, `resources/js/types/moderation.ts`, `lang/fr/moderation.php`, `lang/en/moderation.php`.
- Modify: `resources/js/pages/Conversations/Show.vue`, `resources/js/components/conversations/ConversationHeader.vue`, `resources/js/components/admin/AdminMemberCard.vue`, `resources/js/pages/Admin/Members/Index.vue`, `resources/js/layouts/AdminLayout.vue`, `resources/js/components/admin/AdminBottomNavigation.vue`, `app/Http/Controllers/Admin/MemberController.php`, `app/Support/FrontendTranslations.php`.
- Test: `tests/Browser/ConversationModerationTest.php`, `tests/Feature/Localization/InertiaTranslationsTest.php`.

**Interfaces:**
- `ReportConversationDialog` props : `conversationId: number`, `canBlock: boolean`, `returnHref: string` ; utilise la route Wayfinder de la tâche 1.
- `SetMemberBanDialog` props : `memberId: number`, `banned: boolean` ; utilise la route Wayfinder de la tâche 2.
- Fiche admin : fil de lecture seul, décision, état de clôture, liens vers membres et confirmation de sanction ; aucune réutilisation du composable de participant.

- [x] Écrire les tests navigateur desktop/mobile : annulation sans création ni blocage, option Oui initiale, choix Non conservé, avertissement avant envoi, motif obligatoire, affichage après blocage, accès admin et clavier, clôture sans perte d'accès, sanction et levée confirmées.
- [x] Exécuter `php artisan test tests/Browser/ConversationModerationTest.php` et constater les échecs attendus.
- [x] Réutiliser `useResponsiveModal`, champs et composants Reka existants. Le cas interlocuteur admin ne sélectionne pas un blocage impossible. Boutons occupés et erreurs utilisent les catalogues. Ajouter navigation admin et badge banni à la gestion des membres ; conserver suppression comme action distincte.
- [x] Générer Wayfinder avec `php artisan wayfinder:generate --with-form`, vérifier `bun run lint:check`, `bun run format:check`, `bun run types:check`, puis les tests navigateur et de localisation ; commiter.

### Tâche 6 : information légale et réacceptation

**Files:**
- Modify: `config/legal.php`, `lang/fr/legal.php`, `lang/en/legal.php`, `bootstrap/app.php`, `routes/web.php`, `app/Http/Middleware/HandleInertiaRequests.php`.
- Create: `app/Http/Middleware/EnsureCurrentTermsAccepted.php`, `app/Http/Controllers/TermsAcceptanceController.php`, `app/Http/Requests/AcceptCurrentTermsRequest.php`, `resources/js/pages/auth/AcceptTerms.vue`.
- Modify: catalogues compte FR/EN, documents PRD, sécurité/confidentialité, modèle de données, architecture, design system, règles éditoriales et opérations.
- Test: `tests/Feature/LegalPagesTest.php`, `tests/Feature/TermsReacceptanceTest.php`, tests d'inscription e-mail/Google, `tests/Browser/ConversationModerationTest.php`.

**Interfaces:**
- GET/POST `terms/accept`, names `terms.acceptance.show` et `terms.acceptance.store`, derrière authentification et contrôle d'activité mais hors middleware de réacceptation.
- `EnsureCurrentTermsAccepted::handle(Request $request, Closure $next): Response`, appliqué aux routes privées éligibles après contrôle du statut.
- Acceptation `accepted=true`, version et date serveur `2026-10-04`, aucune confiance dans la version soumise par le client.

- [x] Écrire les tests : comptes anciens renvoyés vers réacceptation, case non précochée, refus sans accès privé, acceptation enregistrée côté serveur, Google/nouveaux comptes enregistrant la nouvelle version, admin/partenaire soumis au même contrat, compte banni ne contournant pas sa sanction par ces routes.
- [x] Exécuter `php artisan test tests/Feature/LegalPagesTest.php tests/Feature/TermsReacceptanceTest.php` et constater le rouge.
- [x] Mettre à jour les textes FR/EN pour expliquer accès intégral et persistant après signalement, blocage facultatif, sanction sans expiration, conservation sans échéance, refus de suppression en application et exception de suppression administrative. Décrire le logiciel sans affirmer une restriction des droits légaux.
- [x] Implémenter la réacceptation, garder déconnexion et pages légales accessibles, recontrôler activité/version sous verrou et prévenir les boucles. Mettre à jour la documentation uniquement à partir du comportement livré, dont l'extension Reverb et la migration explicite.
- [x] Rejouer les tests légaux, authentification/inscription et navigateur ; commiter.

### Tâche 7 : vérification globale et livraison

**Files:** Ensemble de la branche, sans inclure les dossiers non suivis préexistants.

- [x] Relire le diff face à la spécification ; rechercher texte visible en dur, données privées dans les logs, chemins administratifs contournant les Policies, jobs non protégés et références nullable non gérées. Ajouter un test de régression pour chaque problème observé avant sa correction.
- [ ] Exécuter `composer ci:check` sur la base MySQL dédiée. Confirmer contrôles PHP, analyse, Wayfinder, tests frontend, build Vite et tests Pest/browser. Exécuter le build Docker `docker build --target runtime --tag dlp-friends:ci .` séparément.
- [x] Réaliser la revue indépendante de branche selon la méthode d'exécution choisie ; corriger les problèmes prouvés et rejouer les contrôles concernés. Ne pas annoncer terminé tant qu'un critère obligatoire reste sans preuve.
- [ ] Commit final Conventional Commits, push de la branche et PR vers `main` avec `Closes #256`, explication des changements de confidentialité/conservation et preuves des tests. Attacher la PR au chat ; attendre les checks requis et résoudre les conversations. Ne pas merger ni publier une release sans instruction.

## Revue du plan

Les six tâches fonctionnelles couvrent signalement, revue, sanction, authentification,
temps réel, conservation/export, interfaces et textes légaux de la spécification.
Les signatures consommées sont celles produites par les tâches précédentes.
Les cinq cas de Review Focus ont chacun un test explicitement attribué.
Le travail sur Reverb est ciblé : les bibliothèques installées permettent les
données signées des canaux privés, mais ne permettent pas de supposer le support
de `pusher:signin`. Aucune dépendance supplémentaire n'est nécessaire.

## Revue de l'implémentation

La revue indépendante de la branche a identifié cinq cas, chacun reproduit
avant correction et vérifié ensuite :

- La conservation utilise les statuts courants lus sous verrou MySQL, même si
  une suppression avait déjà établi un snapshot antérieur au bannissement.
- Les blocages détachés ne provoquent plus d'erreur dans les espaces événements
  après la levée d'un bannissement.
- Les huit événements privés recontrôlent les droits au moment de leur diffusion
  différée ; une vérification lors de la création du job ne suffit pas.
- Un code de récupération 2FA valide renvoie un compte banni vers la connexion
  et y affiche effectivement le message de sanction dans le navigateur.
- Les routes privées Fortify et les abonnements/diffusions Reverb exigent aussi
  les CGU actuelles, et refusent un compte banni.

Les fixtures de rollback et le catalogue attendu des traductions ont été adaptés
aux migrations et au domaine de modération. Le test navigateur de notifications
attend désormais le toast existant de sauvegarde avant de vérifier la base :
la suite complète avait démontré une course avec l'enregistrement asynchrone.

Le build Docker local a été tenté, mais le démon ne répond plus, même à
`/_ping`. La preuve du build `runtime` et du smoke test doit donc venir du job
Docker existant de la PR. Aucun service de développement n'a été redémarré.

Validation locale finale : `composer ci:check` réussit, avec 1 226 tests Pest
réussis, 5 tests ignorés et 27 881 assertions (1 231 tests au total). Les contrôles
PHP/frontend, PHPStan, types, Wayfinder, tests unitaires frontend et build Vite
sont également réussis. Les deux étapes de livraison encore ouvertes ci-dessus
sont suivies par les checks requis de la PR, dont le build Docker.
