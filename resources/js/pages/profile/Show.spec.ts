import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Show from './Show.vue';

vi.mock('@/routes/member-profile', () => ({
    edit: () => ({ url: '/profile/edit' }),
    show: () => ({ url: '/profile' }),
}));

describe('member profile page', () => {
    it('renders the public profile summary and edit action', () => {
        const wrapper = shallowMount(Show, {
            props: {
                age: 26,
                profile: {
                    display_name: 'Magic Friend',
                    bio: 'Toujours partant pour une attraction.',
                    visit_frequency: 'often',
                    visibility: 'visible',
                    onboarding_completed_at: '2026-08-16T10:00:00Z',
                },
            },
            global: {
                stubs: {
                    Head: true,
                    Link: { template: '<a><slot /></a>' },
                    Button: { template: '<button><slot /></button>' },
                    Badge: { template: '<span><slot /></span>' },
                },
            },
        });

        expect(wrapper.text()).toContain('Magic Friend');
        expect(wrapper.text()).toContain('26 ans');
        expect(wrapper.text()).toContain(
            'Toujours partant pour une attraction.',
        );
        expect(wrapper.text()).toContain('Souvent');
        expect(wrapper.text()).toContain('Visible');
        expect(wrapper.text()).toContain('Modifier mon profil');
    });
});
