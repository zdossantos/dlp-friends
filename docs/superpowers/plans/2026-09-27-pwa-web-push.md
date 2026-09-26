# PWA Installable et Notifications Web Push Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer l'issue #203 sous la forme d'une PWA installable et sûre sur Android et iOS/iPadOS, puis envoyer des notifications Web Push génériques pour toutes les catégories internes existantes.

**Architecture:** Vite construit un service worker `injectManifest` qui ne précache que le shell public haché et une page hors connexion ; toutes les données Laravel/Inertia restent `network only`. Laravel stocke des abonnements chiffrés par appareil, des préférences par catégorie, puis livre via VAPID dans une file avec contrôles d'accès et idempotence. Un module Vue unique pilote installation, permission, abonnement, mise à jour et badge dans le tutoriel comme dans les réglages.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4, Redis queues, Minishlink WebPush, Bun 1.3.14, Vite PWA/Workbox, Inertia 3, Vue 3 Composition API, TypeScript, Pest, Pest Browser et Playwright.

**Spec:** `docs/superpowers/specs/2026-09-27-pwa-web-push-design.md`

## Global Constraints

- Lire `superpowers:writing-good-tests` avant le premier changement de test et respecter rouge → vert → refactorisation pour chaque tâche.
- Ne jamais mettre en cache une page Laravel/Inertia, une réponse authentifiée, `/storage`, une image membre, une requête API/XHR, un formulaire ou un flux temps réel.
- Ne jamais exposer endpoint, clés Web Push, contenu de message, identité de membre ou autre donnée privée dans Inertia, les logs, l'export ou le payload système.
- Demander la permission système uniquement après un geste explicite ; sur iOS, expliquer l'installation manuelle et ne proposer le Push qu'en mode autonome.
- Chaque libellé visible, erreur, toast et texte d'accessibilité existe en français et en anglais ; respecter clavier, lecteurs d'écran, cibles de 44 px et `prefers-reduced-motion`.
- Conserver les middlewares, Policies et notifications `database`/`broadcast` actuels ; le Web Push est un canal complémentaire et non une source de vérité.
- Toute cible de clic Push est une URL relative de même origine validée côté serveur et côté service worker.
- Utiliser les icônes de marque existantes sans élément Disney ; pour tout nouveau bitmap, charger la compétence `imagegen`, puis vérifier visuellement le résultat.

## Review Focus

- Prouver par test que le service worker ne peut jamais servir ou conserver une donnée privée.
- Vérifier le cycle `waiting → action utilisateur → skipWaiting → controllerchange → un seul reload` sans interrompre un formulaire.
- Vérifier les matrices Android/iOS : navigateur, standalone, permission `default`/`granted`/`denied`, installation indisponible.
- Vérifier chiffrement, unicité et révocation des abonnements, ainsi que les erreurs permanentes/temporaires de livraison.
- Vérifier les cinq préférences : messages, nouveaux univers croisés, événements, annonces partenaires et administration.

---

### Task 1: Identité PWA, manifeste et métadonnées mobiles

**Files:**
- Create: `public/app.webmanifest`
- Create: `public/pwa/icon-192.png`
- Create: `public/pwa/icon-512.png`
- Create: `public/pwa/icon-maskable-192.png`
- Create: `public/pwa/icon-maskable-512.png`
- Create: `public/pwa/screenshot-fr.png`
- Create: `public/pwa/screenshot-en.png`
- Modify: `resources/views/components/brand-head.blade.php`
- Modify: `resources/views/app.blade.php`
- Test: `tests/Feature/PwaManifestTest.php`

**Interfaces:**
- Produces: `/app.webmanifest` avec `id: "/"`, `scope: "/"`, `start_url: "/app"`, `display: "standalone"`, icônes `any`/`maskable` et captures FR/EN.
- Produces: métadonnées `theme-color`, manifest et Apple Web App cohérentes sur les pages installables.

- [ ] **Step 1: Écrire les tests de manifeste, fichiers et balises puis les lancer**

