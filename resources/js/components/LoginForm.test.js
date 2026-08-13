import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import LoginForm from './LoginForm.vue';
import api, { setToken } from '../api.js';

vi.mock('../api.js', () => ({
    default: { post: vi.fn() },
    setToken: vi.fn(),
}));

async function signIn(wrapper, email = 'olena@example.com', password = 'password') {
    const [emailField, passwordField] = wrapper.findAll('input');

    await emailField.setValue(email);
    await passwordField.setValue(password);
    await wrapper.find('form').trigger('submit');
    await flushPromises();
}

describe('LoginForm', () => {
    it('stores the token and hands the player to the parent', async () => {
        const player = { id: 1, name: 'Олена Ковальчук', balance: { formatted: '50.00' } };
        api.post.mockResolvedValue({ data: { token: 'plain-text-token', player } });

        const wrapper = mount(LoginForm);
        await signIn(wrapper);

        expect(api.post).toHaveBeenCalledWith('/login', {
            email: 'olena@example.com',
            password: 'password',
        });
        expect(setToken).toHaveBeenCalledWith('plain-text-token');
        expect(wrapper.emitted('authenticated')[0][0]).toEqual(player);
    });

    it('shows the error and keeps the session closed on bad credentials', async () => {
        api.post.mockRejectedValue(
            Object.assign(new Error('Request failed'), {
                response: { status: 422, data: { message: 'Невірний email або пароль.' } },
            }),
        );

        const wrapper = mount(LoginForm);
        await signIn(wrapper, 'olena@example.com', 'wrong');

        expect(wrapper.text()).toContain('Невірний email або пароль.');
        expect(setToken).not.toHaveBeenCalled();
        expect(wrapper.emitted('authenticated')).toBeUndefined();
    });

    it('falls back to a readable message when the server cannot be reached', async () => {
        api.post.mockRejectedValue(new Error('Network Error'));

        const wrapper = mount(LoginForm);
        await signIn(wrapper);

        expect(wrapper.text()).toContain('Не вдалося зв’язатися з сервером.');
    });

    it('never renders the credentials as demo hints', () => {
        const wrapper = mount(LoginForm);

        // They belong in the README; leaking them into the UI was a bug once.
        expect(wrapper.text()).not.toContain('example.com');
    });
});
