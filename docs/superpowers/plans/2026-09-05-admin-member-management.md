# Admin Member Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer le catalogue administrateur des membres de l'issue #122, sa suppression immédiate notifiée, l'ouverture d'une conversation classique et l'identification protégée des administrateurs dans l'application.

**Architecture:** Des contrôleurs web Inertia minces délèguent les mutations à deux actions transactionnelles et les autorisations à une `UserPolicy`. Le catalogue repose sur une seule requête paginée avec agrégats SQL, tandis que les profils exposent seulement `is_admin` et les permissions calculées côté serveur. Le frontend réutilise les dialogues, tableaux, routes Wayfinder et `MatchDialog` existants.

**Tech Stack:** PHP 8.4, Laravel 13, Pest/Pest Browser, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Bun 1.3.14.

**Spec:** `docs/superpowers/specs/2026-09-05-admin-member-management-design.md`

## Global Constraints

- Aucun administrateur ne peut supprimer un autre administrateur.
- La suppression des données actives est immédiate après confirmation explicite ; les sauvegardes expirent sous 30 jours.
- Le tableau affiche 20 comptes par page et recherche le nom d'affichage ou l'e-mail.
- Aucun contenu de message privé ne doit être sélectionné ou exposé.
- La conversation administrative est une conversation classique unique par paire, sans swipe artificiel, limitée à ses deux participants.
- Un compte administrateur ne peut pas être bloqué.
- Le badge « Administrateur » / « Administrator » et la bordure dorée apparaissent uniquement sur les vraies cartes et fiches de profil.
- Tout texte visible doit provenir des catalogues français et anglais.
- Chaque changement de comportement suit rouge, vert, refactorisation et se termine par un commit Conventional Commits.

---

### Task 1: Catalogue paginé, statistiques et autorisation

**Files:**
- Create: `app/Policies/UserPolicy.php`
- Create: `app/Http/Controllers/Admin/MemberController.php`
- Create: `tests/Feature/Admin/ManageMembersTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `User::hasRole(RoleName::Admin)`, les relations `sentSwipes`, `receivedSwipes`, `lowMatches`, `highMatches`, `blocksCreated`, `blocksReceived`, `authoredMessages`.
- Produces: route GET nommée `admin.members.index` et prop Inertia `members: LengthAwarePaginator<AdminMemberRow>` ; `UserPolicy::viewAny(User): bool`, `delete(User, User): bool`, `startConversation(User, User): bool`.

- [ ] **Step 1: Écrire les tests rouges du catalogue et des permissions**

Ajouter des tests qui créent un administrateur, un membre ordinaire, les swipes
directionnels, deux matchs, des messages et des blocages, puis vérifient :

```php
$this->actingAs($admin)
    ->get(route('admin.members.index', ['search' => $member->email]))
    ->assertOk()
    ->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Members/Index')
        ->has('members.data', 1)
        ->where('members.per_page', 20)
        ->where('members.data.0.likes_sent_count', 1)
        ->where('members.data.0.likes_received_count', 1)
        ->where('members.data.0.passes_sent_count', 1)
        ->where('members.data.0.passes_received_count', 1)
        ->where('members.data.0.matches_count', 2)
        ->where('members.data.0.messages_sent_count', 2)
        ->where('members.data.0.blocked_count', 1)
        ->where('members.data.0.blocked_by_count', 1)
        ->missing('members.data.0.messages'));

$this->actingAs($member)
    ->get(route('admin.members.index'))
    ->assertForbidden();
