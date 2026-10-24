import { expect, test } from 'bun:test';
import { mergeChatMessages } from '../../resources/js/lib/conversationState';
import {
    resolveGroupChatDraft,
} from '../../resources/js/lib/groupChatState';

test('group timeline deduplicates HTTP, realtime and older-page deliveries', () => {
    const message = { id: 42, group_chat_id: 7, author_user_id: 8 };
    expect(mergeChatMessages([message], [message])).toEqual([message]);
    expect(
        mergeChatMessages(
            [message],
            [
                { ...message, id: 1 },
                { ...message, content: 'fresh' },
            ],
        ),
    ).toEqual([
        { ...message, id: 1 },
        { ...message, content: 'fresh' },
    ]);
});
test('group draft migrates a legacy event draft and survives a reload', () => {
    expect(
        resolveGroupChatDraft(undefined, { content: 'ancien' }, null),
    ).toEqual({ content: 'ancien' });
    expect(resolveGroupChatDraft(undefined, undefined, 'rechargé')).toEqual({
        content: 'rechargé',
    });
    expect(
        resolveGroupChatDraft({ content: '' }, { content: 'ancien' }, 'ancien'),
    ).toEqual({ content: '' });
});
