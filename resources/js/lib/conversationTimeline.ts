export type ConversationDayLabels = {
    today: string;
    yesterday: string;
};

function dateParts(
    value: string | Date,
    locale: string,
    timeZone: string,
): { year: string; month: string; day: string } {
    const parts = new Intl.DateTimeFormat(locale, {
        timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(typeof value === 'string' ? new Date(value) : value);
    const part = (type: Intl.DateTimeFormatPartTypes): string =>
        parts.find((item) => item.type === type)?.value ?? '';

    return { year: part('year'), month: part('month'), day: part('day') };
}

export function conversationDayKey(
    iso: string,
    locale: string,
    timeZone: string,
): string {
    const { year, month, day } = dateParts(iso, locale, timeZone);

    return `${year}-${month}-${day}`;
}

export function shouldShowDaySeparator(
    previous: string | null,
    current: string,
    locale: string,
    timeZone: string,
): boolean {
    return (
        previous === null ||
        conversationDayKey(previous, locale, timeZone) !==
            conversationDayKey(current, locale, timeZone)
    );
}

function previousCalendarDayKey(key: string): string {
    const [year, month, day] = key.split('-').map(Number);
    const previous = new Date(Date.UTC(year, month - 1, day - 1));

    return `${previous.getUTCFullYear()}-${String(previous.getUTCMonth() + 1).padStart(2, '0')}-${String(previous.getUTCDate()).padStart(2, '0')}`;
}

export function conversationDayLabel(
    iso: string,
    now: Date,
    locale: string,
    timeZone: string,
    labels: ConversationDayLabels,
): string {
    const key = conversationDayKey(iso, locale, timeZone);
    const todayKey = conversationDayKey(now.toISOString(), locale, timeZone);

    if (key === todayKey) {
        return labels.today;
    }

    if (key === previousCalendarDayKey(todayKey)) {
        return labels.yesterday;
    }

    return new Intl.DateTimeFormat(locale, {
        timeZone,
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    }).format(new Date(iso));
}
