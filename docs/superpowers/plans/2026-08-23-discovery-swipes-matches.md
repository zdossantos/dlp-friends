# Discovery, Swipes and Reciprocal Matches Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer le parcours mobile-first qui classe les membres par affinité, enregistre une décision unique et confirme un match après deux likes réciproques.

**Architecture:** Le domaine est séparé en un service de lecture `DiscoveryService` et une action transactionnelle `CreateSwipe`. Les contrôleurs Inertia ne font que sérialiser les résultats et orchestrer les redirects ; Vue gère la présentation, les raccourcis accessibles et le verrouillage immédiat, tandis que les contraintes SQL garantissent l’unicité.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Pest 5, Inertia 3, Vue 3.5, TypeScript 6, Vitest 4, Tailwind CSS 4, shadcn-vue/Reka UI, Wayfinder, Bun 1.3.14.

**Spec:** `docs/superpowers/specs/2026-08-23-discovery-swipes-matches-design.md`

## Global Constraints

- Le serveur reste la source de vérité pour l’éligibilité, les décisions et les matches.
- Une décision vaut exactement `like` ou `pass` et une paire acteur–cible n’existe qu’une fois.
- Un match stocke une paire canonique `user_low_id < user_high_id` et n’existe qu’une fois.
- Le score vaut le nombre de passions actives communes, plus `0,25` pour une fréquence non nulle identique.
- Les profils masqués, incomplets, inactifs, mineurs, sans date de naissance, déjà évalués ou bloqués dans un sens sont exclus.
- Toutes les actions restent disponibles au clavier ; le geste tactile n’est qu’une amélioration.
- Aucun filtre avancé, retour arrière, limite quotidienne, conversation, administration ou action de blocage n’est ajouté.
- Les changements suivent TDD : test rouge observé, implémentation minimale, test vert, puis commit.

---

## File Structure

### Domaine et stockage

- `database/migrations/2026_08_23_000000_create_discovery_tables.php` — schéma passions, pivot, blocages, swipes et matches.
- `app/Enums/SwipeDecision.php` — valeurs autorisées et libellés métier.
- `app/Models/PassionCategory.php`, `Passion.php`, `Swipe.php`, `MemberMatch.php`, `Block.php` — relations et casts Eloquent.
- `database/factories/PassionCategoryFactory.php`, `PassionFactory.php`, `SwipeFactory.php`, `MemberMatchFactory.php`, `BlockFactory.php` — données de test ciblées.
- `app/Data/DiscoveryProfileData.php` — contrat public sérialisable d’une suggestion.
- `app/Contracts/DiscoveryTieBreaker.php` et `app/Services/RandomDiscoveryTieBreaker.php` — départage injectable.
- `app/Services/DiscoveryService.php` — exclusions, score, chargement borné et tri.
- `app/Actions/CreateSwipe.php` — validation métier et transaction réciproque.

### HTTP et interface

- `app/Http/Controllers/DiscoveryController.php` — rendu Inertia de la première suggestion.
- `app/Http/Controllers/SwipeController.php` — validation de la décision et redirect.
- `app/Http/Requests/StoreSwipeRequest.php` — validation HTTP stricte de `like` ou `pass`.
- `routes/web.php` — routes membre `discovery.index` et `discovery.swipe`.
- `resources/js/types/discovery.ts` — contrats TypeScript de la page.
- `resources/js/components/discovery/SwipeCard.vue` — carte accessible et geste horizontal.
- `resources/js/pages/Discovery/Index.vue` — états, soumission et confirmation de match.
- `resources/js/components/AppSidebar.vue` — entrée « Découvrir ».
- `resources/js/routes/**` et `resources/js/actions/**` — sorties Wayfinder régénérées.

### Tests

