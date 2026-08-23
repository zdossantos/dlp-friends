# Mobile-First Member Shell Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the shared dashboard sidebar with a route-driven, icon-only member dock and reserve the responsive sidebar for administration while preserving accessible discovery gestures and public/auth mobile-first screens.

**Architecture:** A pure `resolvePageLayout(name)` function maps Inertia page families to dedicated member, admin, auth, and settings layouts. The member shell owns safe-area spacing and a route-driven bottom dock; page-specific actions remain inside Profile, while the existing sidebar becomes admin-only. Discovery keeps its existing backend contract and replaces visible decision buttons with pointer gestures, keyboard arrows, and visually hidden semantic controls.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 Composition API, TypeScript 6, Tailwind CSS 4, Reka UI, Lucide Vue, Vitest 4, Vue Test Utils, Pest.

**Spec:** `docs/superpowers/specs/2026-08-23-mobile-first-member-shell-design.md`

## Global Constraints

- Public and member interfaces are mobile-first; only administration uses the desktop-first sidebar.
- `MemberLayout` renders no global header.
- The member dock shows icons only, with accessible names, visible focus, `aria-current="page"`, and touch targets of at least 48 × 48 pixels.
- The dock exposes only implemented routes: `discovery.index` and `member-profile.show`; settings remain a Profile-page action and no matches icon is rendered.
- Incomplete profiles use member styling without the dock.
- Discovery renders no visible cross, heart, “Passer”, or “J'aime” decision control; pointer swipe, keyboard arrows, and reader-accessible semantic controls remain available.
- No business rule, Laravel authorization, native/PWA feature, dependency, Disney-owned logo, character, or illustration is added.
- Light, dark, and system themes continue to use the existing design tokens.
- Follow strict red-green-refactor: every production behavior change starts with a targeted failing test that fails for the expected missing behavior.

## Execution Preflight

Run from the isolated worktree:

```bash
composer install --no-interaction
bun install --frozen-lockfile
php artisan wayfinder:generate --with-form
bun run test
php artisan test
```

Expected: dependencies install successfully; generated Wayfinder modules exist; the current frontend and backend suites pass before Task 1. If a baseline test fails, stop and report the exact failure before editing production files.

---

### Task 1: Central Page-Layout Resolution

**Files:**
- Create: `resources/js/layouts/resolvePageLayout.ts`
- Create: `resources/js/layouts/resolvePageLayout.spec.ts`
- Create: `resources/js/layouts/AdminLayout.vue`
- Create: `resources/js/layouts/MemberLayout.vue`
- Modify: `resources/js/app.ts`
- Preserve temporarily: `resources/js/layouts/AppLayout.vue`

**Interfaces:**
- Consumes: Inertia component names such as `Welcome`, `auth/Login`, `settings/Account`, `Dashboard`, `Discovery/Index`, and `profile/Show`.
- Produces: `resolvePageLayout(name: string): Component | Component[] | null`; dedicated `AdminLayout` and `MemberLayout` Vue components consumed by the resolver and later tasks.

- [ ] **Step 1: Write the failing resolver test**

Create `resources/js/layouts/resolvePageLayout.spec.ts`:

```ts
import { describe, expect, it } from 'vitest';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { resolvePageLayout } from './resolvePageLayout';

describe('resolvePageLayout', () => {
    it.each([
        ['Welcome', null],
        ['auth/Login', AuthLayout],
        ['Dashboard', AdminLayout],
        ['Discovery/Index', MemberLayout],
        ['profile/Create', MemberLayout],
        ['profile/Show', MemberLayout],
    ])('maps %s to its application shell', (name, expected) => {
        expect(resolvePageLayout(name)).toBe(expected);
    });

    it('nests settings inside the member shell', () => {
        expect(resolvePageLayout('settings/Account')).toEqual([
            MemberLayout,
            SettingsLayout,
        ]);
    });
});
```

- [ ] **Step 2: Run the resolver test and verify RED**

Run:

```bash
bun run test:unit -- resources/js/layouts/resolvePageLayout.spec.ts
```

