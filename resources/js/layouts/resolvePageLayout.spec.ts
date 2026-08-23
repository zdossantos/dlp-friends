import { describe, expect, it } from 'vitest';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { resolvePageLayout } from './resolvePageLayout';

describe('resolvePageLayout', () => {
    it.each([
        ['Welcome', null],
        ['auth/Login', AuthLayout],
        ['Dashboard', AdminLayout],
        ['Discovery/Index', MemberLayout],
        ['profile/Create', MemberLayout],
        ['profile/Show', MemberLayout],
    ])('maps %s to its application shell', (name, expected) => {
        expect(resolvePageLayout(name)).toBe(expected);
    });

    it('nests settings inside the member shell', () => {
        expect(resolvePageLayout('settings/Account')).toEqual([
            MemberLayout,
            SettingsLayout,
        ]);
    });
});
