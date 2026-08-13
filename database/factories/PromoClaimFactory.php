<?php

namespace Database\Factories;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromoClaim>
 */
class PromoClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $promoCode = PromoCode::factory();

        return [
            'user_id' => User::factory(),
            'promo_code_id' => $promoCode,
            'code_attempted' => fn (array $attributes) => PromoCode::find($attributes['promo_code_id'])?->code ?? 'UNKNOWN',
            'status' => ClaimStatus::Applied,
            'rejection_reason' => null,
            'amount_cents' => fn (array $attributes) => PromoCode::find($attributes['promo_code_id'])?->bonus_amount_cents ?? 1_000,
            'revoked_at' => null,
        ];
    }

    /**
     * A refused attempt: nothing was credited, and for an unknown code there
     * is no promo_code_id to point at.
     */
    public function rejected(RejectionReason $reason = RejectionReason::NotFound): static
    {
        return $this->state(fn () => [
            'status' => ClaimStatus::Rejected,
            'rejection_reason' => $reason,
            'amount_cents' => null,
            'promo_code_id' => $reason === RejectionReason::NotFound ? null : PromoCode::factory(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'status' => ClaimStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
