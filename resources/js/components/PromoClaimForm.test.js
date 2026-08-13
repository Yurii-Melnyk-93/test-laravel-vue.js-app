import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PromoClaimForm from './PromoClaimForm.vue';
import api from '../api.js';

vi.mock('../api.js', () => ({
    default: { post: vi.fn() },
}));

function claimResponse({ code = 'WELCOME100', bonus = '100.00', balance = 15_000 } = {}) {
    return {
        data: {
            claim: { code, status: 'applied' },
            bonus_amount: { cents: 10_000, formatted: bonus },
            balance: { cents: balance, formatted: '150.00' },
        },
    };
}

function rejection(status, data) {
    return Object.assign(new Error('Request failed'), { response: { status, data } });
}

async function submitCode(wrapper, code) {
    await wrapper.find('input').setValue(code);
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

describe('PromoClaimForm', () => {
    it('credits the code and hands the new balance to the parent', async () => {
        api.post.mockResolvedValue(claimResponse());

        const wrapper = mount(PromoClaimForm);
        await submitCode(wrapper, 'WELCOME100');

        expect(api.post).toHaveBeenCalledWith('/promo/claim', { code: 'WELCOME100' });
        expect(wrapper.text()).toContain('застосовано');
        expect(wrapper.text()).toContain('100.00');

        // The parent updates the balance from this payload, without refetching.
        expect(wrapper.emitted('claimed')).toHaveLength(1);
        expect(wrapper.emitted('claimed')[0][0]).toEqual({ cents: 15_000, formatted: '150.00' });

        // Cleared so the next code can be typed straight away.
        expect(wrapper.find('input').element.value).toBe('');
    });

    it('shows the reason the server gave when a business rule refuses', async () => {
        api.post.mockRejectedValue(
            rejection(409, { message: 'Ви вже використовували цей промокод.', reason: 'already_used' }),
        );

        const wrapper = mount(PromoClaimForm);
        await submitCode(wrapper, 'WELCOME100');

        expect(wrapper.text()).toContain('Ви вже використовували цей промокод.');
        expect(wrapper.emitted('claimed')).toBeUndefined();

        // The refusal was still written to the history, so the list must refresh.
        expect(wrapper.emitted('recorded')).toHaveLength(1);

        // Left in place so the player can correct it.
        expect(wrapper.find('input').element.value).toBe('WELCOME100');
    });

    it('shows the field error when validation fails', async () => {
        api.post.mockRejectedValue(
            rejection(422, {
                message: 'The given data was invalid.',
                errors: { code: ['Промокод має містити 6–12 латинських літер або цифр.'] },
            }),
        );

        const wrapper = mount(PromoClaimForm);
        await submitCode(wrapper, 'AB1');

        expect(wrapper.text()).toContain('6–12 латинських літер');

        // Validation never reached the domain, so nothing was written and the
        // history has no reason to reload.
        expect(wrapper.emitted('recorded')).toBeUndefined();
    });

    it('falls back to a readable message when the server cannot be reached', async () => {
        api.post.mockRejectedValue(new Error('Network Error'));

        const wrapper = mount(PromoClaimForm);
        await submitCode(wrapper, 'WELCOME100');

        expect(wrapper.text()).toContain('Не вдалося зв’язатися з сервером.');
    });

    it('sends one request even when the form is submitted repeatedly', async () => {
        // Held open so every extra submit lands while the first is in flight.
        let release;
        api.post.mockReturnValue(new Promise((resolve) => {
            release = () => resolve(claimResponse());
        }));

        const wrapper = mount(PromoClaimForm);
        await wrapper.find('input').setValue('WELCOME100');

        await wrapper.find('form').trigger('submit');
        await wrapper.find('form').trigger('submit');
        await wrapper.find('form').trigger('submit');

        expect(api.post).toHaveBeenCalledTimes(1);

        release();
        await flushPromises();

        expect(wrapper.emitted('claimed')).toHaveLength(1);
    });

    it('disables the controls while the request is in flight', async () => {
        let release;
        api.post.mockReturnValue(new Promise((resolve) => {
            release = () => resolve(claimResponse());
        }));

        const wrapper = mount(PromoClaimForm);
        await wrapper.find('input').setValue('WELCOME100');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
        expect(wrapper.find('input').attributes('disabled')).toBeDefined();
        expect(wrapper.find('button').text()).toBe('Застосування…');

        release();
        await flushPromises();

        // The field is cleared on success, so the button stays disabled until
        // something is typed again — that is the empty-input rule, not loading.
        expect(wrapper.find('input').attributes('disabled')).toBeUndefined();
        expect(wrapper.find('button').text()).toBe('Застосувати');

        await wrapper.find('input').setValue('BONUS50');
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });

    it('does not submit an empty code', async () => {
        const wrapper = mount(PromoClaimForm);

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });

    it('upper-cases what the player types, matching how codes are stored', async () => {
        api.post.mockResolvedValue(claimResponse());

        const wrapper = mount(PromoClaimForm);
        await submitCode(wrapper, 'welcome100');

        expect(api.post).toHaveBeenCalledWith('/promo/claim', { code: 'WELCOME100' });
    });
});