Expected: FAIL because `resolvePageLayout.ts`, `MemberLayout.vue`, and `AdminLayout.vue` do not exist.

- [ ] **Step 3: Implement the resolver and minimal dedicated layouts**

Create `resources/js/layouts/resolvePageLayout.ts`:

```ts
import type { Component } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

export function resolvePageLayout(name: string): Component | Component[] | null {
    if (name === 'Welcome') return null;
    if (name.startsWith('auth/')) return AuthLayout;
    if (name === 'Dashboard') return AdminLayout;
    if (name.startsWith('settings/')) return [MemberLayout, SettingsLayout];
    return MemberLayout;
}
```

Create `resources/js/layouts/AdminLayout.vue` as a compatibility wrapper around the current admin-capable layout:

```vue
<script setup lang="ts">
import AppSidebarLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

withDefaults(defineProps<{ breadcrumbs?: BreadcrumbItem[] }>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppSidebarLayout :breadcrumbs="breadcrumbs"><slot /></AppSidebarLayout>
</template>
```

Create the initial `resources/js/layouts/MemberLayout.vue`:

```vue
<template>
    <div class="flex min-h-svh w-full flex-col bg-background text-foreground">
        <slot />
    </div>
</template>
```

Replace the switch and obsolete layout imports in `resources/js/app.ts` with:

```ts
import { resolvePageLayout } from '@/layouts/resolvePageLayout';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: resolvePageLayout,
    progress: { color: '#7138B6' },
});
```

- [ ] **Step 4: Run resolver and existing frontend tests and verify GREEN**

Run:

```bash
bun run test:unit -- resources/js/layouts/resolvePageLayout.spec.ts resources/js/pages/Dashboard.spec.ts resources/js/pages/profile/Show.spec.ts
```

Expected: PASS with the resolver mapping all page families correctly.

- [ ] **Step 5: Commit the layout boundary**

```bash
git add resources/js/app.ts resources/js/layouts/AdminLayout.vue resources/js/layouts/MemberLayout.vue resources/js/layouts/resolvePageLayout.ts resources/js/layouts/resolvePageLayout.spec.ts
git commit -m "refactor: separate member and admin layouts"
```

---

### Task 2: Safe-Area Member Shell and Icon-Only Dock

**Files:**
- Create: `resources/js/components/MemberBottomNavigation.vue`
- Create: `resources/js/components/MemberBottomNavigation.spec.ts`
- Create: `resources/js/layouts/MemberLayout.spec.ts`
- Modify: `resources/js/layouts/MemberLayout.vue`

**Interfaces:**
- Consumes: `usePage().props.auth.user.profile?.onboarding_completed_at`, `useCurrentUrl()`, `discovery.index()`, and `member-profile.show()`.
- Produces: `MemberBottomNavigation`, which renders `nav[data-test="member-bottom-navigation"]` only for a complete profile; `MemberLayout`, which reserves dock and safe-area space without a header.

- [ ] **Step 1: Write failing dock and shell tests**

Create `resources/js/components/MemberBottomNavigation.spec.ts` with hoisted route and completion state:

