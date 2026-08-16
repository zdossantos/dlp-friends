import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Dashboard from './Dashboard.vue';

describe('admin dashboard', () => {
    it('renders the account statistics and recent registrations', () => {
        const wrapper = shallowMount(Dashboard, {
            props: {
                stats: {
                    totalAccounts: 12,
                    activeAccounts: 10,
                    verifiedAccounts: 9,
                    completedProfiles: 7,
                },
                recentRegistrations: [
                    {
                        email: 'new@example.com',
                        status: 'active',
                        profile_completed: true,
                        registered_at: '2026-08-16T10:00:00Z',
                    },
                ],
            },
            global: {
                stubs: {
                    Head: true,
                    Card: { template: '<section><slot /></section>' },
                    CardHeader: { template: '<header><slot /></header>' },
                    CardTitle: { template: '<h2><slot /></h2>' },
                    CardDescription: { template: '<p><slot /></p>' },
                    CardContent: { template: '<div><slot /></div>' },
                    Badge: { template: '<span><slot /></span>' },
                },
            },
        });

        expect(wrapper.text()).toContain('12');
        expect(wrapper.text()).toContain('10');
        expect(wrapper.text()).toContain('9');
        expect(wrapper.text()).toContain('7');
        expect(wrapper.text()).toContain('new@example.com');
        expect(wrapper.text()).toContain('Profil complété');
    });
});
