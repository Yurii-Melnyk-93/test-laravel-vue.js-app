import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PromoHistory from './PromoHistory.vue';
import api from '../api.js';

vi.mock('../api.js', () => ({
    default: { get: vi.fn(), patch: vi.fn() },
}));

function row(overrides = {}) {
    return {
        id: 1,
        code: 'WELCOME100',
        status: 'applied',
        rejection_reason: null,
        rejection_message: null,
        amount: { cents: 10_000, formatted: '100.00' },
        can_revoke: true,
        created_at: '2026-08-13T10:30:00+00:00',
        revoked_at: null,
        ...overrides,
    };
}

function page(rows, meta = {}) {
    return {
        data: {
            data: rows,
            meta: { current_page: 1, last_page: 1, total: rows.length, ...meta },
        },
    };
}

async function mountHistory(response = page([row()])) {
    api.get.mockResolvedValue(response);

    const wrapper = mount(PromoHistory);
    await flushPromises();

    return wrapper;
}

function lastParams() {
    return api.get.mock.calls.at(-1)[1].params;
}

function revokeButtons(wrapper) {
    return wrapper.findAll('button').filter((b) => b.text() === 'Скасувати');
}

async function confirm(wrapper) {
    await wrapper.findAll('button').find((b) => b.text() === 'Так, скасувати').trigger('click');
    await flushPromises();
}

describe('PromoHistory', () => {
    it('lists the date, amount and status of each claim', async () => {
        const wrapper = await mountHistory();

        expect(wrapper.text()).toContain('WELCOME100');
        expect(wrapper.text()).toContain('100.00');
        expect(wrapper.text()).toContain('Застосовано');
        expect(wrapper.text()).toMatch(/13\.08\.26|13\.08\.2026/);
    });

    it('explains a rejected attempt and shows no amount for it', async () => {
        const wrapper = await mountHistory(
            page([
                row({
                    status: 'rejected',
                    rejection_reason: 'expired',
                    rejection_message: 'Термін дії промокоду вичерпано.',
                    amount: null,
                    can_revoke: false,
                }),
            ]),
        );

        expect(wrapper.text()).toContain('Відхилено');
        expect(wrapper.text()).toContain('Термін дії промокоду вичерпано.');
        expect(wrapper.text()).toContain('—');
        expect(wrapper.text()).not.toContain('100.00');
    });

    it('asks the server for one status when a filter is picked', async () => {
        const wrapper = await mountHistory();

        await wrapper.findAll('button').find((b) => b.text() === 'Відхилені').trigger('click');
        await flushPromises();

        expect(lastParams().status).toBe('rejected');
    });

    it('returns to the first page when the filter changes', async () => {
        const wrapper = await mountHistory(page([row()], { current_page: 2, last_page: 3 }));

        await wrapper.findAll('button').find((b) => b.text() === 'Далі').trigger('click');
        await flushPromises();
        expect(lastParams().page).toBe(3);

        // Page three may not exist once the list is filtered, which would show
        // an empty history and look like a bug.
        await wrapper.findAll('button').find((b) => b.text() === 'Застосовані').trigger('click');
        await flushPromises();
        expect(lastParams().page).toBe(1);
    });

    it('hides pagination when everything fits on one page', async () => {
        const wrapper = await mountHistory(page([row()], { last_page: 1 }));

        expect(wrapper.findAll('button').some((b) => b.text() === 'Далі')).toBe(false);
    });

    it('stops at the last page', async () => {
        const wrapper = await mountHistory(page([row()], { current_page: 2, last_page: 2 }));

        const next = wrapper.findAll('button').find((b) => b.text() === 'Далі');
        expect(next.attributes('disabled')).toBeDefined();
    });

    it('shows an empty state instead of a bare list', async () => {
        const wrapper = await mountHistory(page([]));

        expect(wrapper.text()).toContain('Записів немає');
    });

    it('surfaces a loading failure instead of staying blank', async () => {
        api.get.mockRejectedValue(new Error('Network Error'));

        const wrapper = mount(PromoHistory);
        await flushPromises();

        expect(wrapper.text()).toContain('Не вдалося завантажити історію.');
    });

    it('offers a revoke button only where the server allows it', async () => {
        const wrapper = await mountHistory(
            page([
                row({ id: 1, can_revoke: true }),
                row({ id: 2, status: 'revoked', can_revoke: false }),
            ]),
        );

        expect(revokeButtons(wrapper)).toHaveLength(1);
    });

    it('asks for confirmation before touching the balance', async () => {
        const wrapper = await mountHistory();

        await revokeButtons(wrapper)[0].trigger('click');

        expect(wrapper.find('[role="dialog"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Скасувати нарахування?');

        // Nothing is sent until the dialog is confirmed.
        expect(api.patch).not.toHaveBeenCalled();
    });

    it('sends nothing when the confirmation is declined', async () => {
        const wrapper = await mountHistory();

        await revokeButtons(wrapper)[0].trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Ні, залишити').trigger('click');

        expect(api.patch).not.toHaveBeenCalled();
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('revokes the confirmed claim, reports the new balance and refreshes', async () => {
        const wrapper = await mountHistory(page([row({ id: 7 })]));
        const balance = { cents: 5_000, formatted: '50.00' };
        api.patch.mockResolvedValue({ data: { claim: row({ id: 7, status: 'revoked' }), balance } });

        await revokeButtons(wrapper)[0].trigger('click');
        await confirm(wrapper);

        expect(api.patch).toHaveBeenCalledWith('/promo/7/revoke');
        expect(wrapper.emitted('revoked')).toEqual([[balance]]);

        // The row changed status on the server, so the list is refetched.
        expect(api.get).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('sends one request even if the confirm button is pressed twice', async () => {
        const wrapper = await mountHistory(page([row({ id: 7 })]));
        api.patch.mockReturnValue(new Promise(() => {}));

        await revokeButtons(wrapper)[0].trigger('click');

        // Both clicks land in the same tick, before Vue can paint the button
        // as disabled — awaiting between them would only prove that `disabled`
        // works, which is not the guard that protects a balance.
        const button = wrapper.findAll('button').find((b) => b.text() === 'Так, скасувати');
        button.trigger('click');
        button.trigger('click');
        await flushPromises();

        expect(api.patch).toHaveBeenCalledTimes(1);
    });

    it('explains a refused revoke without hiding the list', async () => {
        const wrapper = await mountHistory(page([row({ id: 7 })]));
        api.patch.mockRejectedValue({
            response: { status: 409, data: { reason: 'already_revoked', message: 'Це нарахування вже скасоване.' } },
        });

        await revokeButtons(wrapper)[0].trigger('click');
        await confirm(wrapper);

        expect(wrapper.text()).toContain('Це нарахування вже скасоване.');
        expect(wrapper.text()).toContain('WELCOME100');

        // Our copy of the row is evidently stale, so it is refetched.
        expect(api.get).toHaveBeenCalledTimes(2);
    });

    it('reloads from the first page when the parent reports a new claim', async () => {
        const wrapper = await mountHistory(page([row()], { current_page: 2, last_page: 3 }));

        await wrapper.findAll('button').find((b) => b.text() === 'Далі').trigger('click');
        await flushPromises();

        await wrapper.vm.reload();

        // A fresh claim is the newest row, so it lives on page one.
        expect(lastParams().page).toBe(1);
    });
});
