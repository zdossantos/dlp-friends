import { externalBrowserUrl } from '@/lib/externalBrowser';

const root = document.querySelector('[data-browser-help]');
const button = root?.querySelector<HTMLButtonElement>('[data-copy-link]');
const link = root?.querySelector<HTMLInputElement>('#browser-link');
const status = root?.querySelector<HTMLElement>('#copy-status');

const openBrowser = root?.querySelector<HTMLAnchorElement>(
    '[data-open-browser]',
);

if (openBrowser && link) {
    openBrowser.href = externalBrowserUrl(link.value, navigator.userAgent);
    openBrowser.addEventListener('click', () => {
        if (status) {
            status.textContent = status.dataset.openHint ?? '';
        }
    });
}

if (button && link && status) {
    button.hidden = false;
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(link.value);
            status.textContent = status.dataset.copied ?? '';
        } catch {
            link.focus();
            link.select();
            status.textContent = status.dataset.copyFailed ?? '';
        }
    });
}

export {};
