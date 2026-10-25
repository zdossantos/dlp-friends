import { expect, mock, test } from 'bun:test';
import { createRenderer, ref } from 'vue';
mock.module('../../resources/js/composables/usePwa', () => ({
    usePwa: () => ({ isStandalone: ref(true) }),
}));
const { useWebPush } =
    await import('../../resources/js/composables/useWebPush');
const renderer = createRenderer({
    createComment: () => ({}),
    insert() {},
    remove() {},
    parentNode() {},
    nextSibling() {},
});

function mountPush() {
    let push;
    const failures = [];
    const app = renderer.createApp({
        setup() {
            push = useWebPush('test-key', 1);

            return () => null;
        },
    });
    app.config.errorHandler = (error) => failures.push(error);
    app.mount({});

    return { app, push, failures };
}
function browser() {
    globalThis.window = {
        isSecureContext: true,
        PushManager: {},
        Notification: {},
    };
    globalThis.Notification = {
        permission: 'granted',
        requestPermission: async () => 'granted',
    };
    globalThis.document = { cookie: '' };
    globalThis.navigator = {
        userAgent: 'Test',
        serviceWorker: { getRegistration: async () => undefined },
    };
    globalThis.localStorage = {
        getItem: () => null,
        setItem() {},
        removeItem() {},
    };
}
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));

test('push initialization reports a network failure without rejecting its lifecycle hook', async () => {
    browser();
    navigator.serviceWorker.getRegistration = async () => {
        throw new Error('offline');
    };
    const { app, push, failures } = mountPush();
    await settle();
    expect(push.initialized.value).toBe(true);
    expect(push.error.value).toBe('subscription_failed');
    expect(failures).toEqual([]);
    app.unmount();
});

test('successful push initialization clears an earlier synchronization error', async () => {
    browser();
    navigator.serviceWorker.getRegistration = async () => ({
        pushManager: {
            getSubscription: async () => ({
                toJSON: () => ({
                    endpoint: 'https://push.example/device',
                    keys: {},
                }),
            }),
        },
    });
    globalThis.fetch = async () => ({ ok: false, status: 503 });
    const first = mountPush();
    await settle();
    expect(first.push.subscribed.value).toBe(false);
    expect(first.push.error.value).toBe('subscription_failed');
    first.app.unmount();

    globalThis.fetch = async () => ({
        ok: true,
        json: async () => ({ uuid: 'device-uuid' }),
    });
    const second = mountPush();
    await settle();
    expect(second.push.initialized.value).toBe(true);
    expect(second.push.subscribed.value).toBe(true);
    expect(second.push.error.value).toBeUndefined();
    expect(second.failures).toEqual([]);
    second.app.unmount();
});

test('push disable releases busy state when browser storage throws', async () => {
    browser();
    const { app, push } = mountPush();
    await settle();
    localStorage.setItem = () => {
        throw new Error('storage disabled');
    };
    await expect(push.disableCurrent()).resolves.toBeUndefined();
    expect(push.busy.value).toBe(false);
    expect(push.error.value).toBe('subscription_failed');
    app.unmount();
});

test('a failed server revocation remains retryable and is surfaced', async () => {
    browser();
    const { app, push } = mountPush();
    await settle();
    localStorage.getItem = () => 'device-uuid';
    const removed = [];
    localStorage.removeItem = (key) => removed.push(key);
    globalThis.fetch = async () => ({ ok: false, status: 503 });
    await push.disableCurrent();
    expect(push.error.value).toBe('subscription_failed');
    expect(removed).not.toContain('web-push-device-uuid');
    expect(push.busy.value).toBe(false);
    app.unmount();
});
