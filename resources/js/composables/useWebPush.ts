import { computed, onMounted, ref } from 'vue';
import { usePwa } from '@/composables/usePwa';
import { xsrfHeader } from '@/lib/csrf';

const busy = ref(false);
const error = ref<string>();
const permission = ref<NotificationPermission>(
    typeof Notification === 'undefined' ? 'default' : Notification.permission,
);
const subscribed = ref(false);

const applicationServerKey = (value: string): Uint8Array<ArrayBuffer> => {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const binary = atob(
        (value + padding).replace(/-/g, '+').replace(/_/g, '/'),
    );

    return Uint8Array.from(binary, (character) => character.charCodeAt(0));
};

const platformName = (): string => {
    if (/iPad|iPhone|iPod/.test(navigator.userAgent)) {
        return 'iOS';
    }

    if (/Android/.test(navigator.userAgent)) {
        return 'Android';
    }

    return 'Web';
};

export function useWebPush(vapidPublicKey: string) {
    const { isStandalone } = usePwa();
    const isIos = computed(
        () =>
            typeof navigator !== 'undefined' &&
            /iPad|iPhone|iPod/.test(navigator.userAgent),
    );
    const supported = computed(
        () =>
            typeof window !== 'undefined' &&
            'serviceWorker' in navigator &&
            'PushManager' in window &&
            'Notification' in window &&
            (!isIos.value || isStandalone.value) &&
            vapidPublicKey.length > 0,
    );

    onMounted(async () => {
        if (!supported.value) {
            subscribed.value = false;

            return;
        }

        const registration = await navigator.serviceWorker.getRegistration();
        subscribed.value = Boolean(
            await registration?.pushManager.getSubscription(),
        );
    });

    const enable = async (): Promise<boolean> => {
        if (!supported.value || permission.value === 'denied') {
            return false;
        }

        busy.value = true;
        error.value = undefined;

        try {
            permission.value = await Notification.requestPermission();

            if (permission.value !== 'granted') {
                return false;
            }

            const registration = await navigator.serviceWorker.ready;
            const subscription =
                (await registration.pushManager.getSubscription()) ??
                (await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: applicationServerKey(vapidPublicKey),
                }));
            const json = subscription.toJSON();
            const response = await fetch('/settings/notifications/devices', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...xsrfHeader(document.cookie),
                },
                body: JSON.stringify({
                    endpoint: json.endpoint,
                    keys: json.keys,
                    content_encoding: 'aes128gcm',
                    device_name: `${platformName()} · ${navigator.userAgent.includes('Safari') ? 'Safari' : 'Navigateur'}`,
                    platform: platformName(),
                }),
            });

            if (!response.ok) {
                throw new Error('subscription_sync_failed');
            }

            const device = (await response.json()) as { uuid: string };
            localStorage.setItem('web-push-device-uuid', device.uuid);
            subscribed.value = true;

            return true;
        } catch {
            error.value = 'subscription_failed';

            return false;
        } finally {
            busy.value = false;
        }
    };

    const disableCurrent = async (): Promise<void> => {
        busy.value = true;

        try {
            const registration = await navigator.serviceWorker.ready;
            await (
                await registration.pushManager.getSubscription()
            )?.unsubscribe();
            subscribed.value = false;
            const uuid = localStorage.getItem('web-push-device-uuid');

            if (uuid) {
                await fetch(
                    `/settings/notifications/devices/${encodeURIComponent(uuid)}`,
                    {
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: {
                            Accept: 'application/json',
                            ...xsrfHeader(document.cookie),
                        },
                    },
                );
                localStorage.removeItem('web-push-device-uuid');
            }
        } finally {
            busy.value = false;
        }
    };

    return {
        supported,
        subscribed,
        permission,
        busy,
        error,
        enable,
        disableCurrent,
    };
}