- `tests/Feature/DiscoverySchemaTest.php` — contraintes et relations du socle.
- `tests/Unit/DiscoveryServiceTest.php` — score, tri, exclusions et budget de requêtes.
- `tests/Feature/CreateSwipeTest.php` — décisions, réciprocité, répétitions et autorisations.
- `tests/Feature/DiscoveryPageTest.php` — contrat Inertia et redirects.
- `resources/js/components/discovery/SwipeCard.spec.ts` — émissions, clavier, geste et verrouillage.
- `resources/js/pages/Discovery/Index.spec.ts` — états, soumission et dialogue.

---

### Task 1: Add the discovery data foundation

**Files:**
- Create: `database/migrations/2026_08_23_000000_create_discovery_tables.php`
- Create: `app/Enums/SwipeDecision.php`
- Create: `app/Models/PassionCategory.php`
- Create: `app/Models/Passion.php`
- Create: `app/Models/Swipe.php`
- Create: `app/Models/MemberMatch.php`
- Create: `app/Models/Block.php`
- Create: `database/factories/PassionCategoryFactory.php`
- Create: `database/factories/PassionFactory.php`
- Create: `database/factories/SwipeFactory.php`
- Create: `database/factories/MemberMatchFactory.php`
- Create: `database/factories/BlockFactory.php`
- Modify: `app/Models/User.php`
- Modify: `app/Models/Profile.php`
- Test: `tests/Feature/DiscoverySchemaTest.php`

**Interfaces:**
- Consumes: `User`, `Profile`, `UserStatus`, `ProfileVisibility` et les conventions Eloquent existantes.
- Produces: `SwipeDecision`, relations `Profile::passions()`, `User::sentSwipes()`, `receivedSwipes()`, `lowMatches()`, `highMatches()`, `blocksCreated()` et `blocksReceived()`.

- [ ] **Step 1: Write failing schema and relationship tests**

Créer `DiscoverySchemaTest.php` avec `RefreshDatabase` et quatre tests : attachement unique d’une passion, rejet d’une décision hors enum, rejet d’un second swipe pour la même paire et rejet d’un second match pour la même paire canonique. Le cœur des assertions est :

```php
$profile->passions()->attach($passion);
$this->expectException(QueryException::class);
$profile->passions()->attach($passion);

Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id]);
$this->expectException(QueryException::class);
Swipe::factory()->create(['actor_user_id' => $actor->id, 'target_user_id' => $target->id]);

MemberMatch::factory()->create(['user_low_id' => $low->id, 'user_high_id' => $high->id]);
$this->expectException(QueryException::class);
MemberMatch::factory()->create(['user_low_id' => $low->id, 'user_high_id' => $high->id]);
```

- [ ] **Step 2: Run the schema test and observe the red state**

Run: `php artisan test tests/Feature/DiscoverySchemaTest.php`

Expected: FAIL because the tables, enum, models and factories do not exist.

- [ ] **Step 3: Implement the additive schema**

Créer les six tables dans l’ordre des dépendances. Utiliser les contraintes suivantes :

```php
$table->unique(['profile_id', 'passion_id']);
$table->unique(['actor_user_id', 'target_user_id']);
$table->enum('decision', ['like', 'pass']);
$table->unique(['user_low_id', 'user_high_id']);
$table->unique(['blocker_user_id', 'blocked_user_id']);
```

Le `down()` supprime dans l’ordre inverse : `blocks`, `matches`, `swipes`, `passion_profile`, `passions`, `passion_categories`.

- [ ] **Step 4: Implement the enum, models, factories and relations**

Définir :

```php
enum SwipeDecision: string
{
    case Like = 'like';
    case Pass = 'pass';
}
```

`Swipe::casts()` mappe `decision` vers `SwipeDecision`. `MemberMatch` définit `$table = 'matches'` et accepte `user_low_id`, `user_high_id`. Les factories créent toujours des utilisateurs distincts et une paire canonique. `Passion` porte `name`, `is_active`, `sort_order`, et `Profile::passions()` utilise `belongsToMany(Passion::class)`.

- [ ] **Step 5: Run targeted tests and static checks**

Run: `php artisan test tests/Feature/DiscoverySchemaTest.php && composer analyse`

Expected: PASS with no PHPStan errors.