```ts
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MemberBottomNavigation from './MemberBottomNavigation.vue';

const state = vi.hoisted(() => ({ url: '/discover', complete: true }));

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href.url" v-bind="$attrs"><slot /></a>',
    },
    usePage: () => ({
        get url() {
            return state.url;
        },
        props: {
            auth: {
                user: {
                    profile: state.complete
                        ? { onboarding_completed_at: '2026-08-23T10:00:00Z' }
                        : null,
                },
            },
        },
    }),
}));

vi.mock('@/routes/discovery', () => ({ index: () => ({ url: '/discover' }) }));
vi.mock('@/routes/member-profile', () => ({ show: () => ({ url: '/profile' }) }));

describe('MemberBottomNavigation', () => {
    beforeEach(() => {
        state.url = '/discover';
        state.complete = true;
    });

    it('renders only implemented icon destinations with accessible names', () => {
        const wrapper = mount(MemberBottomNavigation);
        const links = wrapper.findAll('a');

        expect(links).toHaveLength(2);
        expect(links.map((link) => link.attributes('aria-label'))).toEqual([
            'Découvrir',
            'Profil',
        ]);
        expect(wrapper.text()).not.toContain('Découvrir');
        expect(wrapper.text()).not.toContain('Profil');
        expect(wrapper.find('a[href="/discover"]').attributes('aria-current')).toBe('page');
    });

    it('moves the active state with the current route', () => {
        state.url = '/profile/edit';
        const wrapper = mount(MemberBottomNavigation);

        expect(wrapper.find('a[href="/profile"]').attributes('aria-current')).toBe('page');
        expect(wrapper.find('a[href="/discover"]').attributes('aria-current')).toBeUndefined();
    });

    it('hides navigation before onboarding completes', () => {
        state.complete = false;
        expect(mount(MemberBottomNavigation).find('nav').exists()).toBe(false);
    });
});
```

Create `resources/js/layouts/MemberLayout.spec.ts`:

```ts
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MemberLayout from './MemberLayout.vue';

describe('MemberLayout', () => {
    it('renders no header and reserves space for the safe-area dock', () => {
        const wrapper = mount(MemberLayout, {
            slots: { default: '<main data-test="page">Page</main>' },
            global: { stubs: { MemberBottomNavigation: true, Toaster: true } },
        });

        expect(wrapper.find('header').exists()).toBe(false);
        expect(wrapper.find('[data-test="member-shell-content"]').classes()).toContain(
            'pb-[calc(6rem+env(safe-area-inset-bottom))]',
        );
    });
});
```

- [ ] **Step 2: Run the dock tests and verify RED**

```bash
bun run test:unit -- resources/js/components/MemberBottomNavigation.spec.ts resources/js/layouts/MemberLayout.spec.ts
```

Expected: FAIL because the navigation component and safe-area content contract do not exist.

- [ ] **Step 3: Implement the route-driven icon dock**

Create `resources/js/components/MemberBottomNavigation.vue` with `Sparkles` and `UserRound` icons, a computed completion state, and these two items:

```ts
const items = [
    { label: 'Découvrir', href: discovery(), icon: Sparkles },
    { label: 'Profil', href: showProfile(), icon: UserRound },
];
```

Render each item as:

```vue
<Link
    :href="item.href"
    :aria-label="item.label"
    :aria-current="isCurrentOrParentUrl(item.href) ? 'page' : undefined"
    class="grid size-12 place-items-center rounded-2xl text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none aria-[current=page]:bg-secondary aria-[current=page]:text-primary"
>
    <component :is="item.icon" class="size-6" aria-hidden="true" />
</Link>
```

Wrap the links in a fixed safe-area dock:

```vue
<div v-if="isProfileComplete" class="fixed inset-x-0 bottom-0 z-40 flex justify-center px-4 [padding-bottom:max(0.75rem,env(safe-area-inset-bottom))]">
    <nav data-test="member-bottom-navigation" aria-label="Navigation principale" class="flex min-h-16 w-full max-w-sm items-center justify-around rounded-3xl border bg-card/95 px-4 shadow-xl backdrop-blur">
        <!-- route links -->
    </nav>
</div>
```

- [ ] **Step 4: Complete `MemberLayout` with app surface, content spacing, and toaster**

Use this structure in `resources/js/layouts/MemberLayout.vue`:

```vue
<script setup lang="ts">
import MemberBottomNavigation from '@/components/MemberBottomNavigation.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="relative flex min-h-svh w-full flex-col overflow-x-hidden bg-background text-foreground">
        <div aria-hidden="true" class="pointer-events-none fixed inset-0 bg-[radial-gradient(circle_at_top_left,var(--color-secondary),transparent_42%),radial-gradient(circle_at_bottom_right,var(--color-accent),transparent_38%)] opacity-35" />
        <div data-test="member-shell-content" class="relative flex min-h-svh w-full flex-1 flex-col pb-[calc(6rem+env(safe-area-inset-bottom))]">
            <slot />
        </div>
        <MemberBottomNavigation />
        <Toaster />
    </div>
</template>
```

