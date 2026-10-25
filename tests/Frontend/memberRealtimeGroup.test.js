import { expect, mock, test } from 'bun:test';
import { createSSRApp } from 'vue';
import { renderToString } from 'vue/server-renderer';

let receive;
let receiveEvent;
const reloads = [];
const visits = [];
const remembered = [];
const notices = [];
const page = {
    component: 'Conversations/Group',
    url: '/trips/42/chat',
    props: {
        trip: { id: 42 },
        auth: { unread_notifications_count: 0 },
        i18n: {
            locale: 'fr',
            messages: {
                notifications: {
                    items: { new_group_message: ':sender · :group' },
                },
                conversations: { notification: { open: 'Ouvrir' } },
            },
        },
    },
};
const inertia = await import('@inertiajs/vue3');
mock.module('@inertiajs/vue3', () => ({
    ...inertia,
    usePage: () => page,
    router: {
        reload(options) {
            reloads.push(options);
        },
        visit(destination) {
            visits.push(destination);
        },
        remember(value, key) {
            remembered.push([value, key]);
        },
    },
}));
const echoVue = await import('@laravel/echo-vue');
mock.module('@laravel/echo-vue', () => ({
    ...echoVue,
    useEcho(_channel, _events, callback) {
        receiveEvent = callback;
    },
    useEchoNotification(_channel, callback) {
        receive = callback;
    },
}));
mock.module('vue-sonner', () => ({ toast: (...args) => notices.push(args) }));
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
    expect(notices[0][0]).toBe('Alice · Sortie');
    expect(context.unreadNotificationsCount.value).toBe(1);
});

test('a group access change reloads the conversation index only once', async () => {
    page.component = 'Conversations/Index';
    page.url = '/conversations?category=groups';
    reloads.length = 0;
    await renderToString(
        createSSRApp({
            setup() {
                useMemberRealtimeNotifications(7);

                return () => null;
            },
        }),
    );
    receiveEvent({
        group_chat_id: 9,
        context: 'trip',
        context_id: 42,
        access: 'granted',
    });
    expect(reloads).toEqual([{ only: ['groups'] }]);
});

test('group access changes refresh only the affected screen and clear a revoked draft', async () => {
    page.component = 'Conversations/Group';
    page.url = '/trips/42/chat';
    reloads.length = visits.length = remembered.length = 0;
    const removed = [];
    globalThis.sessionStorage = { removeItem: (key) => removed.push(key) };
    await renderToString(
        createSSRApp({
            setup() {
                useMemberRealtimeNotifications(7);

                return () => null;
            },
        }),
    );
    const change = {
        group_chat_id: 9,
        context: 'trip',
        context_id: 42,
        access: 'read_only',
    };
    receiveEvent({ ...change, context_id: 43 });
    expect(reloads).toEqual([]);
    receiveEvent(change);
    expect(reloads).toEqual([{ only: ['chat'] }]);
    receiveEvent({ ...change, access: 'revoked' });
    expect(remembered).toEqual([[{ content: '' }, 'group-chat-draft:9']]);
    expect(removed).toEqual(['7:group-chat-draft:9']);
    expect(visits).toEqual([
        { url: '/conversations?category=groups', method: 'get' },
    ]);
    reloads.length = 0;
    page.component = 'Events/Index';
    page.url = '/events?panel=8';
    receiveEvent({ ...change, context: 'event' });
    expect(reloads).toEqual([
        { only: ['organized', 'participating', 'panel'], preserveScroll: true },
    ]);
    page.component = 'settings/Account';
    page.url = '/settings/account';
    reloads.length = 0;
    receiveEvent(change);
    expect(reloads).toEqual([]);
});
