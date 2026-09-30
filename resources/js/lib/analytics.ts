import { router } from '@inertiajs/vue3';
import { resolveAppMode } from '@/lib/appMode';
import {
    normalizeAnalyticsPath,
    resolveAnalyticsPage,
} from '@/lib/analyticsPage';

export { normalizeAnalyticsPath } from '@/lib/analyticsPage';

declare global {
    interface Window {
        gtag?: (...args: unknown[]) => void;
    }
}

type Gtag = (...args: unknown[]) => void;

interface AnalyticsRuntime {
    appMode?: 'pwa' | 'browser';
    documentTitle?: string;
    gtag: Gtag | undefined;
    initialReferrer: string;
    initialUrl: string;
    locale?: string;
    onAnalyticsReady?: (listener: () => void) => void;
    onNavigate: (listener: (url: string) => void) => void;
    origin: string;
}

function analyticsLocation(origin: string, path: string): string {
    return `${origin}${path}`;
}

function normalizeAnalyticsReferrer(referrer: string): string | undefined {
    if (!referrer) {
        return undefined;
    }

    try {
        const url = new URL(referrer);

        return analyticsLocation(
            url.origin,
            normalizeAnalyticsPath(url.pathname),
        );
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
        get gtag() {
            return window.gtag;
        },
        appMode: resolveAppMode(),
        documentTitle: window.document.title,
        initialReferrer: window.document.referrer,
        initialUrl: window.location.pathname,
        locale: window.document.documentElement.lang,
        onAnalyticsReady: (listener: () => void) => {
            window.addEventListener('analytics:ready', listener, {
                once: true,
            });
        },
        onNavigate: (listener: (url: string) => void) => {
            router.on('navigate', (event) => listener(event.detail.page.url));
        },
        origin: window.location.origin,
    };

    let trackingStarted = false;
    const startTracking = () => {
        if (!activeRuntime.gtag || trackingStarted) {
            return;
        }

        trackingStarted = true;

        const locale = activeRuntime.locale ?? 'fr';
        const appMode = activeRuntime.appMode ?? 'browser';
        const initialPage = resolveAnalyticsPage(
            activeRuntime.initialUrl,
            locale,
        );

        let previousLocation = analyticsLocation(
            activeRuntime.origin,
            initialPage.pagePath,
        );
        const initialReferrer = normalizeAnalyticsReferrer(
            activeRuntime.initialReferrer,
        );

        activeRuntime.gtag('event', 'page_view', {
            app_mode: appMode,
            page_location: previousLocation,
            page_path: initialPage.pagePath,
            page_title: initialPage.pageTitle,
            page_type: initialPage.pageType,
            ...(initialReferrer ? { page_referrer: initialReferrer } : {}),
        });

        activeRuntime.onNavigate((url) => {
            const page = resolveAnalyticsPage(url, locale);
            const pageLocation = analyticsLocation(
                activeRuntime.origin,
                page.pagePath,
            );

            activeRuntime.gtag?.('event', 'page_view', {
                app_mode: appMode,
                page_location: pageLocation,
                page_path: page.pagePath,
                page_referrer: previousLocation,
                page_title: page.pageTitle,
                page_type: page.pageType,
            });

            previousLocation = pageLocation;
        });
    };

    if (activeRuntime.gtag) {
        startTracking();
    } else {
        activeRuntime.onAnalyticsReady?.(startTracking);
    }
}
