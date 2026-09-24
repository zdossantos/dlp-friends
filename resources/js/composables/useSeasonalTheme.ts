import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    MAX_BROWSER_TIMEOUT,
    millisecondsUntilTransition,
    syncSeasonalThemeClass,
} from '@/lib/seasonalTheme';
import type { SeasonalThemeState } from '@/types/seasonalTheme';

let initialized = false;
let transitionTimer: ReturnType<typeof setTimeout> | null = null;

function readInitialState(): SeasonalThemeState | null {
    const serializedPage =
        document.querySelector<HTMLElement>('[data-page]')?.dataset.page;

    if (serializedPage === undefined) {
        return null;
    }

    try {
        const page = JSON.parse(serializedPage) as {
            props?: { seasonalTheme?: SeasonalThemeState };
        };

        return page.props?.seasonalTheme ?? null;
    } catch {
        return null;
    }
}

function scheduleTransition(state: SeasonalThemeState): void {
    if (transitionTimer !== null) {
        clearTimeout(transitionTimer);
        transitionTimer = null;
    }

    const delay = millisecondsUntilTransition(state.nextTransitionAt);

    if (delay === null) {
        return;
    }

    transitionTimer = setTimeout(() => {
        const remaining = millisecondsUntilTransition(state.nextTransitionAt);

        if (remaining === MAX_BROWSER_TIMEOUT) {
            scheduleTransition(state);

            return;
        }

        router.reload({
            only: ['seasonalTheme'],
        });
    }, delay);
}

function applyState(state: SeasonalThemeState): void {
    syncSeasonalThemeClass(state.active);
    scheduleTransition(state);
}

export function initializeSeasonalTheme(): void {
    if (typeof document === 'undefined' || initialized) {
        return;
    }

    initialized = true;
    const initialState = readInitialState();

    if (initialState !== null) {
        applyState(initialState);
    }

    router.on('navigate', (event) => {
        applyState(event.detail.page.props.seasonalTheme);
    });

    router.on('success', (event) => {
        applyState(event.detail.page.props.seasonalTheme);
    });
}

export function useSeasonalTheme() {
    const page = usePage();

    return computed(() => page.props.seasonalTheme);
}
