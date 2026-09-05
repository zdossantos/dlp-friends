import { router } from '@inertiajs/vue3';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

type Gtag = (...args: unknown[]) => void;

interface AnalyticsRuntime {
    gtag: Gtag | undefined;
    initialReferrer: string;
    initialUrl: string;
    onNavigate: (listener: (url: string) => void) => void;
    origin: string;
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

function analyticsLocation(origin: string, url: string): string {
    return `${origin}${normalizeAnalyticsPath(url)}`;
}

function normalizeAnalyticsReferrer(referrer: string): string | undefined {
    if (!referrer) {
        return undefined;
    }

    try {
        const url = new URL(referrer);

        return analyticsLocation(url.origin, url.pathname);
    } catch {
        return undefined;
    }
}

export async function initializeAnalytics(
    inertiaReady: Promise<unknown> = Promise.resolve(),
    runtime?: AnalyticsRuntime,
): Promise<void> {
    await inertiaReady;

    if (!runtime && typeof window === 'undefined') {
        return;
    }

    const activeRuntime = runtime ?? {
        gtag: window.gtag,
        initialReferrer: window.document.referrer,
        initialUrl: window.location.pathname,
        onNavigate: (listener: (url: string) => void) => {
            router.on('navigate', (event) => listener(event.detail.page.url));
        },
        origin: window.location.origin,
    };

    if (!activeRuntime.gtag) {
        return;
    }

    let previousLocation = analyticsLocation(
        activeRuntime.origin,
        activeRuntime.initialUrl,
    );
    const initialReferrer = normalizeAnalyticsReferrer(
        activeRuntime.initialReferrer,
    );

    activeRuntime.gtag('event', 'page_view', {
        page_location: previousLocation,
        page_path: normalizeAnalyticsPath(activeRuntime.initialUrl),
        ...(initialReferrer ? { page_referrer: initialReferrer } : {}),
    });

    activeRuntime.onNavigate((url) => {
        const pageLocation = analyticsLocation(activeRuntime.origin, url);

        activeRuntime.gtag?.('event', 'page_view', {
            page_location: pageLocation,
            page_path: normalizeAnalyticsPath(url),
            page_referrer: previousLocation,
        });

        previousLocation = pageLocation;
    });
}
