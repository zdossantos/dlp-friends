# Realtime Member Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Diffuser les nouveaux univers croisés et messages dans tout l’espace membre, enrichir le dialogue de match et filtrer les échanges par nom.

**Architecture:** Laravel diffuse après commit des charges utiles destinées au canal privé de chaque membre. Un composable monté une seule fois dans `MemberLayout` transforme ces événements en dialogue ou toast et déduplique les livraisons ; la page des échanges applique séparément un filtre local normalisé à sa liste réactive.

**Tech Stack:** PHP 8.4, Laravel 13 Broadcast/Reverb, Pest, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, vue-sonner, Pest Browser/Playwright.

**Spec:** `docs/superpowers/specs/2026-09-06-realtime-member-notifications-design.md`

## Global Constraints

- L’application reste strictement amicale et emploie le vocabulaire canonique « univers croisés » et « échange ».
- Tout texte visible est traduit en français et en anglais dans les catalogues existants.
- Les événements sont émis après commit et uniquement sur des canaux privés autorisés.
- Les blocages restent appliqués côté serveur par les Policies et Actions existantes.
- Les profils masqués continuent de recevoir les messages et notifications de leurs échanges accessibles par URL directe.
- La recherche porte uniquement sur le nom public du membre, jamais sur le contenu des messages.
- Aucun push, e-mail ou historique persistant de notifications n’est ajouté.

---

### Task 1: Diffuser un match créé aux deux membres

**Files:**
- Create: `app/Events/MatchCreated.php`
- Modify: `app/Actions/CreateSwipe.php`
- Test: `tests/Feature/CreateSwipeTest.php`

**Interfaces:**
- Produces: `MatchCreated(MemberMatch $memberMatch, User $recipient)` diffusé comme `.match.created` sur `App.Models.User.{recipient.id}`.
- Produces: charge utile `match_id`, `conversation_id`, `member: { id, display_name, avatar }`, où `member` est l’autre participant.

- [ ] **Step 1: Write the failing event tests**

Ajouter à `CreateSwipeTest` des tests avec `Event::fake([MatchCreated::class])` qui prouvent qu’un premier like ne diffuse rien, qu’un like réciproque diffuse exactement deux événements destinés après création de la conversation, et que chaque événement vise le canal privé de son destinataire.

```php
Event::fake([MatchCreated::class]);

$action->handle($lowUser, $highUser, SwipeDecision::Like);
Event::assertNotDispatched(MatchCreated::class);

$match = $action->handle($highUser, $lowUser, SwipeDecision::Like);

Event::assertDispatchedTimes(MatchCreated::class, 2);
Event::assertDispatched(MatchCreated::class, fn (MatchCreated $event): bool =>
    $event->memberMatch->is($match)
    && $event->recipient->is($lowUser)
    && $event->memberMatch->conversation !== null
);
```

Tester aussi directement les charges adaptées aux deux utilisateurs et vérifier qu’elles ne contiennent ni e-mail ni donnée de profil privée.

- [ ] **Step 2: Run the tests and verify RED**

Run: `php artisan test tests/Feature/CreateSwipeTest.php`

Expected: FAIL parce que `App\Events\MatchCreated` n’existe pas et qu’aucun événement n’est déclenché.

- [ ] **Step 3: Implement the event and creation detection**

Créer un événement final qui implémente `ShouldBroadcast` et `ShouldDispatchAfterCommit`, charge `conversation`, `lowUser.profile.avatar` et `highUser.profile.avatar`, puis retourne les deux `PrivateChannel`.

```php
final class MatchCreated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public MemberMatch $memberMatch,
        public User $recipient,
    ) {}

    public function broadcastAs(): string
    {
        return 'match.created';
    }
}
```

Dans `CreateSwipe`, mémoriser le résultat de `insertOrIgnore`, garantir la conversation, puis déclencher seulement lorsque le match vient d’être inséré et que la conversation existe.

```php
$matchCreated = MemberMatch::query()->insertOrIgnore([...]) === 1;
// charger le match et garantir sa conversation
if ($matchCreated) {
    MatchCreated::dispatch($match, $match->lowUser);
    MatchCreated::dispatch($match, $match->highUser);
}
```

- [ ] **Step 4: Run the tests and verify GREEN**

Run: `php artisan test tests/Feature/CreateSwipeTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Events/MatchCreated.php app/Actions/CreateSwipe.php tests/Feature/CreateSwipeTest.php
git commit -m "feat: broadcast new matches to both members"
```

---

### Task 2: Fournir l’identité nécessaire aux annonces

