<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Exceptions\PromoException;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class PromoService
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Credits the bonus behind a promo code to the player.
     *
     * Every refusal is written to `promo_claims` as a rejected row before the
     * exception is thrown, which is what makes the "rejected" filter on the
     * history endpoint meaningful.
     *
     * @throws PromoException when a business rule refuses the claim
     */
    public function claim(User $player, string $code): PromoClaim
    {
        $code = mb_strtoupper(trim($code));

        $promoCode = PromoCode::where('code', $code)->first();

        if ($promoCode === null) {
            $this->reject($player, $code, null, RejectionReason::NotFound);
        }

        if ($promoCode->hasExpired()) {
            $this->reject($player, $code, $promoCode, RejectionReason::Expired);
        }

        if ($this->alreadyConsumed($player, $promoCode)) {
            $this->reject($player, $code, $promoCode, RejectionReason::AlreadyUsed);
        }

        try {
            return DB::transaction(function () use ($player, $promoCode, $code) {
                $claim = PromoClaim::create([
                    'user_id' => $player->getKey(),
                    'promo_code_id' => $promoCode->getKey(),
                    'code_attempted' => $code,
                    'status' => ClaimStatus::Applied,
                    'amount_cents' => $promoCode->bonus_amount_cents,
                ]);

                $this->wallet->credit($player, $claim, $promoCode->bonus_amount_cents);

                return $claim;
            });
        } catch (UniqueConstraintViolationException) {
            // Two identical requests raced past the check above and this one
            // lost. The transaction already rolled back, so nothing was
            // credited twice — for the caller it is simply "already used".
            $this->reject($player, $code, $promoCode, RejectionReason::AlreadyUsed);
        }
    }

    /**
     * Mirrors the predicate of the `promo_claims_one_per_player_per_code`
     * index: a rejected attempt never consumed the code, anything else did.
     */
    private function alreadyConsumed(User $player, PromoCode $promoCode): bool
    {
        return PromoClaim::query()
            ->where('user_id', $player->getKey())
            ->where('promo_code_id', $promoCode->getKey())
            ->where('status', '!=', ClaimStatus::Rejected)
            ->exists();
    }

    /**
     * Records the failed attempt and aborts.
     *
     * Deliberately called outside the crediting transaction: a rollback would
     * take the rejected row down with it and the attempt would vanish.
     */
    private function reject(User $player, string $code, ?PromoCode $promoCode, RejectionReason $reason): never
    {
        PromoClaim::create([
            'user_id' => $player->getKey(),
            'promo_code_id' => $promoCode?->getKey(),
            'code_attempted' => $code,
            'status' => ClaimStatus::Rejected,
            'rejection_reason' => $reason,
            'amount_cents' => null,
        ]);

        throw PromoException::rejected($reason);
    }
}