- [ ] **Step 6: Commit the data foundation**

```bash
git add app/Enums app/Models database/factories database/migrations tests/Feature/DiscoverySchemaTest.php
git commit -m "feat: add discovery data foundation"
```

---

### Task 2: Build the explainable discovery service

**Files:**
- Create: `app/Data/DiscoveryProfileData.php`
- Create: `app/Contracts/DiscoveryTieBreaker.php`
- Create: `app/Services/RandomDiscoveryTieBreaker.php`
- Create: `app/Services/DiscoveryService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/DiscoveryServiceTest.php`

**Interfaces:**
- Consumes: `Profile::passions()`, `User::sentSwipes()`, block relations and `VisitFrequency`.
- Produces: `DiscoveryService::for(User $user): Collection`, where every item is `DiscoveryProfileData`; `DiscoveryProfileData::toArray(): array<string, mixed>`.

- [ ] **Step 1: Write failing score and ordering tests**

Construire un acteur avec passions `Attractions` et `Parades`, puis trois profils : deux passions communes, une passion plus même fréquence, et une passion seule. Injecter un départage fixe :

```php
$tieBreaker = new class implements DiscoveryTieBreaker {
    public function rank(int $profileId): int { return $profileId; }
};

$results = (new DiscoveryService($tieBreaker))->for($actor);

expect($results->pluck('profileId')->all())->toBe([$twoCommon->profile->id, $sameFrequency->profile->id, $oneCommon->profile->id]);
expect($results[1]->score)->toBe(1.25)
    ->and($results[1]->commonPassions)->toBe(['Attractions'])
    ->and($results[1]->frequencyBonus)->toBeTrue();
```

Ajouter un test d’égalité qui prouve que `rank()` détermine uniquement l’ordre de deux scores strictement identiques.

- [ ] **Step 2: Write failing exclusion and query-budget tests**

Créer explicitement un profil masqué, incomplet, utilisateur `pending_deletion`, utilisateur mineur, utilisateur sans date de naissance, profil déjà swipé, bloc sortant et bloc entrant. Vérifier que seul le profil éligible demeure. Écouter les requêtes avec `DB::listen()` après la préparation et exiger un budget constant inférieur ou égal à six requêtes pour un jeu de dix candidats.

- [ ] **Step 3: Run the service tests and observe the red state**

Run: `php artisan test tests/Unit/DiscoveryServiceTest.php`

Expected: FAIL because the service contracts do not exist.

- [ ] **Step 4: Implement the DTO and tie-breaker contract**

Définir le DTO immuable :

```php
final readonly class DiscoveryProfileData
{
    /** @param list<string> $commonPassions */
    public function __construct(
        public int $userId,
        public int $profileId,
        public string $displayName,
        public int $age,
        public ?string $bio,
        public ?string $visitFrequency,
        public int $commonPassionCount,
        public array $commonPassions,
        public bool $frequencyBonus,
        public float $score,
    ) {}
}
```

`DiscoveryTieBreaker::rank(int $profileId): int` est lié à `RandomDiscoveryTieBreaker` dans `AppServiceProvider`. L’implémentation de production utilise `random_int(PHP_INT_MIN, PHP_INT_MAX)` une seule fois par profil.

`DiscoveryProfileData::toArray()` retourne les clés camelCase du contrat frontend (`userId`, `profileId`, `displayName`, `age`, `bio`, `visitFrequency`, `commonPassionCount`, `commonPassions`, `frequencyBonus`, `score`) sans exposer le modèle Eloquent.

- [ ] **Step 5: Implement exclusions, eager loading, scoring and sorting**

Charger les IDs des passions actives de l’acteur, puis les candidats avec `user` et `passions` actives. Filtrer `birth_date <= today()->subYears(18)` et employer `whereDoesntHave` pour swipes et blocs dans les deux sens. Construire les DTO en mémoire et trier avec cette clé logique :

```php
[$commonPassionCount DESC, $frequencyBonus DESC, $tieBreaker->rank($profileId) ASC]
```

