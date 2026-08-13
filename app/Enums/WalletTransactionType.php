<?php

namespace App\Enums;

enum WalletTransactionType: string
{
    /** Credit: a promo bonus landed on the balance. */
    case PromoBonus = 'promo_bonus';

    /** Debit: a previously credited promo bonus was taken back. */
    case PromoRevoke = 'promo_revoke';
}
