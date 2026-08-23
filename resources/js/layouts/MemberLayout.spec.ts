import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import MemberLayout from './MemberLayout.vue';

describe('MemberLayout', () => {
    it('renders no header and reserves space for the safe-area dock', () => {
        const wrapper = mount(MemberLayout, {
            slots: { default: '<main data-test="page">Page</main>' },
            global: {
                stubs: {
                    MemberBottomNavigation: true,
                    Toaster: true,
                },
            },
        });

        expect(wrapper.find('header').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="member-shell-content"]').classes(),
        ).toContain('pb-[calc(6rem+env(safe-area-inset-bottom))]');
    });
});