Run: `php artisan test tests/Feature/PwaManifestTest.php`

Expected: FAIL car le manifeste et les assets PWA n'existent pas.

- [ ] **Step 2: Générer les bitmaps de marque, les contrôler visuellement et ajouter le manifeste minimal**

Le safe zone maskable doit garder tout le symbole utile dans les 80 % centraux. Les captures ne contiennent ni profil réel, ni personnage/logo Disney.

- [ ] **Step 3: Relier le manifeste et les métadonnées depuis les layouts, sans dupliquer les balises**

- [ ] **Step 4: Relancer le test ciblé et le contrôle de format**

Run: `php artisan test tests/Feature/PwaManifestTest.php && bun run format:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add public/app.webmanifest public/pwa resources/views/components/brand-head.blade.php resources/views/app.blade.php tests/Feature/PwaManifestTest.php
git commit -m "feat(pwa): add installable app identity"
```

### Task 2: Service worker à cache privé impossible

**Files:**
- Create: `resources/js/service-worker.ts`
- Create: `resources/js/lib/pwa/serviceWorkerPolicy.ts`
- Create: `resources/js/lib/pwa/serviceWorkerPolicy.test.ts`
- Create: `public/offline.html`
- Modify: `vite.config.ts`
- Modify: `package.json`
- Modify: `bun.lock`
- Modify: `resources/js/app.ts`
- Test: `tests/Feature/PwaServiceWorkerTest.php`

**Interfaces:**
- Produces: service worker stable `/service-worker.js`, précache injecté et fallback `/offline.html`.
- Produces: `isCacheableStaticRequest(request): boolean`, vrai uniquement pour `GET` statique explicitement autorisé.
- Consumes: `vite-plugin-pwa` en mode `injectManifest`; aucune stratégie runtime pour les réponses applicatives.

- [ ] **Step 1: Écrire les tests rouges de politique réseau**

Tester navigation, Inertia, `Authorization`, `/storage`, méthodes non-GET, API et image privée comme non cachables ; tester seulement assets Vite hachés, fontes, icônes et page hors connexion comme précachables.

Run: `bun test resources/js/lib/pwa/serviceWorkerPolicy.test.ts && php artisan test tests/Feature/PwaServiceWorkerTest.php`

Expected: FAIL sur les modules et le worker absents.

- [ ] **Step 2: Ajouter les dépendances PWA verrouillées et implémenter le worker minimal**

Le handler de navigation utilise le réseau et ne retourne `offline.html` qu'en cas d'échec. `activate` supprime uniquement les caches préfixés `dlp-friends-`; aucun Background Sync.

- [ ] **Step 3: Enregistrer le worker après le montage uniquement en production ou mode E2E explicite**

Une erreur d'enregistrement est absorbée sans casser le rendu et sans journaliser de secret.

- [ ] **Step 4: Exécuter tests et inspecter le build généré**

Run: `bun test resources/js/lib/pwa/serviceWorkerPolicy.test.ts && php artisan test tests/Feature/PwaServiceWorkerTest.php && bun run build`

Expected: PASS ; `public/build/service-worker.js` existe et ne contient aucune route privée précachée.

- [ ] **Step 5: Commit**

```bash
git add resources/js/service-worker.ts resources/js/lib/pwa public/offline.html vite.config.ts package.json bun.lock resources/js/app.ts tests/Feature/PwaServiceWorkerTest.php
git commit -m "feat(pwa): add privacy-safe offline worker"
```

### Task 3: Cycle de mise à jour, installation et navigation standalone

**Files:**
- Create: `resources/js/composables/usePwa.ts`
- Create: `resources/js/lib/pwa/capabilities.ts`
- Create: `resources/js/lib/pwa/capabilities.test.ts`
- Create: `resources/js/components/pwa/PwaUpdatePrompt.vue`
- Create: `resources/js/components/pwa/ExternalLink.vue`
- Modify: `resources/js/layouts/AppLayout.vue`
- Modify: `resources/js/app.ts`
- Modify: `resources/css/app.css`
- Modify: `lang/fr/common.php`
- Modify: `lang/en/common.php`

