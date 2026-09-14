import { describe, expect, test } from 'bun:test';
import {
    eventChatAccessReloadOptions,
    isEventWorkspaceUrl,
} from '../../resources/js/lib/eventChatAccess';

describe('event chat access updates', () => {
    test('reloads only event workspace data while preserving scroll', () => {
        expect(
            eventChatAccessReloadOptions({ event_id: 12, access: 'revoked' }),
        ).toEqual({
            only: ['organized', 'participating', 'panel'],
            preserveScroll: true,
        });
    });

    test('recognizes event workspace URLs only', () => {
        expect(isEventWorkspaceUrl('/events')).toBe(true);
        expect(isEventWorkspaceUrl('/events/mine?panel=1')).toBe(true);
        expect(isEventWorkspaceUrl('/events/12/chat')).toBe(true);
        expect(isEventWorkspaceUrl('/conversations')).toBe(false);
    });
});
