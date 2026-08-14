import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ConfirmDialog from './ConfirmDialog.vue';

function mountDialog(props = {}) {
    return mount(ConfirmDialog, {
        attachTo: document.body,
        props: { open: true, title: 'Скасувати нарахування?', ...props },
    });
}

function button(wrapper, text) {
    return wrapper.findAll('button').find((b) => b.text() === text);
}

describe('ConfirmDialog', () => {
    it('renders nothing while closed', () => {
        const wrapper = mountDialog({ open: false });

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('shows the question and confirms it', async () => {
        const wrapper = mountDialog({
            message: 'Бонус 100.00 буде знято з балансу.',
            confirmLabel: 'Так, скасувати',
        });

        expect(wrapper.text()).toContain('Скасувати нарахування?');
        expect(wrapper.text()).toContain('Бонус 100.00 буде знято з балансу.');

        await button(wrapper, 'Так, скасувати').trigger('click');

        expect(wrapper.emitted('confirm')).toHaveLength(1);
    });

    it('cancels through the button, the backdrop and Escape', async () => {
        const wrapper = mountDialog();

        await button(wrapper, 'Закрити').trigger('click');
        await wrapper.find('.fixed').trigger('click');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('cancel')).toHaveLength(3);
        expect(wrapper.emitted('confirm')).toBeUndefined();
    });

    it('cannot be cancelled while the request is in flight', async () => {
        const wrapper = mountDialog({ busy: true });

        await button(wrapper, 'Закрити').trigger('click');
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        // Closing mid-request would hide an operation the user cannot see the
        // outcome of, so it is refused until the answer arrives.
        expect(wrapper.emitted('cancel')).toBeUndefined();
        expect(wrapper.text()).toContain('Виконується…');
    });

    it('focuses the confirm button so the keyboard can answer', async () => {
        const wrapper = mountDialog({ confirmLabel: 'Так, скасувати' });
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(button(wrapper, 'Так, скасувати').element);
    });

    it('stops listening for Escape once it closes', async () => {
        const wrapper = mountDialog();

        await wrapper.setProps({ open: false });
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));

        expect(wrapper.emitted('cancel')).toBeUndefined();
    });
});
