const storageKey = 'event-workspace-scroll';
let rememberedScrollTop: number | null = null;
let rememberedOpener: string | null = null;

export function rememberEventWorkspaceScroll(opener: string): void {
    rememberedScrollTop = window.scrollY;
    rememberedOpener = opener;

    try {
        sessionStorage.setItem(storageKey, String(rememberedScrollTop));
        sessionStorage.setItem('event-panel-opener', opener);
    } catch {
        // Position and focus remain available in memory when storage is denied.
    }
}

export function eventWorkspaceOpener(): string | null {
    let opener = rememberedOpener;

    if (opener === null) {
        try {
            opener = sessionStorage.getItem('event-panel-opener');
        } catch {
            return null;
        }
    }

    return opener !== null && /^event-(create|link-\d+)$/.test(opener)
        ? opener
        : null;
}

export function restoreEventWorkspaceScroll(): void {
    if (rememberedScrollTop === null) {
        try {
            const stored = sessionStorage.getItem(storageKey);

            if (stored !== null) {
                rememberedScrollTop = Number(stored);
            }
        } catch {
            return;
        }
    }

    const scrollTop = rememberedScrollTop;

    if (scrollTop === null || !Number.isFinite(scrollTop)) {
        return;
    }

    window.scrollTo({ top: scrollTop, behavior: 'instant' });
}

export function forgetEventWorkspaceScroll(): void {
    rememberedScrollTop = null;
    rememberedOpener = null;

    try {
        sessionStorage.removeItem(storageKey);
        sessionStorage.removeItem('event-panel-opener');
    } catch {
        // Clearing the optional persistent cache must not interrupt navigation.
    }
}
