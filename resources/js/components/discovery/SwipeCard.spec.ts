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
    commonInterestCount: 3,
    commonInterests: ['Attractions', 'Parades', 'Pins'],
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

async function dispatchPointerMove(
    element: Element,
    clientX: number,
    clientY = 0,
    pointerId = 1,
) {
    const event = new Event('pointermove', {
        bubbles: true,
        cancelable: true,
    });
    Object.defineProperties(event, {
        clientX: { value: clientX },
        clientY: { value: clientY },
        pointerId: { value: pointerId },
    });
    element.dispatchEvent(event);
    await nextTick();
}

describe('SwipeCard', () => {
    it('renders the profile with no visible decision controls and keeps reader-accessible actions', () => {
        const wrapper = mountCard();

        expect(wrapper.text()).toContain('Mina Parade');
        expect(wrapper.text()).toContain('29 ans');
        expect(wrapper.text()).toContain(
            'Toujours partante pour une journee attractions et spectacles.',
        );
        expect(wrapper.text()).not.toContain('Score');
        expect(wrapper.text()).not.toContain('3,25');
        expect(wrapper.text()).toContain('3 intérêts communs');
        expect(wrapper.text()).toContain('Souvent');
        expect(wrapper.text()).toContain('Même fréquence de visite');
        expect(wrapper.text()).toContain('Attractions');
        expect(wrapper.text()).toContain('Parades');
        expect(wrapper.text()).toContain('Pins');
        expect(
            wrapper.find('[data-test="visible-swipe-actions"]').exists(),
        ).toBe(false);

        expect(
            wrapper.get('button[aria-label="Passer ce profil"]').classes(),
        ).toContain('sr-only');
        expect(
            wrapper.get('button[aria-label="Aimer ce profil"]').classes(),
        ).toContain('sr-only');
    });

    it('disables reader actions and keyboard decisions while locked', async () => {
        const wrapper = mountCard(true);

        expect(
            wrapper.get('button[aria-label="Passer ce profil"]').attributes(),
        ).toHaveProperty('disabled');
        expect(
            wrapper.get('button[aria-label="Aimer ce profil"]').attributes(),
        ).toHaveProperty('disabled');

        await wrapper.get('[tabindex="0"]').trigger('keydown.right');

        expect(wrapper.emitted('like')).toBeUndefined();
    });

    it('emits pass and like from focused keyboard arrows', async () => {
        const wrapper = mountCard();
        const card = wrapper.get('[tabindex="0"]');

        await card.trigger('keydown.left');
        await card.trigger('keydown.right');

        expect(wrapper.emitted('pass')).toEqual([[]]);
        expect(wrapper.emitted('like')).toEqual([[]]);
    });

    it('emits decisions from reader-accessible semantic controls', async () => {
        const wrapper = mountCard();

        await wrapper
            .get('button[aria-label="Passer ce profil"]')
            .trigger('click');
        await wrapper
            .get('button[aria-label="Aimer ce profil"]')
            .trigger('click');

        expect(wrapper.emitted('pass')).toEqual([[]]);
        expect(wrapper.emitted('like')).toEqual([[]]);
    });

    it('uses a 72 pixel horizontal pointer threshold for swipe gestures', async () => {
        vi.useFakeTimers();
        const passWrapper = mountCard();
        const passCard = passWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(passCard.element, 'pointerdown', 200);
        await dispatchPointerEvent(passCard.element, 'pointerup', 110);
        expect(passCard.attributes('style')).toContain('translate3d(-120vw');
        expect(passWrapper.emitted('pass')).toBeUndefined();
        vi.advanceTimersByTime(280);
        expect(passWrapper.emitted('pass')).toEqual([[]]);
        expect(passWrapper.emitted('like')).toBeUndefined();

        const likeWrapper = mountCard();
        const likeCard = likeWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(likeCard.element, 'pointerdown', 110);
        await dispatchPointerEvent(likeCard.element, 'pointerup', 200);
        expect(likeCard.attributes('style')).toContain('translate3d(120vw');
        expect(likeWrapper.emitted('like')).toBeUndefined();
        vi.advanceTimersByTime(280);
        expect(likeWrapper.emitted('like')).toEqual([[]]);
        expect(likeWrapper.emitted('pass')).toBeUndefined();

        const shortWrapper = mountCard();
        const shortCard = shortWrapper.get('[tabindex="0"]');
        await dispatchPointerEvent(shortCard.element, 'pointerdown', 110);
        await dispatchPointerEvent(shortCard.element, 'pointerup', 130);
        expect(shortWrapper.emitted('pass')).toBeUndefined();
        expect(shortWrapper.emitted('like')).toBeUndefined();
        vi.useRealTimers();
    });

    it('follows and rotates with the pointer before returning short swipes to the centre', async () => {
        const wrapper = mountCard();
        const card = wrapper.get('[tabindex="0"]');

        await dispatchPointerEvent(card.element, 'pointerdown', 120, 200);
        await dispatchPointerMove(card.element, 170, 210);

        expect(card.attributes('style')).toContain(
            'translate3d(50px, 1.5px, 0)',
        );
        expect(card.attributes('style')).toContain('rotate(2.5deg)');

        await dispatchPointerEvent(card.element, 'pointerup', 170, 210);

        expect(card.attributes('style')).toContain('translate3d(0px, 0px, 0)');
        expect(card.attributes('style')).toContain('rotate(0deg)');
        expect(card.classes()).toContain('transition-[transform,opacity]');
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
        vi.useFakeTimers();
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

        vi.advanceTimersByTime(280);
        expect(wrapper.emitted('pass')).toEqual([[]]);
        expect(releasePointerCapture).toHaveBeenCalledWith(7);
        vi.useRealTimers();
    });
});
