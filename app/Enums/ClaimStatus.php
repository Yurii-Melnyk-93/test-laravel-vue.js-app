<?php

namespace App\Enums;

enum ClaimStatus: string
{
    /** The bonus was credited and is currently on the balance. */
    case Applied = 'applied';

    /** The attempt failed; nothing was credited. Kept for the history filter. */
    case Rejected = 'rejected';

    /** The bonus was credited and later taken back. */
    case Revoked = 'revoked';

    /**
     * A rejected attempt never consumed the code, so it must not block
     * the player from trying the same code again.
     */
    public function consumesCode(): bool
    {
        return $this !== self::Rejected;
    }
}
