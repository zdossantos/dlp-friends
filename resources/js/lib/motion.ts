export function prefersReducedMotion(
    media?: Pick<MediaQueryList, 'matches'>,
): boolean {
    if (media) {
        return media.matches;
    }

    return typeof window !== 'undefined' &&
        typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)').matches
        : false;
}
