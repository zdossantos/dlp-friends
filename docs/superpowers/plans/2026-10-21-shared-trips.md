# Trajets entre membres — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer l’issue 304 : proposer et gérer des trajets amicaux, échanger entre participants acceptés et signaler un désistement tardif.

**Architecture:** Domaine Trajet séparé des événements et visites ; participations transactionnelles, sorties volontaires conservées et signalements dédiés sans accès administratif au chat. Un stockage commun de groupes événement/trajet préserve les messages existants ; les émissions privées recalculent leurs destinataires à la diffusion. Les composants et la navigation actuels restent la base des écrans.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4, Inertia 3, Vue 3/TypeScript, Reka UI, Bun 1.3.14, Pest/Playwright.

**Spec:** [Conception validée le 21 octobre 2026](../specs/2026-10-21-shared-trips-design.md). La dernière validation de l’utilisateur porte sur le document écrit, y compris les motifs obligatoires pour refus, sortie volontaire, exclusion et annulation.

## Global Constraints

- Rencontres strictement amicales entre adultes ; aucun paiement, billet, GPS, résidence de profil ou contrôle de présence physique.
- Départ futur dans les douze prochains mois calendaires ; saisie Europe/Paris, stockage UTC.
- Audience initiale Mes matchs ; chaque élargissement Tous les membres confirmé côté serveur ; calendrier indépendant.
- Places voiture hors conducteur ; train/RER/bus cinquante personnes organisateur compris.
- Motifs obligatoires de 1 000 caractères maximum pour refus, sortie volontaire, exclusion et annulation ; explication et décision de signalement de 1 000 caractères maximum.
- Champs structurants verrouillés dès la première acceptation, même après sortie de tous les participants ; rendez-vous précis modifiable avec notification.
- Lecture seule trajet dès annulation ou vingt-quatre heures après départ ; délai événement inchangé.
- Signalement d’une sortie volontaire acceptée dans `[départ − 12 heures, départ[` ; aucune sanction automatique ni lecture administrative du chat.
- Purge trajet/chat trente jours après départ ou annulation antérieure ; dossier de signalement minimal détaché ; gel des données bannies préservé.
- Aucun texte utilisateur en dur : catalogues FR/EN ; contrôles serveur ; 320 px, clavier, clair/sombre et champs Safari.
- Aucun nouvel e-mail, dépendance ou moteur polymorphe général ; aucun reseed destructeur ni migration automatique Docker.
- Continuer dans le worktree isolé et la branche `feature/304-shared-trips`, PR #312 ; préserver les fichiers non suivis et le checkout principal modifié. La branche a déjà été créée depuis main après #310 ; ne pas la recréer depuis un main plus ancien.

## Review Focus

1. Heure d’été/hiver et fin de mois : la limite de douze mois et la fenêtre de douze heures utilisent des instants cohérents, sans accepter un horaire local inexistant (tâches 1 et 6).
2. Deux onglets ou reprise réseau : acceptation, sortie, exclusion et signalement ne doublonnent ni état ni notification (tâches 2 et 6).
3. Réduction d’audience après acceptation : le participant conserve son accès acquis ; un tiers sans match et une personne révoquée ne l’obtiennent jamais (tâches 1, 3 et 8).
4. Message mis en file puis exclusion : aucune réaction, notification Broadcast ou Push différée n’atteint l’ancien participant (tâches 4 et 7).
5. Retour depuis un profil ou un signalement, puis rechargement : filtres, défilement et brouillon sont conservés, aucun clic membre n’applique un filtre implicite (tâches 8 et 9).

## Carte des fichiers et contrats communs

Les chemins ci-dessous sont relatifs au worktree. Les nouveaux fichiers ne sont créés qu’au moment de leur tâche. Les noms EventChat existants sont remplacés par GroupChat dans les consommateurs de stockage ; les endpoints publics événements gardent leurs noms et paramètres. Aucun deuxième stockage de messages.

