import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Welcome from './Welcome.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
    Link: {
        props: ['href'],
        template: '<a :href="href.url"><slot /></a>',
    },
}));

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
                AppearanceTabs: true,
                AppLogoIcon: true,
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
        expect(wrapper.get('a[href="/register"]').text()).toContain(
            'Créer mon compte',
        );
        expect(wrapper.get('a[href="/login"]').text()).toContain(
            'Se connecter',
        );
        expect(wrapper.text()).not.toContain("Let's get started");
    });

    it('offers the member space instead of guest calls to action when signed in', () => {
        const wrapper = mountWelcome({ id: 1 });

        expect(wrapper.get('a[href="/app"]').text()).toContain(
            'Ouvrir mon espace',
        );
        expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
    });
});