**Interfaces:**
- Produces: états `browser | standalone | installable | installed | unsupported` et capacités Push.
- Produces: `install()`, `activateWaitingWorker()` et un seul rechargement après `controllerchange`.
- Produces: lien externe HTTPS avec `noopener noreferrer`; les liens internes restent dans la navigation Inertia.

- [ ] **Step 1: Écrire les tests rouges de détection et de transition**

Inclure `display-mode`, `navigator.standalone`, `beforeinstallprompt`, `appinstalled`, absence d'API et garde anti-double reload.

Run: `bun test resources/js/lib/pwa/capabilities.test.ts`

Expected: FAIL car les helpers n'existent pas.

- [ ] **Step 2: Implémenter le composable central et le protocole de mise à jour consentie**

Ne jamais appeler `skipWaiting` automatiquement. Afficher le prompt seulement lorsque le worker est `waiting`; envoyer `{type: 'SKIP_WAITING'}` après clic.

- [ ] **Step 3: Ajouter le prompt accessible, les safe areas et le composant de lien externe**

- [ ] **Step 4: Exécuter tests, types et lint**

Run: `bun test resources/js/lib/pwa/capabilities.test.ts && bun run types:check && bun run lint:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/usePwa.ts resources/js/lib/pwa resources/js/components/pwa resources/js/layouts/AppLayout.vue resources/js/app.ts resources/css/app.css lang/fr/common.php lang/en/common.php
git commit -m "feat(pwa): manage install and update lifecycle"
```

### Task 4: Dernière étape du tutoriel d'installation

**Files:**
- Create: `database/migrations/2026_09_27_100000_add_install_app_product_onboarding_step.php`
- Modify: `app/Enums/ProductOnboardingStep.php`
- Modify: `app/Actions/AdvanceProductOnboarding.php`
- Modify: `app/Http/Controllers/ProductOnboardingController.php`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Create: `resources/js/components/onboarding/InstallAppStep.vue`
- Modify: `lang/fr/onboarding.php`
- Modify: `lang/en/onboarding.php`
- Modify: `tests/Feature/ProductOnboardingTest.php`
- Modify: `tests/Feature/ProductOnboardingTransitionTest.php`
- Modify: `tests/Browser/OnboardingTest.php`

**Interfaces:**
- Produces: `ProductOnboardingStep::InstallApp` après `ConversationDemo`.
- Produces: action explicite `complete` ou `skip` avec confirmation ; un tutoriel ancien déjà terminé reste terminé.
- Consumes: `usePwa()` et, après Task 8, la méthode d'abonnement Push sans coupler le backend du tutoriel au navigateur.

- [ ] **Step 1: Ajouter les tests rouges de transition, migration et parcours navigateur**

Vérifier ancien compte terminé, progression conversation → installation, relance standalone, iOS manuel, Chromium natif, plateforme incompatible et confirmation de passage.

Run: `php artisan test tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Browser/OnboardingTest.php`

Expected: FAIL car la nouvelle étape n'existe pas.

- [ ] **Step 2: Ajouter l'étape et préserver les progressions historiques**

- [ ] **Step 3: Construire l'interface progressive FR/EN et accessible**

Afficher l'ordre bénéfice → installation → notifications → résultat → fin. Le refus ou le passage ne simule jamais une installation ou une permission.

- [ ] **Step 4: Relancer les tests ciblés et les types**

Run: `php artisan test tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Browser/OnboardingTest.php && bun run types:check`

