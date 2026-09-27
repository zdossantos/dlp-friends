import { computed, readonly, ref } from 'vue';
import {
    createSingleReloadHandler,
    detectPwaState,
} from '@/lib/pwa/capabilities';

type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Navigator {
        standalone?: boolean;
    }
}

const installPrompt = ref<BeforeInstallPromptEvent>();
const installed = ref(false);
const waitingWorker = ref<ServiceWorker>();
let initialized = false;
let updateActivationRequested = false;

const standalone = (): boolean =>
    window.matchMedia('(display-mode: standalone)').matches ||
    navigator.standalone === true;

const observeRegistration = (registration: ServiceWorkerRegistration): void => {
    if (registration.waiting) {
        waitingWorker.value = registration.waiting;
    }

    registration.addEventListener('updatefound', () => {
        const worker = registration.installing;

        worker?.addEventListener('statechange', () => {
            if (
                worker.state === 'installed' &&
                navigator.serviceWorker.controller
            ) {
                waitingWorker.value = worker;
            }
        });
    });
};

export const initializePwaLifecycle = async (): Promise<void> => {
    if (initialized || typeof window === 'undefined') {
        return;
    }

    initialized = true;
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        installPrompt.value = event as BeforeInstallPromptEvent;
    });
    window.addEventListener('appinstalled', () => {
        installed.value = true;
        installPrompt.value = undefined;
    });

    if ('serviceWorker' in navigator) {
        const reloadOnce = createSingleReloadHandler(() =>
            window.location.reload(),
        );
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!updateActivationRequested) {
                return;
            }

            reloadOnce();
        });

        const registration = await navigator.serviceWorker
            .getRegistration('/')
            .catch(() => undefined);

        if (registration) {
            observeRegistration(registration);
        }
    }
};

export const observePwaRegistration = (
    registration: ServiceWorkerRegistration,
): void => observeRegistration(registration);

export function usePwa() {
    const state = computed(() =>
        detectPwaState({
            standaloneMedia:
                typeof window !== 'undefined' &&
                window.matchMedia('(display-mode: standalone)').matches,
            iosStandalone:
                typeof navigator !== 'undefined' &&
                navigator.standalone === true,
            installed: installed.value,
            installPromptAvailable: installPrompt.value !== undefined,
            serviceWorkerSupported:
                typeof navigator !== 'undefined' &&
                'serviceWorker' in navigator,
        }),
    );

    const install = async (): Promise<
        'accepted' | 'dismissed' | 'unavailable'
    > => {
        const prompt = installPrompt.value;

        if (!prompt) {
            return 'unavailable';
        }

        await prompt.prompt();
        const { outcome } = await prompt.userChoice;
        installPrompt.value = undefined;

        return outcome;
    };

    const activateWaitingWorker = (): void => {
        if (waitingWorker.value) {
            updateActivationRequested = true;
            waitingWorker.value.postMessage({ type: 'SKIP_WAITING' });
        }
    };

    return {
        state,
        isStandalone: computed(standalone),
        canInstall: computed(() => installPrompt.value !== undefined),
        updateAvailable: computed(() => waitingWorker.value !== undefined),
        waitingWorker: readonly(waitingWorker),
        install,
        activateWaitingWorker,
    };
}
