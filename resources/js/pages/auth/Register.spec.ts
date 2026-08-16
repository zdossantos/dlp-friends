import { shallowMount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Register from './Register.vue';

vi.mock('@/routes', () => ({
    login: () => ({ url: '/login' }),
}));

vi.mock('@/routes/register', () => ({
    store: {
        form: () => ({ action: '/register', method: 'post' }),
    },
}));

describe('registration form', () => {
    it('collects account data without a public name', () => {
        const wrapper = shallowMount(Register, {
            props: { passwordRules: '' },
            global: {
                stubs: {
                    Form: {
                        template:
                            '<form><slot :errors="{}" :processing="false" /></form>',
                    },
                    Head: true,
                    TextLink: true,
                    Button: { template: '<button><slot /></button>' },
                    Input: { template: '<input v-bind="$attrs" />' },
                    Label: { template: '<label><slot /></label>' },
                    PasswordInput: { template: '<input v-bind="$attrs" />' },
                    InputError: true,
                    Spinner: true,
                },
            },
        });

        expect(wrapper.find('input[name="username"]').exists()).toBe(false);
        expect(wrapper.find('input[name="email"]').exists()).toBe(true);
        expect(wrapper.find('input[name="birth_date"]').exists()).toBe(true);
        expect(wrapper.find('input[name="password"]').exists()).toBe(true);
        expect(
            wrapper.find('input[name="password_confirmation"]').exists(),
        ).toBe(true);
    });
});
