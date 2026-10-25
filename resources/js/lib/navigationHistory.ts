import type { VisitOptions } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';

const historyKey = 'app-navigation-depth';
let depth = 0;

// Inertia clears remembered state on reload. Keep the current depth per tab
// for that case, and use remembered state when traversing encrypted history.
export function initializeNavigationHistory(): void {
    let reloadDepth = 0;

    try {
        const navigation = performance.getEntriesByType('navigation')[0] as
            PerformanceNavigationTiming | undefined;
        const saved = JSON.parse(sessionStorage.getItem(historyKey) ?? 'null');

        if (
            navigation?.type === 'reload' &&
            saved?.url === window.location.href
        ) {
            reloadDepth = Number(saved.depth) || 0;
        }
    } catch {
        // Storage can be unavailable; regular in-app history still works.
    }

    function remember(): void {
        router.remember(depth, historyKey);

        try {
            sessionStorage.setItem(
                historyKey,
                JSON.stringify({ url: window.location.href, depth }),
            );
        } catch {
            // Reload recovery is optional when browser storage is disabled.
        }
    }

    let initial = true;
    let traversing = false;
    const page = usePage();
    const rememberState = router.remember.bind(router);
    router.remember = (data, key) => {
        // Delayed InfiniteScroll callbacks must not overwrite the entry being restored.
        if (
            !traversing ||
            new URL(page.url, window.location.origin).href ===
                window.location.href
        ) {
            rememberState(data, key);
        }
    };
    window.addEventListener('popstate', (event) => {
        traversing = Boolean(event.state?.page);
    });
    router.on('navigate', (event) => {
        if (initial || traversing) {
            depth = Number(
                event.detail.page.rememberedState?.[historyKey] ??
                    (initial ? reloadDepth : 0),
            );
        } else {
            depth += 1;
        }

        initial = false;
        traversing = false;
        remember();
    });
    // Replacing a page (filters, validation or refresh) must retain its position.
    router.on('finish', () => {
        // Cancelled visits finish before Inertia restores the traversed page.
        if (!traversing) {
            remember();
        }
    });
}

export function goBack(fallback: string, options: VisitOptions = {}): void {
    if (depth > 0) {
        window.history.back();

        return;
    }

    router.visit(fallback, { ...options, replace: true });
}