Expected: PASS pour le parcours sans abonnement serveur ; l'activation Push est branchée dans Task 8.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_27_100000_add_install_app_product_onboarding_step.php app/Enums/ProductOnboardingStep.php app/Actions/AdvanceProductOnboarding.php app/Http/Controllers/ProductOnboardingController.php resources/js/pages/Onboarding/Show.vue resources/js/components/onboarding/InstallAppStep.vue lang/fr/onboarding.php lang/en/onboarding.php tests/Feature/ProductOnboardingTest.php tests/Feature/ProductOnboardingTransitionTest.php tests/Browser/OnboardingTest.php
git commit -m "feat(onboarding): add pwa installation step"
```

### Task 5: Abonnements chiffrés et préférences par catégorie

**Files:**
- Create: `database/migrations/2026_09_27_110000_create_web_push_subscriptions_and_preferences.php`
- Create: `app/Enums/WebPushPreference.php`
- Create: `app/Models/WebPushSubscription.php`
- Create: `app/Models/NotificationPreference.php`
- Create: `database/factories/WebPushSubscriptionFactory.php`
- Create: `database/factories/NotificationPreferenceFactory.php`
- Modify: `app/Models/User.php`
- Modify: `app/Actions/BuildUserDataExport.php`
- Modify: `app/Actions/RequestAccountDeletion.php`
- Modify: `app/Actions/DeleteMember.php`
- Test: `tests/Feature/WebPushSubscriptionSchemaTest.php`
- Test: `tests/Feature/WebPushDataControlTest.php`

**Interfaces:**
- Produces: plusieurs appareils par membre, UUID public, endpoint unique, clés `p256dh`/`auth` chiffrées, métadonnées bornées et révocation.
- Produces: préférences `messages`, `matches`, `events`, `partner_announcements`, `administration`.
- Migrates: choix `partner_notification_preferences.enabled` sans perte.
- Produces: export sans endpoint/clés et révocation immédiate à la demande de suppression.

- [ ] **Step 1: Écrire les tests rouges de schéma, chiffrement, export et suppression**

Inclure contrainte d'endpoint actif unique, cascade, casts chiffrés et absence des secrets dans JSON/log/array/export.

Run: `php artisan test tests/Feature/WebPushSubscriptionSchemaTest.php tests/Feature/WebPushDataControlTest.php`

Expected: FAIL car modèles et tables manquent.

- [ ] **Step 2: Implémenter migrations, modèles, relations et migration du choix partenaire**

- [ ] **Step 3: Étendre export et désactivation de compte avec métadonnées non secrètes uniquement**

- [ ] **Step 4: Relancer les tests ciblés et PHPStan**

Run: `php artisan test tests/Feature/WebPushSubscriptionSchemaTest.php tests/Feature/WebPushDataControlTest.php && composer analyse`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_09_27_110000_create_web_push_subscriptions_and_preferences.php app/Enums/WebPushPreference.php app/Models/WebPushSubscription.php app/Models/NotificationPreference.php database/factories/WebPushSubscriptionFactory.php database/factories/NotificationPreferenceFactory.php app/Models/User.php app/Actions/BuildUserDataExport.php app/Actions/RequestAccountDeletion.php app/Actions/DeleteMember.php tests/Feature/WebPushSubscriptionSchemaTest.php tests/Feature/WebPushDataControlTest.php
git commit -m "feat(push): store encrypted device subscriptions"
```

### Task 6: API sécurisée des appareils et préférences

**Files:**
- Create: `app/Http/Controllers/Settings/WebPushSubscriptionController.php`
- Create: `app/Http/Controllers/Settings/NotificationPreferenceController.php`
- Create: `app/Http/Requests/Settings/WebPushSubscriptionStoreRequest.php`
- Create: `app/Http/Requests/Settings/NotificationPreferenceUpdateRequest.php`
- Create: `app/Actions/UpsertWebPushSubscription.php`
- Create: `app/Policies/WebPushSubscriptionPolicy.php`
- Modify: `routes/settings.php`
- Test: `tests/Feature/Settings/WebPushSubscriptionTest.php`
- Modify: `tests/Feature/Settings/PartnerNotificationPreferenceTest.php`

