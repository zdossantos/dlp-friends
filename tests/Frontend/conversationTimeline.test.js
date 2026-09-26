import { describe, expect, test } from 'bun:test';
import {
    conversationDayKey,
    conversationDayLabel,
    shouldShowDaySeparator,
} from '../../resources/js/lib/conversationTimeline';

const labels = { today: 'Aujourd’hui', yesterday: 'Hier' };

describe('conversation timeline dates', () => {
    test('groups timestamps by the configured timezone rather than UTC', () => {
        expect(
            conversationDayKey(
                '2026-10-25T23:30:00Z',
                'fr',
                'Europe/Paris',
            ),
        ).toBe('2026-10-26');
    });

    test('keeps both sides of the daylight-saving fallback on the same local day', () => {
        expect(
            shouldShowDaySeparator(
                '2026-10-25T00:30:00Z',
                '2026-10-25T02:30:00Z',
                'fr',
                'Europe/Paris',
            ),
        ).toBe(false);
    });

    test('labels today yesterday and an older localized date', () => {
        const now = new Date('2026-09-24T12:00:00+02:00');

        expect(
            conversationDayLabel(
                '2026-09-24T08:00:00Z',
                now,
                'fr',
                'Europe/Paris',
                labels,
            ),
        ).toBe('Aujourd’hui');
        expect(
            conversationDayLabel(
                '2026-09-23T08:00:00Z',
                now,
                'fr',
                'Europe/Paris',
                labels,
            ),
        ).toBe('Hier');
        expect(
            conversationDayLabel(
                '2026-09-20T08:00:00Z',
                now,
                'fr',
                'Europe/Paris',
                labels,
            ),
        ).toContain('20');
    });
});
