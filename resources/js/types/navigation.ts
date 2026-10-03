import type { InertiaLinkProps } from '@inertiajs/vue3';
import type { LucideIcon } from '@lucide/vue';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon;
    isActive?: boolean;
    items?: NavItem[];
    testId?: string;
};

export type BottomNavigationItem = {
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    activeParents?: string[];
    unreadCount?: number;
    unreadLabel?: string;
    testId?: string;
};

export type WorkspaceDestination = {
    role: 'user' | 'partner' | 'admin';
    label: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    testId: string;
};
