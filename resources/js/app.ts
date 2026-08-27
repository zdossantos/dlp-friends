import { createInertiaApp } from '@inertiajs/vue3';
import { configureEcho } from '@laravel/echo-vue';
import { initializeTheme } from '@/composables/useAppearance';
import { resolvePageLayout } from '@/layouts/resolvePageLayout';
import { initializeFlashToast } from '@/lib/flashToast';
import { initializeDomTranslations } from '@/lib/translateDom';

configureEcho(
    import.meta.env.VITE_REVERB_APP_KEY
        ? { broadcaster: 'reverb' }
        : { broadcaster: 'null' },
);

const appName = import.meta.env.VITE_APP_NAME || 'DLP Friends';

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
initializeDomTranslations();
