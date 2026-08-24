import type { Component } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

export function resolvePageLayout(
    name: string,
): Component | Component[] | null {
    if (name === 'Welcome') {
        return null;
    }

    if (name.startsWith('auth/')) {
        return AuthLayout;
    }

    if (name === 'Dashboard' || name.startsWith('Admin/')) {
        return AdminLayout;
    }

    if (name.startsWith('settings/')) {
        return [MemberLayout, SettingsLayout];
    }

    return MemberLayout;
}
