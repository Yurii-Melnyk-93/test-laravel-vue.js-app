import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import PromoHistory from './PromoHistory.vue';
import api from '../api.js';

vi.mock('../api.js', () => ({
    default: { get: vi.fn() },
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

    it('reloads from the first page when the parent reports a new claim', async () => {
        const wrapper = await mountHistory(page([row()], { current_page: 2, last_page: 3 }));

        await wrapper.findAll('button').find((b) => b.text() === 'Далі').trigger('click');
        await flushPromises();

        await wrapper.vm.reload();

        // A fresh claim is the newest row, so it lives on page one.
        expect(lastParams().page).toBe(1);
    });
});
