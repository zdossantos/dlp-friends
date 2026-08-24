# Interest Catalog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an admin-managed interest catalog with an accessible member tag selector, a configurable default limit of five, and archival that preserves history without consuming selection capacity.

**Architecture:** Rename the existing passion schema and domain to interests through a data-preserving migration, then keep effective selections distinct from suspended history through `interest_profile.is_selected`. Focused actions own transactional status, ordering, and selection changes; Laravel Policies and Form Requests protect every write, while Inertia/Vue pages render the admin catalog and member tags.

**Tech Stack:** PHP 8.4, Laravel 13, Eloquent, Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS, Reka UI, Pest, Vitest, Vue Test Utils, Bun 1.3.14.

**Spec:** `docs/superpowers/specs/2026-08-24-interest-catalog-design.md`

## Global Constraints

- Use “intérêt” in French product copy and `interest` in code; do not introduce `passion` in new runtime code.
- Rename existing tables and columns without losing deployed data.
- Categories remain technical and have no administration UI in this issue.
- The initial global maximum is exactly 5 and remains editable by an administrator.
- Archived interests are invisible to members, consume no capacity, and remain visible to administrators as history.
- An administrator receives no global authorization bypass and no implicit access to private messages.
- Use Laravel Policies, Form Requests, transactions, existing UI primitives, Composition API, and TypeScript.
- Follow red-green-refactor and commit each independently passing task.

---

## File Structure

- `database/migrations/2026_08_24_000000_rename_passions_to_interests.php`: rename deployed schema and add pivot history state.
- `database/migrations/2026_08_24_010000_create_interest_settings_table.php`: persist the singleton selection limit.
- `app/Models/Interest.php`, `InterestCategory.php`, `InterestSetting.php`: catalog persistence and ordered/active scopes.
- `app/Actions/SyncProfileInterests.php`: synchronize active member choices while preserving suspended rows.
- `app/Actions/SetInterestStatus.php`: archive or reactivate atomically and restore only profiles with capacity.
- `app/Actions/MoveInterest.php`: normalize and change catalog order atomically.
- `app/Policies/InterestPolicy.php`: admin-only catalog capabilities without a global Gate bypass.
- `app/Http/Controllers/Admin/InterestController.php`: catalog index and CRUD.
- `app/Http/Controllers/Admin/InterestStatusController.php`: archive/reactivate endpoint.
- `app/Http/Controllers/Admin/InterestOrderController.php`: move endpoint.
- `app/Http/Controllers/Admin/InterestSettingController.php`: limit endpoint.
- `app/Http/Requests/*Interest*Request.php`: server-authoritative validation and authorization.
- `resources/js/components/profile/InterestTagSelector.vue`: accessible tag toggle behavior.
- `resources/js/pages/Admin/Interests/Index.vue`: responsive interest catalog administration.
- `resources/js/types/interest.ts`: shared interest contracts.

---

### Task 1: Rename the Existing Passion Domain Without Data Loss

**Files:**
- Create: `database/migrations/2026_08_24_000000_rename_passions_to_interests.php`
- Create: `tests/Feature/Database/InterestRenameMigrationTest.php`
- Rename: `app/Models/Passion.php` → `app/Models/Interest.php`
- Rename: `app/Models/PassionCategory.php` → `app/Models/InterestCategory.php`
- Rename: `database/factories/PassionFactory.php` → `database/factories/InterestFactory.php`
- Rename: `database/factories/PassionCategoryFactory.php` → `database/factories/InterestCategoryFactory.php`
- Modify: `app/Models/Profile.php`
- Modify: `app/Services/DiscoveryService.php`
- Modify: `app/Data/DiscoveryProfileData.php`
- Modify: `resources/js/types/discovery.ts`
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Modify: `resources/js/pages/Discovery/Index.vue`
- Modify: `tests/Feature/DiscoverySchemaTest.php`
- Modify: `tests/Feature/DiscoveryPageTest.php`
- Modify: `tests/Unit/DiscoveryServiceTest.php`
- Modify: `resources/js/components/discovery/SwipeCard.spec.ts`
- Modify: `resources/js/pages/Discovery/Index.spec.ts`

**Interfaces:**
- Produces: `Interest`, `InterestCategory`, `Profile::interests()`, `Profile::interestHistory()`, `DiscoveryProfileData::$commonInterests`, and database tables `interest_categories`, `interests`, `interest_profile`.
- Produces: pivot column `interest_profile.is_selected: bool`, defaulting to `true`.

- [ ] **Step 1: Write the failing migration test**

