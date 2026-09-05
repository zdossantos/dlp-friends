import { router } from '@inertiajs/vue3';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

const identifierSegment =
    /^\d+$|^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

export function normalizeAnalyticsPath(path: string): string {
    const pathname = path.split(/[?#]/, 1)[0] || '/';

    return pathname
        .split('/')
        .map((segment) => (identifierSegment.test(segment) ? '{id}' : segment))
        .join('/');
}

function trackPageView(url: string): void {
    window.gtag?.('event', 'page_view', {
        page_location: `${window.location.origin}${normalizeAnalyticsPath(url)}`,
        page_path: normalizeAnalyticsPath(url),
    });
}

export function initializeAnalytics(): void {
    if (typeof window === 'undefined' || !window.gtag) {
        return;
    }

    trackPageView(window.location.pathname);

    router.on('navigate', (event) => {
        trackPageView(event.detail.page.url);
    });
}