- [ ] **Step 5: Run dock, shell, resolver, and URL tests and verify GREEN**

```bash
bun run test:unit -- resources/js/components/MemberBottomNavigation.spec.ts resources/js/layouts/MemberLayout.spec.ts resources/js/layouts/resolvePageLayout.spec.ts resources/js/composables/useCurrentUrl.spec.ts
```

Expected: PASS; the dock has two accessible icon links, active state follows nested routes, onboarding hides it, and no header exists.

- [ ] **Step 6: Commit the member shell**

```bash
git add resources/js/components/MemberBottomNavigation.vue resources/js/components/MemberBottomNavigation.spec.ts resources/js/layouts/MemberLayout.vue resources/js/layouts/MemberLayout.spec.ts
git commit -m "feat: add mobile member navigation dock"
```

---

### Task 3: Profile Actions and Admin-Only Sidebar

**Files:**
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `resources/js/pages/profile/Show.spec.ts`
- Modify: `resources/js/components/AppSidebar.vue`
- Modify: `resources/js/components/AppSidebar.spec.ts`
- Modify: `resources/js/layouts/app/AppSidebarLayout.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`

**Interfaces:**
- Consumes: shared roles from `usePage()`, `account.edit()`, `dashboard()`, and `member-profile.edit()/show()` routes.
- Produces: Profile-local icon actions labelled `Réglages` and conditional `Administration`; an admin sidebar that always contains `Administration` and `Retour au profil` and is rendered only by `AdminLayout`.

- [ ] **Step 1: Extend the Profile test for role-aware page actions**

Replace the single mount with a helper whose hoisted roles can change, mock `usePage`, `@/routes`, and `@/routes/account`, then add:

```ts
it('shows settings without administration to a normal member', () => {
    roleState.roles = [{ name: 'user' }];
    const wrapper = mountProfile();

    expect(wrapper.get('a[aria-label="Réglages"]').attributes('href')).toBe('/settings/account');
    expect(wrapper.find('a[aria-label="Administration"]').exists()).toBe(false);
});

it('adds administration to profile actions for an admin', () => {
    roleState.roles = [{ name: 'user' }, { name: 'admin' }];
    const wrapper = mountProfile();

    expect(wrapper.get('a[aria-label="Administration"]').attributes('href')).toBe('/dashboard');
});
```

Update the Link stub to forward `href` and attributes so the test exercises rendered links.

- [ ] **Step 2: Replace sidebar expectations with the admin-only contract**

Update `resources/js/components/AppSidebar.spec.ts` to remove role and completion state and assert:

```ts
it('contains only admin navigation and a return to the member profile', () => {
    const wrapper = mountSidebar();

    expect(wrapper.get('a[href="/dashboard"]').text()).toBe('Administration');
    expect(wrapper.get('a[href="/profile"]').text()).toBe('Retour au profil');
    expect(wrapper.find('a[href="/discover"]').exists()).toBe(false);
});
```

- [ ] **Step 3: Run Profile and sidebar tests and verify RED**

```bash
bun run test:unit -- resources/js/pages/profile/Show.spec.ts resources/js/components/AppSidebar.spec.ts
```

Expected: FAIL because Profile has no settings/admin icon actions and the sidebar still mixes member and admin destinations.

- [ ] **Step 4: Implement Profile-local actions**

In `resources/js/pages/profile/Show.vue`, compute:

```ts
const page = usePage();
const isAdmin = computed(() =>
    page.props.auth.user.roles.some((role) => role.name === 'admin'),
);
```

Place a page-local action group at the top right of the profile card. Render `Button size="icon" variant="outline" as-child` links to account settings and, under `v-if="isAdmin"`, dashboard. Give the links `aria-label="Réglages"` and `aria-label="Administration"`; mark `Settings` and `LayoutDashboard` icons `aria-hidden="true"`. Keep `Modifier mon profil` as the visible primary page action, not a dock item.

