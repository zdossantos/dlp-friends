const storageKey = 'event-workspace-scroll';
let rememberedScrollTop: number | null = null;

export function rememberEventWorkspaceScroll(
    _opener?: HTMLElement | null,
): void {
    void _opener;
    rememberedScrollTop = window.scrollY;
    sessionStorage.setItem(storageKey, String(rememberedScrollTop));
}

export function restoreEventWorkspaceScroll(): void {
    const stored = sessionStorage.getItem(storageKey);

    if (rememberedScrollTop === null && stored !== null) {
        rememberedScrollTop = Number(stored);
    }

    const scrollTop = rememberedScrollTop;

    if (scrollTop === null || !Number.isFinite(scrollTop)) {
        return;
    }

    window.scrollTo({ top: scrollTop, behavior: 'instant' });
}

export function forgetEventWorkspaceScroll(): void {
    rememberedScrollTop = null;
    sessionStorage.removeItem(storageKey);
}
