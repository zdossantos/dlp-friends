import { shallowMount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InterestTagSelector from './InterestTagSelector.vue';
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
                interests: [],
                selectedInterestIds: [],
                interestLimit: 5,
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

    it('passes the catalog and interest validation errors to the selector', () => {
        const wrapper = shallowMount(ProfileForm, {
            props: {
                profile: null,
                action: '/profile',
                method: 'post',
                submitLabel: 'Créer mon profil',
                visitFrequencies: [],
                visibilities: [],
                interests: [{ id: 1, name: 'Chill' }],
                selectedInterestIds: [1],
                interestLimit: 5,
            },
            global: {
                stubs: {
                    Form: {
                        template:
                            '<form><slot :errors="{ interest_ids: \'Choix invalide\' }" :processing="false" /></form>',
                    },
                    InterestTagSelector: {
                        props: ['interests', 'selectedIds', 'limit'],
                        template: '<div data-test="interest-selector" />',
                    },
                    InputError: {
                        props: ['message'],
                        template: '<p>{{ message }}</p>',
                    },
                    Input: { template: '<input v-bind="$attrs" />' },
                    Label: { template: '<label><slot /></label>' },
                    Button: { template: '<button><slot /></button>' },
                    Spinner: true,
                },
            },
        });

        const selector = wrapper.getComponent(InterestTagSelector);

        expect(selector.props('interests')).toEqual([{ id: 1, name: 'Chill' }]);
        expect(selector.props('selectedIds')).toEqual([1]);
        expect(selector.props('limit')).toBe(5);
        expect(wrapper.text()).toContain('Choix invalide');
    });
});