- Domaine : `app/Models/Trip.php`, `TripRegistration.php`, `TripDeparture.php` ; `app/Enums/TripTransport.php`, `TripDirection.php`, `TripAudience.php`, `TripRegistrationStatus.php` ; factories correspondantes. `TripDeparture` représente uniquement une sortie volontaire d’une participation acceptée, pas le voyage lui-même.
- `trips` : organisateur nullable pour conservation protégée, transport, sens, départ général, destination, `departs_at`, rendez-vous précis, audience, places voiture nullable, `first_accepted_at`, `cancelled_at`, motif d’annulation, timestamps. `trip_registrations` : paire trajet/membre unique, statut, motif de décision/sortie, dates de décision ; `trip_departures` : inscription, membre, départ prévu et sortie volontaire horodatée. Les nouvelles demandes peuvent réutiliser l’inscription, jamais ses sorties historiques.
- Groupe : `app/Models/GroupChat{,Message,Read,MessageReaction}.php`, `app/Policies/GroupChatPolicy.php`, `app/Data/GroupChatData.php` et Actions de groupe communes. `group_chats` possède `event_id` ou `trip_id`, jamais les deux ni aucun ; les trois tables enfants conservent les IDs et relations existants.
- HTTP : routes `trips.*` dans `routes/web.php`, contrôleurs/Form Requests explicites, `app/Data/TripWorkspaceData.php` pour props publiques/privées selon Policy.
- Interface : `resources/js/types/trip.ts`, `types/groupChat.ts`, pages `Trips/Index.vue`, `Trips/Mine.vue`, `Trips/Show.vue`, composants `trips/*`, `groups/GroupChat.vue` ; adaptation des pages Groupes et des composants événements existants.
- Modération : `TripDepartureReport`, Policy, Form Requests, Actions et contrôleurs membre/admin dédiés ; composants administratifs partagés quand leur second usage est réel.

Les tableaux PHP des créations/modifications contiennent uniquement les clés validées décrites par leurs Form Requests. Aucun contrôleur n’effectue une mutation métier directement.

---

### Task 1: Créer et consulter une proposition autorisée

**Files:** créer migrations trajets/inscriptions/sorties, les modèles/énumérations/factories ci-dessus, `app/Policies/TripPolicy.php`, `app/Actions/CreateTrip.php`, `app/Actions/UpdateTrip.php`, `app/Http/Requests/StoreTripRequest.php`, `UpdateTripRequest.php`, `app/Http/Controllers/TripController.php`, `app/Data/TripWorkspaceData.php`, `lang/{fr,en}/trips.php`, `tests/Feature/TripTest.php`. Modifier `routes/web.php`, `app/Models/User.php` pour relations/scopes.

**Interfaces:** `CreateTrip::handle(User $organizer, array $data): Trip`, `UpdateTrip::handle(User $organizer, Trip $trip, array $data): Trip` ; `TripPolicy::{view,viewPrivate,manage,request}(User $user, Trip $trip): bool` ; `Trip::scopeVisibleTo(Builder $query, User $viewer): void` ; `TripWorkspaceData::detail(User $viewer, Trip $trip): array`. Enums : transport car/train/rer/bus, direction outbound/return, audience matches/members, statut pending/accepted/refused/withdrawn/removed/blocked. Routes index/store/show/update/mine ; les tests de contrat Inertia peuvent utiliser `component('Trips/Show', false)` avant la création des écrans à la tâche 8, sans prétendre tester leur rendu. Réserver `trips/mine` avant `{trip}`.

