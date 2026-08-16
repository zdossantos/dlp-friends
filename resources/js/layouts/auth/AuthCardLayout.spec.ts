import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import AuthCardLayout from './AuthCardLayout.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
}));

vi.mock('@/routes', () => ({
    home: () => ({ url: '/' }),
}));

describe('AuthCardLayout', () => {
    it('exposes the brand, theme control and form landmark', () => {
        const wrapper = mount(AuthCardLayout, {
            props: {
                title: 'Créer un compte',
                description: 'Rejoignez la communauté',
            },
            slots: { default: '<form>Formulaire</form>' },
            global: {
                stubs: {
                    Link: { template: '<a><slot /></a>' },
                    AppLogoIcon: true,
                    AppearanceTabs: {
                        template: '<div aria-label="Thème">Thème</div>',
                    },
                    Card: { template: '<section><slot /></section>' },
                    CardHeader: { template: '<header><slot /></header>' },
                    CardTitle: { template: '<h1><slot /></h1>' },
                    CardDescription: { template: '<p><slot /></p>' },
                    CardContent: { template: '<div><slot /></div>' },
                },
            },
        });

        expect(wrapper.text()).toContain('DLP Friends');
        expect(wrapper.text()).toContain('Créer un compte');
        expect(wrapper.text()).toContain('Rejoignez la communauté');
        expect(wrapper.text()).toContain('Thème');
        expect(wrapper.find('main').text()).toContain('Formulaire');
    });
});
