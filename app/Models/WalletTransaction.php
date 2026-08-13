<?php

namespace App\Models;

use App\Enums\WalletTransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger. Rows are never updated or deleted: a mistake is
 * corrected by writing an opposite entry, never by rewriting history.
 */
#[Fillable([
    'user_id',
    'promo_claim_id',
    'type',
    'amount_cents',
    'balance_after_cents',
])]
class WalletTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => WalletTransactionType::class,
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function promoClaim(): BelongsTo
    {
        return $this->belongsTo(PromoClaim::class);
    }
}
