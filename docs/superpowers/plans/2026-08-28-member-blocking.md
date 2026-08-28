# Immediate Member Blocking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Let a member block another member from a public profile or conversation with immediate bilateral effects on discovery, conversation archival, messaging, and Reverb authorization.

**Architecture:** Keep blocks as the directed audit record and centralize bilateral pair detection on User. A transactional BlockUser action owns idempotent persistence and conversation archival; Laravel Policies protect public profile access and every conversation capability. One reusable Vue confirmation component submits the same Inertia route from both UI surfaces.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Pest Browser, Playwright, Reverb.

**Spec:** docs/superpowers/specs/2026-08-28-member-blocking-design.md

## Global Constraints

- The product remains strictly friendly and reserved for adults.
- Never reveal which member initiated a block.
- Reporting, moderation, and unblocking are out of scope.
- Every sensitive behavior remains enforced by Laravel.
- Every user-visible string comes from the Laravel/frontend catalogs in French and English.
- Follow red-green-refactor and observe each expected failure before implementation.
- Do not add dependencies, migrations, or unrelated abstractions.

---

### Task 1: Transactional idempotent blocking domain

**Files:**
- Create: app/Actions/BlockUser.php
- Modify: app/Models/User.php
- Create: tests/Feature/BlockUserTest.php
- Create: tests/Feature/BlockUserConcurrencyTest.php

**Interfaces:**
- Consumes: Block, MemberMatch, Conversation and the canonical user_low_id/user_high_id fields.
- Produces: User::hasBlockedRelationshipWith(User $other): bool and BlockUser::handle(User $blocker, User $blocked): Block.

- [ ] **Step 1: Write failing business tests**

Add observable tests equivalent to:

    test('a member block is unique idempotent and archives the pair conversation', function () {
        [$blocker, $blocked, $conversation] = matchedConversation();
        $action = app(BlockUser::class);

        $first = $action->handle($blocker, $blocked);
        $second = $action->handle($blocker, $blocked);

        expect($second->is($first))->toBeTrue()
            ->and(Block::query()->count())->toBe(1)
            ->and($conversation->fresh()->archived_at)->not->toBeNull();
    });

    test('a block is bilateral for relationship checks', function () {
        [$left, $right] = memberPair();
        Block::factory()->create([
            'blocker_user_id' => $left->id,
            'blocked_user_id' => $right->id,
        ]);

        expect($left->hasBlockedRelationshipWith($right))->toBeTrue()
            ->and($right->hasBlockedRelationshipWith($left))->toBeTrue();
    });

Also assert self-blocking raises ValidationException, success without a match, and preservation of an existing archived_at timestamp.

- [ ] **Step 2: Run the test and verify RED**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockUserTest.php

Expected: FAIL because BlockUser and hasBlockedRelationshipWith do not exist.

- [ ] **Step 3: Implement the minimal model query and transaction**

Implement bilateral lookup as one Block query with the two directed pairs. In BlockUser: reject equal IDs with ValidationException and the blocking.unavailable translation; sort and lock both user rows; firstOrCreate the directed Block; find the canonical MemberMatch; update only an active conversation with one now() value; return the Block.

- [ ] **Step 4: Run the feature test and verify GREEN**

Run Step 2 again. Expected: PASS without warnings.

- [ ] **Step 5: Prove concurrent idempotence with MySQL**

Adapt the two-connection barriers from tests/Feature/CreateSwipeConcurrencyTest.php. Two overlapping calls by the same blocker must finish, produce one row and one archived conversation. Observe the duplicate/lock failure before adding the minimal unique-race recovery.

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockUserConcurrencyTest.php

Expected after correction: PASS.

- [ ] **Step 6: Commit**

    git add app/Actions/BlockUser.php app/Models/User.php tests/Feature/BlockUserTest.php tests/Feature/BlockUserConcurrencyTest.php
    git commit -m "feat: add transactional member blocking"

