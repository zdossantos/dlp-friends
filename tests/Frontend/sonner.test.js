import { describe, expect, test } from 'bun:test';
import { resolveToasterPosition } from '../../resources/js/components/ui/sonner/position';

describe('responsive toaster position', () => {
    test('uses the top center on mobile while preserving the desktop default', () => {
        expect(resolveToasterPosition(true)).toBe('top-center');
        expect(resolveToasterPosition(false)).toBe('bottom-right');
    });

    test('preserves an explicit desktop position', () => {
        expect(resolveToasterPosition(false, 'top-left')).toBe('top-left');
    });
});
