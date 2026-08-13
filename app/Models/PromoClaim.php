<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use Database\Factories\PromoClaimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'promo_code_id',
    'code_attempted',
    'status',
    'rejection_reason',
    'amount_cents',
    'revoked_at',
])]
class PromoClaim extends Model
{
    /** @use HasFactory<PromoClaimFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ClaimStatus::class,
            'rejection_reason' => RejectionReason::class,
            'amount_cents' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    /** Only an applied claim can be revoked. */
    public function isRevocable(): bool
    {
        return $this->status === ClaimStatus::Applied;
    }

    public function scopeWithStatus(Builder $query, ?ClaimStatus $status): Builder
    {
        return $status === null ? $query : $query->where('status', $status);
    }
}
