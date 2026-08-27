import { describe, expect, test } from 'bun:test';
import { resolveReverbHost } from '../../resources/js/lib/reverbHost';

describe('resolveReverbHost', () => {
    test.each([
        ['localhost', '192.168.1.23', '192.168.1.23'],
        ['127.0.0.1', '192.168.1.23', '192.168.1.23'],
        ['localhost', 'localhost', 'localhost'],
        ['realtime.example.com', 'app.example.com', 'realtime.example.com'],
    ])(
        'resolves configured host %s from page host %s to %s',
        (configuredHost, pageHost, expectedHost) => {
            expect(resolveReverbHost(configuredHost, pageHost)).toBe(
                expectedHost,
            );
        },
    );
});
