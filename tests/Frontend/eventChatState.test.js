import { describe, expect, test } from 'bun:test';
import {
    eventChatUnreadIncrement,
    mergeEventChatMessages,
} from '../../resources/js/lib/eventChatState';

describe('event chat state', () => {
    test('sorts and deduplicates HTTP and realtime messages by persistent id', () => {
        const messages = mergeEventChatMessages(
            [
                { id: 5, content: 'B' },
                { id: 4, content: 'A' },
            ],
            [
                { id: 5, content: 'B' },
                { id: 6, content: 'C' },
                { id: 3, content: 'Older' },
            ],
        );

        expect(messages.map(({ id }) => id)).toEqual([3, 4, 5, 6]);
    });

    test('increments unread only for another author while the chat is closed', () => {
        expect(eventChatUnreadIncrement({ author_user_id: 8 }, 7, false)).toBe(1);
        expect(eventChatUnreadIncrement({ author_user_id: 7 }, 7, false)).toBe(0);
        expect(eventChatUnreadIncrement({ author_user_id: 8 }, 7, true)).toBe(0);
    });
});