```

Tester séparément le nom d'affichage, l'e-mail insensible à la casse, la
conservation de `search` dans les liens et la présence des administrateurs sans
actions disponibles.

- [ ] **Step 2: Lancer le test et confirmer l'échec attendu**

Run: `php artisan test tests/Feature/Admin/ManageMembersTest.php --filter='catalog'`

Expected: FAIL parce que la route `admin.members.index` et le contrôleur n'existent pas.

- [ ] **Step 3: Implémenter la policy et la requête agrégée minimale**

Déclarer les capacités avec des rôles chargés explicitement :

```php
final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->hasRole(RoleName::Admin);
    }

    public function delete(User $actor, User $member): bool
    {
        return $actor->hasRole(RoleName::Admin)
            && ! $member->hasRole(RoleName::Admin);
    }

    public function startConversation(User $actor, User $member): bool
    {
        return $this->delete($actor, $member)
            && $member->status === UserStatus::Active
            && $member->profile?->isComplete() === true;
    }
}
```

Dans `MemberController::index(Request $request)`, appeler
`Gate::authorize('viewAny', User::class)`, joindre `profiles`, charger les rôles
et utiliser `withCount` avec les alias exacts suivants :

```php
'sentSwipes as likes_sent_count'
'receivedSwipes as likes_received_count'
'sentSwipes as passes_sent_count'
'receivedSwipes as passes_received_count'
'lowMatches as low_matches_count'
'highMatches as high_matches_count'
'authoredMessages as messages_sent_count'
'blocksCreated as blocked_count'
'blocksReceived as blocked_by_count'
```

Contraindre les quatre compteurs de swipes par `SwipeDecision::Like` ou
`SwipeDecision::Pass`, calculer `matches_count` comme la somme des deux colonnes,
ordonner par `users.created_at DESC, users.id DESC`, puis appeler
`paginate(20)->withQueryString()->through(...)`. Le mapping expose `can_delete`
et `can_start_conversation` via la policy, mais aucune relation de messages.

Ajouter la route dans le groupe `profile.complete`, `onboarding.complete`,
`role:admin` :

```php
Route::get('members', [AdminMemberController::class, 'index'])
    ->name('members.index');
```

- [ ] **Step 4: Relancer les tests du catalogue**

Run: `php artisan test tests/Feature/Admin/ManageMembersTest.php --filter='catalog'`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Policies/UserPolicy.php app/Http/Controllers/Admin/MemberController.php routes/web.php tests/Feature/Admin/ManageMembersTest.php
git commit -m "feat: add admin member catalog"
```

### Task 2: Suppression immédiate et notification localisée

**Files:**
- Create: `app/Actions/DeleteMember.php`
- Create: `app/Mail/MemberDeletedByAdminMail.php`
- Create: `resources/views/mail/admin/member-deleted.blade.php`
- Create: `tests/Feature/Mail/MemberDeletedByAdminMailTest.php`
- Modify: `app/Http/Controllers/Admin/MemberController.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Modify: `routes/web.php`
- Modify: `tests/Feature/Admin/ManageMembersTest.php`

**Interfaces:**
- Consumes: `UserPolicy::delete(User $actor, User $member)`.
- Produces: `DeleteMember::handle(User $member): void`, route DELETE `admin.members.destroy`, mailable scalaire `MemberDeletedByAdminMail(string $displayName)`.

- [ ] **Step 1: Écrire les tests rouges de suppression**

Tester qu'une cible ordinaire disparaît avec ses sessions, swipes, matchs,
conversations, messages et blocages en cascade, et qu'un mail est mis en file
avec la langue capturée :

```php
Mail::fake();

$this->actingAs($admin)
    ->delete(route('admin.members.destroy', $member))
    ->assertRedirect(route('admin.members.index'));

