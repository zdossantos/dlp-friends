import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, inject, provide, ref } from 'vue';
import type { InjectionKey, Ref } from 'vue';
import Index from './Index.vue';

const dialogOpenKey = Symbol('dialog-open') as InjectionKey<Ref<boolean>>;
const { formSubmissions } = vi.hoisted(() => ({
    formSubmissions: vi.fn<(action: string, method: string) => void>(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        Form: defineComponent({
            inheritAttrs: false,
            setup(_, { attrs, slots }) {
                const action = String(attrs.action ?? '');
                const method = String(attrs.method ?? 'get');
                const errors =
                    action === '/admin/interests'
                        ? { name: 'Le nom à ajouter est obligatoire.' }
                        : action === '/admin/interests/1?_method=PUT'
                          ? { name: 'Le nom à modifier est obligatoire.' }
                          : action === '/admin/interest-setting?_method=PATCH'
                            ? { max_selections: 'La limite est obligatoire.' }
                            : {};

                return () =>
                    h(
                        'form',
                        {
                            ...attrs,
                            onSubmit: (event: Event) => {
                                event.preventDefault();
                                formSubmissions(action, method);
                            },
                        },
                        slots.default?.({ errors, processing: false }),
                    );
            },
        }),
        Head: { render: () => null },
    };
});

const DialogStub = defineComponent({
    setup(_, { slots }) {
        const isOpen = ref(false);

        provide(dialogOpenKey, isOpen);

        return () => h('div', slots.default?.());
    },
});

const DialogTriggerStub = defineComponent({
    setup(_, { slots }) {
        const isOpen = inject(dialogOpenKey);

        if (!isOpen) {
            throw new Error('DialogTrigger must be rendered inside Dialog.');
        }

        return () =>
            h(
                'span',
                {
                    'data-test': 'dialog-trigger',
                    onClick: () => {
                        isOpen.value = true;
                    },
                },
                slots.default?.(),
            );
    },
});

const DialogContentStub = defineComponent({
    setup(_, { slots }) {
        const isOpen = inject(dialogOpenKey);

        if (!isOpen) {
            throw new Error('DialogContent must be rendered inside Dialog.');
        }

        return () =>
            isOpen.value
                ? h('div', { 'data-test': 'dialog-content' }, slots.default?.())
                : null;
    },
});

const ButtonStub = defineComponent({
    inheritAttrs: false,
    setup(_, { attrs, slots }) {
        return () => h('button', attrs, slots.default?.());
    },
});

const InputStub = defineComponent({
    inheritAttrs: false,
    props: {
        defaultValue: {
            type: [Number, String],
            required: false,
        },
    },
    setup(props, { attrs }) {
        return () => h('input', { ...attrs, value: props.defaultValue });
    },
});

const InputErrorStub = defineComponent({
    props: {
        message: {
            type: String,
            required: false,
        },
    },
    setup(props) {
        return () =>
            props.message
                ? h('p', { 'data-test': 'input-error' }, props.message)
                : null;
    },
});

const interests = [
    {
        id: 1,
        name: 'Chill',
        is_active: false,
        sort_order: 0,
        profiles_count: 12,
    },
    {
        id: 2,
        name: 'Spectacles',
        is_active: true,
        sort_order: 1,
        profiles_count: 1,
    },
];

const formFor = (wrapper: VueWrapper, selector: string): HTMLFormElement => {
    const form = wrapper.get(selector).element.closest('form');

    if (!(form instanceof HTMLFormElement)) {
        throw new Error(`No form found for ${selector}.`);
    }

    return form;
};

const expectForm = (
    form: HTMLFormElement,
    action: string,
    method = 'post',
): void => {
    expect(form.getAttribute('action')).toBe(action);
    expect(form.getAttribute('method')).toBe(method);
};

describe('admin interest catalog page', () => {
    const mountPage = () => {
        formSubmissions.mockClear();

        return mount(Index, {
            props: {
                interests,
                setting: { max_selections: 5 },
            },
            global: {
                stubs: {
                    Badge: { template: '<span><slot /></span>' },
                    Button: ButtonStub,
                    Card: { template: '<section><slot /></section>' },
                    CardContent: { template: '<div><slot /></div>' },
                    CardDescription: { template: '<p><slot /></p>' },
                    CardHeader: { template: '<header><slot /></header>' },
                    CardTitle: { template: '<h2><slot /></h2>' },
                    Dialog: DialogStub,
                    DialogClose: { template: '<span><slot /></span>' },
                    DialogContent: DialogContentStub,
                    DialogDescription: { template: '<p><slot /></p>' },
                    DialogFooter: { template: '<footer><slot /></footer>' },
                    DialogHeader: { template: '<header><slot /></header>' },
                    DialogTitle: { template: '<h2><slot /></h2>' },
                    DialogTrigger: DialogTriggerStub,
                    Input: InputStub,
                    InputError: InputErrorStub,
                    Label: { template: '<label><slot /></label>' },
                },
            },
        });
    };

    it('shows state, history count, the selection limit, and both ordering boundaries', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Intérêts');
        expect(wrapper.text()).toContain('Archivé');
        expect(wrapper.text()).toContain('12 profils');
        expect(
            wrapper.get('input[name="max_selections"]').attributes('value'),
        ).toBe('5');
        expect(
            wrapper.get('[aria-label="Monter Chill"]').attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[aria-label="Descendre Spectacles"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[aria-label="Descendre Chill"]')
                .attributes('disabled'),
        ).toBeUndefined();
        expect(
            wrapper
                .get('[aria-label="Monter Spectacles"]')
                .attributes('disabled'),
        ).toBeUndefined();
    });

    it('binds the primary non-dialog forms to the generated action URLs and methods', () => {
        const wrapper = mountPage();

        expectForm(
            wrapper.get('[data-test="create-interest-form"]')
                .element as HTMLFormElement,
            '/admin/interests',
        );
        expectForm(
            wrapper.get('[data-test="edit-interest-form"]')
                .element as HTMLFormElement,
            '/admin/interests/1?_method=PUT',
        );
        expectForm(
            formFor(wrapper, 'input[name="max_selections"]'),
            '/admin/interest-setting?_method=PATCH',
        );
        expectForm(
            formFor(wrapper, 'input[name="direction"][value="up"]'),
            '/admin/interests/1/move?_method=PATCH',
        );
        expectForm(
            formFor(wrapper, 'input[name="direction"][value="down"]'),
            '/admin/interests/1/move?_method=PATCH',
        );
        expectForm(
            formFor(wrapper, 'input[name="is_active"][value="1"]'),
            '/admin/interests/1/status?_method=PATCH',
        );

        expect(
            wrapper
                .get(
                    '[data-test="create-interest-form"] [data-test="input-error"]',
                )
                .text(),
        ).toBe('Le nom à ajouter est obligatoire.');
        expect(
            wrapper
                .get(
                    '[data-test="edit-interest-form"] [data-test="input-error"]',
                )
                .text(),
        ).toBe('Le nom à modifier est obligatoire.');
        expect(
            formFor(wrapper, 'input[name="max_selections"]').querySelector(
                '[data-test="input-error"]',
            )?.textContent,
        ).toBe('La limite est obligatoire.');
    });

    it('opens archive confirmation before rendering and submitting the status form', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="dialog-content"]').exists()).toBe(
            false,
        );
        expect(formSubmissions).not.toHaveBeenCalled();

        await wrapper
            .get('[aria-label="Archiver Spectacles"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Archiver l’intérêt Spectacles');
        const archiveForm = wrapper.get('[data-test="dialog-content"] form');
        expectForm(
            archiveForm.element as HTMLFormElement,
            '/admin/interests/2/status?_method=PATCH',
        );
        expect(
            archiveForm.get('input[name="is_active"]').attributes('value'),
        ).toBe('0');

        await archiveForm.trigger('submit');

        expect(formSubmissions).toHaveBeenCalledTimes(1);
        expect(formSubmissions).toHaveBeenCalledWith(
            '/admin/interests/2/status?_method=PATCH',
            'post',
        );
    });

    it('opens deletion confirmation before rendering and submitting the delete form', async () => {
        const wrapper = mountPage();

        expect(wrapper.find('[data-test="dialog-content"]').exists()).toBe(
            false,
        );
        expect(formSubmissions).not.toHaveBeenCalled();

        await wrapper
            .get('[aria-label="Supprimer Spectacles"]')
            .trigger('click');

        expect(wrapper.text()).toContain('Supprimer l’intérêt Spectacles');
        const deleteForm = wrapper.get('[data-test="dialog-content"] form');
        expectForm(
            deleteForm.element as HTMLFormElement,
            '/admin/interests/2?_method=DELETE',
        );

        await deleteForm.trigger('submit');

        expect(formSubmissions).toHaveBeenCalledTimes(1);
        expect(formSubmissions).toHaveBeenCalledWith(
            '/admin/interests/2?_method=DELETE',
            'post',
        );
    });
});
