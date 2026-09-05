import { describe, expect, test } from 'bun:test';
import {
    initializeAnalytics,
    normalizeAnalyticsPath,
} from '../../resources/js/lib/analytics';

describe('normalizeAnalyticsPath', () => {
    test('keeps public and static application paths unchanged', () => {
        expect(normalizeAnalyticsPath('/fr/matching')).toBe('/fr/matching');
        expect(normalizeAnalyticsPath('/settings/profile')).toBe('/settings/profile');
    });

    test('removes identifiers from analytics paths', () => {
        expect(normalizeAnalyticsPath('/conversations/42')).toBe('/conversations/{id}');
        expect(
            normalizeAnalyticsPath(
                '/profiles/0198f30e-7b67-7260-9c7d-4f15d4da0d31',
            ),
        ).toBe('/profiles/{id}');
    });

    test('never includes query parameters or fragments', () => {
        expect(normalizeAnalyticsPath('/discover?page=2#profile')).toBe(
            '/discover',
        );
    });
});

describe('initializeAnalytics', () => {
    test('waits for Inertia before sending the initial page view', async () => {
        const calls = [];
        let resolveInertia;
        const inertiaReady = new Promise((resolve) => {
            resolveInertia = resolve;
        });

        const initialization = initializeAnalytics(inertiaReady, {
            gtag: (...args) => calls.push(args),
            initialReferrer: '',
            initialUrl: '/fr',
            onNavigate: () => {},
            origin: 'https://dlp-friends.example',
        });

        expect(calls).toEqual([]);

        resolveInertia();
        await initialization;

        expect(calls).toHaveLength(1);
    });

    test('sends one initial page view and one additional view per Inertia navigation', async () => {
        const calls = [];
        let navigate;

        await initializeAnalytics(Promise.resolve(), {
            gtag: (...args) => calls.push(args),
            initialReferrer:
                'https://www.google.com/search?q=private#sensitive',
            initialUrl: '/settings/profile?tab=privacy#danger',
            onNavigate: (listener) => {
                navigate = listener;
            },
            origin: 'https://dlp-friends.example',
        });

        expect(calls).toEqual([
            [
                'event',
                'page_view',
                {
                    page_location:
                        'https://dlp-friends.example/settings/profile',
                    page_path: '/settings/profile',
                    page_referrer: 'https://www.google.com/search',
                },
            ],
        ]);

        navigate('/conversations/42?from=notification#latest');

        expect(calls).toEqual([
            [
                'event',
                'page_view',
                {
                    page_location:
                        'https://dlp-friends.example/settings/profile',
                    page_path: '/settings/profile',
                    page_referrer: 'https://www.google.com/search',
                },
            ],
            [
                'event',
                'page_view',
                {
                    page_location:
                        'https://dlp-friends.example/conversations/{id}',
                    page_path: '/conversations/{id}',
                    page_referrer:
                        'https://dlp-friends.example/settings/profile',
                },
            ],
        ]);
    });

    test('normalizes an internal referrer containing an identifier', async () => {
        const calls = [];

        await initializeAnalytics(Promise.resolve(), {
            gtag: (...args) => calls.push(args),
            initialReferrer:
                'https://dlp-friends.example/profiles/42?source=email#details',
            initialUrl: '/fr',
            onNavigate: () => {},
            origin: 'https://dlp-friends.example',
        });

        expect(calls[0][2].page_referrer).toBe(
            'https://dlp-friends.example/profiles/{id}',
        );
    });

    test.each(['', 'not a valid URL'])(
        'omits an empty or invalid initial referrer: %s',
        async (initialReferrer) => {
            const calls = [];

            await initializeAnalytics(Promise.resolve(), {
                gtag: (...args) => calls.push(args),
                initialReferrer,
                initialUrl: '/en',
                onNavigate: () => {},
                origin: 'https://dlp-friends.example',
            });

            expect(calls[0][2]).not.toHaveProperty('page_referrer');
        },
    );

    test('does not subscribe or send a page view when GA4 is unavailable', async () => {
        let subscribed = false;

        await initializeAnalytics(Promise.resolve(), {
            gtag: undefined,
            initialReferrer: '',
            initialUrl: '/fr',
            onNavigate: () => {
                subscribed = true;
            },
            origin: 'https://dlp-friends.example',
        });

        expect(subscribed).toBe(false);
    });
});
