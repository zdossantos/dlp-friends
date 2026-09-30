export type SwipePoint = { x: number; y: number };

export type NotificationSwipeResolution = {
    axis: 'horizontal' | 'vertical' | 'pending';
    offset: number;
    open: boolean;
};

const intentThreshold = 12;

export function resolveNotificationSwipe(
    start: SwipePoint,
    current: SwipePoint,
    actionWidth: number,
): NotificationSwipeResolution {
    const deltaX = current.x - start.x;
    const deltaY = current.y - start.y;

    if (
        Math.abs(deltaX) < intentThreshold &&
        Math.abs(deltaY) < intentThreshold
    ) {
        return { axis: 'pending', offset: 0, open: false };
    }

    if (Math.abs(deltaY) > Math.abs(deltaX)) {
        return { axis: 'vertical', offset: 0, open: false };
    }

    const offset = Math.max(-actionWidth, Math.min(0, deltaX));

    return {
        axis: 'horizontal',
        offset,
        open: Math.abs(offset) >= actionWidth * 0.4,
    };
}

export function shouldCloseNotificationSwipe(key: string): boolean {
    return key === 'Escape';
}

export function notificationSwipeTransition(reducedMotion: boolean): string {
    return reducedMotion ? 'none' : 'transform 180ms ease-out';
}
