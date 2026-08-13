<?php

namespace App\Http\Resources;

use App\Models\PromoClaim;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PromoClaim */
class PromoClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code_attempted,
            'status' => $this->status->value,
            'rejection_reason' => $this->rejection_reason?->value,
            'rejection_message' => $this->rejection_reason?->message(),

            // Null for rejected attempts: nothing was credited.
            'amount' => $this->amount_cents === null ? null : Money::toArray($this->amount_cents),

            // Sent so the UI does not have to reimplement the rule for which
            // rows may show a revoke button.
            'can_revoke' => $this->isRevocable(),

            'created_at' => $this->created_at?->toIso8601String(),
            'revoked_at' => $this->revoked_at?->toIso8601String(),
        ];
    }
}
