import { expect, test } from 'bun:test';
import { mergeGroupChatMessages, groupChatUnreadIncrement, resolveGroupChatDraft } from '../../resources/js/lib/groupChatState';

test('group timeline deduplicates HTTP, realtime and older-page deliveries', () => {
    const message = { id: 42, group_chat_id: 7, author_user_id: 8 };
    expect(mergeGroupChatMessages([message], [message])).toEqual([message]);
    expect(mergeGroupChatMessages([message], [{ ...message, id: 1 }, { ...message, content: 'fresh' }])).toEqual([{ ...message, id: 1 }, { ...message, content: 'fresh' }]);
    expect(groupChatUnreadIncrement(message, 8, false)).toBe(0);
    expect(groupChatUnreadIncrement(message, 9, true)).toBe(0);
    expect(groupChatUnreadIncrement(message, 9, false)).toBe(1);
});
test('group draft migrates a legacy event draft and survives a reload', () => {
    expect(resolveGroupChatDraft(undefined, {content:'ancien'}, null)).toEqual({content:'ancien'});
    expect(resolveGroupChatDraft(undefined, undefined, 'rechargé')).toEqual({content:'rechargé'});
    expect(resolveGroupChatDraft({content:''}, {content:'ancien'}, 'ancien')).toEqual({content:''});
});
