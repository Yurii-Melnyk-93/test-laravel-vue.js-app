import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BalanceCard from './BalanceCard.vue';

const player = {
    id: 1,
    name: 'Олена Ковальчук',
    email: 'olena@example.com',
    balance: { cents: 15_000, formatted: '150.00' },
};

describe('BalanceCard', () => {
    it('shows the balance exactly as the server formatted it', () => {
        const wrapper = mount(BalanceCard, { props: { player } });

        expect(wrapper.text()).toContain('150.00');
        expect(wrapper.text()).toContain('Олена Ковальчук');

        // Formatting money is the server's job; the client must not round or
        // reformat and risk showing a different figure than the ledger holds.
        expect(wrapper.text()).not.toContain('15000');
    });

    it('asks the parent to log out', async () => {
        const wrapper = mount(BalanceCard, { props: { player } });

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('logout')).toHaveLength(1);
    });

    it('blocks the logout button while the parent is busy', () => {
        const wrapper = mount(BalanceCard, { props: { player, busy: true } });

        expect(wrapper.find('button').attributes('disabled')).toBeDefined();
    });
});
