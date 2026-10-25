export function resolveGroupChatDraft(
    current: unknown,
    legacy: unknown,
    stored: string | null,
): { content: string } {
    for (const candidate of [
        current,
        stored === null ? undefined : { content: stored },
        legacy,
    ]) {
        if (
            candidate !== null &&
            typeof candidate === 'object' &&
            'content' in candidate &&
            typeof candidate.content === 'string'
        ) {
            return { content: candidate.content };
        }
    }

    return { content: '' };
}