- [ ] Écrire les tests Pest `creates_match_only_trip`, `requires_confirmation_for_every_expansion`, `pending_member_receives_no_private_props`, `accepted_member_keeps_access_after_audience_reduction`, `validates_paris_calendar_boundary_and_dst`. Assertions : 403 tiers, aucun `meeting_point`/participants/messages dans les props non autorisées, défaut matches et calendrier inchangé ; départ passé ou horaire local inexistant refusé, frontière des douze mois à Paris acceptée puis dépassée refusée. Ajouter un cas compte masqué/inactif/bloqué selon les Policies existantes.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect($trip->audience)->toBe(TripAudience::Matches);
$this->actingAs($pending)->get(route('trips.show', $trip))->assertInertia(
    fn ($page) => $page->component('Trips/Show', false)->missing('trip.meeting_point')->missing('trip.participants')->missing('messages')
);
```

- [ ] Exécuter `php artisan test tests/Feature/TripTest.php` ; constater un échec dû au nouveau comportement absent, pas à la configuration de base.
- [ ] Implémenter schéma, validation FR/EN, création/modification, audience confirmée et données publiques/privées. Places voiture entier positif ; non automobile ignore/refuse une capacité voiture. La liste courante exclut passés/annulés ; détail minimal historique propre après perte de participation. Ne pas ajouter de chat temporaire.
- [ ] Relancer la commande jusqu’au vert et `composer lint:check` sur le code ajouté ; vérifier que les mutations API directes respectent les mêmes règles.
- [ ] Commit `feat(trips): créer des propositions avec audience protégée`.

### Task 2: Décider, sortir, exclure et annuler sous verrou

**Files:** créer `app/Actions/{RequestTripParticipation,DecideTripRegistration,WithdrawFromTrip,RemoveTripParticipant,CancelTrip}.php`, contrôleurs `TripRegistrationController`, `TripRegistrationDecisionController`, `TripRegistrationRemovalController`, `TripCancellationController`, Form Requests correspondantes, `tests/Feature/{TripRegistrationTest,TripRegistrationConcurrencyTest,UpdateTripTest}.php`. Modifier `Trip`, `UpdateTrip`, `TripPolicy`, routes/catalogues.

**Interfaces:** `RequestTripParticipation::handle(User $member, Trip $trip): TripRegistration` ; `DecideTripRegistration::handle(User $organizer, TripRegistration $registration, bool $accept, ?string $reason): TripRegistration` ; `WithdrawFromTrip::handle(User $member, Trip $trip, ?string $reason): void` ; `RemoveTripParticipant::handle(User $organizer, TripRegistration $registration, string $reason): void` ; `CancelTrip::handle(User $organizer, Trip $trip, string $reason): void`. L’annulation technique est traitée séparément à la tâche 5, sans inventer un motif utilisateur. `Trip::remainingPlaces(): int` exclut le conducteur.

- [ ] Écrire les tests Pest des transitions : motif obligatoire sauf acceptation/retrait pending, auteur autorisé, capacité voiture 1 accepte 1 passager, non automobile 49 passagers maximum, sortie libère une place, retour exige nouvelle demande, répétition ne répète aucun effet. Sortie après départ autorisée mais nouvelles demandes/décisions interdites. Première acceptation fige les champs même après sortie ; rendez-vous et places restent modifiables dans leurs limites.
- [ ] Écrire le test de concurrence MySQL avec deux connexions/processus réels, d’après `EventRegistrationConcurrencyTest` : deux demandes pending pour une dernière place, un seul succès final. Un manque de `pcntl` ne compte pas comme preuve de concurrence ; utiliser le runtime Docker PHP si nécessaire.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect($trip->fresh()->remainingPlaces())->toBe(0);
expect($trip->registrations()->where('status', TripRegistrationStatus::Accepted)->count())->toBe(1);
expect($trip->fresh()->first_accepted_at)->not->toBeNull();
```

- [ ] Exécuter `php artisan test tests/Feature/TripRegistrationTest.php tests/Feature/TripRegistrationConcurrencyTest.php tests/Feature/UpdateTripTest.php` et enregistrer le rouge pertinent.
- [ ] Implémenter les Actions : comptes ordonnés puis trajet puis inscription, vérifications fraîches et transaction ; contrainte unique et reprise idempotente. Créer une `TripDeparture` uniquement pour sortie volontaire depuis accepted, en conservant l’horaire prévu. Exclusion et annulation exigent confirmation serveur en plus du motif. Les raisons ne sont sérialisées que pour leurs destinataires.
- [ ] Relancer les tests au vert ; commit `feat(trips): gérer les participations et les exclusions motivées`.

### Task 3: Mutualiser le stockage et les Actions de groupe

**Files:** créer `database/migrations/2026_10_21_110000_generalize_event_chats_to_group_chats.php`, modèles `GroupChat*`, `GroupChatPolicy`, `GroupChatData`, Actions `SendGroupChatMessage`, `MarkGroupChatRead`, `SetGroupChatMessageReaction`, `AnnounceGroupChatMembership`, `tests/Feature/{GroupChatMigrationTest,TripChatAuthorizationTest}.php`. Adapter les modèles/factories `EventChat*`, leurs références PHP, `resources/js/types/event.ts`, `resources/js/lib/eventChatState.ts`, `resources/js/components/events/EventChat.vue`, `EventWorkspaceData`, contrôleurs/Form Requests chat événements, `User`, `NewGroupMessageNotification` et tests existants concernés.

