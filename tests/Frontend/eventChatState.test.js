import { describe, expect, test } from 'bun:test';
import {
    groupChatUnreadIncrement,
    mergeGroupChatMessages,
} from '../../resources/js/lib/groupChatState';

describe('event chat state', () => {
    test('sorts and deduplicates HTTP and realtime messages by persistent id', () => {
        const messages = mergeGroupChatMessages(
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
        expect(groupChatUnreadIncrement({ author_user_id: 8 }, 7, false)).toBe(1);
        expect(groupChatUnreadIncrement({ author_user_id: 7 }, 7, false)).toBe(0);
        expect(groupChatUnreadIncrement({ author_user_id: 8 }, 7, true)).toBe(0);
    });
});