- [ ] **Step 5: Make `AppSidebar` admin-only and retain responsive behavior**

Replace the computed mixed navigation with a constant:

```ts
const mainNavItems: NavItem[] = [
    { title: 'Administration', href: dashboard(), icon: LayoutDashboard },
    { title: 'Retour au profil', href: showProfile(), icon: UserRound },
];
```

Remove `usePage`, `app`, discovery imports, and onboarding conditions. Point the logo link to `dashboard()`. Keep `collapsible="icon"`, `variant="inset"`, `NavMain`, and `NavUser` so the existing small-screen sheet remains functional.

In `AppSidebarLayout.vue`, keep breadcrumbs and `AppSidebarHeader`, but rename member-facing CSS assumptions only where necessary; do not add member conditions.

In `layouts/settings/Layout.vue`, change the sub-navigation wrapper to a horizontally scrollable or wrapping row below `lg`, then retain the existing vertical layout at `lg`:

```vue
<nav class="flex gap-1 overflow-x-auto pb-1 lg:flex-col lg:overflow-visible" aria-label="Réglages">
```

- [ ] **Step 6: Run Profile, sidebar, settings, and dashboard tests and verify GREEN**

```bash
bun run test:unit -- resources/js/pages/profile/Show.spec.ts resources/js/components/AppSidebar.spec.ts resources/js/pages/Dashboard.spec.ts
```

Expected: PASS with the Profile actions, admin-only sidebar, and dashboard rendering contracts intact.

- [ ] **Step 7: Commit the page actions and admin shell**

```bash
git add resources/js/pages/profile/Show.vue resources/js/pages/profile/Show.spec.ts resources/js/components/AppSidebar.vue resources/js/components/AppSidebar.spec.ts resources/js/layouts/app/AppSidebarLayout.vue resources/js/layouts/settings/Layout.vue
git commit -m "feat: separate profile actions from admin navigation"
```

---

### Task 4: Gesture-Only Discovery Card and Mobile Page Composition

**Files:**
- Modify: `resources/js/components/discovery/SwipeCard.vue`
- Modify: `resources/js/components/discovery/SwipeCard.spec.ts`
- Modify: `resources/js/pages/Discovery/Index.vue`
- Modify: `resources/js/pages/Discovery/Index.spec.ts`

**Interfaces:**
- Consumes: the existing `DiscoveryProfile`, `SwipeDecision`, `locked` prop, and `like`/`pass` emits; existing `router.post` error/retry flow.
- Produces: a dominant swipe card with no visible decision buttons, pointer swipe left/right, focused ArrowLeft/ArrowRight controls, and `.sr-only` semantic buttons for assistive technology.

- [ ] **Step 1: Rewrite the visible-action test before production changes**

Replace the first two button-oriented tests in `SwipeCard.spec.ts` with:

```ts
it('renders no visible decision controls and keeps reader-accessible actions', () => {
    const wrapper = mountCard();

    expect(wrapper.text()).toContain('Mina Parade');
    expect(wrapper.find('[data-test="visible-swipe-actions"]').exists()).toBe(false);

    const pass = wrapper.get('button[aria-label="Passer ce profil"]');
    const like = wrapper.get('button[aria-label="Aimer ce profil"]');
    expect(pass.classes()).toContain('sr-only');
    expect(like.classes()).toContain('sr-only');
});

it('disables reader actions while the card is locked', async () => {
    const wrapper = mountCard(true);

    expect(wrapper.get('button[aria-label="Passer ce profil"]').attributes()).toHaveProperty('disabled');
    expect(wrapper.get('button[aria-label="Aimer ce profil"]').attributes()).toHaveProperty('disabled');
    await wrapper.get('[tabindex="0"]').trigger('keydown.right');
    expect(wrapper.emitted('like')).toBeUndefined();
});
```

Keep the pointer threshold, diagonal/cancel, pointer identity, and keyboard-arrow tests. Replace the focused-child shortcut test with activation of each `.sr-only` button and an assertion that `pass` and `like` each emit once.

