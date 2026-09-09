import { expect, test } from 'bun:test';
import {
    availableEventActions,
    capacityLabel,
    parisLocalFormValue,
} from '../../resources/js/lib/eventState';

test('event actions follow role, registration and lifecycle state', () => {
    expect(
        availableEventActions({
            role: 'member',
            status: 'accepted',
            started: false,
            cancelled: false,
        }),
    ).toEqual(['withdraw']);
    expect(
        availableEventActions({
            role: 'member',
            status: 'pending',
            started: true,
            cancelled: false,
        }),
    ).toEqual([]);
    expect(
        availableEventActions({
            role: 'organizer',
            status: null,
            started: false,
            cancelled: false,
        }),
    ).toEqual(['edit', 'cancel']);
    expect(
        availableEventActions({
            role: 'member',
            status: null,
            started: false,
            cancelled: false,
            full: true,
        }),
    ).toEqual([]);
});

test('capacity labels and Paris-local form values are deterministic', () => {
    expect(capacityLabel(2, 6)).toEqual({ occupied: 2, available: 4 });
    expect(parisLocalFormValue('2026-10-10T08:30:00+00:00')).toBe(
        '2026-10-10T10:30',
    );
});