**Interfaces:**
- Produces: `POST /settings/notifications/devices`, `DELETE /settings/notifications/devices/{uuid}` et `PATCH /settings/notifications`.
- Returns: UUID, libellé borné, plateforme, confirmation/succès/révocation ; jamais endpoint ni clés.
- Produces: upsert idempotent ; refuse de transférer un endpoint actif détenu par un autre compte.

- [ ] **Step 1: Écrire les tests rouges de validation, autorisation, idempotence et confidentialité**

Tester CSRF/session middleware, abonnement étranger 404/403, tailles de clés, protocole HTTPS, tout désactiver et defaults au premier abonnement.

Run: `php artisan test tests/Feature/Settings/WebPushSubscriptionTest.php tests/Feature/Settings/PartnerNotificationPreferenceTest.php`

Expected: FAIL sur les nouvelles routes.

- [ ] **Step 2: Implémenter les Form Requests, Policy, Action et contrôleurs**

- [ ] **Step 3: Remplacer le contrôleur partenaire sans casser les données historiques**

- [ ] **Step 4: Relancer les tests ciblés**

Run: `php artisan test tests/Feature/Settings/WebPushSubscriptionTest.php tests/Feature/Settings/PartnerNotificationPreferenceTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Settings app/Http/Requests/Settings app/Actions/UpsertWebPushSubscription.php app/Policies/WebPushSubscriptionPolicy.php routes/settings.php tests/Feature/Settings/WebPushSubscriptionTest.php tests/Feature/Settings/PartnerNotificationPreferenceTest.php
git commit -m "feat(push): add device subscription api"
```

### Task 7: Transport VAPID, éligibilité et idempotence

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`
- Modify: `.env.example`
- Modify: `config/services.php`
- Create: `app/Contracts/WebPushNotification.php`
- Create: `app/Notifications/Channels/WebPushChannel.php`
- Create: `app/Jobs/SendWebPushNotification.php`
- Create: `app/Actions/DeliverWebPushNotification.php`
- Create: `app/Support/WebPushTarget.php`
- Create: `database/migrations/2026_09_27_120000_create_web_push_deliveries_table.php`
- Create: `app/Models/WebPushDelivery.php`
- Test: `tests/Feature/WebPushDeliveryTest.php`

**Interfaces:**
- Consumes: `minishlink/web-push`, `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`.
- Produces contract: catégorie de préférence, traduction générique, cible interne validée, identifiant de notification et vérification d'accès.
- Produces: une livraison idempotente par `(notification_id, subscription_id)`, retry uniquement temporaire, révocation sur 404/410.

- [ ] **Step 1: Écrire les tests rouges avec transport simulé**

Tester VAPID absent, compte inactif, préférence off, cible inaccessible, blocage social, succès, 404/410, 429/5xx retry et deux exécutions du même job.

Run: `php artisan test tests/Feature/WebPushDeliveryTest.php`

Expected: FAIL car le transport n'existe pas.

- [ ] **Step 2: Installer la bibliothèque verrouillée et implémenter le contrat/target allowlist**

- [ ] **Step 3: Implémenter Action et Job avec double contrôle avant file et juste avant envoi**

Les logs utilisent seulement UUID interne, catégorie, code de résultat et corrélation. Le payload contient texte générique, cible relative, déduplication et unread count.

- [ ] **Step 4: Relancer tests ciblés et analyse**

Run: `php artisan test tests/Feature/WebPushDeliveryTest.php && composer analyse`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock .env.example config/services.php app/Contracts/WebPushNotification.php app/Notifications/Channels/WebPushChannel.php app/Jobs/SendWebPushNotification.php app/Actions/DeliverWebPushNotification.php app/Support/WebPushTarget.php database/migrations/2026_09_27_120000_create_web_push_deliveries_table.php app/Models/WebPushDelivery.php tests/Feature/WebPushDeliveryTest.php
git commit -m "feat(push): deliver queued vapid notifications"
```

### Task 8: Brancher toutes les notifications internes