$this->assertDatabaseMissing('users', ['id' => $member->id]);
$this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
Mail::assertQueued(
    MemberDeletedByAdminMail::class,
    fn (MemberDeletedByAdminMail $mail): bool =>
        $mail->hasTo($memberEmail) && $mail->locale === 'en',
);
```

Ajouter trois tests : suppression d'un administrateur refusée et sans mail,
suppression par un non-administrateur refusée, exception de `Mail::queue()` qui
laisse malgré tout le compte supprimé et retourne un succès.

- [ ] **Step 2: Vérifier que les tests échouent pour les symboles absents**

Run: `php artisan test tests/Feature/Admin/ManageMembersTest.php --filter='delet'`

Expected: FAIL sur la route ou la classe `DeleteMember` absente.

- [ ] **Step 3: Implémenter l'action transactionnelle et le mail scalaire**

Capturer les valeurs avant suppression et ne jamais passer `User` au mail :

```php
public function handle(User $member): void
{
    [$email, $locale, $displayName] = [
        $member->email,
        $member->preferredLocale(),
        $member->profile?->display_name ?? $member->email,
    ];

    DB::transaction(function () use ($member): void {
        DB::table('sessions')->where('user_id', $member->id)->delete();
        $member->delete();
    });

    try {
        Mail::to($email)->queue(
            (new MemberDeletedByAdminMail($displayName))->locale($locale),
        );
    } catch (Throwable $exception) {
        report($exception);
    }
}
```

Le mailable utilise `Queueable`, `SerializesModels`, un sujet traduit et la vue
Markdown localisée. Le contrôleur charge `roles` et `profile`, autorise `delete`,
appelle l'action, émet un toast Inertia localisé, puis redirige vers le catalogue.

- [ ] **Step 4: Tester les rendus français et anglais du mail**

Dans `MemberDeletedByAdminMailTest`, rendre chaque locale et vérifier le sujet,
le nom, la révocation d'accès, le retrait des données actives et le canal de
contact, sans identifiant interne ni contenu privé.

Run: `php artisan test tests/Feature/Mail/MemberDeletedByAdminMailTest.php tests/Feature/Admin/ManageMembersTest.php --filter='delet|mail'`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/DeleteMember.php app/Mail/MemberDeletedByAdminMail.php app/Http/Controllers/Admin/MemberController.php resources/views/mail/admin/member-deleted.blade.php lang/fr/administration.php lang/en/administration.php routes/web.php tests/Feature/Admin/ManageMembersTest.php tests/Feature/Mail/MemberDeletedByAdminMailTest.php
git commit -m "feat: delete members with admin notification"
```

### Task 3: Match et conversation classiques initiés par l'administrateur

**Files:**
- Create: `app/Actions/OpenAdminMemberConversation.php`
- Create: `app/Data/AdminMemberConversationResult.php`
- Create: `app/Http/Controllers/Admin/MemberConversationController.php`
- Create: `tests/Feature/Admin/OpenMemberConversationTest.php`
- Modify: `routes/web.php`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`

**Interfaces:**
- Consumes: `UserPolicy::startConversation`, contraintes uniques `matches(user_low_id,user_high_id)` et `conversations(match_id)`.
- Produces: `OpenAdminMemberConversation::handle(User $admin, User $member): AdminMemberConversationResult`, dont les propriétés sont `Conversation $conversation` et `bool $created`; route POST `admin.members.conversation.store`.

- [ ] **Step 1: Écrire les tests rouges de création et réutilisation**

```php
$response = $this->actingAs($admin)->post(
    route('admin.members.conversation.store', $member),
);

$response->assertRedirect(route('admin.members.index'));
$this->assertDatabaseCount('matches', 1);
$this->assertDatabaseCount('conversations', 1);
$this->assertDatabaseCount('swipes', 0);

$this->actingAs($admin)
    ->post(route('admin.members.conversation.store', $member))
    ->assertRedirect(route('conversations.show', $conversation));
```

Après le premier POST, faire un GET du catalogue et vérifier le contrat éphémère
`createdMatch.displayName` et `createdMatch.conversationHref`, puis vérifier qu'il
est consommé au GET suivant. Tester également cible administratrice, inactive,
profil incomplet, paire bloquée et acteur non administrateur.

- [ ] **Step 2: Confirmer l'échec attendu**

Run: `php artisan test tests/Feature/Admin/OpenMemberConversationTest.php`

Expected: FAIL car la route et l'action n'existent pas.

- [ ] **Step 3: Implémenter l'action idempotente**

Dans une transaction, trier les identifiants, verrouiller les deux utilisateurs
dans cet ordre, recharger la cible avec `roles` et `profile`, revalider son rôle,
son statut, son profil et l'absence de blocage, puis :

```php
$match = MemberMatch::query()->firstOrCreate([
    'user_low_id' => $lowId,
    'user_high_id' => $highId,
]);