---

### Task 2: Enforce the block in Policies, sending and Reverb

**Files:**
- Modify: app/Policies/ConversationPolicy.php
- Modify: app/Actions/SendMessage.php
- Keep and verify: routes/channels.php
- Modify: tests/Feature/ConversationTest.php
- Modify: tests/Feature/SendMessageTest.php
- Modify: tests/Feature/MessageBroadcastTest.php
- Create: tests/Feature/BlockMessageRaceTest.php

**Interfaces:**
- Consumes: User::hasBlockedRelationshipWith() from Task 1.
- Produces: ConversationPolicy::view() retaining history access and ConversationPolicy::send() requiring an active, unblocked participant pair.

- [ ] **Step 1: Add failing bilateral authorization tests**

For both block directions assert:

    expect(Gate::forUser($left)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($right)->allows('view', $conversation))->toBeTrue()
        ->and(Gate::forUser($left)->allows('send', $conversation))->toBeFalse()
        ->and(Gate::forUser($right)->allows('send', $conversation))->toBeFalse();

Assert SendMessage raises AuthorizationException without persisting/broadcasting, and POST /broadcasting/auth returns 403 for both participants.

- [ ] **Step 2: Run and verify RED**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/ConversationTest.php tests/Feature/SendMessageTest.php tests/Feature/MessageBroadcastTest.php

Expected: blocked active conversations still authorize sends and channel subscription.

- [ ] **Step 3: Make send authorization bilateral**

Load the match users and require archived_at to be null, view() to pass, and lowUser->hasBlockedRelationshipWith(highUser) to be false. Keep view() unchanged. Keep routes/channels.php delegating to the send Policy.

- [ ] **Step 4: Lock and reauthorize inside SendMessage**

Inside the DB transaction reload and lock the conversation, lock its two members in ascending ID order, reload the match relation, then call Gate::forUser($author)->authorize('send', $lockedConversation) immediately before insertion. Dispatch MessageSent only for a created message.

- [ ] **Step 5: Add the block/send race test**

Using two MySQL connections, serialize an overlapping send and block. Assert either the message commits before blocking or is rejected after blocking, and never has created_at after archived_at.

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockMessageRaceTest.php

- [ ] **Step 6: Verify and commit**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/ConversationTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php tests/Feature/BlockMessageRaceTest.php
    git add app/Policies/ConversationPolicy.php app/Actions/SendMessage.php routes/channels.php tests/Feature
    git commit -m "feat: deny messages across blocked pairs"

---

### Task 3: Add the authorized public member profile

**Files:**
- Create: app/Http/Controllers/PublicMemberProfileController.php
- Modify: app/Policies/ProfilePolicy.php
- Modify: routes/web.php
- Create: resources/js/pages/Members/Show.vue
- Create: resources/js/types/member.ts
- Modify: resources/js/types/index.ts
- Create: tests/Feature/PublicMemberProfileTest.php
- Modify: tests/Browser/DiscoveryTest.php
- Modify: resources/js/components/discovery/SwipeCard.vue

**Interfaces:**
- Produces: route members.show, Inertia page Members/Show, and PublicMemberProfile with id, display_name, age, avatar, bio, visit_frequency and interests.

- [ ] **Step 1: Write failing authorization and serialization tests**

Assert success with only the public fields. Assert 404 for self, inactive, minor, incomplete, hidden, missing/inactive avatar and either block direction. Assert email, roles, birth_date and internal profile IDs are absent.

    $this->actingAs($viewer)->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Members/Show')
            ->where('member.id', $member->id)
            ->missing('member.email'));

- [ ] **Step 2: Run and verify RED**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/PublicMemberProfileTest.php

Expected: FAIL because members.show does not exist.

- [ ] **Step 3: Implement ProfilePolicy::viewPublic and the controller**