**Files:**
- Modify: `app/Notifications/NewMessageNotification.php`
- Modify: `app/Notifications/NewMatchNotification.php`
- Modify: `app/Notifications/EventLifecycleNotification.php`
- Modify: `app/Notifications/PartnerAnnouncementNotification.php`
- Modify: `app/Notifications/PartnerAnnouncementDecisionNotification.php`
- Modify: `app/Notifications/PartnerModerationRequestedNotification.php`
- Modify: `lang/fr/notifications.php`
- Modify: `lang/en/notifications.php`
- Modify: `tests/Feature/MemberNotificationTest.php`
- Modify: `tests/Feature/EventNotificationTest.php`
- Modify: `tests/Feature/Admin/PartnerModerationNotificationTest.php`
- Create: `tests/Feature/WebPushNotificationCoverageTest.php`

**Interfaces:**
- Maps: message → `messages`, match → `matches`, cycle événement → `events`, annonce/décision partenaire → `partner_announcements`, modération → `administration`.
- Preserves: payloads database/broadcast et comportement temps réel existants.
- Produces: titre/corps génériques localisés ne contenant aucun message, nom de personne, événement ou partenaire.

- [ ] **Step 1: Ajouter une matrice rouge couvrant chaque classe, locale, préférence et donnée interdite**

Run: `php artisan test tests/Feature/WebPushNotificationCoverageTest.php tests/Feature/MemberNotificationTest.php tests/Feature/EventNotificationTest.php tests/Feature/Admin/PartnerModerationNotificationTest.php`

Expected: FAIL car les notifications n'implémentent pas le contrat Push.

- [ ] **Step 2: Implémenter le contrat dans les six notifications et les rendre toutes compatibles file/après commit**

- [ ] **Step 3: Ajouter les traductions système génériques FR/EN**

- [ ] **Step 4: Relancer la matrice et les tests de notifications existants**

Run: `php artisan test tests/Feature/WebPushNotificationCoverageTest.php tests/Feature/MemberNotificationTest.php tests/Feature/EventNotificationTest.php tests/Feature/Admin/PartnerModerationNotificationTest.php tests/Feature/NotificationCenterTest.php`

Expected: PASS sans modification du contrat en-app.

- [ ] **Step 5: Commit**

```bash
git add app/Notifications lang/fr/notifications.php lang/en/notifications.php tests/Feature/WebPushNotificationCoverageTest.php tests/Feature/MemberNotificationTest.php tests/Feature/EventNotificationTest.php tests/Feature/Admin/PartnerModerationNotificationTest.php
git commit -m "feat(push): notify every internal category"
```

### Task 9: Réception Push, clic, badge et réglages mobiles

**Files:**
- Modify: `resources/js/service-worker.ts`
- Create: `resources/js/lib/pwa/pushPayload.ts`
- Create: `resources/js/lib/pwa/pushPayload.test.ts`
- Create: `resources/js/composables/useWebPush.ts`
- Create: `resources/js/components/settings/WebPushDevices.vue`
- Modify: `resources/js/pages/settings/Notifications.vue`
- Modify: `resources/js/components/onboarding/InstallAppStep.vue`
- Modify: `resources/js/types/global.d.ts`
- Modify: `lang/fr/account.php`
- Modify: `lang/en/account.php`
- Test: `tests/Browser/NotificationSettingsTest.php`

**Interfaces:**
- Produces: permission explicite, conversion VAPID, `pushManager.subscribe`, synchro idempotente et révocation locale/serveur.
- Produces: `push`, `notificationclick`, focus d'un client existant ou `openWindow`, message au client et Badging API optionnelle.
- Validates: seules les cibles relatives allowlistées ; sinon `/notifications`.

- [ ] **Step 1: Écrire les tests rouges du payload et des parcours settings/onboarding**

Tester payload invalide, URL absolue/protocole hostile, déduplication, client existant, absence Badge API, permission refusée non redemandée, iOS navigateur, abonnement et révocation.