**Interfaces:** `GroupChat::{isReadOnly(): bool,readOnlyReason(): ?string}` ; `GroupChatPolicy::{view,send}(User $user, GroupChat $chat): bool` ; `SendGroupChatMessage::handle(User $author, GroupChat $chat, string $content): GroupChatMessage` ; `MarkGroupChatRead::handle(User $reader, GroupChat $chat, int $messageId): void` ; `SetGroupChatMessageReaction::handle(User $member, GroupChat $chat, GroupChatMessage $message, bool $reacted): array` (même payload de réaction que l’Action actuelle). `GroupChatData::{chat,message,unreadCount}` conservent les champs partagés existants et utilisent `group_chat_id` dans le contrat commun. Les adaptateurs événements préservent leurs URLs.

- [ ] Écrire un test qui crée un groupe événement avec messages, réaction et dernière lecture avant migration, exécute la migration, puis constate mêmes IDs/contenus/liens et nombre de non-lus. Vérifier qu’une ligne avec zéro ou deux contextes échoue à la contrainte MySQL.
- [ ] Écrire les tests du trajet : pending/ancien participant/tiers n’accèdent ni au fil, ni lecture, ni réaction/envoi ; annulation readonly immédiate, départ +23:59 writable puis +24:00 readonly ; événement conserve +7 jours. Interdire message ou curseur appartenant à un autre groupe/contexte.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect($group->messages()->pluck('id')->all())->toBe($originalMessageIds);
expect(GroupChatData::unreadCount($group, $member))->toBe($originalUnreadCount);
expect($tripGroup->isReadOnly())->toBeTrue(); // horloge figée à départ +24h
```

- [ ] Exécuter les deux nouveaux fichiers et les tests `EventChatSchemaTest`, `EventChatAuthorizationTest`, `EventChatUnreadTest` ; constater le rouge avant adaptation.
- [ ] Renommer les tables et clés en préservant contraintes/IDs ; ajouter le contexte trip exclusif. Basculer les consommateurs vers modèles/Actions communs, avec contrôleurs événement comme adaptateurs. Créer le groupe à la création du trajet et annonces d’adhésion génériques sans motif. Pas de faux événement, pas de table message trajet.
- [ ] Exécuter `php artisan test --filter='EventChat|GroupChat|TripChat'` et `composer analyse` ; commit `refactor(chat): partager les groupes entre événements et trajets`.

### Task 4: Diffuser uniquement aux membres encore autorisés

**Files:** créer `app/Events/{GroupChatMessageSent,GroupChatMessageReactionUpdated,GroupChatAccessChanged}.php`, `tests/Feature/GroupChatDeliveryTest.php`. Adapter anciennes émissions `EventChat*`, `routes/channels.php`, `resources/js/composables/useMemberRealtimeNotifications.ts`, `resources/js/lib/memberNotifications.ts`, `resources/js/components/events/EventChat.vue`, tests de broadcast existants.

**Interfaces:** événements sent/reaction portent l’ID du message et recalculent destinataires avec `User::scopeInGroupChat(Builder $query, GroupChat $chat): void` et `Gate(view)` à `broadcastOn()` ; access-changed porte uniquement groupe, contexte et état, jamais contenu ou rendez-vous. Écoute sur `App.Models.User.ID`, payload `group_chat_id`, déduplication par ID message.

- [ ] Écrire le test qui prépare une émission, retire un membre puis appelle sa diffusion : aucun canal personnel retiré et aucun canal partagé contenant le message. Répéter pour réaction, bannissement, auteur sorti, organisateur indisponible et message supprimé avant traitement ; aucun accès/donnée périmée ni exception de job abandonné.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect(collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all())
    ->not->toContain('private-App.Models.User.'.$removed->id)
    ->not->toContain('private-group-chat.'.$group->id)
    ->not->toContain('private-event-chat.'.$group->id);
```

- [ ] Exécuter `php artisan test tests/Feature/GroupChatDeliveryTest.php tests/Feature/EventChatBroadcastTest.php` et constater le rouge.
- [ ] Implémenter les événements communs à destinataires frais et adapter dès cette tâche l’écoute personnelle dans EventChat, la reconnexion et les non-lus ; retirer toute diffusion privée sur les anciens canaux partagés. Ne pas modifier le contrat des conversations privées à deux personnes.
- [ ] Relancer les tests au vert, puis les tests frontend de notifications ; commit `fix(chat): révoquer la diffusion vers les anciens participants`.

### Task 5: Appliquer blocages et inactivation aux trajets

