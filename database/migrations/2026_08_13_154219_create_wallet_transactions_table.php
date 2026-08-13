<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promo_claim_id')->constrained()->cascadeOnDelete();

            $table->string('type');

            // Signed on purpose: positive credits, negative debits. The sum of
            // this column for a player must always equal their balance.
            $table->bigInteger('amount_cents');

            // Balance right after this entry was written, so the ledger can be
            // audited without replaying every row.
            $table->bigInteger('balance_after_cents');

            $table->timestamps();

            // One credit and at most one revoke per claim. This is what makes
            // a repeated revoke impossible even under a race: the second
            // insert collides here instead of silently debiting twice.
            $table->unique(['promo_claim_id', 'type']);

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