Le score sérialisé est `$commonPassionCount + ($frequencyBonus ? 0.25 : 0.0)` ; la comparaison d’ordre ne repose pas sur un float.

- [ ] **Step 6: Run targeted tests and static checks**

Run: `php artisan test tests/Unit/DiscoveryServiceTest.php && composer analyse`

Expected: PASS, including the fixed query budget.

- [ ] **Step 7: Commit the discovery engine**

```bash
git add app/Contracts app/Data app/Services app/Providers/AppServiceProvider.php tests/Unit/DiscoveryServiceTest.php
git commit -m "feat: add explainable discovery engine"
```

---

### Task 3: Record unique swipes and reciprocal matches

**Files:**
- Create: `app/Actions/CreateSwipe.php`
- Test: `tests/Feature/CreateSwipeTest.php`

**Interfaces:**
- Consumes: `SwipeDecision`, `Swipe`, `MemberMatch`, profile eligibility and block relations.
- Produces: `CreateSwipe::handle(User $actor, User $target, SwipeDecision $decision): ?MemberMatch`; throws `ValidationException` with key `decision` or `target` for expected rejections.

- [ ] **Step 1: Write failing decision and eligibility tests**

Tester `pass`, premier `like`, auto-swipe, cible masquée, cible inactive, cible mineure, cible sans date de naissance, bloc dans chaque sens et décision répétée. Exemple :

```php
$match = app(CreateSwipe::class)->handle($actor, $target, SwipeDecision::Pass);

expect($match)->toBeNull();
$this->assertDatabaseHas('swipes', [
    'actor_user_id' => $actor->id,
    'target_user_id' => $target->id,
    'decision' => 'pass',
]);
```

- [ ] **Step 2: Write failing reciprocity and idempotency tests**

Prouver qu’un premier like retourne `null`, que le like inverse retourne un `MemberMatch` canonique, et que deux tentatives supplémentaires laissent exactement deux swipes et un match. Simuler l’insertion concurrente par une paire de swipes préexistante puis deux appels inverses entourés d’une capture de l’erreur unique attendue.

- [ ] **Step 3: Run the action tests and observe the red state**

Run: `php artisan test tests/Feature/CreateSwipeTest.php`

Expected: FAIL because `CreateSwipe` does not exist.

- [ ] **Step 4: Implement eligibility validation and transaction**

Dans `handle()`, charger `target.profile`, refuser les états inéligibles avec `ValidationException::withMessages()`, puis exécuter :

```php
return DB::transaction(function () use ($actor, $target, $decision): ?MemberMatch {
    [$lowId, $highId] = collect([$actor->id, $target->id])->sort()->values()->all();

    User::query()
        ->whereKey([$lowId, $highId])
        ->orderBy('id')
        ->lockForUpdate()
        ->get();

    Swipe::query()->create([
        'actor_user_id' => $actor->id,
        'target_user_id' => $target->id,
        'decision' => $decision,
    ]);

    if ($decision === SwipeDecision::Pass || ! Swipe::query()
        ->where('actor_user_id', $target->id)
        ->where('target_user_id', $actor->id)
        ->where('decision', SwipeDecision::Like)
        ->exists()) {
        return null;
    }

    MemberMatch::query()->insertOrIgnore(['user_low_id' => $lowId, 'user_high_id' => $highId, 'created_at' => now(), 'updated_at' => now()]);

    return MemberMatch::query()
        ->where('user_low_id', $lowId)
        ->where('user_high_id', $highId)
        ->firstOrFail();
});
```

Le verrouillage des deux utilisateurs dans l’ordre canonique sérialise les décisions opposées portant sur la même paire : la seconde transaction voit toujours le premier like commité. Convertir une violation de l’unicité du swipe en erreur de validation « Vous avez déjà évalué ce profil. » sans masquer les autres erreurs SQL.

- [ ] **Step 5: Run targeted tests and static checks**

Run: `php artisan test tests/Feature/CreateSwipeTest.php && composer analyse`