Run: `bun test resources/js/lib/pwa/pushPayload.test.ts && php artisan test tests/Browser/NotificationSettingsTest.php tests/Browser/OnboardingTest.php`

Expected: FAIL sur réception et UI absentes.

- [ ] **Step 2: Implémenter validation du payload et événements du worker**

Un payload invalide affiche une notification générique ouvrant `/notifications`. Toujours utiliser `event.waitUntil`.

- [ ] **Step 3: Implémenter le composable et la page réglages complète**

Inclure les cinq interrupteurs, appareils, tout désactiver, états réparables et instructions système après `denied`.

- [ ] **Step 4: Brancher l'action Push réelle dans la dernière étape du tutoriel**

- [ ] **Step 5: Relancer tests, types, lint et build**

Run: `bun test resources/js/lib/pwa/pushPayload.test.ts && php artisan test tests/Browser/NotificationSettingsTest.php tests/Browser/OnboardingTest.php && bun run types:check && bun run lint:check && bun run build`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/service-worker.ts resources/js/lib/pwa resources/js/composables/useWebPush.ts resources/js/components/settings/WebPushDevices.vue resources/js/pages/settings/Notifications.vue resources/js/components/onboarding/InstallAppStep.vue resources/js/types/global.d.ts lang/fr/account.php lang/en/account.php tests/Browser/NotificationSettingsTest.php tests/Browser/OnboardingTest.php
git commit -m "feat(push): add mobile subscription experience"
```

### Task 10: Exploitation, documentation produit et vérification complète

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/quality-ci-cd.md`
- Modify: `docs/operations.md`
- Modify: `README.md`
- Modify: `.env.example`
- Test: `tests/Feature/Infrastructure/PwaProductionTest.php`

**Interfaces:**
- Documents: génération contrôlée des clés VAPID, rotation, workers, variables, CSP/connect-src, autorisation réseau `*.push.apple.com`, métriques et diagnostic.
- Documents: limites iOS/Android, installation, offline volontairement minimal et procédure de rollback du worker.

- [ ] **Step 1: Ajouter le test rouge des en-têtes de production et de la configuration obligatoire**

Vérifier MIME/portée du worker, absence de cache HTTP long sur `service-worker.js`, cache immuable seulement sur assets hachés et disponibilité de `offline.html`/manifest.

Run: `php artisan test tests/Feature/Infrastructure/PwaProductionTest.php`

Expected: FAIL tant que l'infrastructure et les docs ne sont pas alignées.

- [ ] **Step 2: Ajuster la configuration serveur réellement présente et documenter opérations/produit**

Ne pas générer de clés au démarrage et ne pas exécuter de migration automatiquement dans Docker.

- [ ] **Step 3: Lancer toute la vérification pertinente**

Run: `php artisan wayfinder:generate --with-form && composer lint:check && composer analyse && composer test && bun run lint:check && bun run format:check && bun run types:check && bun run build && docker build --target runtime --tag dlp-friends:issue-203 .`

Expected: toutes les commandes PASS avec sorties fraîches.

- [ ] **Step 4: Audit manuel sur vrais moteurs mobiles**

Vérifier au minimum Chrome Android installé et Safari iOS/iPadOS installé : lancement standalone, safe areas, installation, opt-in Push, notification verrouillée générique, clic vers cible autorisée, révocation, hors connexion et mise à jour consentie. Consigner les appareils/versions et résultats dans la PR.

- [ ] **Step 5: Relire la différence contre les issues #203 et #182**

Run: `git diff main...HEAD --check && git status --short`

Expected: aucune erreur whitespace, aucun secret/asset inattendu et uniquement les changements du périmètre.

- [ ] **Step 6: Commit final documentation/infrastructure**

```bash
git add docs/PRD.md docs/quality-ci-cd.md docs README.md .env.example tests/Feature/Infrastructure/PwaProductionTest.php
git commit -m "docs(pwa): document web push operations"
```
