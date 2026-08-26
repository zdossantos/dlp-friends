import { router } from '@inertiajs/vue3';
import type { I18n } from '@/types/i18n';

const translatedAttributes = ['aria-label', 'placeholder', 'title'] as const;

function translateText(value: string, copy: Record<string, string>): string {
    const trimmed = value.trim();
    const translated = copy[trimmed];

    if (!translated) {
        const pattern = Object.entries(copy).find(
            ([key]) =>
                key.endsWith('*') && trimmed.startsWith(key.slice(0, -1)),
        );

        if (!pattern) {
            return value;
        }

        const [key, replacement] = pattern;

        return value.replace(
            trimmed,
            replacement + trimmed.slice(key.length - 1),
        );
    }

    return value.replace(trimmed, translated);
}

function translate(root: ParentNode, i18n: I18n): void {
    document.documentElement.lang = i18n.locale;
    const [title, ...suffix] = document.title.split(' - ');
    document.title = [i18n.messages.copy[title] ?? title, ...suffix].join(
        ' - ',
    );
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();

    while (node) {
        if (!['SCRIPT', 'STYLE'].includes(node.parentElement?.tagName ?? '')) {
            node.nodeValue = translateText(
                node.nodeValue ?? '',
                i18n.messages.copy,
            );
        }

        node = walker.nextNode();
    }

    root.querySelectorAll<HTMLElement>('*').forEach((element) => {
        translatedAttributes.forEach((attribute) => {
            const value = element.getAttribute(attribute);

            if (value) {
                element.setAttribute(
                    attribute,
                    translateText(value, i18n.messages.copy),
                );
            }
        });
    });
}

export function initializeDomTranslations(): void {
    let i18n: I18n | undefined;
    const root = document.getElementById('app');
    const page = root?.dataset.page;

    if (page) {
        i18n = (JSON.parse(page) as { props: { i18n: I18n } }).props.i18n;
    }

    if (root && i18n) {
        translate(root, i18n);
    }

    const observer = new MutationObserver((mutations) => {
        if (!i18n) {
            return;
        }

        mutations.forEach((mutation) =>
            mutation.addedNodes.forEach((node) => {
                if (node instanceof HTMLElement) {
                    translate(node, i18n!);
                } else if (node.nodeType === Node.TEXT_NODE) {
                    node.nodeValue = translateText(
                        node.nodeValue ?? '',
                        i18n!.messages.copy,
                    );
                }
            }),
        );
    });

    if (root) {
        observer.observe(root, { childList: true, subtree: true });
    }

    router.on('navigate', (event) => {
        i18n = event.detail.page.props.i18n as I18n;

        if (root) {
            translate(root, i18n);
        }
    });
}