Expected: PASS with one canonical match at most.

- [ ] **Step 6: Commit the matching action**

```bash
git add app/Actions/CreateSwipe.php tests/Feature/CreateSwipeTest.php
git commit -m "feat: create reciprocal matches from swipes"
```

---

### Task 4: Expose the Inertia discovery contract

**Files:**
- Create: `app/Http/Controllers/DiscoveryController.php`
- Create: `app/Http/Controllers/SwipeController.php`
- Create: `app/Http/Requests/StoreSwipeRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/LandingController.php`
- Test: `tests/Feature/DiscoveryPageTest.php`
- Regenerate: `resources/js/routes/discovery/index.ts`
- Regenerate: `resources/js/actions/App/Http/Controllers/DiscoveryController.ts`
- Regenerate: `resources/js/actions/App/Http/Controllers/SwipeController.ts`

**Interfaces:**
- Consumes: `DiscoveryService::for(User): Collection`, `CreateSwipe::handle(...)` and `DiscoveryProfileData::toArray()`.
- Produces: GET prop `suggestion: array|null`, flash prop `match: { id: number, displayName: string }|null`, POST body `{ decision: 'like'|'pass' }`.

- [ ] **Step 1: Write failing Inertia rendering tests**

Tester accès protégé, composant `Discovery/Index`, suggestion publique, état `null`, et absence d’e-mail :

```php
$this->actingAs($actor)->get(route('discovery.index'))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('Discovery/Index')
        ->where('suggestion.displayName', $target->profile->display_name)
        ->missing('suggestion.email'));
```

- [ ] **Step 2: Write failing POST and match-flash tests**

Tester le rejet de `super-like`, le redirect vers `discovery.index`, l’exclusion du profil après succès, et la propriété flash de match lors du like inverse.

- [ ] **Step 3: Run the HTTP tests and observe the red state**

Run: `php artisan test tests/Feature/DiscoveryPageTest.php`

Expected: FAIL because the routes and controllers do not exist.

- [ ] **Step 4: Implement controllers and routes**

`DiscoveryController::__invoke(Request $request, DiscoveryService $service): Response` prend le premier résultat et rend :

```php
return Inertia::render('Discovery/Index', [
    'suggestion' => $service->for($request->user())->first()?->toArray(),
    'match' => fn () => $request->session()->pull('discovery.match'),
]);
```

`StoreSwipeRequest::rules()` retourne `['decision' => ['required', Rule::enum(SwipeDecision::class)]]`. `SwipeController::__invoke(StoreSwipeRequest $request, User $target, CreateSwipe $action): RedirectResponse` convertit `$request->validated('decision')` avec `SwipeDecision::from()`, appelle l’action et place uniquement `id` et `displayName` sous `discovery.match`. Les deux routes vivent dans le groupe `profile.complete`.

Modifier `LandingController` pour envoyer un membre non-admin complet vers `discovery.index`; conserver le dashboard admin prioritaire.

- [ ] **Step 5: Regenerate Wayfinder bindings**

Run: `php artisan wayfinder:generate --with-form`

Expected: generated TypeScript includes `discovery.index` and `discovery.swipe` bindings.

- [ ] **Step 6: Run targeted tests and type generation checks**

Run: `php artisan test tests/Feature/DiscoveryPageTest.php && bun run types:check`

Expected: PASS.

- [ ] **Step 7: Commit the HTTP contract**

```bash
git add app/Http/Controllers/DiscoveryController.php app/Http/Controllers/SwipeController.php app/Http/Controllers/LandingController.php app/Http/Requests/StoreSwipeRequest.php routes/web.php tests/Feature/DiscoveryPageTest.php resources/js/routes resources/js/actions
git commit -m "feat: expose discovery through inertia"
```

---

### Task 5: Build the accessible SwipeCard

**Files:**
- Create: `resources/js/types/discovery.ts`
- Modify: `resources/js/types/index.ts`
- Create: `resources/js/components/discovery/SwipeCard.vue`
- Test: `resources/js/components/discovery/SwipeCard.spec.ts`

