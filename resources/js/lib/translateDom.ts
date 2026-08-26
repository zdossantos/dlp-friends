import { router } from '@inertiajs/vue3';
import type { I18n } from '@/types/i18n';

const translatedAttributes = ['aria-label', 'placeholder', 'title'] as const;

function translateText(value: string, copy: Record<string, string>): string {
    const trimmed = value.trim();
    const translated = copy[trimmed];

    if (!translated) {
        const pattern = Object.entries(copy).find(([key]) => {
            if (!key.includes('*')) {
                return false;
            }

            const [prefix, suffix] = key.split('*');

            return trimmed.startsWith(prefix) && trimmed.endsWith(suffix);
        });

        if (!pattern) {
            return value;
        }

        const [key, replacement] = pattern;
        const [prefix, suffix] = key.split('*');
        const dynamicValue = trimmed.slice(
            prefix.length,
            suffix.length === 0 ? undefined : -suffix.length,
        );
        const translated = replacement.includes('*')
            ? replacement.replace('*', dynamicValue)
            : replacement + dynamicValue;

        return value.replace(trimmed, translated);
    }

    return value.replace(trimmed, translated);
}

function translateTextNode(node: Node, copy: Record<string, string>): void {
    const value = node.nodeValue ?? '';
    const translated = translateText(value, copy);

    if (translated !== value) {
        node.nodeValue = translated;
    }
}

function translateAttribute(
    element: HTMLElement,
    attribute: (typeof translatedAttributes)[number],
    copy: Record<string, string>,
): void {
    const value = element.getAttribute(attribute);

    if (!value) {
        return;
    }

    const translated = translateText(value, copy);

    if (translated !== value) {
        element.setAttribute(attribute, translated);
    }
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
            translateTextNode(node, i18n.messages.copy);
        }

        node = walker.nextNode();
    }

    root.querySelectorAll<HTMLElement>('*').forEach((element) => {
        translatedAttributes.forEach((attribute) => {
            translateAttribute(element, attribute, i18n.messages.copy);
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
                    translateTextNode(node, i18n!.messages.copy);
                }
            }),
        );

        mutations
            .filter((mutation) => mutation.type === 'characterData')
            .forEach((mutation) =>
                translateTextNode(mutation.target, i18n!.messages.copy),
            );

        mutations
            .filter(
                (mutation) =>
                    mutation.type === 'attributes' &&
                    mutation.target instanceof HTMLElement &&
                    translatedAttributes.includes(
                        mutation.attributeName as (typeof translatedAttributes)[number],
                    ),
            )
            .forEach((mutation) =>
                translateAttribute(
                    mutation.target as HTMLElement,
                    mutation.attributeName as (typeof translatedAttributes)[number],
                    i18n!.messages.copy,
                ),
            );
    });

    if (root) {
        observer.observe(root, {
            characterData: true,
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: [...translatedAttributes],
        });
    }

    router.on('navigate', (event) => {
        i18n = event.detail.page.props.i18n as I18n;

        if (root) {
            translate(root, i18n);
        }
    });
}
