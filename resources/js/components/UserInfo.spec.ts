import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import UserInfo from './UserInfo.vue';

const stubs = {
    Avatar: { template: '<div><slot /></div>' },
    AvatarImage: { template: '<img />' },
    AvatarFallback: { template: '<span><slot /></span>' },
};

describe('UserInfo', () => {
    it('shows the profile display name when available', () => {
        const wrapper = shallowMount(UserInfo, {
            props: {
                user: {
                    id: 1,
                    email: 'magic@example.com',
                    email_verified_at: '2026-08-16T10:00:00Z',
                    profile: {
                        display_name: 'Magic Friend',
                        bio: null,
                        visit_frequency: 'often',
                        visibility: 'visible',
                        onboarding_completed_at: '2026-08-16T10:00:00Z',
                    },
                    roles: [{ name: 'user' }],
                },
            },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('Magic Friend');
    });

    it('falls back to email before profile completion', () => {
        const wrapper = shallowMount(UserInfo, {
            props: {
                user: {
                    id: 2,
                    email: 'new@example.com',
                    email_verified_at: '2026-08-16T10:00:00Z',
                    profile: null,
                    roles: [{ name: 'user' }],
                },
            },
            global: { stubs },
        });

        expect(wrapper.text()).toContain('new@example.com');
    });
});