$conversation = Conversation::query()->firstOrCreate([
    'match_id' => $match->id,
]);

return new AdminMemberConversationResult(
    conversation: $conversation,
    created: $match->wasRecentlyCreated || $conversation->wasRecentlyCreated,
);
```

Transformer les conflits de concurrence et cibles non disponibles en une
`ValidationException` localisée, sans créer de swipe.

- [ ] **Step 4: Implémenter le contrat de redirection**

Le contrôleur autorise `startConversation`. Si `created` vaut `false`, il
redirige vers `conversations.show`. Sinon il stocke en session
`admin.members.created_match` avec `displayName` et `conversationHref`, puis
redirige vers `admin.members.index`. `MemberController::index` consomme cette
clé une seule fois et l'expose sous `createdMatch`.

- [ ] **Step 5: Relancer les tests ciblés**

Run: `php artisan test tests/Feature/Admin/OpenMemberConversationTest.php`

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/OpenAdminMemberConversation.php app/Data/AdminMemberConversationResult.php app/Http/Controllers/Admin/MemberConversationController.php app/Http/Controllers/Admin/MemberController.php routes/web.php lang/fr/administration.php lang/en/administration.php tests/Feature/Admin/OpenMemberConversationTest.php
git commit -m "feat: let admins open member conversations"
```

### Task 4: Rendre les administrateurs impossibles à bloquer

**Files:**
- Modify: `app/Policies/ProfilePolicy.php`
- Modify: `app/Actions/BlockUser.php`
- Modify: `app/Http/Controllers/PublicMemberProfileController.php`
- Modify: `tests/Feature/BlockUserTest.php`
- Modify: `tests/Feature/BlockMemberControllerTest.php`
- Modify: `tests/Feature/PublicMemberProfileTest.php`

**Interfaces:**
- Consumes: `RoleName::Admin`, `User::hasRole()`.
- Produces: prop publique `canBlock: bool`; `BlockUser::handle()` lève `ValidationException` avec `blocking.unavailable` pour une cible administratrice.

- [ ] **Step 1: Écrire les tests rouges aux niveaux policy, HTTP et action**

Créer une cible avec le rôle admin et vérifier : POST de blocage en 404,
`BlockUser::handle()` en erreur de validation, table `blocks` vide, fiche publique
avec `member.is_admin === true`, `canBlock === false`, `canUnblock === false`.

- [ ] **Step 2: Exécuter les tests et observer le blocage actuellement accepté**

Run: `php artisan test tests/Feature/BlockUserTest.php tests/Feature/BlockMemberControllerTest.php tests/Feature/PublicMemberProfileTest.php --filter='admin'`

Expected: FAIL parce qu'un administrateur peut encore être bloqué.

- [ ] **Step 3: Ajouter les deux protections serveur**

Dans `ProfilePolicy::block`, charger les rôles de la cible et ajouter
`! $target->hasRole(RoleName::Admin)`. Dans `BlockUser`, vérifier la même règle
avant la transaction puis la revérifier sur la cible verrouillée et rechargée :

```php
if ($lockedBlocked->load('roles')->hasRole(RoleName::Admin)) {
    throw ValidationException::withMessages([
        'member' => __('blocking.unavailable'),
    ]);
}
```

Le contrôleur public charge `roles`, expose `member.is_admin`, `canBlock` et
`canUnblock`, tous deux faux pour un administrateur.

- [ ] **Step 4: Relancer les tests de blocage**

