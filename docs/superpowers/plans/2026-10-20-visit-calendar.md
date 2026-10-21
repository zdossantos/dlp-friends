# Visit Calendar Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Livrer l’issue 305 : séjours personnels, audience globale et consultation autorisée dans un agenda et un calendrier mobile.

**Architecture:** Une ligne Visit représente un séjour inclusif. Les requêtes Eloquent appliquent les autorisations avant agrégation et pagination ; Inertia reçoit seulement les données accessibles. Vue réutilise les primitives adaptatives et la navigation membre existantes.

**Tech Stack:** PHP 8.4, Laravel 13, MySQL 8.4, Inertia 3, Vue 3, TypeScript, Tailwind, Reka UI, Bun 1.3.14, Pest et Pest Browser/Chromium.

**Spec:** `docs/superpowers/specs/2026-10-20-visit-calendar-design.md` (approuvée le 20 octobre 2026).

## Global Constraints

- Un séjour dure de 1 à 30 jours inclus, bornes comprises.
- Le dernier jour ne dépasse pas aujourd’hui plus 12 mois calendaires, dans le fuseau applicatif.
- Les séjours qui se chevauchent sont autorisés ; une personne compte une seule fois par jour.
- Un séjour terminé disparaît des surfaces ; suppression automatique après 30 jours, suspendue pour les comptes bannis.
- `hidden` est la préférence initiale ; visites, liens sociaux et trajets ont des audiences indépendantes.
- Aucun texte visible codé en dur : catalogues Laravel FR/EN, y compris validation et accessibilité.
- Respecter 320 px, clair/sombre, clavier, lecteur d’écran et cibles de 44 px.
- Aucun trajet, message, invitation, inscription ou nouveau contact n’est créé.
- Aucune migration automatique au démarrage Docker ; aucune dépendance supplémentaire.
- Préserver le checkout initial et ses modifications ; travailler dans le worktree `issue-305-visits`, branche `feature/305-visit-calendar`, créée depuis `origin/main` actualisé.

## Review Focus

- Date invalide, année bissextile et passage heure été/hiver : refuser une date impossible sans décaler une journée (tâche 1).
- Ancien formulaire d’audience resté ouvert : la transition réelle persistée détermine l’exigence de confirmation sous verrou (tâche 2).
- Recherche contenant `%`, `_`, apostrophe ou noms identiques : recherche littérale, pas d’expansion SQL ni de confusion entre personnes (tâche 3).
- Blocage ou masquage entre deux pages de résultats : aucun résultat devenu privé au chargement suivant (tâche 3).
- Retour navigateur après ouverture d’une liste et changement de mois : restaurer filtres, vue, date, défilement et focus, sans conserver une audience seulement envisagée (tâche 4).

---

### Task 1: Gérer les séjours personnels

**Files:** Créer `database/migrations/2026_10_20_100000_create_visits_table.php`, `app/Models/Visit.php`, `database/factories/VisitFactory.php`, `app/Policies/VisitPolicy.php`, `app/Http/Requests/StoreVisitRequest.php`, `app/Http/Requests/UpdateVisitRequest.php`, `app/Http/Controllers/VisitController.php`, `lang/fr/visits.php`, `lang/en/visits.php`, `tests/Feature/ManageVisitTest.php`. Modifier `app/Models/User.php`, `routes/web.php`.

**Interfaces:** `Visit::user(): BelongsTo`; `User::visits(): HasMany`; champs `starts_on`, `ends_on` convertis en dates immuables. `VisitPolicy::create(User): bool`, `update(User, Visit): bool`, `delete(User, Visit): bool`. Routes `visits.store` POST `/visits`, `visits.update` PATCH `/visits/{visit}`, `visits.destroy` DELETE `/visits/{visit}` ; redirection vers `visits.index` avec contexte validé. `visits.index` GET `/visits` fournit d’abord un point de retour minimal authentifié ; la tâche 3 remplace cette réponse par `VisitController::index(VisitIndexRequest): Response` et la page complète, avant exposition de la navigation.

- [x] Préparer les dépendances et la base de référence selon la première étape de tâche 6, avant tout cycle rouge/vert.
- [x] Écrire des tests Pest avec RefreshDatabase et date figée au 20 octobre 2026 : création `[2026-10-23,2026-10-25]`, modification du séjour entier, suppression, propriétaire forcé depuis la session, refus tiers et accès membre requis. AssertDatabaseHas sur les deux bornes, aucun événement ni chat créé.

