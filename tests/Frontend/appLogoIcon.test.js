import { describe, expect, test } from 'bun:test';
import { readFileSync } from 'node:fs';

const source = readFileSync(
    new URL('../../resources/js/components/AppLogoIcon.vue', import.meta.url),
    'utf8',
);

describe('AppLogoIcon', () => {
    test('maps standard, dark and Halloween artwork without changing its box', () => {
        expect(source).toContain("bg-[url('/brand/dlp-friends-logo.svg')]");
        expect(source).toContain("dark:bg-[url('/brand/dlp-friends-logo-dark.svg')]");
        expect(source).toContain(
            "[.seasonal-halloween_&]:bg-[url('/brand/dlp-friends-logo-halloween.svg')]",
        );
        expect(source).toContain(':class="className"');
    });

    test('keeps decorative and named logo semantics distinct', () => {
        expect(source).toContain(":aria-hidden=\"props.accessibleName ? undefined : 'true'\"");
        expect(source).toContain(':aria-label="props.accessibleName"');
        expect(source).toContain(":role=\"props.accessibleName ? 'img' : undefined\"");
    });
});