- [ ] **Step 2: Add a page-level mobile composition assertion**

In `Discovery/Index.spec.ts`, extend the suggested-profile state test:

```ts
const main = cardWrapper.get('main');
expect(main.classes()).toContain('max-w-md');
expect(cardWrapper.find('[data-test="desktop-discovery-intro"]').exists()).toBe(false);
```

This test must fail against the current `max-w-5xl` desktop dashboard composition.

- [ ] **Step 3: Run discovery component and page tests and verify RED**

```bash
bun run test:unit -- resources/js/components/discovery/SwipeCard.spec.ts resources/js/pages/Discovery/Index.spec.ts
```

Expected: FAIL because visible text decision buttons still exist and the page uses the wide dashboard composition.

- [ ] **Step 4: Remove visible decision controls while preserving accessible inputs**

In `SwipeCard.vue`:

- remove `CardFooter` from imports and markup;
- add `aria-describedby="swipe-instructions"` to the focusable card;
- add this reader instruction and controls inside `CardContent`:

```vue
<p id="swipe-instructions" class="sr-only">
    Balayez vers la gauche pour passer ce profil ou vers la droite pour l’aimer. Au clavier, utilisez les flèches gauche et droite.
</p>
<button class="sr-only" type="button" :disabled="locked" aria-label="Passer ce profil" @click="decide('pass')">
    Passer ce profil
</button>
<button class="sr-only" type="button" :disabled="locked" aria-label="Aimer ce profil" @click="decide('like')">
    Aimer ce profil
</button>
```

Keep `@keydown.left.self`, `@keydown.right.self`, Pointer Events, the 72-pixel threshold, and `touch-pan-y`. Restyle the card to `overflow-hidden rounded-[1.75rem]`, use a larger avatar/identity area, retain literal age, bio, score explanation, visit frequency, and passion badges, and do not add cross or heart icons.

- [ ] **Step 5: Recompose `Discovery/Index.vue` as a mobile application screen**

Use a single-column `main`:

```vue
<main class="mx-auto flex w-full max-w-md flex-1 flex-col gap-4 px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6 sm:pt-8">
```

Keep a concise local `<h1>Découvrir</h1>` and supporting sentence, but remove the dashboard eyebrow and wide `max-w-2xl`/`max-w-5xl` framing. Preserve loading, empty, error, retry, suggestion, and match dialog branches exactly; do not change `submit`, `retry`, watcher behavior, or backend payloads.

- [ ] **Step 6: Run discovery tests and verify GREEN**

```bash
bun run test:unit -- resources/js/components/discovery/SwipeCard.spec.ts resources/js/pages/Discovery/Index.spec.ts
```

Expected: PASS with no visible swipe decisions, functional pointer/keyboard/reader paths, and all error and match states preserved.

- [ ] **Step 7: Commit the mobile discovery experience**

```bash
git add resources/js/components/discovery/SwipeCard.vue resources/js/components/discovery/SwipeCard.spec.ts resources/js/pages/Discovery/Index.vue resources/js/pages/Discovery/Index.spec.ts
git commit -m "feat: adopt gesture-first mobile discovery"
```

---

### Task 5: Public Landing and Responsive Member Pages

**Files:**
- Create: `resources/js/pages/Welcome.spec.ts`
- Modify: `resources/js/pages/Welcome.vue`
- Modify: `resources/js/pages/profile/Create.vue`
- Modify: `resources/js/pages/profile/Edit.vue`
- Modify: `resources/js/pages/profile/Show.vue`
- Modify: `resources/js/layouts/auth/AuthCardLayout.vue`
- Modify: `resources/js/layouts/settings/Layout.vue`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: shared `$page.props.auth.user`, `home()`, `login()`, `register()`, `app()`, existing `AppearanceTabs`, `AppLogoIcon`, profile forms, settings forms, and theme tokens.
- Produces: a French DLP Friends public landing page; small-screen-first spacing and safe-area support across auth, onboarding, profile, and settings.

- [ ] **Step 1: Write the failing public landing behavior test**

