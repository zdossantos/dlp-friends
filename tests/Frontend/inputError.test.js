import { compileScript, parse } from '@vue/compiler-sfc';
import { expect, test } from 'bun:test';
import { createRenderer, h, nextTick, ref } from 'vue';

const path = new URL(
    '../../resources/js/components/InputError.vue',
    import.meta.url,
);
const { descriptor } = parse(await Bun.file(path).text());
const compiled = compileScript(descriptor, {
    id: 'input-error',
    inlineTemplate: true,
});
const code = new Bun.Transpiler({ loader: 'ts' })
    .transformSync(compiled.content)
    .replaceAll('"vue"', JSON.stringify(import.meta.resolve('vue')));
const InputError = (
    await import(
        `data:text/javascript;base64,${Buffer.from(code).toString('base64')}`
    )
).default;

const node = () => ({ children: [], props: {}, text: '' });
const renderer = createRenderer({
    createElement: node,
    createComment: node,
    createText: (text) => ({ ...node(), text }),
    setText: (element, text) => (element.text = text),
    setElementText: (element, text) => (element.text = text),
    patchProp: (element, key, _, value) => (element.props[key] = value),
    insert(element, parent, anchor) {
        element.parent = parent;
        const index = anchor ? parent.children.indexOf(anchor) : -1;
        parent.children.splice(
            index < 0 ? parent.children.length : index,
            0,
            element,
        );
    },
    remove(element) {
        const siblings = element.parent.children;
        siblings.splice(siblings.indexOf(element), 1);
    },
    parentNode: (element) => element.parent,
    nextSibling: () => null,
});

test('validation errors update an existing live region without reserving an empty layout slot', async () => {
    const message = ref();
    const container = node();
    const app = renderer.createApp({
        setup: () => () =>
            h(InputError, { id: 'email-error', message: message.value }),
    });
    app.mount(container);
    const region = container.children[0];
    expect(region.props['aria-live']).toBe('polite');
    expect(region.props.id).toBe('email-error');
    expect(region.props.class ?? '').toContain('sr-only');

    message.value = 'Cette adresse est déjà utilisée.';
    await nextTick();
    expect(container.children[0]).toBe(region);
    expect(region.props.class ?? '').not.toContain('sr-only');
    expect(region.children.some((child) => child.text === message.value)).toBe(
        true,
    );

    message.value = undefined;
    await nextTick();
    expect(container.children[0]).toBe(region);
    expect(region.props.class ?? '').toContain('sr-only');
    expect(region.children.some((child) => child.text)).toBe(false);
    app.unmount();
});
