import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Create from './Create.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<span />' },
}));

vi.mock('@/routes/member-profile', () => ({
    store: { url: () => '/profile' },
}));

describe('profile onboarding page', () => {
    it('uses a small-screen-first app surface without vertical centering', () => {
        const wrapper = mount(Create, {
            props: {
                profile: null,
                visitFrequencies: [],
                visibilities: [],
            },
            global: {
                stubs: {
                    ProfileForm: true,
                    Card: { template: '<section><slot /></section>' },
                    CardHeader: { template: '<header><slot /></header>' },
                    CardTitle: { template: '<h1><slot /></h1>' },
                    CardDescription: { template: '<p><slot /></p>' },
                    CardContent: { template: '<div><slot /></div>' },
                },
            },
        });

        const main = wrapper.get('main');

        expect(main.classes()).toContain('min-h-svh');
        expect(main.classes()).toContain('max-w-xl');
        expect(main.classes()).not.toContain('items-center');
    });
});