Create `resources/js/pages/Welcome.spec.ts`:

```ts
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Welcome from './Welcome.vue';

vi.mock('@/routes', () => ({
    app: () => ({ url: '/app' }),
    login: () => ({ url: '/login' }),
    register: () => ({ url: '/register' }),
}));

function mountWelcome(user: object | null) {
    return mount(Welcome, {
        global: {
            mocks: { $page: { props: { auth: { user } } } },
            stubs: {
                Head: true,
                AppearanceTabs: true,
                AppLogoIcon: true,
                Link: {
                    props: ['href'],
                    template: '<a :href="href.url"><slot /></a>',
                },
            },
        },
    });
}

describe('Welcome', () => {
    it('presents the friendly adult service and guest actions in French', () => {
        const wrapper = mountWelcome(null);

        expect(wrapper.text()).toContain('Des rencontres strictement amicales');
        expect(wrapper.text()).toContain('réservé aux adultes');
        expect(wrapper.text()).toContain('indépendant et non affilié');
        expect(wrapper.get('a[href="/register"]').text()).toContain('Créer mon compte');
        expect(wrapper.get('a[href="/login"]').text()).toContain('Se connecter');
        expect(wrapper.text()).not.toContain("Let's get started");
    });

    it('offers the member space instead of guest calls to action when signed in', () => {
        const wrapper = mountWelcome({ id: 1 });

        expect(wrapper.get('a[href="/app"]').text()).toContain('Ouvrir mon espace');
        expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
    });
});
```

- [ ] **Step 2: Run the Welcome test and verify RED**

```bash
bun run test:unit -- resources/js/pages/Welcome.spec.ts
```

Expected: FAIL because the current page is the English Laravel starter landing page and has no DLP Friends value proposition or theme control.

- [ ] **Step 3: Replace the Laravel starter landing with the approved public surface**

Rewrite `Welcome.vue` rather than editing the embedded Laravel SVG. Import `AppearanceTabs`, `AppLogoIcon`, `Button`, and `Card` primitives. Build:

- a top row with the DLP Friends logo/name and `AppearanceTabs`;
- a hero headed `Des rencontres strictement amicales entre fans adultes`;
- body copy containing `DLP Friends est réservé aux adultes et indépendant, non affilié à Disney ou Disneyland Paris.`;
- guest links `Créer mon compte` and `Se connecter`, or signed-in `Ouvrir mon espace`;
- three compact benefits: passions communes, découverte réciproque, conversations après match.

Use only existing theme colors, responsive Tailwind classes, and `pt-[max(1rem,env(safe-area-inset-top))]`; do not load the external Inter stylesheet or include Laravel, Disney, or third-party artwork.

- [ ] **Step 4: Adjust existing page spacing without changing form behavior**

Apply these concrete responsive constraints:

- `profile/Create.vue`: replace the dashboard-height calculation with `min-h-svh`, `max-w-xl`, top safe-area padding, and no vertically centered form requirement on short devices.
- `profile/Edit.vue`: use `max-w-xl px-4 pt-[max(1.25rem,env(safe-area-inset-top))] sm:px-6` and rely on `MemberLayout` for bottom space.
- `profile/Show.vue`: use `max-w-md` on the member surface, rounded mobile card composition, and keep the local actions from Task 3.
- `AuthCardLayout.vue`: add safe-area-aware top and bottom padding while retaining `AppearanceTabs`, current card, headings, and slots.
- `settings/Layout.vue`: retain the Task 3 compact tab navigation, reduce outer padding on 320–375px screens, and ensure the final destructive/account action remains above the dock.
- `resources/css/app.css`: set `html { color-scheme: light; }`, `.dark { color-scheme: dark; }`, and `body { min-width: 320px; }` inside the base layer; do not add duplicated color tokens.

- [ ] **Step 5: Run public, auth, profile, settings, and appearance tests and verify GREEN**

```bash
bun run test:unit -- resources/js/pages/Welcome.spec.ts resources/js/layouts/auth/AuthCardLayout.spec.ts resources/js/pages/profile/Show.spec.ts resources/js/components/profile/ProfileForm.spec.ts resources/js/composables/useAppearance.spec.ts
```

