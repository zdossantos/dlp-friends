import { beforeEach, describe, expect, it, vi } from 'vitest';
import { initializeTheme } from './useAppearance';

const mockMatchMedia = (matches: boolean) => {
    const addEventListener = vi.fn();
    const mediaQuery = {
        matches,
        addEventListener,
        removeEventListener: vi.fn(),
    };

    vi.stubGlobal(
        'matchMedia',
        vi.fn(() => mediaQuery),
    );

    return addEventListener;
};

describe('appearance initialization', () => {
    beforeEach(() => {
        const values = new Map<string, string>();
        vi.stubGlobal('localStorage', {
            clear: () => values.clear(),
            getItem: (key: string) => values.get(key) ?? null,
            removeItem: (key: string) => values.delete(key),
            setItem: (key: string, value: string) => values.set(key, value),
        });
        document.documentElement.classList.remove('dark');
    });

    it('prefers a stored appearance over the system preference', () => {
        localStorage.setItem('appearance', 'dark');
        mockMatchMedia(false);

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('uses the system preference when no appearance is stored', () => {
        mockMatchMedia(true);

        initializeTheme();

        expect(document.documentElement.classList.contains('dark')).toBe(true);
    });

    it('does not register duplicate system listeners', () => {
        const addEventListener = mockMatchMedia(false);

        initializeTheme();
        initializeTheme();

        expect(addEventListener).toHaveBeenCalledTimes(1);
    });
});
