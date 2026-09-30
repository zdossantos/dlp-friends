import {
    activateGoogleAnalytics,
    createAnalyticsConsentController,
    createGoogleTagQueue,
    sendPublicPageView,
} from '@/lib/analyticsConsent';
import { resolveAppMode } from '@/lib/appMode';

declare global {
    interface Window {
        dataLayer?: IArguments[];
        gtag?: (...args: unknown[]) => void;
    }
}

const root = document.querySelector<HTMLElement>('[data-analytics-consent]');

if (root) {
    const dialog = root.querySelector<HTMLElement>(
        '[data-test="analytics-consent-dialog"]',
    );
    const accept = root.querySelector<HTMLButtonElement>(
        '[data-analytics-accept]',
    );
    const refuse = root.querySelector<HTMLButtonElement>(
        '[data-analytics-refuse]',
    );
    const settings = root.querySelector<HTMLButtonElement>(
        '[data-analytics-settings]',
    );
    const measurementId = root.dataset.analyticsMeasurementId;
    const spa = root.dataset.analyticsSpa === 'true';
    const locale = root.dataset.analyticsLocale;
    const pageTitle = root.dataset.analyticsPageTitle;
    const pageType = root.dataset.analyticsPageType;
    let activated = false;
    let dialogTrigger: HTMLElement | null = null;

    if (dialog && accept && refuse && settings && measurementId) {
        const showDialog = (trigger?: HTMLElement) => {
            dialogTrigger = trigger ?? null;
            dialog.hidden = false;
            settings.setAttribute('aria-expanded', 'true');
            accept.focus();
        };
        const hideDialog = () => {
            dialog.hidden = true;
            settings.hidden = true;
            settings.setAttribute('aria-expanded', 'false');
            dialogTrigger?.focus();
            dialogTrigger = null;
        };
        const clearAnalyticsCookies = () => {
            const domainParts = window.location.hostname.split('.');
            const domains = domainParts.flatMap((_, index) => {
                const domain = domainParts.slice(index).join('.');

                return domain.includes('.') ? [domain, `.${domain}`] : [];
            });

            document.cookie
                .split(';')
                .map((cookie) => cookie.trim().split('=', 1)[0])
                .filter((name) => name === '_ga' || name.startsWith('_ga_'))
                .forEach((name) => {
                    document.cookie = `${name}=;path=/;max-age=0;SameSite=Lax`;
                    domains.forEach((domain) => {
                        document.cookie = `${name}=;path=/;domain=${domain};max-age=0;SameSite=Lax`;
                    });
                });
        };
        const controller = createAnalyticsConsentController({
            activate: () => {
                if (activated) {
                    return;
                }

                activated = true;
                window.dataLayer = window.dataLayer ?? [];
                window.gtag = createGoogleTagQueue(window.dataLayer);

                activateGoogleAnalytics(measurementId, spa, {
                    appendScript: (source) => {
                        const script = document.createElement('script');
                        script.async = true;
                        script.src = source;
                        document.head.append(script);
                    },
                    notifyAnalyticsReady: () => {
                        window.dispatchEvent(new Event('analytics:ready'));
                    },
                    queue: (...command) => window.gtag?.(...command),
                });

                if (!spa && locale && pageTitle && pageType) {
                    sendPublicPageView(
                        { locale, pageTitle, pageType },
                        {
                            appMode: resolveAppMode(),
                            location: window.location,
                            queue: (...command) => window.gtag?.(...command),
                        },
                    );
                }
            },
            clearAnalyticsCookies,
            cookie: () => document.cookie,
            reload: () => window.location.reload(),
            writeCookie: (value) => {
                document.cookie = `${value}${window.location.protocol === 'https:' ? ';Secure' : ''}`;
            },
        });

        if (controller.current() === null) {
            showDialog();
        } else {
            settings.hidden = true;
        }

        accept.addEventListener('click', () => {
            controller.accept();
            hideDialog();
        });
        refuse.addEventListener('click', () => {
            controller.refuse();
            hideDialog();
        });
        settings.addEventListener('click', () => showDialog(settings));
        document.addEventListener('click', (event) => {
            const target =
                event.target instanceof Element
                    ? event.target.closest<HTMLElement>(
                          '[data-analytics-settings-trigger]',
                      )
                    : null;

            if (target) {
                showDialog(target);
            }
        });
    }
}
