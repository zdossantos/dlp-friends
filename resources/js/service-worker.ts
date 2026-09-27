/// <reference lib="webworker" />

import { clientsClaim, setCacheNameDetails } from 'workbox-core';
import {
    cleanupOutdatedCaches,
    matchPrecache,
    precacheAndRoute,
} from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';
import { parsePushPayload, safePushTarget } from '@/lib/pwa/pushPayload';

declare let self: ServiceWorkerGlobalScope & {
    __WB_MANIFEST: Array<{ revision: string | null; url: string }>;
};

setCacheNameDetails({ prefix: 'dlp-friends' });
precacheAndRoute(self.__WB_MANIFEST);
cleanupOutdatedCaches();
clientsClaim();

registerRoute(
    ({ request }) => request.mode === 'navigate',
    async ({ request }) => {
        try {
            return await fetch(request);
        } catch {
            return (await matchPrecache('/offline.html')) ?? Response.error();
        }
    },
);

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        void self.skipWaiting();
    }
});

self.addEventListener('push', (event) => {
    event.waitUntil(
        (async () => {
            let raw: unknown;

            try {
                raw = event.data?.json();
            } catch {
                raw = undefined;
            }

            const payload = parsePushPayload(
                raw,
                self.navigator.language.toLowerCase().startsWith('en')
                    ? 'en'
                    : 'fr',
            );
            await self.registration.showNotification(payload.title, {
                body: payload.body,
                icon: '/pwa/icon-192.png',
                badge: '/pwa/icon-192.png',
                tag: `dlp-friends-${payload.notificationId}`,
                data: { target: payload.target },
            });

            const registration =
                self.registration as ServiceWorkerRegistration & {
                    setAppBadge?: (count?: number) => Promise<void>;
                };

            if (registration.setAppBadge && payload.unreadCount !== undefined) {
                await registration
                    .setAppBadge(payload.unreadCount)
                    .catch(() => undefined);
            }
        })(),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = safePushTarget(
        (event.notification.data as { target?: unknown } | undefined)?.target,
    );
    event.waitUntil(
        (async () => {
            const windows = await self.clients.matchAll({
                type: 'window',
                includeUncontrolled: true,
            });
            const existing = windows.find((client) => 'focus' in client);

            if (existing && 'navigate' in existing) {
                await existing.navigate(target);
                await existing.focus();

                return;
            }

            await self.clients.openWindow(target);
        })(),
    );
});
