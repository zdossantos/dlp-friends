import { describe, expect, test } from 'bun:test';
import {
    activeConversationId,
    registerNotification,
    selectMatchNotification,
    shouldShowMessageToast,
} from '../../resources/js/lib/memberNotifications';

describe('member realtime notifications', () => {
    test('recognizes only an open conversation page', () => {
        expect(activeConversationId('/conversations/42')).toBe(42);
        expect(activeConversationId('/conversations/42?from=toast')).toBe(42);
        expect(activeConversationId('/conversations')).toBeNull();
        expect(activeConversationId('/members/42')).toBeNull();
    });

    test('shows a message toast only for another member outside its conversation', () => {
        const incoming = {
            id: 9,
            conversation_id: 42,
            author_user_id: 8,
        };

        expect(shouldShowMessageToast(incoming, 7, '/discover')).toBe(true);
        expect(shouldShowMessageToast(incoming, 8, '/discover')).toBe(false);
        expect(shouldShowMessageToast(incoming, 7, '/conversations/42')).toBe(
            false,
        );
    });

    test('keeps one presentation when the page and realtime announce the same match', () => {
        const realtimeMatch = {
            match_id: 12,
            conversation_id: 34,
            member: { id: 56 },
        };
        const pageMatch = {
            match_id: 12,
            conversation_id: 34,
            member: { id: 56 },
        };

        expect(selectMatchNotification(realtimeMatch, pageMatch)).toBe(
            realtimeMatch,
        );
    });

    test('counts a persistent notification UUID only once', () => {
        const seen = new Set();

        expect(registerNotification(seen, { id: 'notification-1' })).toBe(true);
        expect(registerNotification(seen, { id: 'notification-1' })).toBe(
            false,
        );
        expect(registerNotification(seen, { id: 'notification-2' })).toBe(true);
    });
});
