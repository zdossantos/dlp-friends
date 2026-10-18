import { describe, expect, test } from 'bun:test';
import { initializeIosViewportRecovery } from '../../resources/js/lib/pwa/viewport';

function runtime({ ios = true, standalone = true } = {}) {
    const window = new EventTarget();
    const document = new EventTarget();
    const frames = [];
    const viewport = {
        content: 'width=device-width, initial-scale=1, viewport-fit=cover',
    };
    Object.assign(window, {
        navigator: { userAgent: ios ? 'iPhone' : 'Android', standalone },
        matchMedia: () => ({ matches: standalone }),
        requestAnimationFrame: (callback) => frames.push(callback),
    });
    Object.assign(document, {
        visibilityState: 'visible',
        querySelector: () => viewport,
    });

    return { window, document, viewport, frames };
}

describe('iOS standalone viewport recovery', () => {
    test.each(['focus', 'pageshow', 'visibilitychange'])(
        'reapplies the viewport after %s without reloading or restricting zoom',
        (event) => {
            const { window, document, viewport, frames } = runtime();
            initializeIosViewportRecovery(window, document);
            const original = viewport.content;
            (event === 'visibilitychange' ? document : window).dispatchEvent(
                new Event(event),
            );
            expect(viewport.content).not.toBe(original);
            expect(frames).toHaveLength(1);
            frames.shift()();
            expect(viewport.content).toBe(original);
        },
    );

    test('ignores backgrounding and coalesces overlapping resume events', () => {
        const { window, document, viewport, frames } = runtime();
        initializeIosViewportRecovery(window, document);
        document.visibilityState = 'hidden';
        document.dispatchEvent(new Event('visibilitychange'));
        expect(frames).toHaveLength(0);
        document.visibilityState = 'visible';
        window.dispatchEvent(new Event('focus'));
        window.dispatchEvent(new Event('pageshow'));
        expect(frames).toHaveLength(1);
        frames.shift()();
        expect(viewport.content).toBe(
            'width=device-width, initial-scale=1, viewport-fit=cover',
        );
    });

    test.each([{ ios: false }, { standalone: false }])(
        'leaves other browsers untouched: %j',
        (options) => {
            const { window, document, viewport, frames } = runtime(options);
            initializeIosViewportRecovery(window, document);
            window.dispatchEvent(new Event('focus'));
            expect(frames).toHaveLength(0);
            expect(viewport.content).toBe(
                'width=device-width, initial-scale=1, viewport-fit=cover',
            );
        },
    );
});