**Interfaces:**
- Consumes: `DiscoveryProfile`, prop `locked: boolean`.
- Produces: exactly `defineEmits<{ like: []; pass: [] }>()`; no generic decision event.

- [ ] **Step 1: Write failing render and emission tests**

Définir une fixture complète et vérifier nom, âge, bio, score, fréquence et badges. Cliquer les boutons par leur `aria-label`, puis affirmer :

```ts
expect(wrapper.emitted()).toEqual({ pass: [[]], like: [[]] });
```

Vérifier aussi que `locked: true` désactive les deux boutons et qu’une seconde action ne produit aucune émission.

- [ ] **Step 2: Write failing keyboard and pointer tests**

Déclencher `keydown.left`, `keydown.right`, puis des séquences `pointerdown`/`pointerup` de `-90`, `90` et `20` pixels. Attendre respectivement `pass`, `like` et aucune émission sous le seuil de `72` pixels.

- [ ] **Step 3: Run the component test and observe the red state**

Run: `bun run test:unit -- resources/js/components/discovery/SwipeCard.spec.ts`

Expected: FAIL because the component and type do not exist.

- [ ] **Step 4: Implement the TypeScript contract and card markup**

Définir :

```ts
export type DiscoveryProfile = {
    userId: number;
    profileId: number;
    displayName: string;
    age: number;
    bio: string | null;
    visitFrequency: VisitFrequency | null;
    commonPassionCount: number;
    commonPassions: string[];
    frequencyBonus: boolean;
    score: number;
};

export type SwipeDecision = 'like' | 'pass';

export type DiscoveryMatch = {
    id: number;
    displayName: string;
};
```

La racine de la carte porte `tabindex="0"`, un libellé accessible et les handlers clavier/pointer. La fonction locale `decide(decision)` retourne immédiatement si `locked`. Les initiales proviennent des deux premiers mots non vides de `displayName`.

- [ ] **Step 5: Implement visual hierarchy and responsive actions**

Composer la carte avec les primitives `Card`, `Avatar`, `Badge` et `Button`. Employer `w-full max-w-md`, des espacements à partir de `p-4`, une zone d’actions en grille de deux colonnes, un focus visible et un texte explicatif « Utilisez les boutons ou les flèches gauche et droite ».

- [ ] **Step 6: Run component checks**

Run: `bun run test:unit -- resources/js/components/discovery/SwipeCard.spec.ts && bun run types:check && bun run lint:check`

Expected: PASS.

- [ ] **Step 7: Commit the card**

```bash
git add resources/js/types resources/js/components/discovery
git commit -m "feat: add accessible swipe card"
```

---

### Task 6: Assemble discovery states, submission and match dialog

