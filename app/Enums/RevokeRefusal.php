<?php

namespace App\Enums;

/**
 * Why a revoke was refused.
 *
 * Unlike a claim attempt, a refused revoke changes nothing in the database:
 * there is no row to store this on, it only travels to the client as the
 * machine readable `reason` of a 409.
 */
enum RevokeRefusal: string
{
    /** The claim was never applied, so there is nothing to take back. */
    case NotApplied = 'not_applied';

    /** The bonus was already taken back. */
    case AlreadyRevoked = 'already_revoked';

    /** The player has already spent the bonus; we refuse to go negative. */
    case InsufficientBalance = 'insufficient_balance';

    public function message(): string
    {
        return match ($this) {
            self::NotApplied => 'Це нарахування не було застосоване, скасовувати нічого.',
            self::AlreadyRevoked => 'Це нарахування вже скасоване.',
            self::InsufficientBalance => 'Недостатньо коштів на балансі для скасування нарахування.',
        };
    }
}
