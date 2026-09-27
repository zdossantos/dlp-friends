import { computed, onMounted, ref } from 'vue';
import { usePwa } from '@/composables/usePwa';
import { xsrfHeader } from '@/lib/csrf';
import { isIosDevice } from '@/lib/pwa/capabilities';

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
    if (isIosDevice(navigator)) {
        return 'iOS';
    }

    if (/Android/.test(navigator.userAgent)) {
        return 'Android';
    }

    return 'Web';
};

export function useWebPush(vapidPublicKey: string, currentUserId?: number) {
    const initialized = ref(false);
    const { isStandalone } = usePwa();
    const isIos = computed(
        () => typeof navigator !== 'undefined' && isIosDevice(navigator),
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

    const revokeStoredDevice = async (): Promise<void> => {
        const uuid = localStorage.getItem('web-push-device-uuid');

        if (!uuid) {
            return;
        }

        const response = await fetch(
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

        if (response.ok || response.status === 404) {
            localStorage.removeItem('web-push-device-uuid');
        }
    };

    const syncSubscription = async (
        subscription: PushSubscription,
    ): Promise<boolean> => {
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
                device_name: `${platformName()} · Browser`,
                platform: platformName(),
            }),
        });

        if (!response.ok) {
            return false;
        }

        const device = (await response.json()) as { uuid: string };
        localStorage.setItem('web-push-device-uuid', device.uuid);

        if (currentUserId !== undefined) {
            localStorage.setItem('web-push-user-id', String(currentUserId));
        }

        localStorage.removeItem('web-push-opted-out');

        return true;
    };

    onMounted(async () => {
        try {
            if (!supported.value) {
                subscribed.value = false;

                return;
            }

            const registration =
                (await navigator.serviceWorker.getRegistration()) ??
                (permission.value === 'granted'
                    ? await navigator.serviceWorker.ready
                    : undefined);
            const subscription =
                await registration?.pushManager.getSubscription();
            const storedUserId = localStorage.getItem('web-push-user-id');

            if (
                subscription &&
                currentUserId !== undefined &&
                storedUserId !== null &&
                storedUserId !== String(currentUserId)
            ) {
                await subscription.unsubscribe();
                localStorage.removeItem('web-push-device-uuid');
                localStorage.removeItem('web-push-user-id');
                subscribed.value = false;

                return;
            }

            if (localStorage.getItem('web-push-opted-out') === 'true') {
                await subscription?.unsubscribe();
                await revokeStoredDevice();
                subscribed.value = false;

                return;
            }

            if (!subscription) {
                subscribed.value = false;
                await revokeStoredDevice();

                return;
            }

            subscribed.value = await syncSubscription(subscription);
        } finally {
            initialized.value = true;
        }
    });

    const enable = async (): Promise<boolean> => {
        if (!supported.value || permission.value === 'denied') {
            return false;
        }

        busy.value = true;
        error.value = undefined;

        try {
            localStorage.removeItem('web-push-opted-out');
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

            if (!(await syncSubscription(subscription))) {
                throw new Error('subscription_sync_failed');
            }

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
        localStorage.setItem('web-push-opted-out', 'true');

        try {
            const registration =
                'serviceWorker' in navigator
                    ? await navigator.serviceWorker.getRegistration()
                    : undefined;
            await (
                await registration?.pushManager.getSubscription()
            )?.unsubscribe();
            subscribed.value = false;
            await revokeStoredDevice();
            localStorage.removeItem('web-push-user-id');
        } catch {
            error.value = 'subscription_failed';
        } finally {
            busy.value = false;
        }
    };

    return {
        supported,
        initialized,
        subscribed,
        permission,
        busy,
        error,
        enable,
        disableCurrent,
    };
}