```php
public function test_it_renames_the_catalog_and_preserves_existing_associations(): void
{
    $migration = require database_path('migrations/2026_08_24_000000_rename_passions_to_interests.php');
    $migration->down();

    $profileId = DB::table('profiles')->insertGetId([
        'user_id' => User::factory()->create()->id,
        'display_name' => 'Member',
        'visibility' => 'visible',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $categoryId = DB::table('passion_categories')->insertGetId([
        'name' => 'General', 'sort_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $passionId = DB::table('passions')->insertGetId([
        'passion_category_id' => $categoryId,
        'name' => 'Attractions', 'is_active' => true, 'sort_order' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('passion_profile')->insert([
        'profile_id' => $profileId, 'passion_id' => $passionId,
    ]);

    $migration->up();

    $this->assertTrue(Schema::hasColumns('interests', ['interest_category_id']));
    $this->assertTrue(Schema::hasColumns('interest_profile', ['interest_id', 'is_selected']));
    $this->assertDatabaseHas('interest_profile', [
        'profile_id' => $profileId,
        'interest_id' => $passionId,
        'is_selected' => true,
    ]);
}
```

- [ ] **Step 2: Run the test and confirm the missing migration failure**

Run: `php artisan test tests/Feature/Database/InterestRenameMigrationTest.php`

Expected: FAIL because `2026_08_24_000000_rename_passions_to_interests.php` does not exist.

- [ ] **Step 3: Implement the reversible schema rename**

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::rename('passion_categories', 'interest_categories');
        Schema::rename('passions', 'interests');
        Schema::rename('passion_profile', 'interest_profile');

        Schema::table('interests', function (Blueprint $table): void {
            $table->renameColumn('passion_category_id', 'interest_category_id');
        });
        Schema::table('interest_profile', function (Blueprint $table): void {
            $table->renameColumn('passion_id', 'interest_id');
            $table->boolean('is_selected')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('interest_profile', function (Blueprint $table): void {
            $table->dropIndex(['is_selected']);
            $table->dropColumn('is_selected');
            $table->renameColumn('interest_id', 'passion_id');
        });
        Schema::table('interests', function (Blueprint $table): void {
            $table->renameColumn('interest_category_id', 'passion_category_id');
        });
        Schema::rename('interest_profile', 'passion_profile');
        Schema::rename('interests', 'passions');
        Schema::rename('interest_categories', 'passion_categories');
    }
};
```

If MySQL retains old foreign-key constraint names, leave the names intact; test referential behavior instead of rebuilding data-bearing tables. Ensure `down()` remains executable on SQLite and MySQL.

- [ ] **Step 4: Rename PHP domain classes and relations**

```php
// app/Models/Interest.php
#[Fillable(['interest_category_id', 'name', 'is_active', 'sort_order'])]
class Interest extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InterestCategory::class, 'interest_category_id');
    }

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class)
            ->withPivot('is_selected');
    }
}

// app/Models/Profile.php
public function interests(): BelongsToMany
{
    return $this->belongsToMany(Interest::class)
        ->withPivot('is_selected')
        ->wherePivot('is_selected', true);
}

public function interestHistory(): BelongsToMany
{
    return $this->belongsToMany(Interest::class)
        ->withPivot('is_selected');
}
```

Rename factories and every runtime/test reference. Rename discovery output exactly:

```php
public int $commonInterestCount,
/** @var list<string> */
public array $commonInterests,
```

```ts
commonInterestCount: number;
commonInterests: string[];
```

- [ ] **Step 5: Run focused schema, discovery, PHPStan, and frontend tests**

Run:

```bash
php artisan test tests/Feature/Database/InterestRenameMigrationTest.php tests/Feature/DiscoverySchemaTest.php tests/Unit/DiscoveryServiceTest.php tests/Feature/DiscoveryPageTest.php
composer analyse
bun run test:unit -- resources/js/components/discovery/SwipeCard.spec.ts resources/js/pages/Discovery/Index.spec.ts
bun run types:check
```

Expected: all commands PASS and `rg -n 'Passion|passion' app resources/js tests database/factories` returns no runtime references.

- [ ] **Step 6: Commit the vocabulary migration**

```bash
git add app database resources/js tests
git commit -m "refactor: renommer les passions en intérêts"
```

---

### Task 2: Add the Catalog Setting and Idempotent Seed Data

**Files:**
- Create: `database/migrations/2026_08_24_010000_create_interest_settings_table.php`
- Create: `app/Models/InterestSetting.php`
- Create: `database/seeders/InterestCatalogSeeder.php`
- Create: `tests/Feature/InterestCatalogSeederTest.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Interfaces:**
- Consumes: `Interest`, `InterestCategory`, and renamed tables from Task 1.
- Produces: `InterestSetting::current(): InterestSetting` and `InterestSetting::$max_selections`.

