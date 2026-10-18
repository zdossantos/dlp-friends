import { isIosDevice } from './capabilities';

/** Reapply viewport constraints that WebKit can lose after an external window. */
export const initializeIosViewportRecovery = (
    window: Window,
    document: Document,
): void => {
    if (
        !isIosDevice(window.navigator) ||
        !(
            window.navigator.standalone === true ||
            window.matchMedia('(display-mode: standalone)').matches
        )
    ) {
        return;
    }

    let recovering = false;

    const recover = (): void => {
        if (document.visibilityState !== 'visible' || recovering) {
            return;
        }

        const viewport = document.querySelector<HTMLMetaElement>(
            'meta[name="viewport"]',
        );

        if (!viewport) {
            return;
        }

        recovering = true;
        const original = viewport.content;
        // Changing the fit mode makes WebKit recalculate its native viewport.
        // Restore the complete declaration, including the user's zoom settings.
        viewport.content =
            original.replace(/,?\s*viewport-fit\s*=\s*[^,]+/i, '') +
            ', viewport-fit=auto';
        window.requestAnimationFrame(() => {
            viewport.content = original;
            recovering = false;
        });
    };

    window.addEventListener('focus', recover);
    window.addEventListener('pageshow', recover);
    document.addEventListener('visibilitychange', recover);
};
