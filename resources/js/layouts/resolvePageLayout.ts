import type { Component } from 'vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import MemberLayout from '@/layouts/MemberLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

export function usesAdminLayout(name: string): boolean {
    return name === 'Dashboard' || name.startsWith('Admin/');
}

export function resolvePageLayout(
    name: string,
): Component | Component[] | null {
    if (name.startsWith('auth/')) {
        return AuthLayout;
    }

    if (usesAdminLayout(name)) {
        return AdminLayout;
    }

    if (name.startsWith('settings/')) {
        return [MemberLayout, SettingsLayout];
    }

    return MemberLayout;
}