- [ ] **Step 1: Write failing tests for the default and idempotent catalog**

```php
public function test_it_seeds_the_default_limit_and_ordered_interests_idempotently(): void
{
    $this->seed(InterestCatalogSeeder::class);
    $this->seed(InterestCatalogSeeder::class);

    expect(InterestSetting::current()->max_selections)->toBe(5)
        ->and(Interest::query()->orderBy('sort_order')->pluck('name')->all())->toBe([
            'Chill',
            'Attractions à sensations',
            'Attractions calmes',
            'Collection / merch',
            'Pins',
            'Rencontres personnages',
            'Spectacles',
            'Food',
            'Secrets / anecdotes',
            'Événements',
        ]);

    $this->assertDatabaseCount('interest_settings', 1);
    $this->assertDatabaseCount('interests', 10);
}

public function test_reseeding_does_not_reactivate_or_reorder_existing_interests(): void
{
    $this->seed(InterestCatalogSeeder::class);
    Interest::query()->where('name', 'Chill')->update([
        'is_active' => false,
        'sort_order' => 42,
    ]);

    $this->seed(InterestCatalogSeeder::class);

    $this->assertDatabaseHas('interests', [
        'name' => 'Chill', 'is_active' => false, 'sort_order' => 42,
    ]);
}
```

- [ ] **Step 2: Run the seeder test and confirm missing classes/tables**

Run: `php artisan test tests/Feature/InterestCatalogSeederTest.php`

Expected: FAIL because `InterestSetting` and `InterestCatalogSeeder` do not exist.

- [ ] **Step 3: Add the singleton setting migration and model**

```php
Schema::create('interest_settings', function (Blueprint $table): void {
    $table->id();
    $table->unsignedSmallInteger('max_selections')->default(5);
    $table->timestamps();
});

DB::table('interest_settings')->insert([
    'max_selections' => 5,
    'created_at' => now(),
    'updated_at' => now(),
]);
```

```php
#[Fillable(['max_selections'])]
class InterestSetting extends Model
{
    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['max_selections' => 5]);
    }

    protected function casts(): array
    {
        return ['max_selections' => 'integer'];
    }
}
```

- [ ] **Step 4: Implement the non-destructive catalog seeder**

```php
$category = InterestCategory::query()->firstOrCreate(
    ['name' => 'Général'],
    ['sort_order' => 0],
);

foreach (self::INTERESTS as $sortOrder => $name) {
    Interest::query()->firstOrCreate(
        ['name' => $name],
        [
            'interest_category_id' => $category->id,
            'is_active' => true,
            'sort_order' => $sortOrder,
        ],
    );
}

InterestSetting::current();
```

Call `InterestCatalogSeeder::class` from `DatabaseSeeder` before creating the local demo account.

- [ ] **Step 5: Run focused tests and static analysis**

Run:

```bash
php artisan test tests/Feature/InterestCatalogSeederTest.php
composer analyse
```

Expected: PASS.

- [ ] **Step 6: Commit the setting and seed data**

```bash
git add app/Models/InterestSetting.php database/migrations database/seeders tests/Feature/InterestCatalogSeederTest.php
git commit -m "feat: initialiser le catalogue des intérêts"
```

---

### Task 3: Implement Server-Authoritative Member Selection

**Files:**
- Create: `app/Actions/SyncProfileInterests.php`
- Create: `tests/Unit/SyncProfileInterestsTest.php`
- Modify: `app/Http/Requests/MemberProfileRequest.php`
- Modify: `app/Http/Controllers/MemberProfileController.php`
- Modify: `app/Models/Profile.php`
- Modify: `tests/Feature/MemberProfileTest.php`

**Interfaces:**
- Consumes: `InterestSetting::current()`, `Profile::interests()`, `Profile::interestHistory()`.
- Produces: `SyncProfileInterests::handle(Profile $profile, array $interestIds): void` where `$interestIds` is `list<int>`.
- Produces Inertia props: `interests: Array<{id: number; name: string}>`, `selectedInterestIds: number[]`, `interestLimit: number`.

- [ ] **Step 1: Write failing feature tests for active choices and the dynamic limit**

```php
public function test_member_can_select_distinct_active_interests_up_to_the_limit(): void
{
    InterestSetting::current()->update(['max_selections' => 2]);
    [$first, $second] = Interest::factory()->count(2)->create(['is_active' => true]);
    $inactive = Interest::factory()->create(['is_active' => false]);
    $user = User::factory()->create();

    $payload = [
        'display_name' => 'Magic Friend',
        'bio' => null,
        'visit_frequency' => VisitFrequency::Often->value,
        'visibility' => ProfileVisibility::Visible->value,
        'interest_ids' => [$first->id, $second->id],
    ];

    $this->actingAs($user)->post(route('member-profile.store'), $payload)
        ->assertRedirect(route('app'));
    expect($user->fresh()->profile->interests()->pluck('interests.id')->all())
        ->toEqualCanonicalizing([$first->id, $second->id]);

    $this->actingAs($user)->patch(route('member-profile.update'), [
        ...$payload,
        'interest_ids' => [$inactive->id],
    ])->assertSessionHasErrors('interest_ids.0');
}
```

