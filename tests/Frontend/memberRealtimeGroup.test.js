import { expect, mock, test } from 'bun:test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';

let receive;
const notices = [];
const page = {
    component: 'Conversations/Group',
    url: '/trips/42/chat',
    props: { trip: { id: 42 }, auth: { unread_notifications_count: 0 } },
};
mock.module('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { reload() {}, visit() {} },
}));
mock.module('@laravel/echo-vue', () => ({
    useEcho() {},
    useEchoNotification(_channel, callback) {
        receive = callback;
    },
}));
mock.module('vue-sonner', () => ({ toast: (...args) => notices.push(args) }));
mock.module('@/composables/useTranslations', () => ({
    useTranslations: () => ({ t: (key) => key }),
}));
const { useMemberRealtimeNotifications } =
    await import('../../resources/js/composables/useMemberRealtimeNotifications');

test('event message notification remains visible while a trip group is open', async () => {
    globalThis.window = { location: { origin: 'http://localhost' } };
    let context;
    await renderToString(
        createSSRApp({
            setup() {
                context = useMemberRealtimeNotifications(7);

                return () => null;
            },
        }),
    );
    expect(() =>
        receive({
            id: 'event-1',
            target_type: 'event_chat',
            target_id: 8,
            parameters: { sender: 'Alice', group: 'Sortie' },
        }),
    ).not.toThrow();
    expect(notices).toHaveLength(1);
    expect(context.unreadNotificationsCount.value).toBe(1);
});