```php
test('a member can create an inclusive stay without a group', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $member = User::factory()->withProfile()->create();
    $this->actingAs($member)->post(route('visits.store'), [
        'starts_on' => '2026-10-23', 'ends_on' => '2026-10-25',
    ])->assertRedirect(route('visits.index'));
    $this->assertDatabaseHas('visits', [
        'user_id' => $member->id,
        'starts_on' => '2026-10-23', 'ends_on' => '2026-10-25',
    ]);
    $this->assertDatabaseEmpty('events');
    $this->assertDatabaseEmpty('event_chats');
});
```

- [x] Ajouter les datasets : un jour et 30 jours acceptés, 31 jours/refus, fin avant début/refus, début passé/refus, borne du 20 octobre 2027 acceptée et 21 octobre/refus. Dates impossibles, séjour traversant décembre/janvier et 29 février valide/invalide. Séjour en cours : conserver le début passé autorisé, le changer vers un autre jour passé interdit.
- [x] Exécuter `php artisan test tests/Feature/ManageVisitTest.php` et constater un échec dû à l’absence de la fonctionnalité, pas à une base indisponible.
- [x] Ajouter la table (clé étrangère en cascade, index `(user_id, starts_on, ends_on)` et `(ends_on, starts_on)`), modèle/factory, policy, Form Requests et mutations du contrôleur. Utiliser `Y-m-d` strict, dates Paris et limites calendaires sans conversion UTC. Reprendre les middlewares des événements ; ne jamais accepter `user_id` client. Le chevauchement est volontairement permis.
- [x] Exécuter le même test et `composer lint:check`, puis committer les fichiers de la tâche : `feat(visits): manage inclusive personal stays`.

### Task 2: Appliquer les audiences et leur confirmation

**Files:** Créer `database/migrations/2026_10_20_100100_add_visits_visibility_to_users.php`, `app/Enums/VisitsVisibility.php`, `app/Actions/UpdateVisitsVisibility.php`, `app/Http/Requests/UpdateVisitsVisibilityRequest.php`, `app/Http/Controllers/VisitsVisibilityController.php`, `tests/Feature/VisitsVisibilityTest.php`. Modifier User, Visit, VisitPolicy, routes web et catalogues visits de la tâche 1.

**Interfaces:** enum `Hidden='hidden'`, `Matches='matches'`, `Members='members'` ; `User::$visits_visibility`. `UpdateVisitsVisibility::handle(User $user, VisitsVisibility $visibility, bool $confirmed): void`. PATCH `/visits/visibility`, nom `visits.visibility.update`, avant routes avec identifiant. `Visit::scopeVisibleTo(Builder $query, User $viewer): void` ; `scopeOverlapping(Builder $query, CarbonImmutable $from, CarbonImmutable $through): void`. `VisitPolicy::view(User, Visit): bool` utilise la même sélection autorisée.

- [x] Écrire les tests : défaut hidden sur compte existant/nouveau, propriétaire autorisé, hidden inaccessible, matches avec/sans match et members sans match. Réduction immédiate et modification de séjour sans modification d’audience. Vérifier que ni social_links_visibility ni une autre préférence ne change.
- [x] Tester `hidden → members` et `matches → members` sans confirmation : erreur sur `confirmed`, préférence inchangée et absence d’accès réel. Avec `confirmed=true`, accès accordé. Tester un formulaire ancien : members puis réduction à hidden puis soumission members non confirmée, refusée selon l’état actuel.
- [x] Couvrir match supprimé, blocages dans les deux sens, compte inactif/non vérifié/mineur/sans rôle membre, profil masqué/incomplet et avatar inactif. Le propriétaire masqué garde ses propres séjours. L’accès direct ne doit pas contourner le scope.
- [x] Exécuter `php artisan test tests/Feature/VisitsVisibilityTest.php`, constater le rouge, puis implémenter enum, migration, casts, action transactionnelle avec `lockForUpdate`, validation et scope SQL. Les conditions owner/others sont groupées pour éviter un OR qui contourne les autres filtres.
- [x] Relancer les tests des tâches 1–2 ; committer `feat(visits): enforce audiences and explicit sharing consent`.