Add a second test that sets the limit to 1, submits two new IDs, and asserts `interest_ids` has an error. Add a third test proving a profile grandfathered at 2 can update its bio after the limit falls to 1 when it submits the same two IDs, but cannot replace one with a new ID.

- [ ] **Step 2: Run the member profile tests and confirm validation/props fail**

Run: `php artisan test tests/Feature/MemberProfileTest.php`

Expected: FAIL because `interest_ids` is ignored and interest props are absent.

- [ ] **Step 3: Implement dynamic validation including grandfathering**

```php
public function rules(): array
{
    return [
        // existing profile fields...
        'interest_ids' => ['present', 'array'],
        'interest_ids.*' => [
            'integer',
            'distinct',
            Rule::exists('interests', 'id')->where('is_active', true),
        ],
    ];
}

protected function prepareForValidation(): void
{
    $displayName = $this->input('display_name');

    $this->merge([
        'display_name' => is_string($displayName)
            ? preg_replace('/\s+/u', ' ', trim($displayName))
            : $displayName,
        'interest_ids' => $this->input('interest_ids', []),
    ]);
}

public function after(): array
{
    return [function (Validator $validator): void {
        $submitted = collect($this->input('interest_ids', []))->map(fn ($id) => (int) $id);
        $current = $this->user()?->profile?->interests()->pluck('interests.id') ?? collect();
        $limit = InterestSetting::current()->max_selections;

        if ($submitted->count() > $limit && $submitted->diff($current)->isNotEmpty()) {
            $validator->errors()->add(
                'interest_ids',
                "Vous pouvez sélectionner au maximum {$limit} intérêts.",
            );
        }
    }];
}
```

Do not trust client-side disabled states. Keep the `distinct` and active database rule.

- [ ] **Step 4: Write the failing unit test for suspended-history-safe synchronization**

```php
public function test_it_syncs_effective_interests_without_deleting_suspended_history(): void
{
    $profile = User::factory()->withProfile()->create()->profile;
    [$keep, $remove, $suspended] = Interest::factory()->count(3)->create();
    $profile->interestHistory()->attach([
        $keep->id => ['is_selected' => true],
        $remove->id => ['is_selected' => true],
        $suspended->id => ['is_selected' => false],
    ]);

    app(SyncProfileInterests::class)->handle($profile, [$keep->id]);

    $this->assertDatabaseHas('interest_profile', [
        'profile_id' => $profile->id,
        'interest_id' => $suspended->id,
        'is_selected' => false,
    ]);
    $this->assertDatabaseMissing('interest_profile', [
        'profile_id' => $profile->id,
        'interest_id' => $remove->id,
    ]);
}
```

- [ ] **Step 5: Implement transactional synchronization and controller props**

```php
public function handle(Profile $profile, array $interestIds): void
{
    DB::transaction(function () use ($profile, $interestIds): void {
        DB::table('interest_profile')
            ->where('profile_id', $profile->id)
            ->where('is_selected', true)
            ->when($interestIds !== [], fn ($query) => $query->whereNotIn('interest_id', $interestIds))
            ->delete();

        foreach ($interestIds as $interestId) {
            DB::table('interest_profile')->updateOrInsert(
                ['profile_id' => $profile->id, 'interest_id' => $interestId],
                ['is_selected' => true],
            );
        }
    });
}
```

In `MemberProfileController`, remove `interest_ids` before mass-assigning profile fields, call the action in the same outer transaction, and extend `formOptions()`:

```php
'interests' => Interest::query()
    ->where('is_active', true)
    ->orderBy('sort_order')
    ->orderBy('id')
    ->get(['id', 'name']),
'selectedInterestIds' => $profile?->interests()->pluck('interests.id')->all() ?? [],
'interestLimit' => InterestSetting::current()->max_selections,
```

- [ ] **Step 6: Run the focused backend tests**

Run:

```bash
php artisan test tests/Unit/SyncProfileInterestsTest.php tests/Feature/MemberProfileTest.php
composer analyse
```

Expected: PASS.

- [ ] **Step 7: Commit member selection backend**

```bash
git add app/Actions/SyncProfileInterests.php app/Http app/Models/Profile.php tests/Unit/SyncProfileInterestsTest.php tests/Feature/MemberProfileTest.php
git commit -m "feat: enregistrer les intérêts du profil"
```

