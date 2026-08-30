import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from '@/composables/useAppearance';
import { resolvePageLayout } from '@/layouts/resolvePageLayout';
import { initializeFlashToast } from '@/lib/flashToast';
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

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: resolvePageLayout,
    progress: {
        color: '#7138B6',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
