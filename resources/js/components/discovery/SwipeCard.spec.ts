import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import type { DiscoveryProfile } from '@/types';
import SwipeCard from './SwipeCard.vue';

const profile: DiscoveryProfile = {
    userId: 42,
    profileId: 7,
    displayName: 'Mina Parade',
    age: 29,
    bio: 'Toujours partante pour une journee attractions et spectacles.',
    visitFrequency: 'often',
    commonPassionCount: 3,
    commonPassions: ['Attractions', 'Parades', 'Pins'],
    frequencyBonus: true,
    score: 3.25,
};

function mountCard(locked = false) {
    return mount(SwipeCard, {
        props: { profile, locked },
    });
}

async function dispatchPointerEvent(
    element: Element,
    type: 'pointerdown' | 'pointerup' | 'pointercancel',
    clientX: number,
    clientY = 0,
    pointerId = 1,
) {
    const event = new Event(type, { bubbles: true, cancelable: true });
    Object.defineProperties(event, {
        clientX: { value: clientX },
        clientY: { value: clientY },
        pointerId: { value: pointerId },
    });
    element.dispatchEvent(event);
    await nextTick();
}

describe('SwipeCard', () => {
    it('renders the discovery profile and emits only pass and like from button actions', async () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('Mina Parade');
        expect(wrapper.text()).toContain('29 ans');
        expect(wrapper.text()).toContain(
            'Toujours partante pour une journee attractions et spectacles.',
        );
        expect(wrapper.text()).toContain('Score 3,25');
        expect(wrapper.text()).toContain('Souvent');
        expect(wrapper.text()).toContain('Bonus de fréquence');
        expect(wrapper.text()).toContain('Attractions');
        expect(wrapper.text()).toContain('Parades');
        expect(wrapper.text()).toContain('Pins');

        await wrapper
            .get('button[aria-label="Passer ce profil"]')
            .trigger('click');
        await wrapper
            .get('button[aria-label="Aimer ce profil"]')
            .trigger('click');

        expect(wrapper.emitted()).toEqual({ pass: [[]], like: [[]] });
    });

    it('disables visible actions when locked and ignores a second action', async () => {
        const wrapper = mountCard();

        await wrapper
            .get('button[aria-label="Aimer ce profil"]')
            .trigger('click');
        await wrapper.setProps({ locked: true });

        expect(
            wrapper.get('button[aria-label="Passer ce profil"]').attributes(),
        ).toHaveProperty('disabled');
        expect(
            wrapper.get('button[aria-label="Aimer ce profil"]').attributes(),
        ).toHaveProperty('disabled');

        await wrapper
            .get('button[aria-label="Passer ce profil"]')
            .trigger('click');

        expect(wrapper.emitted()).toEqual({ like: [[]] });
    });

    it('emits pass and like from focused keyboard arrows', async () => {
        const wrapper = mountCard();
        const card = wrapper.get('[tabindex="0"]');

        await card.trigger('keydown.left');
        await card.trigger('keydown.right');

        expect(wrapper.emitted('pass')).toEqual([[]]);
        expect(wrapper.emitted('like')).toEqual([[]]);
    });

    it('does not apply card arrow shortcuts from a focused child button', async () => {
        const wrapper = mountCard();

        await wrapper
            .get('button[aria-label="Passer ce profil"]')
            .trigger('keydown.right');

        expect(wrapper.emitted('pass')).toBeUndefined();
        expect(wrapper.emitted('like')).toBeUndefined();
    });

    it('uses a 72 pixel horizontal pointer threshold for swipe gestures', async () => {
        const passWrapper = mountCard();
        const passCard = passWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(passCard.element, 'pointerdown', 200);
        await dispatchPointerEvent(passCard.element, 'pointerup', 110);
        expect(passWrapper.emitted('pass')).toEqual([[]]);
        expect(passWrapper.emitted('like')).toBeUndefined();

        const likeWrapper = mountCard();
        const likeCard = likeWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(likeCard.element, 'pointerdown', 110);
        await dispatchPointerEvent(likeCard.element, 'pointerup', 200);
        expect(likeWrapper.emitted('like')).toEqual([[]]);
        expect(likeWrapper.emitted('pass')).toBeUndefined();

        const shortWrapper = mountCard();
        const shortCard = shortWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(shortCard.element, 'pointerdown', 110);
        await dispatchPointerEvent(shortCard.element, 'pointerup', 130);
        expect(shortWrapper.emitted('pass')).toBeUndefined();
        expect(shortWrapper.emitted('like')).toBeUndefined();
    });

    it('ignores diagonal gestures and clears cancelled pointer state', async () => {
        const diagonalWrapper = mountCard();
        const diagonalCard = diagonalWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(
            diagonalCard.element,
            'pointerdown',
            200,
            100,
        );
        await dispatchPointerEvent(diagonalCard.element, 'pointerup', 100, 220);

        expect(diagonalWrapper.emitted('pass')).toBeUndefined();
        expect(diagonalWrapper.emitted('like')).toBeUndefined();

        const cancelledWrapper = mountCard();
        const cancelledCard = cancelledWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(cancelledCard.element, 'pointerdown', 200);
        await dispatchPointerEvent(cancelledCard.element, 'pointercancel', 200);
        await dispatchPointerEvent(cancelledCard.element, 'pointerup', 100);

        expect(cancelledWrapper.emitted('pass')).toBeUndefined();
        expect(cancelledWrapper.emitted('like')).toBeUndefined();
    });

    it('captures one pointer, ignores other pointer IDs and releases capture', async () => {
        const wrapper = mountCard();
        const card = wrapper.get('[tabindex="0"]');
        const capturedPointers = new Set<number>();
        const setPointerCapture = vi.fn((pointerId: number) => {
            capturedPointers.add(pointerId);
        });
        const releasePointerCapture = vi.fn((pointerId: number) => {
            capturedPointers.delete(pointerId);
        });

        Object.assign(card.element, {
            setPointerCapture,
            hasPointerCapture: (pointerId: number) =>
                capturedPointers.has(pointerId),
            releasePointerCapture,
        });

        await dispatchPointerEvent(card.element, 'pointerdown', 200, 0, 7);
        await dispatchPointerEvent(card.element, 'pointerup', 100, 0, 8);

        expect(setPointerCapture).toHaveBeenCalledWith(7);
        expect(wrapper.emitted('pass')).toBeUndefined();

        await dispatchPointerEvent(card.element, 'pointerup', 100, 0, 7);

        expect(wrapper.emitted('pass')).toEqual([[]]);
        expect(releasePointerCapture).toHaveBeenCalledWith(7);
    });
});