---

### Task 4: Build the Accessible Member Interest Tags

**Files:**
- Create: `resources/js/types/interest.ts`
- Create: `resources/js/components/profile/InterestTagSelector.vue`
- Create: `resources/js/components/profile/InterestTagSelector.spec.ts`
- Modify: `resources/js/types/index.ts`
- Modify: `resources/js/types/auth.ts`
- Modify: `resources/js/components/profile/ProfileForm.vue`
- Modify: `resources/js/components/profile/ProfileForm.spec.ts`
- Modify: `resources/js/pages/profile/Create.vue`
- Modify: `resources/js/pages/profile/Edit.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `resources/js/pages/profile/Show.spec.ts`

**Interfaces:**
- Consumes: `interests`, `selectedInterestIds`, and `interestLimit` Inertia props from Task 3.
- Produces: `InterestOption = { id: number; name: string }` and hidden `interest_ids[]` inputs.

- [ ] **Step 1: Write failing tag interaction tests**

```ts
it('toggles tags and disables only unselected tags at the limit', async () => {
    const wrapper = mount(InterestTagSelector, {
        props: {
            interests: [
                { id: 1, name: 'Chill' },
                { id: 2, name: 'Spectacles' },
            ],
            selectedIds: [1],
            limit: 1,
        },
    });

    const chill = wrapper.get('button[aria-label="Retirer Chill"]');
    const shows = wrapper.get('button[aria-label="Ajouter Spectacles"]');
    expect(chill.attributes('aria-pressed')).toBe('true');
    expect(shows.attributes('disabled')).toBeDefined();

    await chill.trigger('click');
    expect(shows.attributes('disabled')).toBeUndefined();
    await shows.trigger('click');

    expect(wrapper.get('input[name="interest_ids[]"]').attributes('value')).toBe('2');
    expect(wrapper.text()).toContain('1 / 1');
});
```

Add a test asserting the component renders only the `interests` prop it receives; no archived fixture name may appear.

- [ ] **Step 2: Run the component test and confirm the component is missing**

Run: `bun run test:unit -- resources/js/components/profile/InterestTagSelector.spec.ts`

Expected: FAIL because `InterestTagSelector.vue` does not exist.

- [ ] **Step 3: Implement the tag selector**

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';
import type { InterestOption } from '@/types';

const props = defineProps<{
    interests: InterestOption[];
    selectedIds: number[];
    limit: number;
}>();

const selected = ref(new Set(props.selectedIds));
const count = computed(() => selected.value.size);

function toggle(id: number): void {
    const next = new Set(selected.value);
    if (next.has(id)) next.delete(id);
    else if (next.size < props.limit) next.add(id);
    selected.value = next;
}
</script>

<template>
    <fieldset class="grid gap-3">
        <div class="flex items-center justify-between gap-3">
            <legend class="font-medium">Mes intérêts</legend>
            <span aria-live="polite">{{ count }} / {{ limit }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <button
                v-for="interest in interests"
                :key="interest.id"
                type="button"
                :aria-pressed="selected.has(interest.id)"
                :aria-label="`${selected.has(interest.id) ? 'Retirer' : 'Ajouter'} ${interest.name}`"
                :disabled="count >= limit && !selected.has(interest.id)"
                @click="toggle(interest.id)"
            >
                {{ interest.name }}
            </button>
        </div>
        <input
            v-for="id in selected"
            :key="id"
            type="hidden"
            name="interest_ids[]"
            :value="id"
        />
    </fieldset>
</template>
```

Style the buttons with existing Tailwind tokens: selected uses `bg-primary text-primary-foreground`; unselected uses `border bg-background`; disabled uses opacity and cursor states. Render a hidden empty `interest_ids` marker only if Laravel request normalization requires it; verify the zero-selection request in the feature test.

- [ ] **Step 4: Wire the selector into profile pages and show active interests**

Add `interests`, `selectedInterestIds`, and `interestLimit` props through Create/Edit into `ProfileForm`. Pass `errors.interest_ids` to `InputError`. Extend the `Profile` type with:

```ts
interests: InterestOption[];
```

Return only effective active interests from `MemberProfileController::show()` and render them as `Badge` elements under an “Intérêts” heading. Define `Profile.interests?: InterestOption[]` so the shared authentication profile remains compatible without loading the catalog globally. Do not send or render suspended history.

- [ ] **Step 5: Run focused Vue and feature tests**

Run:

