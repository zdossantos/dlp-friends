import { expect, mock, test } from 'bun:test';
import { createRenderer, nextTick, ref } from 'vue';

const channels = new Map();
mock.module('@laravel/echo-vue', () => ({
    echo: () => ({
        private: (name) => {
            if (!channels.has(name)) {
                channels.set(name, {
                    listeners: new Set(),
                    listenForWhisper(_event, callback) {
                        this.listeners.add(callback);
                    },
                    stopListeningForWhisper(_event, callback) {
                        this.listeners.delete(callback);
                    },
                });
            }

            return channels.get(name);
        },
    }),
}));
const { useConversationListTyping } =
    await import('../../resources/js/composables/useTypingSignals');
const renderer = createRenderer({
    createComment: () => ({}),
    insert() {},
    remove() {},
    parentNode() {},
    nextSibling() {},
});

test('list typing follows additions and removals without removing another listener', async () => {
    const conversations = ref([{ id: 1, participant: { id: 11 } }]);
    let typing;
    const app = renderer.createApp({
        setup() {
            typing = useConversationListTyping(() => conversations.value);

            return () => null;
        },
    });
    app.mount({});
    const first = channels.get('conversation.1');
    expect(first).toBeDefined();
    const other = () => {};
    first.listeners.add(other);
    first.listeners.forEach((callback) =>
        callback({ user_id: 11, typing: true }),
    );
    expect(typing.value.has(1)).toBe(true);
    conversations.value.splice(0, 1, { id: 2, participant: { id: 22 } });
    await nextTick();
    expect(first.listeners.size).toBe(1);
    expect(typing.value.size).toBe(0);
    const second = channels.get('conversation.2');
    const listener = [...second.listeners][0];
    conversations.value = [{ id: 2, participant: { id: 22 } }];
    await nextTick();
    expect([...second.listeners]).toEqual([listener]);
    second.listeners.forEach((callback) =>
        callback({ user_id: 99, typing: true }),
    );
    expect(typing.value.size).toBe(0);
    second.listeners.forEach((callback) =>
        callback({ user_id: 22, typing: true }),
    );
    expect(typing.value.has(2)).toBe(true);
    conversations.value = [];
    await nextTick();
    expect(second.listeners.size).toBe(0);
    expect(typing.value.size).toBe(0);
    conversations.value = [{ id: 2, participant: { id: 22 } }];
    await nextTick();
    second.listeners.forEach((callback) =>
        callback({ user_id: 22, typing: true }),
    );
    app.unmount();
    expect(second.listeners.size).toBe(0);
    expect(typing.value.size).toBe(0);
});
