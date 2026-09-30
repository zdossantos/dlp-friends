import { createInertiaApp, router } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from '@/composables/useAppearance';
import {
    initializePwaLifecycle,
    observePwaRegistration,
} from '@/composables/usePwa';
import { initializeSeasonalTheme } from '@/composables/useSeasonalTheme';
import {
    resolvePageLayout,
    usesAdminLayout,
} from '@/layouts/resolvePageLayout';
import { initializeAnalytics } from '@/lib/analytics';
import { initializeFlashToast } from '@/lib/flashToast';
import { resolvePageTitle } from '@/lib/pageTitle';
import { resolveReverbHost } from '@/lib/reverbHost';

const configuredReverbHost = import.meta.env.VITE_REVERB_HOST;

configureEcho(
    import.meta.env.VITE_REVERB_APP_KEY
        ? {
              broadcaster: 'reverb',
              wsHost: resolveReverbHost(
                  configuredReverbHost,
                  typeof window === 'undefined'
                      ? configuredReverbHost
                      : window.location.hostname,
              ),
          }
        : { broadcaster: 'null' },
);

const appName = import.meta.env.VITE_APP_NAME;

const inertiaReady = createInertiaApp({
    title: (title) => resolvePageTitle(title, appName),
    layout: resolvePageLayout,
    progress: {
        color: '#7138B6',
    },
});

void initializePwaLifecycle();

if (
    'serviceWorker' in navigator &&
    (import.meta.env.PROD || import.meta.env.VITE_PWA_E2E === 'true')
) {
    void inertiaReady.then(() =>
        navigator.serviceWorker
            .register('/service-worker.js', { scope: '/' })
            .then(observePwaRegistration)
            .catch(() => undefined),
    );
}

void initializeAnalytics(inertiaReady);

router.on('navigate', (event) => {
    document.documentElement.classList.toggle(
        'app-viewport',
        !usesAdminLayout(event.detail.page.component),
    );
});

// This will set light / dark mode on page load...
initializeTheme();
initializeSeasonalTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
