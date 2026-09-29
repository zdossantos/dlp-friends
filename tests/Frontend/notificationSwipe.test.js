import { describe, expect, test } from 'bun:test';
import {
    notificationSwipeTransition,
    resolveNotificationSwipe,
    shouldCloseNotificationSwipe,
} from '../../resources/js/lib/notificationSwipe';

describe('notification swipe', () => {
    test('opens after an intentional horizontal drag beyond the threshold', () => {
        expect(
            resolveNotificationSwipe(
                { x: 180, y: 100 },
                { x: 100, y: 104 },
                120,
            ),
        ).toEqual({ axis: 'horizontal', offset: -80, open: true });
    });

    test('snaps back when a horizontal drag stays below the reveal threshold', () => {
        expect(
            resolveNotificationSwipe(
                { x: 180, y: 100 },
                { x: 150, y: 102 },
                120,
            ),
        ).toEqual({ axis: 'horizontal', offset: -30, open: false });
    });

    test('leaves vertical scrolling untouched', () => {
        expect(
            resolveNotificationSwipe(
                { x: 180, y: 100 },
                { x: 174, y: 145 },
                120,
            ),
        ).toEqual({ axis: 'vertical', offset: 0, open: false });
    });

    test('bounds translation to the action width', () => {
        expect(
            resolveNotificationSwipe(
                { x: 280, y: 100 },
                { x: 20, y: 101 },
                120,
            ).offset,
        ).toBe(-120);
    });

    test('Escape closes an open row while other keys do not', () => {
        expect(shouldCloseNotificationSwipe('Escape')).toBe(true);
        expect(shouldCloseNotificationSwipe('Enter')).toBe(false);
    });

    test('removes nonessential transition when reduced motion is requested', () => {
        expect(notificationSwipeTransition(true)).toBe('none');
        expect(notificationSwipeTransition(false)).toBe(
            'transform 180ms ease-out',
        );
    });
});