**Files:** créer `app/Actions/{BlockTripRegistrations,DeactivateMemberTrips}.php`, `tests/Feature/TripBlockingTest.php`. Modifier `BlockUser`, `SetMemberBan`, `RequestAccountDeletion`, `DeleteMember`, `PrepareMemberDataForDeletion`, `app/Http/Controllers/BlockMemberController.php`, nouvelle `app/Http/Requests/BlockMemberRequest.php` et `resources/js/components/members/BlockMemberDialog.vue`.

**Interfaces:** `BlockTripRegistrations::handle(User $blocker, User $blocked): void` ; `DeactivateMemberTrips::handle(User $member): void`. Révocation technique sans motif libre utilisateur ; ces Actions ne créent aucune `TripDeparture`. Le formulaire de blocage exige une confirmation supplémentaire lorsque le bloqueur doit quitter un groupe ; le contrôle est revalidé côté serveur après chargement des groupes actuels.

- [ ] Écrire les cas blocage dans les deux sens avec organisateur : participant retiré ; entre participants : bloqueur seul retiré, y compris plusieurs trajets communs. Confirmation annulée/inexistante : aucun blocage ni retrait. Pas de restauration au déblocage et aucun signalement tardif disponible après retrait forcé.
- [ ] Tester bannissement/suppression : groupes organisés annulés techniquement, autres participations retirées, accès immédiatement coupé, masquage seul conforme à la règle existante. Reprise ne duplique pas les effets ; interleaving blocage/acceptation n’accorde pas de droit résiduel.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect($blockingRegistration->fresh()->status)->toBe(TripRegistrationStatus::Blocked);
expect($otherRegistration->fresh()->status)->toBe(TripRegistrationStatus::Accepted);
expect(TripDeparture::query()->count())->toBe(0);
```

- [ ] Exécuter `php artisan test tests/Feature/TripBlockingTest.php tests/Feature/BlockUserTest.php` et constater le rouge.
- [ ] Implémenter les effets et le même ordre de verrous ; ne pas révéler la cause d’un blocage dans un message/notification. Étendre seulement les points de cycle de compte existants nécessaires.
- [ ] Relancer les tests ciblés et ceux de bannissement/suppression concernés ; commit `feat(trips): retirer les accès après blocage ou inactivation`.

### Task 6: Signaler un désistement tardif et le traiter en administration

**Files:** créer migration `trip_departure_reports`, modèle/factory `TripDepartureReport`, `TripDepartureReportPolicy`, Actions `ReportTripDeparture`, `CloseTripDepartureReport`, contrôleurs `TripDepartureReportController`, `Admin/TripDepartureReportController`, Requests `StoreTripDepartureReportRequest`, `Admin/CloseTripDepartureReportRequest`, pages `Admin/TripDepartureReports/{Index,Show}.vue`, `tests/Feature/TripDepartureReportTest.php`, `tests/Browser/TripDepartureReportTest.php`. Modifier routes, `ModerationAudit`, catalogues modération et listes/historiques admin actuels.

**Interfaces:** `ReportTripDeparture::handle(User $organizer, TripDeparture $departure, string $details): TripDepartureReport` ; `CloseTripDepartureReport::handle(User $admin, TripDepartureReport $report, string $decision): void`. Dossier : auteur/cible, départ prévu, sortie, explication, open-key unique nullable, décision/décideur/dates ; référence sortie nullable `nullOnDelete`. Audit référence nullable dédiée au rapport trajet ; aucune liaison factice à `conversation_reports`.

- [ ] Écrire les jeux de données Pest : sortie à −12h exactement autorisée, −12h−1s refusée, −1s autorisée, départ exact/après refusés ; traversée changement d’heure calculée sur instants UTC ; pending/exclu/blocked refusés ; tiers et faux ID 403 ; nouvelle demande ne détruit pas la preuve, deux envois concurrents un seul dossier ouvert. Explication vide ou >1 000 refusée.
- [ ] Tester administrateur actif seul, décision obligatoire ≤1 000, audit consultation/clôture, dossier consultable après purge du trajet sans lieu précis/messages/identités du groupe. Signaler n’accorde jamais `view` au chat, ne bannit pas et ne notifie pas la cible de l’auteur.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect($departure->left_at->equalTo($departure->scheduled_departure_at->subHours(12)))->toBeTrue();
expect($report->target_user_id)->toBe($departure->user_id);
expect(Gate::forUser($admin)->allows('view', $trip->groupChat))->toBeFalse(); // admin non participant
```