### Task 3: Construire agenda, calendrier et liste paginée

**Files:** Créer `app/Http/Requests/VisitIndexRequest.php`, `app/Data/VisitWorkspaceData.php`, `app/Http/Controllers/VisitAttendeeController.php`, `tests/Feature/VisitWorkspaceTest.php`. Compléter VisitController, routes et catalogues visits.

**Interfaces:** `VisitWorkspaceData::build(User $viewer, array $filters): array` avec `filters={month:Y-m,day:Y-m-d,view:agenda|calendar,audience:members|matches,search:string,member:int|null}`. GET `/visits` = `visits.index` rend `Visits/Index`. GET `/visits/attendees` = `visits.attendees.index` rend la même page avec `panel={kind:'attendees',date,members:{data,links,meta}}` et 30 membres par page. Props communes `filters`, `visibility`, `ownVisits`, `days`, `closeHref` ; `days[]={date,isToday,ownVisitIds,otherCount,preview}` ; preview max 3 `{id,display_name,avatar,is_match}` ; ownVisits `{id,starts_on,ends_on}`. Ne jamais exposer les IDs de séjours d’autrui.

- [x] Écrire un fixture de 24 membres autorisés dont 11 matchs, un propriétaire avec deux séjours chevauchants et plusieurs propriétaires privés/bloqués. Assert Inertia sur chaque date : `otherCount=24`, preview de 3, filtre matches `otherCount=11`, propriétaire exclu, chevauchements dédupliqués. Vérifier inclusion des deux bornes et aucune date terminée.
- [x] Ajouter 105 personnes sur un jour : total exact 105, page 1 de 30, toutes les personnes accessibles en parcourant les pages sans doublon pour une base inchangée. Bloquer/masquer entre deux requêtes puis vérifier leur exclusion et le nouveau total.
- [x] Tester recherche `Élodie` via `elodie` et `ELODIE`, recherche vide et sans résultat, deux noms identiques, sélection d’un ID puis prochaines dates accessibles. Les recherches `%`, `_` et apostrophe sont littérales ; un membre privé donne zéro résultat sans signaler ses séjours. Les propres visites restent présentes sous tous ces filtres.
- [x] Ajouter paramètres invalides (mois impossible, jour invalide, vue inconnue, recherche trop longue, identifiant autre type et pagination invalide) et navigation décembre/janvier. Exécuter `php artisan test tests/Feature/VisitWorkspaceTest.php` ; constater le rouge.
- [x] Implémenter la Form Request avec recherche limitée à 100 caractères, pagination entière positive, mois et jour cohérents. Agréger côté MySQL sur une série de dates du mois bornée (au plus 31), `count(distinct user_id)` après scope et filtres. Charger seulement trois personnes par jour pour l’aperçu ; la liste complète est paginée par membres, ordre nom puis ID. Utiliser la collation accent/casse insensible et échapper les métacaractères LIKE. Ne jamais charger tous les séjours pour filtrer en PHP.
- [x] Relancer les tâches 1–3 et `composer analyse` ; committer `feat(visits): query authorized calendar and attendee counts`.

### Task 4: Livrer les parcours Vue et la navigation Sorties

**Files:** Créer `resources/js/pages/Visits/Index.vue`, `resources/js/types/visit.ts`, `resources/js/components/visits/VisitWorkspace.vue`, `VisitAgenda.vue`, `VisitCalendar.vue`, `VisitDayDetail.vue`, `VisitForm.vue`, `VisitVisibilityControl.vue`, `resources/js/components/outings/OutingsNavigation.vue`, `resources/js/components/common/ResponsivePanel.vue`, `ResponsiveConfirmation.vue`, `tests/Browser/VisitTest.php`. Modifier `components/events/AdaptiveEventPanel.vue`, `EventConfirmationDialog.vue`, `EventWorkspace.vue`, `components/MemberBottomNavigation.vue`, `resources/js/app.ts` si la résolution du layout le requiert, catalogues visits, créer `lang/{fr,en}/outings.php` et ajuster les tests de navigation concernés.

**Interfaces:** types TypeScript identiques aux props de tâche 3. ResponsivePanel reçoit open/title/description, émet update:open et expose les hooks de focus pour les wrappers événements ; aucun import du domaine événements dans le composant commun. ResponsiveConfirmation reçoit open/title/description/confirmLabel/busy/destructive et émet confirm/update:open. Les wrappers événements préservent props, slots, test selectors et scroll. OutingsNavigation reçoit `active:'events'|'visits'` ; pas d’onglet Trajets fonctionnel avant #304.

