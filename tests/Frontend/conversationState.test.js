import { describe, expect, test } from 'bun:test';
import {
    applyConversationMessage,
    applyReadReceipt,
    conversationPreview,
} from '../../resources/js/lib/conversationState';

const message = (overrides = {}) => ({
    id: 10,
    conversation_id: 2,
    author_user_id: 8,
    content: 'Bonjour',
    read_at: null,
    created_at: '2026-08-28T20:00:00.000Z',
    ...overrides,
});

const summary = (overrides = {}) => ({
    id: 2,
    participant: { id: 8, display_name: 'Basile', avatar: {} },
    archived_at: null,
    latest_message: null,
    unread_count: 0,
    activity_at: '2026-08-28T18:00:00.000Z',
    ...overrides,
});

describe('conversation list state', () => {
    test('an incoming message updates its preview, increments unread and moves it first', () => {
        const conversations = [summary({ id: 1 }), summary()];

        const updated = applyConversationMessage(
            conversations,
            message(),
            7,
        );

        expect(updated.map(({ id }) => id)).toEqual([2, 1]);
        expect(updated[0].latest_message).toEqual(message());
        expect(updated[0].unread_count).toBe(1);
    });

    test('an outgoing message updates its preview without becoming unread', () => {
        const updated = applyConversationMessage(
            [summary()],
            message({ author_user_id: 7 }),
            7,
        );

        expect(updated[0].unread_count).toBe(0);
        expect(conversationPreview(updated[0], 7)).toBe('Vous : Bonjour');
    });

    test('a duplicate realtime message does not increment unread twice', () => {
        const once = applyConversationMessage([summary()], message(), 7);
        const twice = applyConversationMessage(once, message(), 7);

        expect(twice[0].unread_count).toBe(1);
    });

    test('a delayed older event does not replace the latest preview or increment unread', () => {
        const latest = message({ id: 12, content: 'Le plus récent' });
        const conversations = applyConversationMessage(
            [summary()],
            latest,
            7,
        );

        const updated = applyConversationMessage(
            conversations,
            message({ id: 11, content: 'En retard' }),
            7,
        );

        expect(updated[0].latest_message).toEqual(latest);
        expect(updated[0].unread_count).toBe(1);
    });

    test('messages created in the same second are ordered by their identifier', () => {
        const conversations = [
            summary({
                id: 1,
                latest_message: message({ id: 10, conversation_id: 1 }),
                activity_at: '2026-08-28T20:00:00.000Z',
            }),
            summary({ id: 2 }),
        ];

        const updated = applyConversationMessage(
            conversations,
            message({ id: 11, conversation_id: 2 }),
            7,
        );

        expect(updated.map(({ id }) => id)).toEqual([2, 1]);
    });
});

describe('read receipt state', () => {
    test('marks only the current member messages through the receipt boundary', () => {
        const messages = [
            message({ id: 1, author_user_id: 7 }),
            message({ id: 2, author_user_id: 8 }),
            message({ id: 3, author_user_id: 7 }),
            message({ id: 4, author_user_id: 7 }),
        ];

        const updated = applyReadReceipt(messages, {
            conversation_id: 2,
            reader_user_id: 8,
            last_read_message_id: 3,
            read_at: '2026-08-28T20:01:00.000Z',
        }, 7);

        expect(updated.map(({ read_at }) => read_at)).toEqual([
            '2026-08-28T20:01:00.000Z',
            null,
            '2026-08-28T20:01:00.000Z',
            null,
        ]);
    });
});
