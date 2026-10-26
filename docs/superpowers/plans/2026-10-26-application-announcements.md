# Application announcements Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox syntax for tracking.

**Goal:** Livrer le parcours administratif et membre de l’issue 317.
**Architecture:** Annonce bilingue et livraisons uniques par annonce/utilisateur.
Audience figée à l’aperçu, contrôles serveur à la confirmation et à la livraison.
Notifications persistantes et transport Push existants, panneau existant.
**Tech Stack:** Laravel 13, MySQL, Pest, Inertia 3, Vue 3, TypeScript.
**Spec:** docs/superpowers/specs/2026-10-26-application-announcements-design.md

## Global Constraints
- FR/EN, aucun HTML de contenu, liens validés par SafeHttpsUrl.
- Tous rôles ; comptes actifs vérifiés sans suppression, préférence par défaut true.
- Push choisi à l’envoi, false par défaut ; aucun e-mail ni rechargement PWA.
- Ne pas altérer les règles des annonces partenaires.

## Review Focus
- Deux confirmations/reprises concurrentes ne doivent pas dupliquer une livraison.
- Préférence ou compte désactivé après l’aperçu doit être exclu à la livraison.
- Suppression individuelle suivie d’une reprise ne doit pas recréer la notification.
- Erreur de mise en file après commit doit rester récupérable.
- Compte partenaire ou admin seul doit pouvoir consulter ses annonces.

### Task 1: Audience, aperçu, confirmation et livraison
Files: modèles ApplicationAnnouncement et ApplicationAnnouncementDelivery,
migration dédiée, Policy, Form Request et contrôleur Admin/ApplicationAnnouncementController,
actions PrepareApplicationAnnouncement et SendApplicationAnnouncement,
jobs DeliverApplicationAnnouncement et DispatchApplicationAnnouncement,
notification ApplicationAnnouncementNotification, User scope.
Interfaces: PrepareApplicationAnnouncement::handle(User, array): ApplicationAnnouncement;
SendApplicationAnnouncement::handle(ApplicationAnnouncement): void;
DeliverApplicationAnnouncement(int deliveryId), DispatchApplicationAnnouncement(int announcementId).
- [x] Écrire tests Feature/ApplicationAnnouncementTest pour autorisations, validation,
  audience globale/sélectionnée, compteurs, double confirmation, exclusions tardives,
  reprise, suppression puis reprise, Push facultatif et consentement.
- [x] Exécuter les tests et constater l’échec des routes absentes.
- [x] Implémenter la migration, les contrôles, le snapshot d’audience et les jobs.
- [x] Faire passer les tests et vérifier Pint/PHPStan.

### Task 2: Interface, consultation et préférences
Files: pages Admin/ApplicationAnnouncements, navigation AppSidebar,
MemberNotificationPresenter, NotificationCategory/WebPushPreference,
NotificationItem, Notifications/Index, filtres/types, traductions FR/EN,
NotificationPreferenceUpdateRequest, export, docs PRD/security/data-model/e2e.
Interfaces: props annonces, audience recherchable et aperçu serveur ; notification
category application, contenu localisé, lien facultatif.
- [x] Écrire un test navigateur de rédaction bilingue, aperçu, confirmation,
  consultation responsive et ouverture explicite du changelog.
- [x] Implémenter avec les composants existants ; le premier essai navigateur rouge
  était invalide (base de test partagée), puis les parcours ont été validés séquentiellement.
- [x] Vérifier refus de Push sans abonnement, langue, préférence et contrôle global.
- [x] Exécuter tests ciblés, lint, format, types, build et contrôles PHP.

### Task 3: Vérification et livraison
- [ ] Exécuter les suites pertinentes séquentiellement sur MySQL isolé.
- [x] Faire relire le diff complet, corriger les anomalies significatives avec tests.
  Deux corrections rouge/vert : préférence concurrente MySQL et export de la
  livraison après suppression de notification. Aucun constat reporté.
- [ ] Commit, PR vers main, rattachement au chat et attente de tous les checks CI.
- [ ] Rapporter les preuves et limites, sans fusionner ni déployer.
