import { readFileSync } from 'node:fs';

import { describe, expect, test } from 'bun:test';

const source = readFileSync(
    new URL('../../resources/js/components/AppLogoIcon.vue', import.meta.url),
    'utf8',
);

describe('AppLogoIcon', () => {
    test('keeps decorative and named logo semantics distinct', () => {
        expect(source).toContain(":aria-hidden=\"props.accessibleName ? undefined : 'true'\"");
        expect(source).toContain(':aria-label="props.accessibleName"');
        expect(source).toContain(":role=\"props.accessibleName ? 'img' : undefined\"");
    });
});
