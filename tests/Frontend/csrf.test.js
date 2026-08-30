import { describe, expect, test } from 'bun:test';
import { xsrfHeader } from '../../resources/js/lib/csrf';

describe('xsrfHeader', () => {
    test('uses the current decoded XSRF cookie instead of page-load state', () => {
        expect(
            xsrfHeader(
                'theme=dark; XSRF-TOKEN=current%2Ftoken%3D; session=abc',
            ),
        ).toEqual({ 'X-XSRF-TOKEN': 'current/token=' });
    });

    test('omits the header when the XSRF cookie is unavailable', () => {
        expect(xsrfHeader('theme=dark; session=abc')).toEqual({});
    });
});