- [ ] Exécuter `php artisan test tests/Feature/TripDepartureReportTest.php` et constater le rouge.
- [ ] Implémenter l’intervalle fermé/ouvert, verrou de sortie et contrainte d’unicité ; formulaire et fiche limités au dossier, réutilisation des confirmations, de la sanction et des composants admin utiles. Intégrer les dossiers trajets à l’historique reçu sans modifier le droit de lecture des conversations signalées.
- [ ] Exécuter tests feature et navigateur du dossier, régressions de modération existantes ; commit `feat(moderation): signaler les désistements tardifs des trajets`.

### Task 7: Notifications de gestion et protection des envois différés

**Files:** créer `app/Enums/TripNotificationType.php`, `app/Notifications/TripLifecycleNotification.php`, `app/Events/GroupMessageNotificationBroadcast.php`, `tests/Feature/{TripNotificationTest,GroupChatNotificationDeliveryTest}.php`. Modifier `WebPushPreference`, `NotificationCategory`, `MemberNotificationPresenter`, `NewGroupMessageNotification`, `GroupChatMessageLikedNotification` adaptée depuis `EventChatMessageLikedNotification`, `GroupMessageDatabaseChannel`, `DeliverWebPushNotification`, réglages/types/catalogues notifications.

**Interfaces:** `TripLifecycleNotification::__construct(int $tripId, TripNotificationType $type, ?int $registrationId = null)` ; préférence `trips` et catégorie `trips`, défaut activé comme les préférences existantes ; messages utilisent toujours `group_messages`. L’événement Broadcast personnalisé utilise les identifiants de notification/destinataire et recontrôle le droit lors de sa diffusion effective. Les clés de traduction et cibles sont communes aux trois canaux, sans motif ou rendez-vous précis.

- [ ] Écrire tests des destinataires demande/décision/sortie/exclusion/rendez-vous/annulation, préférence désactivée et reprise sans doublon. Inspecter payloads : aucune raison libre ni lieu précis. Une personne exclue ouvre seulement sa décision minimale ; un tiers ne le peut pas.
- [ ] Préparer notification Broadcast et job Push avant exclusion/blocage/inactivation/lecture du message puis les traiter après : aucune livraison privée ; supprimer le groupe avant traitement ne fait pas échouer le worker. Aucune notification e-mail émise.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
$transport = Mockery::mock(WebPushTransport::class);
$transport->shouldNotReceive('send');
(new DeliverWebPushNotification($transport))->handle($subscription, $notification); // après révocation
expect($broadcastEvent->broadcastOn())->toBe([]);
Mail::assertNothingSent();
expect($payload)->not->toHaveKeys(['meeting_point', 'reason']);
```

- [ ] Exécuter `php artisan test tests/Feature/TripNotificationTest.php tests/Feature/GroupChatNotificationDeliveryTest.php tests/Feature/GroupMessageNotificationTest.php` ; constater le rouge.
- [ ] Implémenter notifications afterCommit et contrôles frais aux étapes DB, Broadcast et Push. Ne pas laisser le canal Laravel broadcast standard réémettre un payload privé déjà préparé sans revalidation. Conserver les compteurs/read-cursors individuels et le réglage messages de groupe.
- [ ] Relancer les tests et les suites notification/préférences existantes ; commit `feat(trips): notifier les changements sans exposer de données privées`.

### Task 8: Livrer les listes et la gestion responsive des trajets

**Files:** créer `resources/js/types/trip.ts`, pages `Trips/{Index,Mine,Show}.vue`, composants `trips/{TripCard,TripForm,TripInformation,TripParticipantList,TripRegistrationActions,TripDepartureReportForm}.vue`, `app/Http/Controllers/{MyTripController,TripParticipantController,TripRegistrationIndexController}.php`, `tests/Browser/TripTest.php`. Modifier `TripWorkspaceData`, `OutingsNavigation`, `lang/{fr,en}/{trips,common}.php` selon libellés réutilisables.

**Interfaces:** données `TripSummary`, `TripDetail`, `TripRegistration` et `TripDeparture` dérivées des sorties serveur tâches 1/2/6 ; `TripForm` émet uniquement les champs de Store/UpdateTripRequest. Les listes de demandes et participants sont autorisées séparément ; jamais un tableau privé chargé dans la page publique. Ouvrir les profils via la présentation publique/panneau existants et leurs endpoints contrôlés.

- [ ] Écrire parcours Pest Browser mobile : créer voiture, date Paris courante, demander/accepter, plein visible sans demande, motif d’exclusion et place libérée, modification du rendez-vous, annulation rouge, signaler sortie tardive. Test annulation/fermeture de chaque confirmation sans mutation.
- [ ] Tester clic membre → profil, fermeture → liste sans filtre implicite, retour/avance avec filtres et position conservés ; direct-entry avec fallback. Ajouter FR/EN, clair/sombre, 320 px, focus/clavier et champs date/mois sans débordement.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
$page->click('@trip-organizer')->assertSee($organizer->profile->display_name);
$page->click('@profile-close')->assertPathIs('/trips');
$page->assertSeeIn('@trip-transport-filter', __('trips.transport.car')); // filtre conservé
```

