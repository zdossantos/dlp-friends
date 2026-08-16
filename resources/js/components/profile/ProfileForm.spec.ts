import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ProfileForm from './ProfileForm.vue';

describe('ProfileForm', () => {
    it('renders the complete accessible member profile contract', () => {
        const wrapper = shallowMount(ProfileForm, {
            props: {
                profile: null,
                action: '/profile',
                method: 'post',
                submitLabel: 'Créer mon profil',
                visitFrequencies: [
                    { value: 'rarely', label: 'Rarement' },
                    { value: 'sometimes', label: 'De temps en temps' },
                ],
                visibilities: [
                    { value: 'visible', label: 'Visible' },
                    { value: 'hidden', label: 'Masqué' },
                ],
            },
            global: {
                stubs: {
                    Form: {
                        template:
                            '<form><slot :errors="{}" :processing="false" /></form>',
                    },
                    Input: { template: '<input v-bind="$attrs" />' },
                    Label: { template: '<label><slot /></label>' },
                    InputError: true,
                    Button: { template: '<button><slot /></button>' },
                    Spinner: true,
                },
            },
        });

        expect(
            wrapper.find('input[name="display_name"]').attributes('maxlength'),
        ).toBe('80');
        expect(
            wrapper.find('textarea[name="bio"]').attributes('maxlength'),
        ).toBe('500');
        expect(wrapper.find('select[name="visit_frequency"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('select[name="visibility"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Visible dans les suggestions');
        expect(wrapper.text()).toContain('Créer mon profil');
    });
});