Require distinct users, active adult target, completed and visible profile, active avatar, and no bilateral block. Load active interests ordered by sort_order/id. Serialize only the interface above. Add GET members/{member} named members.show inside onboarding.complete.

- [ ] **Step 4: Build the typed page**

Define PublicMemberProfile exactly from the produced contract. Compose Members/Show.vue with AvatarPortrait, Badge and Card patterns from profile/Show.vue. Render no email, edit, visibility or settings data. Use only translated copy.

- [ ] **Step 5: Link the active discovery card**

Add an accessible Link to members.show(profile.userId) and stop pointer propagation so it never emits a swipe. Add a Pest Browser journey that opens the active suggestion profile.

    php artisan wayfinder:generate --with-form
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/PublicMemberProfileTest.php tests/Browser/DiscoveryTest.php
    bun run types:check

- [ ] **Step 6: Commit**

    git add app/Http/Controllers/PublicMemberProfileController.php app/Policies/ProfilePolicy.php routes/web.php resources/js/pages/Members resources/js/types resources/js/components/discovery/SwipeCard.vue tests/Feature/PublicMemberProfileTest.php tests/Browser/DiscoveryTest.php
    git commit -m "feat: add public member profiles"

---

### Task 4: Expose one neutral endpoint and reusable dialog

**Files:**
- Create: app/Http/Controllers/BlockMemberController.php
- Modify: app/Policies/ProfilePolicy.php
- Modify: routes/web.php
- Create: lang/fr/blocking.php
- Create: lang/en/blocking.php
- Modify: lang/fr/frontend.php
- Modify: lang/en/frontend.php
- Modify: app/Support/FrontendTranslations.php
- Modify: resources/js/types/i18n.ts
- Create: resources/js/components/members/BlockMemberDialog.vue
- Create: tests/Feature/BlockMemberControllerTest.php
- Modify: tests/Feature/Localization/InertiaTranslationsTest.php

**Interfaces:**
- Produces: route members.block; translation group blocking; BlockMemberDialog props memberId: number and memberDisplayName: string.

- [ ] **Step 1: Write failing endpoint and translation tests**

Assert authorized POST creates/archives, redirects to discovery.index, and flashes exactly:

    [
        'type' => 'success',
        'message' => __('blocking.completed'),
    ]

Repeat POST and assert the same response with one row. Assert self and unknown targets do not disclose relationship state. Assert French and English contain every blocking key.

- [ ] **Step 2: Run and verify RED**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockMemberControllerTest.php tests/Feature/Localization/InertiaTranslationsTest.php

- [ ] **Step 3: Implement controller, Policy and route**

ProfilePolicy::block uses the same target eligibility as public view while allowing an already-created outgoing block to repeat idempotently. BlockMemberController authorizes, calls BlockUser and redirects with the neutral flash. Add POST members/{member}/block named members.block.

- [ ] **Step 4: Add exact bilingual contracts**

Backend blocking.php keys: completed and unavailable. Frontend blocking keys: trigger, title, description, effects, cancel, confirm, submitting, error, profile_link and private_conversation. Add the group to FrontendTranslations and TranslationMessages. Neither language names who initiated the block.

- [ ] **Step 5: Build the dialog**

Use existing Dialog and Button primitives. Submit router.post(blockMember(memberId).url). Disable trigger/cancel/confirm during submission. Preserve an open dialog and show blocking.error in aria-live=assertive on failure. Allow successful navigation to close the page naturally.

- [ ] **Step 6: Verify and commit**

    php artisan wayfinder:generate --with-form
    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockMemberControllerTest.php tests/Feature/Localization/InertiaTranslationsTest.php
    bun run types:check
    bun run lint:check
    git add app/Http/Controllers/BlockMemberController.php app/Policies/ProfilePolicy.php routes/web.php lang app/Support/FrontendTranslations.php resources/js/types/i18n.ts resources/js/components/members/BlockMemberDialog.vue tests/Feature/BlockMemberControllerTest.php tests/Feature/Localization/InertiaTranslationsTest.php
    git commit -m "feat: expose neutral member blocking action"