- [ ] Exécuter `php artisan test tests/Browser/TripTest.php` et constater les contrôles manquants avant implémentation.
- [ ] Implémenter avec `ResponsivePanel`, `ResponsiveConfirmation`, Select Reka, `BackLink`, `UserAvatar` et `ProfilePresentation`. Présenter état pending/refused/withdrawn/removed et motif personnel sans autres identités ; Mes trajets affiche historique minimal. Annoncer les erreurs traduites et conserver les saisies après validation.
- [ ] Exécuter le fichier navigateur, `bun run lint:check`, `bun run types:check`, `bun run format:check` ; commit `feat(trips): proposer et gérer les trajets dans Sorties`.

### Task 9: Ouvrir les deux contextes dans une seule messagerie

**Files:** créer `resources/js/components/groups/GroupChat.vue`, `resources/js/types/groupChat.ts`, `resources/js/lib/groupChatState.ts`, `app/Http/Controllers/{TripChatController,TripChatMessageController,TripChatMessageReactionController,TripChatReadController}.php`, Form Requests chat trajet, `tests/Browser/TripChatTest.php`, `tests/Frontend/groupChatState.test.js`. Modifier `EventChat.vue`, `Conversations/{Index,Group}.vue`, `ConversationIndexController`, `TripWorkspaceData`, types événements, routes et layout de discussion.

**Interfaces:** composant commun consomme `chat`, `messages`, `context: {type: 'event' | 'trip', id: number}`, endpoints concrets et état readonly ; émet les mêmes interactions fil/compositeur existantes. Le panneau d’information reste un adaptateur de domaine. `mergeGroupChatMessages<T extends { id: number }>(current: T[], incoming: T[]): T[]` et `groupChatUnreadIncrement(message: { author_user_id: number }, currentUserId: number, chatIsOpen: boolean): 0 | 1` remplacent les helpers événements avec le même comportement ; état brouillon `group-chat-draft:ID`, migration du brouillon événement existant si présent.

- [ ] Écrire les parcours deux sessions : message/réaction entre acceptés, historique antérieur paginé sans saut ni doublon, non-lus individuels, reconnexion, brouillon conservé après profil et retour/rechargement ; aucun espace de navigation inférieur en mobile. Exclusion avec panneau ouvert : plus de lecture/envoi/diffusion, groupe retiré de la liste.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```javascript
const message = { id: 42, group_chat_id: 7, author_user_id: 8 };
expect(mergeGroupChatMessages([message], [message])).toHaveLength(1);
expect(groupChatUnreadIncrement(message, 8, false)).toBe(0);
```

- [ ] Exécuter `php artisan test tests/Browser/TripChatTest.php` et `bun test tests/Frontend/groupChatState.test.js` ; constater le rouge.
- [ ] Implémenter l’adaptateur commun sur MessageTimeline/Composer/RealtimeStatus ; “Voir le groupe” ouvre les informations autorisées sans deuxième chat. Ajouter groupes trajets à la requête/liste et aux non-lus, préserver les URLs événements et true-history BackLink.
- [ ] Exécuter nouveaux tests et `tests/Browser/EventChatMembershipTest.php`, tests navigateur conversations/événements concernés ; commit `feat(chat): intégrer les trajets à la messagerie de groupe`.

### Task 10: Exporter et purger sans détruire les données protégées

