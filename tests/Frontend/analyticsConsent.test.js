import { describe, expect, test } from 'bun:test';
import {
    ANALYTICS_CONSENT_MAX_AGE,
    activateGoogleAnalytics,
    createAnalyticsConsentController,
    readAnalyticsConsent,
} from '../../resources/js/lib/analyticsConsent';

function consentRuntime(cookie = '') {
    const activations = [];
    const writes = [];
    let cleared = false;
    let reloaded = false;

    return {
        activations,
        writes,
        runtime: {
            activate: () => activations.push('activated'),
            clearAnalyticsCookies: () => {
                cleared = true;
            },
            cookie: () => cookie,
            reload: () => {
                reloaded = true;
            },
            writeCookie: (value) => writes.push(value),
        },
        wasCleared: () => cleared,
        wasReloaded: () => reloaded,
    };
}

describe('analytics consent', () => {
    test('does not activate analytics before a choice', () => {
        const context = consentRuntime();

        const consent = createAnalyticsConsentController(context.runtime);

        expect(consent.current()).toBeNull();
        expect(context.activations).toEqual([]);
    });

    test('a refusal is remembered without activating analytics', () => {
        const context = consentRuntime();
        const consent = createAnalyticsConsentController(context.runtime);

        consent.refuse();

        expect(context.writes).toEqual([
            `analytics_consent=denied;path=/;max-age=${ANALYTICS_CONSENT_MAX_AGE};SameSite=Lax`,
        ]);
        expect(context.activations).toEqual([]);
    });

    test('an acceptance is remembered and activates analytics', () => {
        const context = consentRuntime();
        const consent = createAnalyticsConsentController(context.runtime);

        consent.accept();

        expect(context.writes).toEqual([
            `analytics_consent=granted;path=/;max-age=${ANALYTICS_CONSENT_MAX_AGE};SameSite=Lax`,
        ]);
        expect(context.activations).toEqual(['activated']);
    });

    test('stored acceptance activates analytics on the next page', () => {
        const context = consentRuntime('locale=fr; analytics_consent=granted');

        createAnalyticsConsentController(context.runtime);

        expect(context.activations).toEqual(['activated']);
    });

    test('withdrawing acceptance clears analytics cookies and reloads', () => {
        const context = consentRuntime('analytics_consent=granted; _ga=client');
        const consent = createAnalyticsConsentController(context.runtime);

        consent.refuse();

        expect(context.wasCleared()).toBe(true);
        expect(context.wasReloaded()).toBe(true);
        expect(readAnalyticsConsent('analytics_consent=denied')).toBe('denied');
    });
});

describe('Google Analytics activation', () => {
    test('loads Consent Mode only after activation and keeps advertising denied', () => {
        const commands = [];
        const scripts = [];

        activateGoogleAnalytics('G-TEST123456', false, {
            appendScript: (source) => scripts.push(source),
            notifyAnalyticsReady: () => {},
            queue: (...command) => commands.push(command),
        });

        expect(commands).toEqual([
            [
                'consent',
                'default',
                {
                    ad_personalization: 'denied',
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    analytics_storage: 'denied',
                },
            ],
            [
                'consent',
                'update',
                {
                    ad_personalization: 'denied',
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    analytics_storage: 'granted',
                },
            ],
            ['js', expect.any(Date)],
            ['config', 'G-TEST123456'],
        ]);
        expect(scripts).toEqual([
            'https://www.googletagmanager.com/gtag/js?id=G-TEST123456',
        ]);
    });

    test('lets the Inertia tracker send the page view', () => {
        const commands = [];
        let readyNotifications = 0;

        activateGoogleAnalytics('G-TEST123456', true, {
            appendScript: () => {},
            notifyAnalyticsReady: () => {
                readyNotifications += 1;
            },
            queue: (...command) => commands.push(command),
        });

        expect(commands.at(-1)).toEqual([
            'config',
            'G-TEST123456',
            { send_page_view: false },
        ]);
        expect(readyNotifications).toBe(1);
    });
});
