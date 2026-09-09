import {
    activateGoogleAnalytics,
    createAnalyticsConsentController,
} from '@/lib/analyticsConsent';

declare global {
    interface Window {
        dataLayer?: unknown[][];
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
    let activated = false;

    if (dialog && accept && refuse && settings && measurementId) {
        const showDialog = () => {
            dialog.hidden = false;
            settings.setAttribute('aria-expanded', 'true');
            accept.focus();
        };
        const hideDialog = () => {
            dialog.hidden = true;
            settings.setAttribute('aria-expanded', 'false');
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
                window.gtag = (...command: unknown[]) => {
                    window.dataLayer?.push(command);
                };

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
        }

        accept.addEventListener('click', () => {
            controller.accept();
            hideDialog();
            settings.focus();
        });
        refuse.addEventListener('click', () => {
            controller.refuse();
            hideDialog();
            settings.focus();
        });
        settings.addEventListener('click', showDialog);
    }
}