**Files:**
- Create: `resources/js/pages/Discovery/Index.vue`
- Create: `resources/js/pages/Discovery/Index.spec.ts`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/AppSidebar.spec.ts`

**Interfaces:**
- Consumes: props `suggestion?: DiscoveryProfile|null`, `match: DiscoveryMatch|null`, SwipeCard events, and Wayfinder POST route.
- Produces: loading, error, empty, card and match-confirmation UI states; guarded `submit('like'|'pass')`.

- [ ] **Step 1: Write failing state tests**

Monter la page avec `suggestion: undefined`, `null`, puis une fixture. Vérifier respectivement « Recherche de profils… », « Vous avez exploré tous les profils disponibles » avec lien vers le profil, et la carte. Monter avec `match` et vérifier « C’est un match ! » ainsi que le nom public.

- [ ] **Step 2: Write failing submission and unlock tests**

Mocker `router.post`. Émettre `like` deux fois et prouver un seul appel :

```ts
expect(router.post).toHaveBeenCalledOnce();
expect(router.post).toHaveBeenCalledWith('/discover/42/swipe', { decision: 'like' }, expect.any(Object));
```

Appeler le callback `onError` avec `{ decision: 'Impossible d’enregistrer cette décision.' }`, puis `onFinish`. Vérifier la zone `role="alert"`, le déverrouillage et la possibilité d’un nouvel essai.

- [ ] **Step 3: Write failing navigation test**

Étendre `AppSidebar.spec.ts` pour attendre « Découvrir » pour un membre et vérifier que son lien cible `/discover`, tout en conservant la visibilité conditionnelle de l’administration.

- [ ] **Step 4: Run the page tests and observe the red state**

Run: `bun run test:unit -- resources/js/pages/Discovery/Index.spec.ts resources/js/components/AppSidebar.spec.ts`

Expected: FAIL because the page and navigation item do not exist.

- [ ] **Step 5: Implement page state and guarded submission**

Utiliser `isSubmitting`, `errorMessage` et `lastDecision`. La soumission suit ce contrat :

```ts
const submit = (decision: SwipeDecision): void => {
    if (isSubmitting.value || !props.suggestion) return;
    isSubmitting.value = true;
    lastDecision.value = decision;
    errorMessage.value = null;
    router.post(swipe(props.suggestion.userId).url, { decision }, {
        preserveScroll: true,
        onError: (errors) => { errorMessage.value = String(errors.decision ?? errors.target ?? 'Une erreur est survenue.'); },
        onFinish: () => { isSubmitting.value = false; },
    });
};

const retry = (): void => {
    if (lastDecision.value) submit(lastDecision.value);
};
```

Afficher un `Skeleton` pour `undefined`, un `Alert` avec bouton « Réessayer » qui appelle `retry()` pour l’erreur, l’état vide pour `null`, et `SwipeCard` pour une suggestion. Le dialogue de match utilise `DialogTitle`, `DialogDescription` et un bouton « Continuer à découvrir ».

- [ ] **Step 6: Add the navigation item**

Importer `Sparkles` et `index as discovery` depuis le binding Wayfinder, puis insérer l’entrée « Découvrir » avant « Mon profil » dans `mainNavItems`.

- [ ] **Step 7: Run frontend checks**

Run: `bun run test:unit -- resources/js/pages/Discovery/Index.spec.ts resources/js/components/discovery/SwipeCard.spec.ts resources/js/components/AppSidebar.spec.ts && bun run types:check && bun run lint:check && bun run format:check`

Expected: PASS. Si Prettier signale uniquement les nouveaux fichiers, exécuter `bun run format`, relire le diff, puis relancer la commande complète.

- [ ] **Step 8: Commit the discovery page**

```bash
git add resources/js/pages/Discovery resources/js/components/AppSidebar.vue resources/js/components/AppSidebar.spec.ts
git commit -m "feat: add mobile-first discovery page"
```

---

### Task 7: Verify the complete flow and integration quality

**Files:**
- Modify only files implicated by failures found during verification.

**Interfaces:**
- Consumes: all interfaces produced by Tasks 1–6.
- Produces: a repository passing every required check with no unrelated changes.

- [ ] **Step 1: Run all backend checks**

Run: `composer lint:check && composer analyse && php artisan test`

Expected: PASS with zero failures and zero PHPStan errors.

- [ ] **Step 2: Run all frontend checks**

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run test && bun run build`

Expected: PASS and a successful Vite production build.

- [ ] **Step 3: Inspect route and migration integration**

Run: `php artisan route:list --name=discovery && php artisan migrate:fresh --seed --env=testing`

Expected: exactly one GET discovery route and one POST swipe route; all migrations and seeders succeed.

- [ ] **Step 4: Review the final diff for scope and secrets**

Run: `git diff --check && git status --short && git diff --stat HEAD~6..HEAD`

Expected: no whitespace errors, no environment or credential files, and only the files described in this plan plus deterministic generated Wayfinder files.

- [ ] **Step 5: Confirm the verified worktree state**

Run: `git status --short`

Expected: no uncommitted implementation files. If a verification command required a correction, return to the task that owns that file, rerun its targeted checks and commit the correction with that task’s explicit file list before repeating Task 7.