Run: `php artisan test tests/Feature/BlockUserTest.php tests/Feature/BlockMemberControllerTest.php tests/Feature/PublicMemberProfileTest.php`

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Policies/ProfilePolicy.php app/Actions/BlockUser.php app/Http/Controllers/PublicMemberProfileController.php tests/Feature/BlockUserTest.php tests/Feature/BlockMemberControllerTest.php tests/Feature/PublicMemberProfileTest.php
git commit -m "feat: prevent blocking administrators"
```

### Task 5: Propager et afficher l'identité administrateur sur les profils

**Files:**
- Modify: `app/Data/DiscoveryProfileData.php`
- Modify: `app/Services/DiscoveryService.php`
- Modify: `resources/js/types/discovery.ts`
- Modify: `resources/js/types/member.ts`
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Modify: `resources/js/components/profile/ProfilePresentation.vue`
- Modify: `resources/js/pages/Members/Show.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `lang/fr/profile.php`
- Modify: `lang/en/profile.php`
- Modify: `tests/Unit/DiscoveryServiceTest.php`
- Modify: `tests/Feature/DiscoveryPageTest.php`
- Modify: `tests/Feature/PublicMemberProfileTest.php`

**Interfaces:**
- Consumes: booléen `is_admin` de la fiche publique et rôle de l'utilisateur courant dans les props partagées.
- Produces: `DiscoveryProfileData::$isAdmin`, JSON `isAdmin`, TypeScript `DiscoveryProfile.isAdmin` et prop `ProfilePresentation.isAdmin?: boolean`.

- [ ] **Step 1: Écrire les tests rouges des contrats Inertia**

Vérifier qu'une suggestion administratrice contient `isAdmin: true`, qu'un
membre ordinaire contient `false`, et qu'une fiche publique administratrice
contient `member.is_admin: true` sans action de blocage.

- [ ] **Step 2: Confirmer les propriétés manquantes**

Run: `php artisan test tests/Unit/DiscoveryServiceTest.php tests/Feature/DiscoveryPageTest.php tests/Feature/PublicMemberProfileTest.php --filter='admin'`

Expected: FAIL sur `isAdmin` ou `is_admin` absent.

- [ ] **Step 3: Propager le rôle sans requête N+1**

Modifier l'eager load de découverte en `user.roles`, ajouter `bool $isAdmin` au
DTO, puis mapper :

```php
isAdmin: $profile->user->hasRole(RoleName::Admin),
```

Ajouter `isAdmin: boolean` aux types de découverte et `is_admin: boolean` à
`PublicMember`.

- [ ] **Step 4: Ajouter le badge accessible et la bordure dorée**

Dans `ProfilePresentation`, accepter `isAdmin = false`, appliquer une classe de
bordure ambre quand elle vaut vrai et afficher en haut à droite un badge avec
`ShieldCheck`, `data-test="admin-profile-badge"` et
`t('profile.details.administrator')`. Préserver le slot `hero-actions` dans le
même conteneur sans chevauchement.

Dans `SwipeCard`, appliquer la même bordure et afficher le même libellé en haut à
droite avec `data-test="admin-discovery-badge"`. Passer `:is-admin` depuis les
pages publique et personnelle. Ne modifier aucun profil de démonstration ni
composant de conversation.

- [ ] **Step 5: Masquer les contrôles de blocage dans Vue**

Dans `Members/Show.vue`, rendre les actions seulement avec :

```vue
<template v-if="!member.is_admin" #summary-actions>
    <UnblockMemberButton v-if="canUnblock" ... />
    <BlockMemberDialog v-else-if="canBlock" ... />
</template>
```

- [ ] **Step 6: Relancer tests et contrôles TypeScript**

Run: `php artisan test tests/Unit/DiscoveryServiceTest.php tests/Feature/DiscoveryPageTest.php tests/Feature/PublicMemberProfileTest.php`

