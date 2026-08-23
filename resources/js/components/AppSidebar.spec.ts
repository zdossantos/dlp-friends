import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AppSidebar from './AppSidebar.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href'],
        template: '<a :href="href.url ?? href"><slot /></a>',
    },
    usePage: () => ({
        url: '/dashboard',
        props: {
            auth: {
                user: {
                    profile: {
                        onboarding_completed_at: '2026-08-23T10:00:00Z',
                    },
                    roles: [{ name: 'user' }],
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

    it('contains only admin navigation and a return to the member profile', () => {
        const wrapper = mountSidebar();
        const adminLink = wrapper
            .findAll('a[href="/dashboard"]')
            .find((link) => link.text() === 'Administration');

        expect(adminLink?.text()).toBe('Administration');
        expect(wrapper.get('a[href="/profile"]').text()).toBe(
            'Retour au profil',
        );
        expect(wrapper.find('a[href="/discover"]').exists()).toBe(false);
    });
});