**Files:**
- Modify: `app/Events/MessageSent.php`
- Modify: `app/Http/Controllers/SwipeController.php`
- Modify: `app/Http/Controllers/Admin/MemberController.php`
- Modify: `resources/js/types/discovery.ts`
- Modify: `resources/js/types/conversation.ts`
- Test: `tests/Feature/SendMessageTest.php`
- Test: `tests/Feature/CreateSwipeTest.php`
- Test: test fonctionnel existant du parcours d’échange administrateur dans `tests/Feature/Admin/MemberManagementTest.php` ou son équivalent trouvé par `rg`.

**Interfaces:**
- Produces: `MemberIdentity = { id: number; displayName: string; avatar: AvatarOption }` pour les dialogues.
- Produces: `RealtimeMessage` étendant le message avec `author: { id, display_name }` pour le toast.

- [ ] **Step 1: Write failing payload tests**

Ajouter un test de `MessageSent::broadcastWith()` qui vérifie le nom public de l’auteur et l’absence d’e-mail.

```php
$payload = (new MessageSent($message))->broadcastWith();

expect($payload)
    ->toMatchArray([
        'author' => [
            'id' => $author->id,
            'display_name' => $author->profile->display_name,
        ],
    ])
    ->not->toHaveKey('email');
```

Étendre les assertions Inertia du swipe et du parcours administrateur pour exiger `member.id`, `member.displayName` et `member.avatar.image_url`.

- [ ] **Step 2: Run the tests and verify RED**

Run: `php artisan test tests/Feature/SendMessageTest.php tests/Feature/CreateSwipeTest.php tests/Feature/Admin`

Expected: FAIL sur les nouvelles clés absentes.

- [ ] **Step 3: Add the minimal identity payloads and shared types**

Charger `message.author.profile` dans `MessageSent` et sérialiser uniquement l’identifiant et le nom public. Remplacer `DiscoveryMatch.displayName` par une propriété `member` commune contenant l’avatar complet. Faire produire cette structure au swipe et au dialogue administrateur en réutilisant la forme `AvatarOption` existante.

```ts
export type MemberIdentity = {
    id: number;
    displayName: string;
    avatar: AvatarOption;
};

export type DiscoveryMatch = {
    id: number;
    conversationId: number;
    member: MemberIdentity;
};
```

- [ ] **Step 4: Run the tests and static checks**

Run: `php artisan test tests/Feature/SendMessageTest.php tests/Feature/CreateSwipeTest.php tests/Feature/Admin`

Run: `bun run types:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Events/MessageSent.php app/Http/Controllers/SwipeController.php app/Http/Controllers/Admin/MemberController.php resources/js/types tests/Feature
git commit -m "feat: expose member identity for realtime notices"
```

---

### Task 3: Enrichir le dialogue d’univers croisés

**Files:**
- Modify: `resources/js/components/discovery/MatchDialog.vue`
- Modify: `resources/js/pages/Discovery/Index.vue`
- Modify: `resources/js/pages/Admin/Members/Index.vue`
- Modify: `resources/js/pages/Onboarding/Show.vue`
- Modify: `resources/js/types/discovery.ts`
- Modify: `lang/fr/discovery.php`
- Modify: `lang/en/discovery.php`
- Test: `tests/Browser/DiscoveryTest.php`
- Test: tests navigateur du tutoriel et de l’administration qui utilisent `MatchDialog`.

**Interfaces:**
- Consumes: `match.member: MemberIdentity` produit à la tâche 2.
- Produces: `MatchDialog` affiche `AvatarPortrait` et le nom sans modifier ses actions existantes.

- [ ] **Step 1: Write the failing browser assertions**

Après un match, vérifier l’avatar et le nom dans la boîte de dialogue.

```php
$page
    ->assertPresent('[data-test="match-member-avatar"] img')
    ->assertSee($target->profile->display_name)
    ->assertSee(__('discovery.match.title'));
```

Adapter les données de démonstration du tutoriel afin que le test exige le même rendu sans réseau social réel.

- [ ] **Step 2: Run the focused browser tests and verify RED**

Run: `php artisan test tests/Browser/DiscoveryTest.php --filter='match'`

Expected: FAIL car l’avatar de match n’est pas rendu.

- [ ] **Step 3: Render the avatar and consistent identity**

Importer `AvatarPortrait`, accepter `match: MemberIdentity`, puis ajouter avant le titre :

```vue
<AvatarPortrait
    :avatar="match.avatar"
    data-test="match-member-avatar"
    class="mx-auto size-16 rounded-2xl"
/>
<p class="text-center font-semibold text-secondary-foreground">
    {{ match.displayName }}
</p>
```

Conserver le nom dans la description traduite et mettre à jour les trois consommateurs du composant.

- [ ] **Step 4: Run browser and type checks**