```bash
bun run test:unit -- resources/js/components/profile/InterestTagSelector.spec.ts resources/js/components/profile/ProfileForm.spec.ts resources/js/pages/profile/Show.spec.ts
bun run types:check
php artisan test tests/Feature/MemberProfileTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit the member tag UI**

```bash
git add resources/js app/Http/Controllers/MemberProfileController.php tests/Feature/MemberProfileTest.php
git commit -m "feat: ajouter les tags d’intérêts au profil"
```

---

### Task 5: Protect and Implement Admin Catalog Operations

**Files:**
- Create: `app/Policies/InterestPolicy.php`
- Create: `app/Actions/SetInterestStatus.php`
- Create: `app/Actions/MoveInterest.php`
- Create: `app/Http/Controllers/Admin/InterestController.php`
- Create: `app/Http/Controllers/Admin/InterestStatusController.php`
- Create: `app/Http/Controllers/Admin/InterestOrderController.php`
- Create: `app/Http/Controllers/Admin/InterestSettingController.php`
- Create: `app/Http/Requests/StoreInterestRequest.php`
- Create: `app/Http/Requests/UpdateInterestRequest.php`
- Create: `app/Http/Requests/UpdateInterestStatusRequest.php`
- Create: `app/Http/Requests/MoveInterestRequest.php`
- Create: `app/Http/Requests/UpdateInterestSettingRequest.php`
- Create: `tests/Feature/Admin/InterestCatalogAuthorizationTest.php`
- Create: `tests/Feature/Admin/ManageInterestCatalogTest.php`
- Create: `tests/Unit/SetInterestStatusTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: models and setting from Tasks 1–2.
- Produces named routes: `admin.interests.index|store|update|destroy`, `admin.interests.status`, `admin.interests.move`, `admin.interest-setting.update`.
- Produces: `SetInterestStatus::handle(Interest $interest, bool $active): void` and `MoveInterest::handle(Interest $interest, 'up'|'down' $direction): void`.

- [ ] **Step 1: Write failing authorization tests**

```php
public function test_only_admins_can_manage_interests(): void
{
    $member = User::factory()->withProfile()->create();
    $admin = User::factory()->withProfile()->admin()->create();

    $this->actingAs($member)->get(route('admin.interests.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.interests.index'))->assertOk();
}

public function test_admin_role_does_not_bypass_unrelated_profile_policy(): void
{
    $admin = User::factory()->withProfile()->admin()->create();
    $otherProfile = User::factory()->withProfile()->create()->profile;

    expect(Gate::forUser($admin)->allows('update', $otherProfile))->toBeFalse();
}
```

If the factory has no `admin()` state, assign `RoleName::Admin` with the existing `AssignRole` action in the test.

- [ ] **Step 2: Run authorization tests and confirm missing routes/policy**

Run: `php artisan test tests/Feature/Admin/InterestCatalogAuthorizationTest.php`

Expected: FAIL because admin interest routes do not exist.

- [ ] **Step 3: Implement the policy, requests, and protected routes**

```php
final class InterestPolicy
{
    public function viewAny(User $user): bool { return $user->hasRole(RoleName::Admin); }
    public function create(User $user): bool { return $user->hasRole(RoleName::Admin); }
    public function update(User $user, Interest $interest): bool { return $user->hasRole(RoleName::Admin); }
    public function delete(User $user, Interest $interest): bool { return $user->hasRole(RoleName::Admin); }
}
```

```php
Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
    Route::resource('interests', InterestController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('interests/{interest}/status', InterestStatusController::class)->name('interests.status');
    Route::patch('interests/{interest}/move', InterestOrderController::class)->name('interests.move');
    Route::patch('interest-setting', InterestSettingController::class)->name('interest-setting.update');
});
```

Each Form Request calls the matching Policy ability in `authorize()`. Normalize names by trimming and collapsing whitespace. Use `Rule::unique('interests', 'name')->ignore($this->route('interest'))`; validate status as boolean, direction with `Rule::in(['up', 'down'])`, and `max_selections` as `required|integer|min:1|max:100`.

- [ ] **Step 4: Write failing archival/restoration tests**

```php
public function test_archiving_suspends_selections_and_reactivation_respects_capacity(): void
{
    InterestSetting::current()->update(['max_selections' => 1]);
    $archived = Interest::factory()->create(['is_active' => true]);
    $replacement = Interest::factory()->create(['is_active' => true]);
    $availableProfile = User::factory()->withProfile()->create()->profile;
    $fullProfile = User::factory()->withProfile()->create()->profile;
    $availableProfile->interestHistory()->attach($archived, ['is_selected' => true]);
    $fullProfile->interestHistory()->attach($archived, ['is_selected' => true]);

    $action = app(SetInterestStatus::class);
    $action->handle($archived, false);
    $fullProfile->interestHistory()->attach($replacement, ['is_selected' => true]);
    $action->handle($archived, true);

    $this->assertDatabaseHas('interest_profile', [
        'profile_id' => $availableProfile->id,
        'interest_id' => $archived->id,
        'is_selected' => true,
    ]);
    $this->assertDatabaseHas('interest_profile', [
        'profile_id' => $fullProfile->id,
        'interest_id' => $archived->id,
        'is_selected' => false,
    ]);
}
```