Expected: PASS; public copy and session-dependent actions are correct, profile behavior remains intact, auth content renders, and theme persistence is unchanged.

- [ ] **Step 6: Commit public and responsive page styling**

```bash
git add resources/js/pages/Welcome.vue resources/js/pages/Welcome.spec.ts resources/js/pages/profile/Create.vue resources/js/pages/profile/Edit.vue resources/js/pages/profile/Show.vue resources/js/layouts/auth/AuthCardLayout.vue resources/js/layouts/settings/Layout.vue resources/css/app.css
git commit -m "feat: refresh public and member mobile surfaces"
```

---

### Task 6: Full Verification and Visual QA

**Files:**
- Modify only if a command exposes a regression: the smallest file already listed in Tasks 1–5 that owns that regression.
- Record no generated Wayfinder changes unless `git status` proves they are tracked and intentionally changed.

**Interfaces:**
- Consumes: completed layouts, member dock, profile/admin navigation, discovery card, and public page.
- Produces: recent automated and visual evidence that issue #60 meets its acceptance criteria.

- [ ] **Step 1: Regenerate routes and run frontend static checks**

```bash
php artisan wayfinder:generate --with-form
bun run lint:check
bun run format:check
bun run types:check
```

Expected: all commands exit 0 with no lint, formatting, or TypeScript errors.

- [ ] **Step 2: Run all frontend tests and production build**

```bash
bun run test
bun run build
```

Expected: all Vitest files pass and Vite creates the production bundle successfully.

- [ ] **Step 3: Run relevant backend regression tests**

```bash
php artisan test tests/Feature/DiscoveryPageTest.php tests/Feature/CreateSwipeTest.php tests/Feature/MemberProfileTest.php tests/Feature/DashboardTest.php tests/Feature/LandingTest.php
```

Expected: all selected Pest tests pass; routes, middleware, swipe persistence, and admin authorization are unchanged.

- [ ] **Step 4: Run complete repository checks concerned by the change**

```bash
composer lint:check
composer analyse
php artisan test
```

Expected: PHP formatting, Larastan, and the full backend suite pass.

- [ ] **Step 5: Start the local app for visual verification**

```bash
bun run dev
```

In a separate terminal, use the existing Docker application stack if already running. If it is stopped, run `docker compose up -d` and explicitly avoid migrations unless the current database schema lacks issue #59 migrations.

- [ ] **Step 6: Verify required pages in the browser**

At 375 × 812, 768 × 1024, and 1440 × 900, inspect in both light and dark themes:

- `/`: French public landing, correct guest/member actions, no horizontal overflow;
- `/login`: auth card fits with safe-area spacing and no member dock;
- `/discover`: local title, gesture-only card, icon-only dock, no cross/heart/buttons, keyboard ArrowLeft/ArrowRight submission, no dock overlap;
- `/profile`: local settings action, conditional admin action, edit action, active Profile icon;
- `/settings/account`: compact settings navigation and form/destructive action above dock;
- `/dashboard` as admin: sidebar layout, mobile sidebar trigger, cards/lists without horizontal clipping.

For each width, tab through all controls and confirm focus visibility, accessible names for dock icons, and `aria-current="page"`. Swipe the discovery card left and right using pointer drag. Confirm vertical scroll does not accidentally decide and that the bottom safe area remains visible.

- [ ] **Step 7: Check the final diff and commit any verification-only fixes**

```bash
git diff --check
git status --short
git diff --stat origin/main...HEAD
```

Expected: no whitespace errors, no dependency or generated-file noise, and only issue #60 files plus its spec and plan are changed. If Step 6 required a code fix, rerun the exact failing automated check and commit the focused fix with:

Stage only the focused file or files changed to correct the visual regression,
choosing from the production and test paths listed in Tasks 1–5, then commit
them with `git commit -m "fix: resolve mobile shell regression"`. Do not stage
generated or unrelated files.
