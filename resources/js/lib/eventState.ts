export type EventAction = 'register' | 'withdraw' | 'edit' | 'cancel';

export function availableEventActions(state: {
    role: 'organizer' | 'member';
    status: string | null;
    started: boolean;
    cancelled: boolean;
}): EventAction[] {
    if (state.started || state.cancelled) {
        return [];
    }

    if (state.role === 'organizer') {
        return ['edit', 'cancel'];
    }

    if (state.status === 'pending' || state.status === 'accepted') {
        return ['withdraw'];
    }

    if (state.status === null || state.status === 'withdrawn') {
        return ['register'];
    }

    return [];
}

export function capacityLabel(
    occupied: number,
    capacity: number,
): { occupied: number; available: number } {
    return { occupied, available: Math.max(0, capacity - occupied) };
}

export function parisLocalFormValue(value: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Europe/Paris',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}