Run: `php artisan test tests/Browser/DiscoveryTest.php --filter='match'`

Run: `bun run types:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/discovery/MatchDialog.vue resources/js/pages/Discovery/Index.vue resources/js/pages/Admin/Members/Index.vue resources/js/pages/Onboarding/Show.vue resources/js/types/discovery.ts lang tests/Browser
git commit -m "feat: show member identity in match dialog"
```

---

### Task 4: Monter les notifications personnelles dans le layout membre

**Files:**
- Create: `resources/js/composables/useMemberRealtimeNotifications.ts`
- Modify: `resources/js/layouts/MemberLayout.vue`
- Modify: `resources/js/pages/Conversations/Index.vue`
- Modify: `resources/js/pages/Conversations/Show.vue`
- Modify: `resources/js/types/conversation.ts`
- Modify: `lang/fr/conversations.php`
- Modify: `lang/en/conversations.php`
- Test: `tests/Browser/ConversationTest.php`
- Test: `tests/Browser/DiscoveryTest.php`

**Interfaces:**
- Consumes: `.match.created` et `.message.sent` sur `App.Models.User.{currentUserId}`.
- Produces: `MemberRealtimeContext` avec `activeMatch`, `latestMessage`, `dismissMatch()` et `rememberMatch(id)` ; les toasts dédupliqués naviguent vers `showConversation(id)`.

- [ ] **Step 1: Write failing two-session browser tests**

Sous la garde `REALTIME_BROWSER_TESTS`, ouvrir deux sessions authentifiées et vérifier :

```php
$firstPage = visit('/discover')->inSession('first');
$secondPage = visit('/discover')->inSession('second');
// premier like, puis like réciproque
$firstPage->assertPresent('[data-test="match-heading"]');
$secondPage->assertPresent('[data-test="match-heading"]');
```

Ajouter un scénario message depuis une autre page qui vérifie le titre, la description et le lien du toast, ainsi que l’absence de toast chez l’auteur et dans la conversation ouverte. Réémettre le même message et naviguer pour vérifier qu’un seul toast subsiste.

- [ ] **Step 2: Run the focused tests and verify RED**

Run: `REALTIME_BROWSER_TESTS=true php artisan test tests/Browser/DiscoveryTest.php tests/Browser/ConversationTest.php --filter='realtime|toast'`

Expected: FAIL car `MemberLayout` n’écoute pas les événements personnels et ne rend aucun dialogue global.

- [ ] **Step 3: Implement one global personal subscription**

Le composable utilise un seul canal personnel Echo par utilisateur et y attache les deux écouteurs. Il initialise `seenMatchIds` avec le match Inertia courant lorsque nécessaire et `seenMessageIds` avec les messages traités. Il expose son état par `provideMemberRealtimeContext(context)` dans le layout et `useMemberRealtimeContext()` dans les descendants.

```ts
const channel = useEcho<MemberMatchNotification>(
    `App.Models.User.${currentUserId}`,
    '.match.created',
    handleMatch,
);
channel.channel().listen('.message.sent', handleMessage);
```

Déterminer la conversation ouverte depuis `usePage().url` avec une correspondance stricte du chemin. Pour un message recevable, appeler `toast(author.display_name, { description, action })`; rendre la description avec une classe `line-clamp-1` via le composant/slot Sonner existant plutôt qu’avec du HTML brut.

Monter le composable dans `MemberLayout` avec `auth.user.id`, fournir le contexte, rendre un `MatchDialog` global et utiliser `router.visit(showConversation(id).url)` pour les actions. Retirer `useConversationListRealtime` de `Conversations/Index.vue`; la page injecte le contexte et observe `latestMessage` pour appeler `applyConversationMessage`. Garder l’abonnement `conversation.{id}` de `Show.vue` pour le fil et les reçus.

- [ ] **Step 4: Verify deduplication and cleanup**

Run: `REALTIME_BROWSER_TESTS=true php artisan test tests/Browser/DiscoveryTest.php tests/Browser/ConversationTest.php --filter='realtime|toast'`

Run: `bun run types:check`

