import { describe, expect, test } from 'bun:test';
import {
    parsePushPayload,
    safePushTarget,
} from '../../resources/js/lib/pwa/pushPayload';

describe('Web Push payload safety', () => {
    test('allows only internal product targets', () => {
        expect(safePushTarget('/conversations/42')).toBe('/conversations/42');
        expect(safePushTarget('https://evil.example/phish')).toBe(
            '/notifications',
        );
        expect(safePushTarget('//evil.example/phish')).toBe('/notifications');
        expect(safePushTarget('/settings/security')).toBe('/notifications');
    });

    test('falls back to a generic notification for malformed payloads', () => {
        const payload = parsePushPayload({
            body: 12,
            target: 'javascript:alert(1)',
        });
        expect(payload.title).toBe('DLP Friends');
        expect(payload.target).toBe('/notifications');
        expect(payload.body).not.toContain('12');
    });

    test('uses the payload locale for the generic body', () => {
        expect(parsePushPayload({ locale: 'en', body: null }).body).toBe(
            'You have a new notification.',
        );
        expect(parsePushPayload({ locale: 'fr', body: null }).body).toBe(
            'Tu as une nouvelle notification.',
        );
        expect(parsePushPayload({ body: null }, 'en').body).toBe(
            'You have a new notification.',
        );
    });
});
