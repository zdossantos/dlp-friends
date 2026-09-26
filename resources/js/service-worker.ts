/// <reference lib="webworker" />

import { clientsClaim, setCacheNameDetails } from 'workbox-core';
import {
    cleanupOutdatedCaches,
    matchPrecache,
    precacheAndRoute,
} from 'workbox-precaching';
import { registerRoute } from 'workbox-routing';

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
