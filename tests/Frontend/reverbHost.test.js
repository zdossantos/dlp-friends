import { describe, expect, test } from 'bun:test';
import { resolveReverbHost } from '../../resources/js/lib/reverbHost';

describe('resolveReverbHost', () => {
    test.each([
        ['192.168.1.23', '192.168.1.23'],
        ['localhost', 'localhost'],
        ['dlp-friends.fr', 'dlp-friends.fr'],
    ])('uses the page host %s for Reverb', (pageHost, expectedHost) => {
        expect(resolveReverbHost(pageHost)).toBe(expectedHost);
    });
});
