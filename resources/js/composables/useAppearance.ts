import type { ComputedRef, Ref } from 'vue';
import { computed, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = (): Appearance | null => {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const value = localStorage.getItem('appearance');

        return value === 'light' || value === 'dark' || value === 'system'
            ? value
            : null;
    } catch {
        return null;
    }
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const appearance = ref<Appearance>('system');
const systemPrefersDark = ref(false);

const handleSystemThemeChange = () => {
    systemPrefersDark.value = prefersDark();
    updateTheme(appearance.value);
};

let registeredMediaQuery: MediaQueryList | null = null;

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    appearance.value = getStoredAppearance() ?? 'system';
    systemPrefersDark.value = prefersDark();
    updateTheme(appearance.value);

    const currentMediaQuery = mediaQuery();

    if (currentMediaQuery && currentMediaQuery !== registeredMediaQuery) {
        registeredMediaQuery?.removeEventListener(
            'change',
            handleSystemThemeChange,
        );
        currentMediaQuery.addEventListener('change', handleSystemThemeChange);
        registeredMediaQuery = currentMediaQuery;
    }
}

export function useAppearance(): UseAppearanceReturn {
    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return systemPrefersDark.value ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;

        // Store in localStorage for client-side persistence...
        try {
            localStorage.setItem('appearance', value);
        } catch {
            // Browser storage is optional; the current theme still applies.
        }

        // Store in cookie for SSR...
        setCookie('appearance', value);

        updateTheme(value);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
    };
}