Run: `bun run types:check`

Expected: PASS pour les deux commandes.

- [ ] **Step 7: Commit**

```bash
git add app/Data/DiscoveryProfileData.php app/Services/DiscoveryService.php resources/js/types/discovery.ts resources/js/types/member.ts resources/js/components/discovery/SwipeCard.vue resources/js/components/profile/ProfilePresentation.vue resources/js/pages/Members/Show.vue resources/js/pages/profile/Show.vue lang/fr/profile.php lang/en/profile.php tests/Unit/DiscoveryServiceTest.php tests/Feature/DiscoveryPageTest.php tests/Feature/PublicMemberProfileTest.php
git commit -m "feat: identify administrators on profile cards"
```

### Task 6: Interface du catalogue, confirmation et dialogue de match

**Files:**
- Create: `resources/js/pages/Admin/Members/Index.vue`
- Create: `resources/js/components/admin/DeleteMemberDialog.vue`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `lang/fr/administration.php`
- Modify: `lang/en/administration.php`
- Modify: `tests/Browser/AdminTest.php`
- Generated: `resources/js/routes/admin/members/index.ts`
- Generated: `resources/js/routes/admin/members/conversation/index.ts`

**Interfaces:**
- Consumes: `members`, `filters.search`, `createdMatch` de `MemberController`; routes Wayfinder `admin.members.*`; `MatchDialog(open, match, conversationHref)`.
- Produces: tableau responsive administrateur avec recherche, pagination, suppression confirmée, bouton conversation et célébration à usage unique.

- [ ] **Step 1: Écrire les scénarios navigateur rouges**

Dans `AdminTest.php`, ajouter un scénario qui ouvre le catalogue, recherche un
membre, vérifie tous les compteurs, ouvre puis annule la confirmation et constate
que la ligne reste présente. Ajouter un scénario qui confirme la suppression et
voit le toast, ainsi qu'un scénario qui lance une conversation et voit
`[data-test="admin-created-match-dialog"]` avec le lien vers la messagerie.
Vérifier qu'une ligne administrateur n'a ni bouton supprimer ni bouton discuter.

- [ ] **Step 2: Générer les routes et confirmer l'échec UI**

Run: `php artisan wayfinder:generate --with-form`

Run: `php artisan test tests/Browser/AdminTest.php --filter='member catalog'`

Expected: FAIL car la page et les contrôles ne sont pas encore présents.

- [ ] **Step 3: Construire la page avec les primitives existantes**

Définir localement `AdminMemberRow`, `PaginationLink` et les props exactes.
Utiliser `router.get(index().url, { search }, { preserveState: true,
replace: true })` pour la recherche. Afficher les dates via
`useTranslations().formatDate`, les statuts avec des clés traduites, les huit
compteurs directionnels et les actions conditionnées par `can_delete` et
`can_start_conversation`.

Le bouton conversation soumet un POST Wayfinder. `DeleteMemberDialog` utilise
les composants `Dialog`, nomme explicitement la cible, possède des boutons
annuler/confirmer, et n'appelle `router.delete()` que dans le gestionnaire de
confirmation.

- [ ] **Step 4: Brancher le composant de match existant**

Initialiser l'ouverture avec `createdMatch !== null` et rendre :

```vue
<MatchDialog
    v-if="createdMatch"
    data-test="admin-created-match-dialog"
    v-model:open="matchDialogOpen"
    :match="{ displayName: createdMatch.displayName }"
    :conversation-href="createdMatch.conversationHref"
/>
```

Ajouter le lien « Membres » dans `AppSidebar.vue` avec l'icône `Users`.

- [ ] **Step 5: Vérifier le frontend et les scénarios navigateur**

Run: `bun run format`

Run: `bun run lint:check && bun run types:check && bun run build`

Run: `php artisan test tests/Browser/AdminTest.php --filter='member catalog'`

