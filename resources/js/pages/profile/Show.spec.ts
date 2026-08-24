import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import Show from './Show.vue';

const roleState = vi.hoisted(() => ({
    roles: [{ name: 'user' as const }] as Array<{ name: 'user' | 'admin' }>,
}));
const flushAllMock = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: {
        props: ['href', 'as'],
        template:
            '<a :href="href.url ?? href" :data-as="as" v-bind="$attrs" @click.prevent><slot /></a>',
    },
    router: { flushAll: flushAllMock },
    usePage: () => ({
        props: {
            auth: {
                user: {
                    roles: roleState.roles,
                },
            },
        },
    }),
}));

vi.mock('@/routes', () => ({
    dashboard: () => ({ url: '/dashboard' }),
    logout: () => ({ url: '/logout', method: 'post' }),
}));

vi.mock('@/routes/account', () => ({
    edit: () => ({ url: '/settings/account' }),
}));

vi.mock('@/routes/member-profile', () => ({
    edit: () => ({ url: '/profile/edit' }),
    show: () => ({ url: '/profile' }),
}));

describe('member profile page', () => {
    beforeEach(() => {
        roleState.roles = [{ name: 'user' }];
        flushAllMock.mockReset();
    });

    const mountProfile = () =>
        mount(Show, {
            props: {
                age: 26,
                profile: {
                    display_name: 'Magic Friend',
                    bio: 'Toujours partant pour une attraction.',
                    visit_frequency: 'often',
                    visibility: 'visible',
                    onboarding_completed_at: '2026-08-16T10:00:00Z',
                    interests: [{ id: 1, name: 'Chill' }],
                },
            },
            global: {
                stubs: {
                    Button: { template: '<div><slot /></div>' },
                    Badge: { template: '<span><slot /></span>' },
                },
            },
        });

    it('renders the public profile summary and edit action', () => {
        const wrapper = mountProfile();

        expect(wrapper.text()).toContain('Magic Friend');
        expect(wrapper.text()).toContain('26 ans');
        expect(wrapper.text()).toContain(
            'Toujours partant pour une attraction.',
        );
        expect(wrapper.text()).toContain('Souvent');
        expect(wrapper.text()).toContain('Visible');
        expect(wrapper.text()).toContain('Modifier mon profil');
        expect(wrapper.text()).toContain('Intérêts');
        expect(wrapper.text()).toContain('Chill');
        expect(wrapper.text()).not.toContain('Archivé');
        expect(wrapper.get('main').classes()).toContain('max-w-md');
    });

    it('shows settings without administration to a normal member', () => {
        roleState.roles = [{ name: 'user' }];
        const wrapper = mountProfile();

        expect(wrapper.get('a[aria-label="Réglages"]').attributes('href')).toBe(
            '/settings/account',
        );
        expect(wrapper.find('a[aria-label="Administration"]').exists()).toBe(
            false,
        );
        expect(
            wrapper.get('a[aria-label="Se déconnecter"]').attributes('href'),
        ).toBe('/logout');
        expect(
            wrapper.get('a[aria-label="Se déconnecter"]').attributes('data-as'),
        ).toBe('button');
    });

    it('clears cached client state when logging out', async () => {
        const wrapper = mountProfile();

        await wrapper.get('a[aria-label="Se déconnecter"]').trigger('click');

        expect(flushAllMock).toHaveBeenCalledOnce();
    });

    it('adds administration to profile actions for an admin', () => {
        roleState.roles = [{ name: 'user' }, { name: 'admin' }];
        const wrapper = mountProfile();

        expect(
            wrapper.get('a[aria-label="Administration"]').attributes('href'),
        ).toBe('/dashboard');
    });
});
