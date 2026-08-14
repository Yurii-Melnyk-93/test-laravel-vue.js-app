<?php

namespace App\Services;

use App\Enums\RevokeRefusal;
use App\Enums\WalletTransactionType;
use App\Exceptions\PromoException;
use App\Models\PromoClaim;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;

/**
 * The only place allowed to move a balance.
 *
 * Every movement writes a ledger row and updates the balance in the same
 * transaction, so the sum of `wallet_transactions.amount_cents` for a player
 * always equals `users.balance_cents`.
 */
class WalletService
{
    public function credit(User $player, PromoClaim $claim, int $amountCents): WalletTransaction
    {
        return $this->apply($player, $claim, WalletTransactionType::PromoBonus, $amountCents);
    }

    /**
     * Takes a previously credited bonus back. `$amountCents` is positive; the
     * sign is applied here so no caller can accidentally debit upwards.
     *
     * @throws PromoException when the balance would go negative
     */
    public function debit(User $player, PromoClaim $claim, int $amountCents): WalletTransaction
    {
        return $this->apply($player, $claim, WalletTransactionType::PromoRevoke, -$amountCents);
    }

    private function apply(
        User $player,
        PromoClaim $claim,
        WalletTransactionType $type,
        int $signedAmountCents,
    ): WalletTransaction {
        return DB::transaction(function () use ($player, $claim, $type, $signedAmountCents) {
            // Re-read under a lock so two concurrent movements cannot both
            // compute a new balance from the same stale value.
            $locked = User::whereKey($player->getKey())->lockForUpdate()->firstOrFail();

            $balanceAfter = $locked->balance_cents + $signedAmountCents;

            // Checked here rather than by the caller because only under the
            // lock is the balance we are deciding on the one being written.
            if ($balanceAfter < 0) {
                throw PromoException::refused(RevokeRefusal::InsufficientBalance);
            }

            $transaction = WalletTransaction::create([
                'user_id' => $locked->getKey(),
                'promo_claim_id' => $claim->getKey(),
                'type' => $type,
                'amount_cents' => $signedAmountCents,
                'balance_after_cents' => $balanceAfter,
            ]);

            $locked->forceFill(['balance_cents' => $balanceAfter])->save();

            // Keep the caller's instance in step with what was just written.
            $player->setAttribute('balance_cents', $balanceAfter);

            return $transaction;
        });
    }
}
