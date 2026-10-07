# Weekly email recap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer l’issue 180 avec récapitulatif activé par défaut, désactivable, envoyé le dimanche à 15 h à Paris.

**Architecture:** Deux booléens sur User portent les préférences e-mail, sans mélanger les catégories Web Push. Une Action calcule l’éligibilité et les compteurs avec les scopes Conversation existants. Une commande réserve une livraison unique par membre et échéance, puis un job revérifie les conditions et envoie un Mailable bilingue.

**Tech Stack:** Laravel 13, PHP 8.4, MySQL 8.4, Inertia/Vue 3, Pest et Pest Browser ; aucune nouvelle dépendance.

**Spec:** `docs/superpowers/specs/2026-10-07-weekly-email-recap-design.md`

## Global Constraints

- Dimanche 15 h dans `Europe/Paris`, y compris aux changements d’heure.
- Seuil strict : message reçu non lu âgé de plus de trois jours.
- Deux préférences indépendantes, toutes deux activées par défaut pour les nouveaux comptes et les comptes existants.
- Aucun nom, contenu privé ou donnée personnelle inutile dans l’e-mail ou la trace de livraison.
- Quatre tentatives maximum, délais de 60, 300 et 900 secondes.
- Transport Laravel configuré ; Mailpit en local ; aucun fournisseur de production choisi.
- Traductions Laravel FR/EN, logique métier serveur, migrations explicites.
- Préserver les modifications du checkout principal ; travail dans `feature/180-weekly-email-recap`.

## Review Focus

- Lecture ou désactivation pendant l’attente en file : abandon avant l’envoi (tâche 2).
- Deux workers ou deux lancements : une réservation unique et un seul envoi applicatif (tâche 2).
- Relance autour d’un changement d’heure : même échéance locale et aucune ancienne livraison rejouée (tâche 2).
- Formulaire ne contenant que les réglages e-mail : validation sans imposer une préférence partenaire (tâche 1).
- Suppression du destinataire après réservation : aucune livraison, trace supprimée à la purge (tâche 2).

---

### Task 1: Préférences e-mail et réglages

**Files:**
- Create: `database/migrations/2026_10_07_150000_add_weekly_recap_preferences_to_users.php`
- Modify: `app/Models/User.php`, `app/Http/Controllers/Settings/NotificationPreferenceController.php`, `app/Http/Requests/Settings/NotificationPreferenceUpdateRequest.php`
- Modify: `app/Actions/BuildUserDataExport.php`, `resources/js/pages/settings/Notifications.vue`, `lang/fr/account.php`, `lang/en/account.php`
- Test: `tests/Feature/Settings/WeeklyRecapPreferenceTest.php`, `tests/Browser/NotificationSettingsTest.php`

**Interfaces:**
- Produces: `User.weekly_recap_messages: bool`, `User.weekly_recap_matches: bool`, defaults true in SQL and model attributes.
- Produces: Inertia `emailPreferences: { weekly_recap_messages: boolean, weekly_recap_matches: boolean }`.
- Consumes: existing authenticated PATCH/DELETE `/settings/notifications` and existing switches/form.

- [ ] Write Pest tests asserting GET defaults true without a saved choice; PATCH each false/true persists independently and does not change another account or Web Push; malformed values rejected; email-only PATCH accepted; disable-all sets both false; export exposes both effective values. Migration regression: create a user before applying the new migration and assert both values true afterward.
- [ ] Run `php artisan test tests/Feature/Settings/WeeklyRecapPreferenceTest.php`; confirm failures come from missing props, validation or persistence.
- [ ] Add columns with true defaults, boolean casts and documented properties. Use explicit server updates rather than mass-assigning unrelated request fields. Extend required_without_all for email-only submissions and wrap preference writes with the existing user lock pattern.
- [ ] Add a dedicated translated e-mail section to the current form, with switches and hidden false inputs. Explain that the matches toggle controls the optional counter, while the messages toggle controls all weekly mail. Show Sunday 15 h Paris time.
- [ ] Add browser tests FR/EN: initial switches true, keyboard disable, save, refresh remains false; disable-all disables both. Verify export; retain account-deletion cascade coverage for task 2.
- [ ] Run targeted backend/browser tests, lint/type/format checks; commit `feat(notifications): add weekly recap preferences`.

### Task 2: Éligibilité, réservation et livraison hebdomadaire