**Files:** créer `app/Console/Commands/PurgeExpiredTrips.php`, `tests/Feature/{TripDataControlTest,Console/PurgeExpiredTripsTest}.php`. Modifier `BuildUserDataExport`, `PrepareMemberDataForDeletion`, `PurgeDeletedUser`, `routes/console.php`, règles de suppression et catalogues d’export concernés.

**Interfaces:** commande `trips:purge-expired`, quotidienne à 04:00 Europe/Paris, `withoutOverlapping` et `onOneServer` comme les purges existantes ; état terminal calculé depuis annulation avant départ, sinon départ. Les dossiers sont détachés avant suppression de leurs références ; aucun message copié dans le dossier.

- [ ] Écrire tests −1s/exactement trente jours, annulation avant départ, reprise, dossier ouvert/clôturé détaché, nouveau job après bannissement et suppression d’un autre compte lié à un banni. Aucun rendez-vous/messages conservé dans le rapport ; données bannies requises non détruites par cascade.
- [ ] Tester export : uniquement trajets/inscriptions/messages/motifs propres autorisés ; aucun message d’autrui, liste privée, auteur de signalement reçu, admin ou secret ; préférences incluses. Suppression et purge retirent les nouvelles relations selon politique existante.

Assertions centrales du test, avec les fixtures décrites ci-dessus :

```php
expect(Trip::query()->find($tripId))->toBeNull();
expect($report->fresh()->trip_departure_id)->toBeNull();
expect($report->fresh()->details)->toBe($originalDetails);
```

- [ ] Exécuter `php artisan test tests/Feature/TripDataControlTest.php tests/Feature/Console/PurgeExpiredTripsTest.php` et constater le rouge.
- [ ] Implémenter extension des graphes de conservation, purge sous verrou et export minimal ; ne pas élargir le stockage de données personnelles pour simplifier les FK.
- [ ] Relancer nouveaux tests et suites export/suppression/modération concernées ; commit `feat(privacy): couvrir les trajets dans le cycle des données`.

### Task 11: Vérifier le parcours livré et préparer la PR à la revue

**Files:** modifier `docs/{PRD,security-privacy,data-model,technical-architecture,design-system,editorial-guidelines}.md` seulement pour les comportements livrés ; tests des tâches précédentes et fixtures de démonstration existantes si nécessaires. Ne pas ajouter un seed destructeur ni faux avatars invalides.

- [ ] Relire chaque section de la spécification et associer son comportement au test qui passe ; compléter les cas manquants avant de marquer une tâche terminée. Confirmer que la preuve concurrence MySQL et les deux sessions temps réel ont réellement exécuté leurs assertions, sans skip.
- [ ] Exécuter `composer ci:check`, `bun run test:unit`, `bun run build` et `docker build --target runtime --tag dlp-friends:ci .` ; préserver sorties et statuts. Corriger les erreurs, puis rejouer les contrôles concernés ; ne pas répéter une suite verte sans changement justifiant la répétition.
- [ ] Relire migrations sur base jetable avec données événement existantes, `git diff --check` et diff complet ; rechercher payloads privés, textes non traduits, anciens noms de tables/colonnes et canaux partagés. Aucun élargissement de Policy événement ou admin non prévu.
- [ ] Construire et déployer les services du projet Compose local existant avec conservation de ses volumes. Exécuter explicitement la migration après sauvegarde adaptée ; vérifier services sains, accès LAN et absence de perte des comptes de test. Ajouter seulement des trajets fictifs identifiés et idempotents si les données actuelles n’en contiennent pas.
- [ ] Vérifier sur l’instance LAN le parcours demande → acceptation → discussion → sortie/exclusion → accès coupé et retour historique. Effectuer un contrôle WebKit mobile des champs date/mois et layout ; mentionner séparément toute vérification physique non réalisée.
- [ ] Commit de documentation, push de la branche, réécriture du titre/corps de #312 autour du comportement final et des preuves récentes. Passer prête à relire seulement quand l’implémentation et les checks sont terminés ; attendre les checks requis, ne pas fusionner sans autorisation portant sur cette PR.

## Handoff

Plan proposé pour relecture, pas encore exécuté. Recommandation : exécution native dans cette session, tâche par tâche, car les contrats de participation, messagerie et modération se réutilisent fortement. Une revue indépendante finale reste nécessaire ; si l’utilisateur préfère une mise en œuvre et revue par sous-agents à chaque tâche, employer la méthode correspondante après son choix explicite.