- [x] Écrire les tests Pest Browser sur les réponses réelles : défaut agenda, création d’un jour/séjour, modification et suppression entière avec confirmation. Bascule calendrier, navigation de mois et retour agenda ; dates du séjour visibles aux jours inclusifs.
- [x] Couvrir jour de 24 personnes : repère Toi, trois profils, liste complète, matches à 11, recherche accentuée et clic nom. Couvrir 105 résultats et navigation paginée. Ouvrir depuis une page défilée puis fermer/Retour : même mois, vue, jour, filtres, position et focus.
- [x] Couvrir confirmation : choix members puis annuler, fermeture et Retour conservent l’ancienne audience ; confirmer la change réellement et permet l’accès à une autre session. Une édition de visite ne redemande pas l’accord. Exécuter `php artisan test tests/Browser/VisitTest.php` et constater le rouge.
- [x] Extraire les seules enveloppes réutilisées, conserver les wrappers événements. Ajouter les composants visites : formulaire Date natif et erreurs associées, agenda groupé, grille de 7 colonnes sans noms/avatars empilés, noms accessibles avec date et total exact, marqueur Toi textuel et graphique. `99+` est un libellé compact localisé, pas le total de la liste.
- [x] Utiliser les paramètres Inertia pour l’état partageable ; `preserveScroll` et état de panneau pour restituer le contexte. Recherche différée avec annulation des requêtes précédentes ; le sélecteur de visibilité ne modifie pas la valeur persistée avant succès serveur. Aucun filtre local ne sert d’autorisation.
- [x] Intégrer Sorties dans la navigation basse et OutingsNavigation dans les espaces événements/visites ; conserver les deux sous-vues Découvrir/Mes événements, leurs URL et panneaux. Générer les routes avec `php artisan wayfinder:generate --with-form`.
- [x] Vérifier dans VisitTest : 320×700 et bureau, FR/EN, clair/sombre, clavier, Échap, retour du focus, absence de débordement et erreurs JS, scan accessibilité selon les helpers du dépôt. Relancer `tests/Browser/EventTest.php` et les tests de navigation concernés, puis lint/types/build frontend ; committer `feat(outings): add accessible visit agenda and calendar`.

### Task 5: Exporter et purger les données de visite

**Files:** Créer `app/Console/Commands/PurgeExpiredVisits.php`, `tests/Feature/VisitDataControlTest.php`. Modifier `app/Actions/BuildUserDataExport.php`, `routes/console.php`, `docs/PRD.md`, `docs/data-model.md`, `docs/security-privacy.md`, `docs/operations.md`, `docs/design-system.md`.

**Interfaces:** export `visits_visibility` et `visits[]={starts_on,ends_on,created_at,updated_at}` pour le seul propriétaire. Commande `visits:purge-expired` (SUCCESS), quotidienne à 03:45 Europe/Paris, `withoutOverlapping()->onOneServer()` ; suppression par lots de 500 avec recontrôle sous verrou du propriétaire et de la date de fin.

- [x] Écrire les tests d’export propre et exclusion d’autrui ; compte pending_deletion inaccessible aux listes et purge existante supprimant visites/préférence, suppression administrative immédiate sans effet sur autrui.
- [x] Figer aujourd’hui au 20 novembre 2026 : fin au 21 octobre conservée ; fin au 20 octobre supprimée ; compte banni conservé ; après levée, purge effective ; deuxième lancement idempotent. Tester qu’une modification récente repoussant la fin empêche la suppression d’une ligne présélectionnée.
- [x] Exécuter `php artisan test tests/Feature/VisitDataControlTest.php`, constater le rouge, implémenter l’export et la commande ; réutiliser les suppressions en cascade et la politique de verrouillage des purges existantes.
- [x] Documenter bornes, audience par défaut, confirmation, indépendance des trajets, exception bannissement, délai de purge et sauvegardes. Le PRD mentionne cette livraison issue 305 comme évolution hors MVP ; #304 reste différée. Expliquer les nouveaux composants métier nécessaires et les primitives réellement partagées.
- [x] Relancer VisitDataControlTest et les tests existants d’export/suppression concernés ; committer `feat(visits): export and expire personal visit data`.

