import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AppSidebar from './AppSidebar.vue';

const roleState = vi.hoisted(() => ({
    roles: [{ name: 'user' as const }] as Array<{ name: 'user' | 'admin' }>,
}));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    usePage: () => ({
        props: {
            auth: {
                user: {
                    id: 1,
                    email: 'test@example.com',
                    email_verified_at: '2026-08-16T10:00:00Z',
                    profile: {
                        display_name: 'Magic Friend',
                        bio: null,
                        visit_frequency: 'often',
                        visibility: 'visible',
                        onboarding_completed_at: '2026-08-16T10:00:00Z',
                    },
                    roles: roleState.roles,
                },
            },
        },
    }),
}));

vi.mock('@/routes', () => ({
    app: () => ({ url: '/app' }),
    dashboard: () => ({ url: '/dashboard' }),
}));

vi.mock('@/routes/member-profile', () => ({
    show: () => ({ url: '/profile' }),
}));

vi.mock('@/routes/discovery', () => ({
    index: () => ({ url: '/discover' }),
}));

describe('AppSidebar', () => {
    const mountSidebar = () =>
        mount(AppSidebar, {
            global: {
                stubs: {
                    Sidebar: { template: '<aside><slot /></aside>' },
                    SidebarContent: { template: '<div><slot /></div>' },
                    SidebarFooter: { template: '<footer><slot /></footer>' },
                    SidebarHeader: { template: '<header><slot /></header>' },
                    SidebarMenu: { template: '<div><slot /></div>' },
                    SidebarMenuButton: { template: '<div><slot /></div>' },
                    SidebarMenuItem: { template: '<div><slot /></div>' },
                    AppLogo: true,
                    NavUser: true,
                    NavMain: {
                        props: ['items'],
                        template:
                            '<nav><a v-for="item in items" :key="item.title" :href="item.href.url ?? item.href">{{ item.title }}</a></nav>',
                    },
                },
            },
        });

    beforeEach(() => {
        roleState.roles = [{ name: 'user' }];
    });

    it('shows profile without dashboard to a normal member', () => {
        const wrapper = mountSidebar();

        const discover = wrapper.get('a[href="/discover"]');
        expect(discover.text()).toBe('Découvrir');
        expect(wrapper.text()).toContain('Mon profil');
        expect(wrapper.text()).not.toContain('Administration');
    });

    it('adds administration dashboard for an admin', () => {
        roleState.roles = [{ name: 'user' }, { name: 'admin' }];
        const wrapper = mountSidebar();

        expect(wrapper.get('a[href="/discover"]').text()).toBe('Découvrir');
        expect(wrapper.text()).toContain('Mon profil');
        expect(wrapper.text()).toContain('Administration');
    });
});
