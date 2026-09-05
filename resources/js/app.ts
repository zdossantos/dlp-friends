import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from '@/composables/useAppearance';
import { resolvePageLayout } from '@/layouts/resolvePageLayout';
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

void initializeAnalytics(inertiaReady);

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