---

### Task 5: Integrate both blocking entry points

**Files:**
- Modify: resources/js/pages/Members/Show.vue
- Modify: resources/js/components/conversations/ConversationHeader.vue
- Modify: resources/js/pages/Conversations/Show.vue
- Create: tests/Browser/MemberBlockingTest.php
- Modify: tests/Browser/ConversationTest.php

**Interfaces:**
- Consumes: BlockMemberDialog, members.show, participant.id and the blocking translations.
- Produces: profile and conversation blocking controls plus conversation-to-profile navigation.

- [ ] **Step 1: Write failing browser journeys**

From public profile and conversation: open confirmation, assert neutral effects copy, cancel without mutation, confirm, arrive at /discover, see the neutral toast and observe archived/no-send behavior. Add a failed request case that preserves the dialog and permits retry.

- [ ] **Step 2: Run and verify RED**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/MemberBlockingTest.php tests/Browser/ConversationTest.php

Expected: block triggers are absent.

- [ ] **Step 3: Add the profile action**

Render BlockMemberDialog with member.id and member.display_name in the public profile's secondary actions, away from positive discovery controls.

- [ ] **Step 4: Add conversation navigation and action**

Extend ConversationHeader with profileHref?: string and blockable?: boolean. Link the avatar/name only when profileHref exists. Render BlockMemberDialog only when blockable is true. The onboarding caller stays unchanged because both props default safely. From Conversations/Show pass members.show(participant.id).url and blockable when archived_at is null.

- [ ] **Step 5: Verify and commit**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Browser/MemberBlockingTest.php tests/Browser/ConversationTest.php
    bun run types:check
    bun run lint:check
    bun run format:check
    git add resources/js/pages/Members/Show.vue resources/js/components/conversations/ConversationHeader.vue resources/js/pages/Conversations/Show.vue tests/Browser/MemberBlockingTest.php tests/Browser/ConversationTest.php
    git commit -m "feat: add member blocking controls"

---

### Task 6: Full regression and quality verification

**Files:**
- Modify only files required to correct failures caused by Tasks 1-5.

**Interfaces:**
- Produces: recent evidence for every acceptance criterion.

- [ ] **Step 1: Run all affected suites**

    APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test tests/Feature/BlockUserTest.php tests/Feature/BlockUserConcurrencyTest.php tests/Feature/BlockMessageRaceTest.php tests/Feature/BlockMemberControllerTest.php tests/Feature/PublicMemberProfileTest.php tests/Unit/DiscoveryServiceTest.php tests/Feature/DiscoveryPageTest.php tests/Feature/ConversationTest.php tests/Feature/ConversationIndexTest.php tests/Feature/SendMessageTest.php tests/Feature/StoreMessageTest.php tests/Feature/MessageBroadcastTest.php tests/Feature/Localization/InertiaTranslationsTest.php tests/Browser/MemberBlockingTest.php tests/Browser/DiscoveryTest.php tests/Browser/ConversationTest.php

Expected: PASS without warnings.

- [ ] **Step 2: Run backend checks**

    composer lint:check
    composer analyse
    composer test

Expected: every command exits 0.

- [ ] **Step 3: Run frontend checks**

    php artisan wayfinder:generate --with-form
    bun run lint:check
    bun run format:check
    bun run types:check
    bun run build

Expected: every command exits 0.

- [ ] **Step 4: Inspect the final diff**

    git diff --check
    git status --short
    git diff main...HEAD --stat

Confirm there is no migration, hard-coded visible copy, unblock/reporting feature, or unrelated change.

- [ ] **Step 5: Commit only required corrections**

If verification required corrections:

    git commit -m "fix: complete member blocking safeguards"

If no correction was required, do not create an empty commit.

