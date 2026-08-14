import { describe, expect, it } from 'vitest';
import { messageFrom } from './errors.js';

function failure(status, data) {
    return { response: { status, data } };
}

describe('messageFrom', () => {
    it('prefers the per-field message of a 422', () => {
        const error = failure(422, {
            message: 'The given data was invalid.',
            errors: { code: ['Промокод має містити 6–12 латинських літер або цифр.'] },
        });

        expect(messageFrom(error, 'запасний')).toBe(
            'Промокод має містити 6–12 латинських літер або цифр.',
        );
    });

    it('shows the human message of a business refusal', () => {
        const error = failure(409, { message: 'Це нарахування вже скасоване.', reason: 'already_revoked' });

        expect(messageFrom(error, 'запасний')).toBe('Це нарахування вже скасоване.');
    });

    it('falls back only when the server said nothing useful', () => {
        expect(messageFrom(failure(500, {}), 'запасний')).toBe('запасний');
    });

    it('reports a network failure when there was no response at all', () => {
        expect(messageFrom(new Error('Network Error'), 'запасний')).toBe(
            'Не вдалося зв’язатися з сервером.',
        );
    });
});
