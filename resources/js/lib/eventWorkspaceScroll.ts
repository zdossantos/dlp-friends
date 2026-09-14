const storageKey = 'event-workspace-scroll';
const regionSelector = '[data-test="member-shell-content"]';
let rememberedScrollTop: number | null = null;

export function rememberEventWorkspaceScroll(
    opener?: HTMLElement | null,
): void {
    const region =
        opener?.closest<HTMLElement>(regionSelector) ??
        document.querySelector<HTMLElement>(regionSelector);

    if (region) {
        rememberedScrollTop = region.scrollTop;
        sessionStorage.setItem(storageKey, String(rememberedScrollTop));
    }
}

export function restoreEventWorkspaceScroll(): void {
    const stored = sessionStorage.getItem(storageKey);
    const region = document.querySelector<HTMLElement>(regionSelector);

    if (rememberedScrollTop === null && stored !== null) {
        rememberedScrollTop = Number(stored);
    }

    const scrollTop = rememberedScrollTop;

    if (scrollTop === null || !Number.isFinite(scrollTop) || !region) {
        return;
    }

    region.scrollTop = scrollTop;
}

export function forgetEventWorkspaceScroll(): void {
    rememberedScrollTop = null;
    sessionStorage.removeItem(storageKey);
}