Expected: PASS, un seul dialogue/toast par identifiant et aucun toast sur l’échange ouvert.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useMemberRealtimeNotifications.ts resources/js/layouts/MemberLayout.vue resources/js/pages/Conversations resources/js/types/conversation.ts lang tests/Browser
git commit -m "feat: show realtime notices across member pages"
```

---

### Task 5: Rechercher localement un échange par nom

**Files:**
- Create or Modify: `resources/js/lib/textSearch.ts`
- Modify: `resources/js/pages/Conversations/Index.vue`
- Modify: `lang/fr/conversations.php`
- Modify: `lang/en/conversations.php`
- Test: `tests/Frontend/text-search.test.js`
- Test: `tests/Browser/ConversationTest.php`

**Interfaces:**
- Produces: `normalizeSearchText(value: string): string`.
- Consumes: `ConversationSummary.participant.display_name` et la liste réactive mise à jour en temps réel.

- [ ] **Step 1: Write failing normalization and browser tests**

```js
expect(normalizeSearchText('  Élodie  ')).toBe('elodie');
```

Le test navigateur crée « Élodie » et « Basile », saisit `ELODIE`, vérifie qu’un seul échange reste, puis saisit un terme absent et vérifie l’état sans résultat et son bouton d’effacement.

- [ ] **Step 2: Run the tests and verify RED**

Run: `bun test tests/Frontend/text-search.test.js`

Run: `php artisan test tests/Browser/ConversationTest.php --filter='search'`

Expected: FAIL car le helper et le champ n’existent pas.

- [ ] **Step 3: Implement normalization and reactive filtering**

```ts
export function normalizeSearchText(value: string): string {
    return value
        .trim()
        .toLocaleLowerCase()
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '');
}
```

Dans `Index.vue`, ajouter `searchQuery`, puis :

```ts
const filteredConversations = computed(() => {
    const query = normalizeSearchText(searchQuery.value);
    if (query === '') return visibleConversations.value;

    return visibleConversations.value.filter((conversation) =>
        normalizeSearchText(conversation.participant.display_name).includes(query),
    );
});
```

Utiliser la primitive `Input` existante avec `type="search"`, un `Label` accessible, un bouton d’effacement nommé, et un état sans résultat séparé. Le `v-for` utilise `filteredConversations` ; `visibleConversations` reste mis à jour et réordonné par le temps réel.

- [ ] **Step 4: Run focused checks and verify GREEN**

Run: `bun test tests/Frontend/text-search.test.js`

Run: `php artisan test tests/Browser/ConversationTest.php --filter='search'`

Run: `bun run types:check`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/textSearch.ts resources/js/pages/Conversations/Index.vue lang/fr/conversations.php lang/en/conversations.php tests/Frontend/text-search.test.js tests/Browser/ConversationTest.php
git commit -m "feat: filter conversations by member name"
```

---

### Task 6: Documenter et vérifier la livraison complète

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/documentation-inventory.md` si sa matrice détaillée référence les notifications.
- Test: `tests/Feature/Localization/InertiaTranslationsTest.php`
- Test: `tests/Frontend/translations.test.js`

**Interfaces:**
- Consumes: comportements validés par les tâches 1 à 5.
- Produces: documentation et catalogues cohérents avec l’état réellement livré.

- [ ] **Step 1: Write failing translation coverage**

Ajouter les clés précises de recherche, résultat vide, effacement et action du toast aux assertions frontend/backend existantes.

```js
expect(translationFor(messages, 'conversations.notifications.open')).toBeTruthy();
expect(translationFor(messages, 'conversations.search.placeholder')).toBeTruthy();
```

- [ ] **Step 2: Run localization tests and verify RED if keys remain missing**

Run: `php artisan test tests/Feature/Localization/InertiaTranslationsTest.php`

Run: `bun test tests/Frontend/translations.test.js`

Expected: FAIL uniquement pour toute clé encore absente ; corriger les catalogues avant de poursuivre.

- [ ] **Step 3: Update product and architecture documentation**

Dans le PRD, ajouter les notifications ouvertes et la recherche par nom à l’état implémenté, puis retirer « Notifications de match et de nouveau message » des évolutions futures. Décrire dans l’architecture technique le canal privé personnel, les événements après commit, la déduplication du layout et le maintien du canal propre à la conversation.

- [ ] **Step 4: Run all relevant verification**

```bash
composer lint:check
composer analyse
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run build
php artisan test tests/Feature/CreateSwipeTest.php tests/Feature/SendMessageTest.php tests/Feature/ConversationTest.php tests/Feature/Localization/InertiaTranslationsTest.php
php artisan test tests/Browser/DiscoveryTest.php tests/Browser/ConversationTest.php
git diff --check
```

Expected: toutes les commandes terminent avec un code 0. Les scénarios temps réel conditionnels sont également exécutés avec Reverb actif lorsque l’environnement le permet ; sinon leur non-exécution est signalée explicitement sans revendiquer leur passage.

- [ ] **Step 5: Commit**

```bash
git add docs lang tests resources/js app
git commit -m "docs: mark realtime member notices as implemented"
```

- [ ] **Step 6: Review the complete branch**

Run: `git status --short --branch && git log --oneline main..HEAD && git diff --stat main...HEAD`

Expected: branche propre, commits ciblés et aucun fichier sans rapport avec l’issue.
