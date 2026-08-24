import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InterestTagSelector from './InterestTagSelector.vue';

describe('InterestTagSelector', () => {
    it('toggles tags and disables only unselected tags at the limit', async () => {
        const wrapper = mount(InterestTagSelector, {
            props: {
                interests: [
                    { id: 1, name: 'Chill' },
                    { id: 2, name: 'Spectacles' },
                ],
                selectedIds: [1],
                limit: 1,
            },
        });

        const chill = wrapper.get('button[aria-label="Retirer Chill"]');
        const shows = wrapper.get('button[aria-label="Ajouter Spectacles"]');

        expect(chill.attributes('aria-pressed')).toBe('true');
        expect(shows.attributes('disabled')).toBeDefined();

        await chill.trigger('click');

        expect(shows.attributes('disabled')).toBeUndefined();

        await shows.trigger('click');

        expect(
            wrapper.get('input[name="interest_ids[]"]').attributes('value'),
        ).toBe('2');
        expect(wrapper.text()).toContain('1 / 1');
    });

    it('renders only the interests it receives', () => {
        const wrapper = mount(InterestTagSelector, {
            props: {
                interests: [{ id: 1, name: 'Chill' }],
                selectedIds: [],
                limit: 5,
            },
        });

        expect(wrapper.text()).toContain('Chill');
        expect(wrapper.text()).not.toContain('Archivé');
    });

    it('drops a selected interest removed from a refreshed catalog', async () => {
        const wrapper = mount(InterestTagSelector, {
            props: {
                interests: [
                    { id: 1, name: 'Chill' },
                    { id: 2, name: 'Archivé' },
                ],
                selectedIds: [1, 2],
                limit: 5,
            },
        });

        await wrapper.setProps({
            interests: [{ id: 1, name: 'Chill' }],
            selectedIds: [1, 2],
        });

        expect(wrapper.text()).toContain('1 / 5');
        expect(
            wrapper
                .findAll('input[name="interest_ids[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['1']);
    });
});
