import { describe, expect, test } from 'bun:test';
import { mergeChatMessages } from '../../resources/js/lib/conversationState';

describe('event chat state', () => {
    test('sorts and deduplicates HTTP and realtime messages by persistent id', () => {
        const messages = mergeChatMessages(
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
});
