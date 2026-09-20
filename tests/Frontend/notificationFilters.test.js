import { expect, test } from 'bun:test';
import {
    applyNotificationFilters,
    notificationCategories,
} from '../../resources/js/lib/notificationFilters';

test('notification filters include only active values', () => {
    expect(applyNotificationFilters('events', true).toString()).toBe(
        'category=events&unread=1',
    );
    expect(applyNotificationFilters('conversations', false).toString()).toBe(
        'category=conversations',
    );
    expect(applyNotificationFilters(null, false).toString()).toBe('');
});

test('notification categories expose partner announcements', () => {
    expect(notificationCategories).toEqual([
        'conversations',
        'events',
        'partners',
    ]);
    expect(applyNotificationFilters('partners', true).toString()).toBe(
        'category=partners&unread=1',
    );
});
