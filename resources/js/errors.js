const NETWORK_FAILURE = 'Не вдалося зв’язатися з сервером.';

/**
 * Turns a failed request into the text to show the player.
 *
 * The wording always comes from the server: a 422 carries the per-field
 * message in `errors`, everything else puts it in `message` next to the
 * machine readable `reason`. Keeping a second copy of that wording in the
 * client is what lets the two drift apart, so the client only picks the
 * fallback for the case the server never answered at all.
 */
export function messageFrom(error, fallback) {
    const data = error.response?.data;

    if (!data) {
        return NETWORK_FAILURE;
    }

    const firstFieldError = Object.values(data.errors ?? {})[0]?.[0];

    return firstFieldError ?? data.message ?? fallback;
}
