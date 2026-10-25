import { afterEach, describe, expect, test } from 'bun:test';

const originalWindow = globalThis.window;
const originalStorage = globalThis.sessionStorage;

afterEach(() => {
    globalThis.window = originalWindow;
    globalThis.sessionStorage = originalStorage;
});

async function workspace(storage) {
    const positions = [];
    globalThis.window = {
        scrollY: 420,
        scrollTo: (position) => positions.push(position.top),
    };
    globalThis.sessionStorage = storage;
    const module = await import(
        `../../resources/js/lib/eventWorkspaceScroll.ts?test=${crypto.randomUUID()}`
    );

    return { ...module, positions };
}

describe('event workspace restoration', () => {
    test('restores position when optional browser storage is refused', async () => {
        const denied = () => {
            throw new DOMException('Storage denied', 'SecurityError');
        };
        const scroll = await workspace({
            getItem: denied,
            setItem: denied,
            removeItem: denied,
        });

        expect(() =>
            scroll.rememberEventWorkspaceScroll('event-link-12'),
        ).not.toThrow();
        expect(scroll.eventWorkspaceOpener()).toBe('event-link-12');
        scroll.restoreEventWorkspaceScroll();
        expect(scroll.positions).toEqual([420]);
        expect(() => scroll.forgetEventWorkspaceScroll()).not.toThrow();
        expect(scroll.eventWorkspaceOpener()).toBeNull();
        scroll.restoreEventWorkspaceScroll();
        expect(scroll.positions).toEqual([420]);
    });

    test('restores a saved position and ignores malformed stored positions', async () => {
        for (const [stored, expected] of [
            ['180', [180]],
            ['not a position', []],
            [null, []],
        ]) {
            const scroll = await workspace({ getItem: () => stored });
            scroll.restoreEventWorkspaceScroll();
            expect(scroll.positions).toEqual(expected);
        }
    });

    test('accepts only existing event opener identifiers from storage', async () => {
        for (const [stored, expected] of [
            ['event-link-12', 'event-link-12'],
            ['event-create', 'event-create'],
            ['unknown"]', null],
            [null, null],
        ]) {
            const scroll = await workspace({ getItem: () => stored });
            expect(scroll.eventWorkspaceOpener()).toBe(expected);
        }
    });
});