Also assert the archived profile immediately has zero effective interests and can select `$replacement` through the member endpoint.

- [ ] **Step 5: Implement transactional status changes**

```php
public function handle(Interest $interest, bool $active): void
{
    DB::transaction(function () use ($interest, $active): void {
        $locked = Interest::query()->lockForUpdate()->findOrFail($interest->id);
        $locked->update(['is_active' => $active]);

        if (! $active) {
            DB::table('interest_profile')
                ->where('interest_id', $locked->id)
                ->where('is_selected', true)
                ->update(['is_selected' => false]);
            return;
        }

        $limit = InterestSetting::query()->lockForUpdate()->firstOrFail()->max_selections;
        DB::table('interest_profile')
            ->where('interest_id', $locked->id)
            ->where('is_selected', false)
            ->orderBy('profile_id')
            ->pluck('profile_id')
            ->each(function (int $profileId) use ($locked, $limit): void {
                Profile::query()->whereKey($profileId)->lockForUpdate()->firstOrFail();
                $count = DB::table('interest_profile')
                    ->join('interests', 'interests.id', '=', 'interest_profile.interest_id')
                    ->where('profile_id', $profileId)
                    ->where('interest_profile.is_selected', true)
                    ->where('interests.is_active', true)
                    ->count();

                if ($count < $limit) {
                    DB::table('interest_profile')
                        ->where('profile_id', $profileId)
                        ->where('interest_id', $locked->id)
                        ->update(['is_selected' => true]);
                }
            });
    });
}
```

- [ ] **Step 6: Write and implement CRUD, order, and setting behavior**

Add feature tests proving:

```php
$this->actingAs($admin)->post(route('admin.interests.store'), ['name' => '  Nouvel   intérêt  '])
    ->assertRedirect();
$this->assertDatabaseHas('interests', ['name' => 'Nouvel intérêt', 'is_active' => true]);

$this->actingAs($admin)->patch(route('admin.interest-setting.update'), ['max_selections' => 3])
    ->assertRedirect();
$this->assertDatabaseHas('interest_settings', ['max_selections' => 3]);
```

`MoveInterest` locks all interests ordered by `sort_order, id`, swaps the target with its adjacent neighbor, then writes contiguous zero-based positions. `destroy()` checks `$interest->profiles()->exists()` and returns a validation error when used; otherwise it deletes and normalizes remaining order. `index()` authorizes `viewAny` and returns ordered interests with `profiles_count` plus the singleton setting.

- [ ] **Step 7: Run focused admin and member tests**

Run:

```bash
php artisan test tests/Feature/Admin tests/Unit/SetInterestStatusTest.php tests/Feature/MemberProfileTest.php
composer analyse
```

Expected: PASS.

- [ ] **Step 8: Commit admin catalog behavior**

```bash
git add app/Actions app/Http app/Policies routes/web.php tests/Feature/Admin tests/Unit/SetInterestStatusTest.php tests/Feature/MemberProfileTest.php
git commit -m "feat: administrer le catalogue des intérêts"
```

---

### Task 6: Build the Responsive Admin Interest Page

