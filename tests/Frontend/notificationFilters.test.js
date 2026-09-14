import { expect, test } from 'bun:test';
import { applyNotificationFilters } from '../../resources/js/lib/notificationFilters';

test('notification filters include only active values', () => {
    expect(applyNotificationFilters('events', true).toString()).toBe(
        'category=events&unread=1',
    );
    expect(applyNotificationFilters('conversations', false).toString()).toBe(
        'category=conversations',
    );
    expect(applyNotificationFilters(null, false).toString()).toBe('');
});
