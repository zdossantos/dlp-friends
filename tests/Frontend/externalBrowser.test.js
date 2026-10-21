import { expect, test } from 'bun:test';
import { externalBrowserUrl } from '../../resources/js/lib/externalBrowser';

const destination = 'https://dlp.example/fr';

test('Instagram on iPhone uses its external browser handler', () => {
    expect(externalBrowserUrl(destination, 'iPhone Instagram')).toBe(
        'instagram://extbrowser/?url=https%3A%2F%2Fdlp.example%2Ffr',
    );
});

test('iPhone outside Instagram offers Safari', () => {
    expect(externalBrowserUrl(destination, 'iPhone Safari')).toBe('x-safari-https://dlp.example/fr');
});

test('Android requests a browser with a safe HTTPS fallback', () => {
    expect(externalBrowserUrl(destination, 'Android Instagram')).toBe(
        'intent://dlp.example/fr#Intent;scheme=https;action=android.intent.action.VIEW;category=android.intent.category.BROWSABLE;S.browser_fallback_url=https%3A%2F%2Fdlp.example%2Ffr;end',
    );
});

test('desktop uses the regular destination', () => {
    expect(externalBrowserUrl(destination, 'Firefox Linux')).toBe(destination);
});

test('local HTTP testing keeps its protocol on mobile', () => {
    expect(externalBrowserUrl('http://192.168.1.179:8000/fr', 'iPhone Instagram')).toBe(
        'instagram://extbrowser/?url=http%3A%2F%2F192.168.1.179%3A8000%2Ffr',
    );
    expect(externalBrowserUrl('http://192.168.1.179:8000/fr', 'iPhone')).toBe('http://192.168.1.179:8000/fr');
});
