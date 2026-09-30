export const ANALYTICS_CONSENT_MAX_AGE = 60 * 60 * 24 * 180;

export type AnalyticsConsent = 'granted' | 'denied';

interface PublicAnalyticsPage {
    locale: string;
    pageTitle: string;
    pageType: string;
}

interface PublicPageViewRuntime {
    appMode: 'browser' | 'pwa';
    location: Pick<Location, 'origin' | 'pathname'>;
    queue: (...command: unknown[]) => void;
}

interface AnalyticsConsentRuntime {
    activate: () => void;
    clearAnalyticsCookies: () => void;
    cookie: () => string;
    reload: () => void;
    writeCookie: (value: string) => void;
}

interface GoogleAnalyticsRuntime {
    appendScript: (source: string) => void;
    notifyAnalyticsReady: () => void;
    queue: (...command: unknown[]) => void;
}

export function createGoogleTagQueue(
    dataLayer: IArguments[],
): (...command: unknown[]) => void {
    return function () {
        // gtag.js requires the native Arguments object used by its official snippet.
        // eslint-disable-next-line prefer-rest-params
        dataLayer.push(arguments);
    };
}

export function sendPublicPageView(
    page: PublicAnalyticsPage,
    runtime: PublicPageViewRuntime,
): void {
    runtime.queue('event', 'page_view', {
        app_mode: runtime.appMode,
        language: page.locale,
        page_location: `${runtime.location.origin}${runtime.location.pathname}`,
        page_path: runtime.location.pathname,
        page_title: page.pageTitle,
        page_type: page.pageType,
    });
}

export function activateGoogleAnalytics(
    measurementId: string,
    spa: boolean,
    runtime: GoogleAnalyticsRuntime,
): void {
    const deniedAdvertisingConsent = {
        ad_personalization: 'denied',
        ad_storage: 'denied',
        ad_user_data: 'denied',
    } as const;

    runtime.queue('consent', 'default', {
        ...deniedAdvertisingConsent,
        analytics_storage: 'denied',
    });
    runtime.queue('consent', 'update', {
        ...deniedAdvertisingConsent,
        analytics_storage: 'granted',
    });
    runtime.queue('js', new Date());
    runtime.queue('config', measurementId, { send_page_view: false });
    runtime.appendScript(
        `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(measurementId)}`,
    );

    if (spa) {
        runtime.notifyAnalyticsReady();
    }
}

export function readAnalyticsConsent(cookie: string): AnalyticsConsent | null {
    const value = cookie
        .split(';')
        .map((part) => part.trim())
        .find((part) => part.startsWith('analytics_consent='))
        ?.split('=', 2)[1];

    return value === 'granted' || value === 'denied' ? value : null;
}

export function createAnalyticsConsentController(
    runtime: AnalyticsConsentRuntime,
) {
    let choice = readAnalyticsConsent(runtime.cookie());

    if (choice === 'granted') {
        runtime.activate();
    }

    const remember = (nextChoice: AnalyticsConsent) => {
        runtime.writeCookie(
            `analytics_consent=${nextChoice};path=/;max-age=${ANALYTICS_CONSENT_MAX_AGE};SameSite=Lax`,
        );
        choice = nextChoice;
    };

    return {
        accept: () => {
            remember('granted');
            runtime.activate();
        },
        current: (): AnalyticsConsent | null => choice,
        refuse: () => {
            const withdrawsAcceptance = choice === 'granted';

            remember('denied');

            if (withdrawsAcceptance) {
                runtime.clearAnalyticsCookies();
                runtime.reload();
            }
        },
    };
}
