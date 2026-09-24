import type { SeasonalThemeName } from '@/types/seasonalTheme';

export const MAX_BROWSER_TIMEOUT = 2_147_000_000;

export function seasonalThemeClass(
    active: SeasonalThemeName | null,
): string | null {
    return active === null ? null : `seasonal-${active}`;
}

export function millisecondsUntilTransition(
    transitionAt: string | null,
    now = Date.now(),
): number | null {
    if (transitionAt === null) {
        return null;
    }

    const target = Date.parse(transitionAt);

    if (!Number.isFinite(target)) {
        return null;
    }

    return Math.min(Math.max(target - now, 0), MAX_BROWSER_TIMEOUT);
}

export function syncSeasonalThemeClass(active: SeasonalThemeName | null): void {
    document.documentElement.classList.remove(
        'seasonal-halloween',
        'seasonal-christmas',
    );
    const className = seasonalThemeClass(active);

    if (className !== null) {
        document.documentElement.classList.add(className);
    }
}
