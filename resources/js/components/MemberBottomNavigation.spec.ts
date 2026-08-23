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
                    get profile() {
                        return state.complete
                            ? {
                                  onboarding_completed_at:
                                      '2026-08-23T10:00:00Z',
                              }
                            : null;
                    },
                },
            },
        },
    }),
}));

vi.mock('@/routes/discovery', () => ({
    index: () => ({ url: '/discover' }),
}));

vi.mock('@/routes/member-profile', () => ({
    show: () => ({ url: '/profile' }),
}));

vi.mock('@/composables/useCurrentUrl', () => ({
    useCurrentUrl: () => ({
        isCurrentOrParentUrl: (href: { url: string } | string) =>
            state.url.startsWith(typeof href === 'string' ? href : href.url),
    }),
}));

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
        expect(
            wrapper.find('a[href="/discover"]').attributes('aria-current'),
        ).toBe('page');
        expect(wrapper.get('nav').classes()).toContain('w-fit');
        expect(wrapper.get('nav').classes()).not.toContain('w-full');
    });

    it('moves the active state with the current route', () => {
        state.url = '/profile/edit';
        const wrapper = mount(MemberBottomNavigation);

        expect(
            wrapper.find('a[href="/profile"]').attributes('aria-current'),
        ).toBe('page');
        expect(
            wrapper.find('a[href="/discover"]').attributes('aria-current'),
        ).toBeUndefined();
    });

    it('keeps the profile destination active throughout settings', () => {
        state.url = '/settings/account';
        const wrapper = mount(MemberBottomNavigation);

        expect(
            wrapper.find('a[href="/profile"]').attributes('aria-current'),
        ).toBe('page');
        expect(
            wrapper.find('a[href="/discover"]').attributes('aria-current'),
        ).toBeUndefined();
    });

    it('hides navigation before onboarding completes', () => {
        state.complete = false;

        expect(mount(MemberBottomNavigation).find('nav').exists()).toBe(false);
    });
});
