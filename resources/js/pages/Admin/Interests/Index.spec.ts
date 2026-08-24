import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { defineComponent, h, inject, provide, ref } from 'vue';
import type { InjectionKey, Ref } from 'vue';
import Index from './Index.vue';

const dialogOpenKey = Symbol('dialog-open') as InjectionKey<Ref<boolean>>;
const { formSubmissions, processingActions } = vi.hoisted(() => ({
    formSubmissions: vi.fn<(action: string, method: string) => void>(),
    processingActions: new Set<string>(),
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
                            'data-preserve-scroll':
                                (
                                    attrs.options as
                                        { preserveScroll?: boolean } | undefined
                                )?.preserveScroll === true
                                    ? 'true'
                                    : undefined,
                            onSubmit: (event: Event) => {
                                event.preventDefault();
                                formSubmissions(action, method);
                            },
                        },
                        slots.default?.({
                            errors,
                            processing: processingActions.has(action),
                        }),
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
    {
        id: 3,
        name: 'Parades',
        is_active: true,
        sort_order: 2,
        profiles_count: 0,
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
    const mountPage = (processing: string[] = []) => {
        formSubmissions.mockClear();
        processingActions.clear();
        processing.forEach((action) => processingActions.add(action));

        return mount(Index, {
            props: {
                interests,
                setting: { max_selections: 5 },
            },
            global: {
                stubs: {
                    Badge: { template: '<span><slot /></span>' },
                    Button: ButtonStub,
                    Card: {
                        inheritAttrs: false,
                        template: '<section v-bind="$attrs"><slot /></section>',
                    },
                    CardContent: {
                        inheritAttrs: false,
                        template: '<div v-bind="$attrs"><slot /></div>',
                    },
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
                .get('[aria-label="Descendre Parades"]')
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

    it('disables deletion for every interest present in profile history', () => {
        const wrapper = mountPage();

        for (const interestName of ['Chill', 'Spectacles']) {
            const button = wrapper.get(
                `[aria-label="Supprimer ${interestName}"]`,
            );
            const descriptionId = button.attributes('aria-describedby');

            expect(button.attributes('disabled')).toBeDefined();
            expect(descriptionId).toBeTruthy();
            expect(wrapper.get(`#${descriptionId}`).text()).toContain(
                'ne peut pas être supprimé',
            );
        }

        expect(
            wrapper
                .get('[aria-label="Supprimer Parades"]')
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

    it('keeps the create button aligned with the input when a validation error is shown', () => {
        const wrapper = mountPage();
        const form = wrapper.get('[data-test="create-interest-form"]');
        const input = form.get('input[name="name"]');
        const button = form.get('button[type="submit"]');
        const error = form.get('[data-test="input-error"]');

        expect(input.element.parentElement).toBe(button.element.parentElement);
        expect(error.element.parentElement).not.toBe(
            input.element.parentElement,
        );
    });

    it('keeps the selection limit button aligned with the input when a validation error is shown', () => {
        const wrapper = mountPage();
        const form = formFor(wrapper, 'input[name="max_selections"]');
        const input = form.querySelector('input[name="max_selections"]');
        const button = form.querySelector('button[type="submit"]');
        const error = form.querySelector('[data-test="input-error"]');

        expect(input).not.toBeNull();
        expect(button).not.toBeNull();
        expect(error).not.toBeNull();
        expect(input?.parentElement).toBe(button?.parentElement);
        expect(error?.parentElement).not.toBe(input?.parentElement);
    });

    it('groups creation and selection limit controls above the catalog', () => {
        const wrapper = mountPage();
        const controls = wrapper.get('[data-test="catalog-controls"]');

        expect(
            controls.find('[data-test="create-interest-form"]').exists(),
        ).toBe(true);
        expect(controls.find('input[name="max_selections"]').exists()).toBe(
            true,
        );
        expect(
            controls
                .find('[aria-labelledby="interest-catalog-title"]')
                .exists(),
        ).toBe(false);
    });

    it('uses the editable interest name as the compact accessible label', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).not.toContain('Nom de l’intérêt');
        expect(
            wrapper
                .get('input[aria-label="Nom de l’intérêt Chill"]')
                .attributes('value'),
        ).toBe('Chill');
        expect(wrapper.findAll('h3')).toHaveLength(0);
    });

    it('removes inherited vertical card padding from catalog items', () => {
        const wrapper = mountPage();
        const catalog = wrapper.get(
            '[aria-labelledby="interest-catalog-title"]',
        );
        const card = catalog.get(':scope > section');
        const content = card.get(':scope > div');

        expect(card.classes()).toContain('py-0');
        expect(content.classes()).toContain('p-3');
    });

    it('preserves the scroll position when moving an interest', () => {
        const wrapper = mountPage();

        expect(
            formFor(wrapper, 'input[name="direction"][value="up"]').dataset
                .preserveScroll,
        ).toBe('true');
        expect(
            formFor(wrapper, 'input[name="direction"][value="down"]').dataset
                .preserveScroll,
        ).toBe('true');
    });

    it('disables move and reactivate controls while their forms are processing', () => {
        const wrapper = mountPage([
            '/admin/interests/1/move?_method=PATCH',
            '/admin/interests/1/status?_method=PATCH',
        ]);

        expect(
            wrapper
                .get('[aria-label="Descendre Chill"]')
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .get('[aria-label="Réactiver Chill"]')
                .attributes('disabled'),
        ).toBeDefined();
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

        await wrapper.get('[aria-label="Supprimer Parades"]').trigger('click');

        expect(wrapper.text()).toContain('Supprimer l’intérêt Parades');
        const deleteForm = wrapper.get('[data-test="dialog-content"] form');
        expectForm(
            deleteForm.element as HTMLFormElement,
            '/admin/interests/3?_method=DELETE',
        );

        await deleteForm.trigger('submit');

        expect(formSubmissions).toHaveBeenCalledTimes(1);
        expect(formSubmissions).toHaveBeenCalledWith(
            '/admin/interests/3?_method=DELETE',
            'post',
        );
    });
});