**Files:**
- Create: `resources/js/pages/Admin/Interests/Index.vue`
- Create: `resources/js/pages/Admin/Interests/Index.spec.ts`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/AppSidebar.spec.ts`
- Modify: `resources/js/layouts/resolvePageLayout.ts`
- Modify: `resources/js/layouts/resolvePageLayout.spec.ts`
- Generated: `resources/js/routes/admin/interests/index.ts`
- Generated: `resources/js/routes/admin/interest-setting/index.ts`

**Interfaces:**
- Consumes Inertia props: `interests: Array<{id; name; is_active; sort_order; profiles_count}>`, `setting: {max_selections}`.
- Consumes named routes from Task 5 via generated Wayfinder helpers.

- [ ] **Step 1: Generate route helpers**

Run: `php artisan wayfinder:generate --with-form`

Expected: generated helpers expose index/store/update/destroy/status/move/setting update URLs. Do not hand-edit generated files.

- [ ] **Step 2: Write failing admin page tests**

```ts
it('shows state, history count, ordering actions, and the selection limit', () => {
    const wrapper = mount(Index, {
        props: {
            interests: [{
                id: 1,
                name: 'Chill',
                is_active: false,
                sort_order: 0,
                profiles_count: 12,
            }],
            setting: { max_selections: 5 },
        },
        global: { stubs: inertiaAndUiStubs },
    });

    expect(wrapper.text()).toContain('Intérêts');
    expect(wrapper.text()).toContain('Archivé');
    expect(wrapper.text()).toContain('12 profils');
    expect(wrapper.get('input[name="max_selections"]').attributes('value')).toBe('5');
    expect(wrapper.get('[aria-label="Monter Chill"]').exists()).toBe(true);
});
```

Add tests for Create/Edit form names, archive/reactivate copy, the disabled first “Monter” action, and the validation error slot.

- [ ] **Step 3: Run the page tests and confirm the page is missing**

Run: `bun run test:unit -- resources/js/pages/Admin/Interests/Index.spec.ts`

Expected: FAIL because the admin interest page does not exist.

- [ ] **Step 4: Implement the admin page with existing primitives**

Use `Head`, Inertia `Form`, `Button`, `Input`, `Label`, `Badge`, `Card`, and `InputError`. The page must contain:

```vue
<Form v-bind="updateSetting.form()" class="flex items-end gap-3">
    <div class="grid gap-2">
        <Label for="max_selections">Maximum par membre</Label>
        <Input id="max_selections" name="max_selections" type="number" min="1" max="100"
            :default-value="setting.max_selections" />
    </div>
    <Button type="submit">Enregistrer</Button>
</Form>
```

Render each interest as a responsive card row with visible status, historical count, inline edit form, move forms, status form, and delete form. Use explicit French `aria-label`s. Require confirmation for archive and delete using the project’s existing dialog primitive; never rely on color alone for state.

- [ ] **Step 5: Add navigation and admin layout resolution**

Add an `Intérêts` entry with a suitable Lucide icon to `AppSidebar`. Change layout resolution from the single `Dashboard` match to:

```ts
if (name === 'Dashboard' || name.startsWith('Admin/')) {
    return AdminLayout;
}
```

Update both existing specs to assert the new route and layout.

- [ ] **Step 6: Run focused frontend and backend page tests**

Run:

```bash
bun run test:unit -- resources/js/pages/Admin/Interests/Index.spec.ts resources/js/components/AppSidebar.spec.ts resources/js/layouts/resolvePageLayout.spec.ts
bun run types:check
php artisan test tests/Feature/Admin
```

Expected: PASS.

- [ ] **Step 7: Commit the admin UI**

```bash
git add resources/js
git commit -m "feat: ajouter l’interface des intérêts"
```

---

### Task 7: Align Product Documentation and Complete Verification

**Files:**
- Modify: `docs/product-vision.md`
- Modify: `docs/mvp-v1.md`
- Modify: `docs/data-model.md`
- Modify: `docs/security-privacy.md`
- Modify: `docs/ux-design.md`
- Modify: `docs/roadmap.md`

**Interfaces:**
- Consumes: final route, model, and behavior names from Tasks 1–6.
- Produces: current source-of-truth documentation using “intérêt” consistently.

- [ ] **Step 1: Update current product documentation**

Replace current-domain references as follows while leaving historical
`docs/superpowers/specs` and `docs/superpowers/plans` unchanged:

```text
passion_categories -> interest_categories
passions -> interests
passion_profile -> interest_profile
passion(s) -> intérêt(s) in French product copy
nombre de passions communes -> nombre d’intérêts communs
```

Document the five-interest default, admin configurability, archive visibility,
suspended history, and conditional restoration. Do not add category management
or moderation to the implemented scope.

- [ ] **Step 2: Verify no stale runtime vocabulary remains**

Run:

```bash
rg -n "Passion|passion" app database/factories database/seeders resources/js tests routes
```

Expected: no matches. The rename migration may contain old table names by
necessity and is excluded from this check.

- [ ] **Step 3: Run all PHP checks**

Run:

```bash
composer lint:check
composer analyse
php artisan test
```

Expected: PASS with no lint, PHPStan, or test failures.

- [ ] **Step 4: Regenerate routes and run all frontend checks**

Run:

```bash
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
bun run test
bun run build
```

Expected: PASS; Vite produces the production bundle.

- [ ] **Step 5: Run repository integrity checks**

Run:

```bash
git diff --check
git status --short
```

Expected: no whitespace errors and only intended issue-14 files changed.

- [ ] **Step 6: Commit documentation and generated artifacts**

```bash
git add docs resources/js/routes
git commit -m "docs: documenter le catalogue des intérêts"
```

- [ ] **Step 7: Record final verification evidence**

Run:

```bash
git log --oneline --decorate -10
git status --short --branch
```

Expected: the feature branch is clean and contains the design, plan, and
independently verified implementation commits.