Expected: PASS pour toutes les commandes.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/Admin/Members/Index.vue resources/js/components/admin/DeleteMemberDialog.vue resources/js/components/AppSidebar.vue resources/js/routes/admin/members lang/fr/administration.php lang/en/administration.php tests/Browser/AdminTest.php
git commit -m "feat: add admin member management interface"
```

### Task 7: Validation navigateur des badges administrateur

**Files:**
- Modify: `tests/Browser/DiscoveryTest.php`
- Modify: `tests/Browser/ProfileAndNavigationTest.php`
- Modify: `tests/Browser/MemberBlockingTest.php`

**Interfaces:**
- Consumes: sélecteurs `admin-discovery-badge`, `admin-profile-badge` et absence des actions de blocage.
- Produces: preuve Chromium du badge, de la bordure et de l'absence de blocage sur chaque surface prévue.

- [ ] **Step 1: Ajouter les scénarios navigateur ciblés**

Créer un administrateur avec profil et vérifier son badge sur la découverte, sa
fiche publique et sa propre fiche. Vérifier le libellé français puis anglais,
l'absence de `block-member-trigger` sur la fiche publique et l'absence du badge
sur une carte ordinaire.

- [ ] **Step 2: Exécuter les scénarios et corriger uniquement les écarts observés**

Run: `php artisan test tests/Browser/DiscoveryTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/MemberBlockingTest.php --filter='administrator badge|administrator cannot be blocked'`

Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Browser/DiscoveryTest.php tests/Browser/ProfileAndNavigationTest.php tests/Browser/MemberBlockingTest.php
git commit -m "test: cover administrator profile identity"
```

### Task 8: Aligner la documentation produit et vérifier la branche

**Files:**
- Modify: `docs/PRD.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/data-model.md`
- Modify: `docs/technical-architecture.md`
- Modify: `docs/editorial-guidelines.md`

**Interfaces:**
- Consumes: comportement livré par les Tasks 1 à 7.
- Produces: documentation cohérente sur suppression immédiate, agrégats privés, exception de match, protection et badge administrateur.

- [ ] **Step 1: Mettre à jour les contrats documentaires**

Dans le PRD, marquer le catalogue, les statistiques, la suppression et la
conversation comme implémentés. Dans sécurité/confidentialité, préciser que la
suppression retire immédiatement les données actives tandis que les sauvegardes
expirent sous 30 jours, et que les administrateurs voient des agrégats sans corps
de message. Dans le modèle de données et l'architecture, documenter l'exception
de match administratif sans swipes et l'unicité par paire. Dans la charte,
documenter les libellés de badge FR/EN.

- [ ] **Step 2: Contrôler les références et les marqueurs incomplets**

Run: `rg -n "pending_deletion|likes réciproques|reciprocal likes|administrateur|administrator" docs/PRD.md docs/security-privacy.md docs/data-model.md docs/technical-architecture.md docs/editorial-guidelines.md`

Expected: chaque occurrence décrit la nouvelle exception sans contradiction.

- [ ] **Step 3: Lancer tous les contrôles ciblés puis complets**

Run: `composer lint:check`

Run: `composer analyse`

Run: `php artisan wayfinder:generate --with-form`

Run: `bun run lint:check && bun run format:check && bun run types:check && bun run build`

Run: `composer test`

Run: `git diff --check`

Expected: toutes les commandes réussissent, la suite Pest ne contient aucun
échec et `git diff --check` ne produit aucune sortie.

- [ ] **Step 4: Commit documentaire final**

```bash
git add docs/PRD.md docs/security-privacy.md docs/data-model.md docs/technical-architecture.md docs/editorial-guidelines.md
git commit -m "docs: document admin member management"
```

- [ ] **Step 5: Demander une revue de code**

Invoquer `superpowers:requesting-code-review`, corriger toute observation fondée,
relancer les contrôles affectés, puis préparer l'intégration avec
`superpowers:finishing-a-development-branch`.