### Task 6: Vérifier et préparer l’intégration

**Files:** Tous les fichiers des tâches précédentes, aucun refactoring additionnel. Consulter `CONTRIBUTING.md` et `docs/quality-ci-cd.md` pour la PR.

**Interfaces:** Toutes les routes, types et règles précédentes ; aucun nouveau contrat.

- [x] Installer les dépendances du worktree avec `composer install` et `bun install --frozen-lockfile` avant le premier test ; préparer `.env` local sans publier de secret. Vérifier d’abord une référence existante (`php artisan test tests/Feature/CreateEventTest.php`). Préparer la base dédiée via `docker compose --profile test up -d --wait mysql-test` et `php tests/Support/verify-test-database.php`. Ne pas lancer deux suites RefreshDatabase simultanément sur la même base ; ne pas supprimer un service déjà utilisé ailleurs.
- [x] Exécuter les tests ciblés visites et les régressions événements/navigation/export après leurs dernières modifications ; réparer les échecs liés au changement, sans masquer un échec de référence.
- [x] Exécuter `composer ci:check` (inclut PHP, Wayfinder, tests unitaires frontend, build et Pest) et `docker build --target runtime --tag dlp-friends:ci .`. Consigner les commandes, leurs sorties et toute limitation réelle ; un contrôle indisponible ne compte pas comme réussi.
- [x] Faire une revue finale du diff : chaque critère d’acceptation de #305 est couvert, zéro fuite dans les réponses ou compteurs, pas de champ privé superflu, navigation événements conservée, traductions complètes. Faire la revue indépendante selon le mode d’exécution choisi.
- [x] Vérifier `git diff --check` et un arbre propre après les commits ; préparer la PR vers main au titre `feat(sorties): add private visit agenda and calendar`, avec `Closes #305`, résumé produit et validation réelle. Si la PR est créée, l’attacher à la tâche ; ne pas fusionner sans autorisation.

## Revue du plan

Couverture : les dates et la propriété sont en tâche 1 ; audiences et confirmation
serveur en tâche 2 ; confidentialité, recherche et exactitude des compteurs en
tâche 3 ; tous les parcours et régressions visuelles/navigation en tâche 4 ;
export, suppression, conservation et documentation en tâche 5 ; vérification
complète et intégration en tâche 6. Les cinq risques Review Focus ont chacun un
test explicitement affecté. Les signatures et props définies ici sont le contrat
commun aux tâches ; aucune architecture de trajets ou de chat n’est ajoutée.


## Résultat de vérification — 21 octobre 2026

- `composer ci:check` : réussi après les dernières corrections ; 1 644 tests
  réussis, 6 ignorés, 31 949 assertions. Inclut Pint, PHPStan, ESLint,
  Prettier, TypeScript, Wayfinder, tests unitaires frontend, Vite et Pest.
- `docker build --target runtime --tag dlp-friends:ci .` : réussi.
- Régressions ciblées visites : 25 tests / 295 assertions ; après correction
  d'une attente asynchrone du test de modification, 13 parcours navigateur /
  102 assertions réussis, puis nouvelle suite complète réussie.
- Revue indépendante du diff : aucun Critical ; les défauts Important ont
  été reproduits en échec puis corrigés (jour passé, historique navigateur,
  focus, cibles tactiles et état filtré vide). Les captures corrigées ont été
  validées ; documentation du design system existant conservée.

Arbitrages consignés :

1. Conserver exactement 30 jours calendaires à Paris, conformément à la spec,
   plutôt que le jour supplémentaire d'un exemple erroné du plan. Si cette
   interprétation était incorrecte, la rétention serait plus courte d'un jour.
2. Utiliser PRD, spec et design-system existants comme autorité produit et
   visuelle, sans recréer PRODUCT.md/DESIGN.md. Une erreur imposerait une
   décision visuelle séparée.
3. Reclasser l'état filtré vide en Important : le conseil de création ne
   permettait pas de récupérer une recherche sans résultat. Coût si excessif :
   un petit état traduit et son bouton de réinitialisation supplémentaires.

Mineur différé : le repère personnel « Toi » du calendrier utilise 10 px,
contre 12 px minimum documentés. Cet écart local n'est pas promu en règle
pour les autres écrans.