**Files:**
- Create: `app/Actions/BuildWeeklyEmailRecap.php`, `app/Console/Commands/DispatchWeeklyEmailRecaps.php`, `app/Jobs/SendWeeklyEmailRecap.php`
- Create: `app/Models/WeeklyEmailRecapDelivery.php`, `database/migrations/2026_10_07_151000_create_weekly_email_recap_deliveries_table.php`
- Create: `app/Mail/WeeklyEmailRecapMail.php`, `resources/views/mail/weekly-recap.blade.php`, `lang/fr/weekly-recap.php`, `lang/en/weekly-recap.php`
- Modify: `routes/console.php`, `app/Actions/BuildUserDataExport.php`
- Test: `tests/Feature/Mail/WeeklyEmailRecapTest.php`

**Interfaces:**
- `BuildWeeklyEmailRecap::handle(User $user, CarbonImmutable $periodEnd): ?array` returns null if ineligible, otherwise `{ conversations: int, matches: int|null }`. Null matches omits the optional section.
- `DispatchWeeklyEmailRecaps` registers `notifications:dispatch-weekly-recaps`; no argument accepts arbitrary past/future sending windows.
- `WeeklyEmailRecapDelivery` stores user_id (cascade), period_ends_at, sent_at and skipped_at, timestamps; unique `(user_id, period_ends_at)`.
- `SendWeeklyEmailRecap::__construct(int $deliveryId)`; `handle(BuildWeeklyEmailRecap $build): void`.
- `WeeklyEmailRecapMail::__construct(int $conversations, ?int $matches)`; routes generated from configured application URL.

- [ ] Write fixtures with fixed Sunday `2026-10-11 15:00 Europe/Paris`: received unread at minus 3 days 1 second is eligible; exact minus 3 days and newer are excluded. Sent/read messages and matches alone never trigger mail. Counts distinguish conversations from message totals and include only matches within `[previous local Sunday 15 h, current local Sunday 15 h)`.
- [ ] Add cases for hidden other profile, blocks both directions, archived conversation, banned/deleting/deleted/unverified recipient, invalid address, inactive counterpart, partner-only account, disabled messages, disabled match counter. The recipient’s own hidden profile does not suppress otherwise visible conversations.
- [ ] Run targeted tests and confirm missing production behavior fails; implement Action with visible participant scopes and received unread filtering. Do not load message contents.
- [ ] Add dispatch tests using queue fake only at the queue boundary: eligible delivery reservation, repeated invocation creates one row/job, subsequent week gets a new reservation, unique DB constraint rejects duplicate pair. Implement chunked command with insert-or-ignore reservation and dispatch after commit.
- [ ] Add job tests using mail fake only at the external transport boundary: one successful send and sent_at; rerun sends nothing; reading, preference change, block, hide, deletion or expired period after queuing causes skip. Recompute counts and locale at send time.
- [ ] Implement job locking through a DB transaction with row lock on delivery, then user; send only pending deliveries and mark success. Transport exceptions roll back for retries; skipped terminal state prevents later replay. Set tries=4 and backoff=[60,300,900]. Add failing-transport tests confirming no sent_at is persisted and a later successful attempt sends once.
- [ ] Render real Mailable in FR/EN tests and assert intended counts, locale, internal authenticated app/settings links, absence of fixture author names and private content; optional section absent when matches is null. Reuse existing Markdown mail layout.
- [ ] Add schedule boundary tests: Sunday before 15 h not due, at 15 h due, Monday not due; test winter/summer transition dates. Configure weeklyOn(0, '15:00'), timezone Europe/Paris, withoutOverlapping and onOneServer. Derive the current period from the most recent Sunday 15 h so manual relaunch uses the same key.
- [ ] Add personal export delivery metadata without private content; test purge cascade deletes delivery rows. Run targeted suite plus existing notification, export and deletion tests; commit `feat(notifications): send Sunday weekly email recaps`.

### Task 3: Documentation et vérification complète

**Files:**
- Modify: `docs/PRD.md`, `docs/data-model.md`, `docs/operations.md`

**Interfaces:**
- Consumes: command, scheduler, fields and delivery table from tasks 1–2.
- Produces: documented operation and evidence of verification, without applying development migrations automatically.

- [ ] Update PRD implementation status and describe opt-out defaults, eligibility and Sunday 15 h. Document minimal delivery data, export/cascade and explicit migration in data model/operations; explain scheduler, queue workers, retries and Mailpit inspection.
- [ ] Run `composer lint:check`, `composer analyse`, Wayfinder generation, frontend lint/format/types and build; then `composer test` against dedicated MySQL test service. Do not share test DB concurrently with another suite.
- [ ] Run `docker build --target runtime --tag dlp-friends:ci .`; record failures or unavailable dependencies accurately. Verify local Mailpit rendering if services are available without mailing real users.
- [ ] Review the diff against the approved spec, every acceptance criterion and the five review-focus cases; address findings and rerun only affected checks.
- [ ] Commit documentation with Conventional Commits. Report branch, test evidence and remaining limitations. No deployment, migration of production or merge without authorization.
