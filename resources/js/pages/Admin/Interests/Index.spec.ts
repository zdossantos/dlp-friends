import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Index from './Index.vue';

vi.mock('@inertiajs/vue3', () => ({
    Form: {
        template:
            '<form><slot :errors="{ name: \'Le nom est obligatoire.\', max_selections: \'La limite est obligatoire.\' }" :processing="false" /></form>',
    },
    Head: { template: '<span />' },
}));

vi.mock('@/routes/admin/interests', () => ({
    index: () => ({ url: '/admin/interests' }),
    destroy: Object.assign(
        (interest: number) => ({ url: `/admin/interests/${interest}` }),
        { form: () => ({}) },
    ),
    move: Object.assign(
        (interest: number) => ({ url: `/admin/interests/${interest}/move` }),
        { form: () => ({}) },
    ),
    status: Object.assign(
        (interest: number) => ({
            url: `/admin/interests/${interest}/status`,
        }),
        { form: () => ({}) },
    ),
    store: { form: () => ({}) },
    update: Object.assign(
        (interest: number) => ({ url: `/admin/interests/${interest}` }),
        { form: () => ({}) },
    ),
}));

vi.mock('@/routes/admin/interest-setting', () => ({
    update: { form: () => ({}) },
}));

describe('admin interest catalog page', () => {
    const mountPage = () =>
        mount(Index, {
            props: {
                interests: [
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
                ],
                setting: { max_selections: 5 },
            },
            global: {
                stubs: {
                    Badge: { template: '<span><slot /></span>' },
                    Button: {
                        template: '<button><slot /></button>',
                    },
                    Card: { template: '<section><slot /></section>' },
                    CardContent: { template: '<div><slot /></div>' },
                    CardDescription: { template: '<p><slot /></p>' },
                    CardHeader: { template: '<header><slot /></header>' },
                    CardTitle: { template: '<h2><slot /></h2>' },
                    Dialog: { template: '<div><slot /></div>' },
                    DialogClose: { template: '<button><slot /></button>' },
                    DialogContent: { template: '<div><slot /></div>' },
                    DialogDescription: { template: '<p><slot /></p>' },
                    DialogFooter: { template: '<footer><slot /></footer>' },
                    DialogHeader: { template: '<header><slot /></header>' },
                    DialogTitle: { template: '<h2><slot /></h2>' },
                    DialogTrigger: { template: '<div><slot /></div>' },
                    Input: {
                        props: ['defaultValue'],
                        template: '<input :value="defaultValue" />',
                    },
                    InputError: {
                        props: ['message'],
                        template: '<p v-if="message">{{ message }}</p>',
                    },
                    Label: { template: '<label><slot /></label>' },
                },
            },
        });

    it('shows state, history count, ordering actions, and the selection limit', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Intérêts');
        expect(wrapper.text()).toContain('Archivé');
        expect(wrapper.text()).toContain('12 profils');
        expect(
            wrapper.get('input[name="max_selections"]').attributes('value'),
        ).toBe('5');
        expect(wrapper.find('[aria-label="Monter Chill"]').exists()).toBe(true);
        expect(
            wrapper.get('[aria-label="Monter Chill"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('provides named creation and inline edit forms with validation errors', () => {
        const wrapper = mountPage();

        expect(
            wrapper
                .find('[data-test="create-interest-form"] input[name="name"]')
                .exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="edit-interest-form"] input[name="name"]')
                .exists(),
        ).toBe(true);
        expect(wrapper.text()).toContain('Le nom est obligatoire.');
        expect(wrapper.text()).toContain('La limite est obligatoire.');
    });

    it('offers archive confirmation for active interests and reactivation for archived ones', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain('Réactiver');
        expect(wrapper.text()).toContain('Archiver');
        expect(wrapper.text()).toContain('Archiver l’intérêt Spectacles');
        expect(
            wrapper.find('[aria-label="Archiver Spectacles"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[aria-label="Supprimer Spectacles"]').exists(),
        ).toBe(true);
    });
});
