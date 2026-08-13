<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Enums\WalletTransactionType;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These tests deliberately bypass the application layer and write straight to
 * the database. The point is to prove the guarantees hold in the schema
 * itself, so they survive a bug, a race, or a future careless code path.
 */
class SchemaGuardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_player_cannot_hold_two_claims_of_the_same_code(): void
    {
        $player = User::factory()->create();
        $code = PromoCode::factory()->create();

        PromoClaim::factory()->for($player)->for($code)->create();

        $this->assertViolates(
            'promo_claims.user_id, promo_claims.promo_code_id',
            fn () => PromoClaim::factory()->for($player)->for($code)->create(),
        );
    }

    public function test_revoking_does_not_free_the_code_for_a_second_claim(): void
    {
        $player = User::factory()->create();
        $code = PromoCode::factory()->create();

        PromoClaim::factory()->for($player)->for($code)->revoked()->create();

        // Otherwise claim -> revoke -> claim would print money.
        $this->assertViolates(
            'promo_claims.user_id, promo_claims.promo_code_id',
            fn () => PromoClaim::factory()->for($player)->for($code)->create(),
        );
    }

    public function test_rejected_attempts_do_not_block_retrying_the_same_code(): void
    {
        $player = User::factory()->create();
        $code = PromoCode::factory()->expired()->create();

        PromoClaim::factory()->for($player)->for($code)->create([
            'status' => ClaimStatus::Rejected,
            'rejection_reason' => RejectionReason::Expired,
            'amount_cents' => null,
        ]);

        PromoClaim::factory()->for($player)->for($code)->create([
            'status' => ClaimStatus::Rejected,
            'rejection_reason' => RejectionReason::Expired,
            'amount_cents' => null,
        ]);

        $this->assertSame(2, PromoClaim::count());
    }

    public function test_different_players_may_claim_the_same_code(): void
    {
        $code = PromoCode::factory()->create();

        PromoClaim::factory()->for(User::factory())->for($code)->create();
        PromoClaim::factory()->for(User::factory())->for($code)->create();

        $this->assertSame(2, PromoClaim::count());
    }

    public function test_a_claim_cannot_be_debited_twice(): void
    {
        $claim = PromoClaim::factory()->create();

        $this->ledgerEntry($claim, WalletTransactionType::PromoRevoke, -1_000);

        // A repeated revoke collides here even if two requests race past the
        // status check at the same moment.
        $this->assertViolates(
            'wallet_transactions.promo_claim_id, wallet_transactions.type',
            fn () => $this->ledgerEntry($claim, WalletTransactionType::PromoRevoke, -1_000),
        );
    }

    public function test_a_claim_may_have_one_credit_and_one_debit(): void
    {
        $claim = PromoClaim::factory()->create();

        $this->ledgerEntry($claim, WalletTransactionType::PromoBonus, 1_000);
        $this->ledgerEntry($claim, WalletTransactionType::PromoRevoke, -1_000);

        $this->assertSame(2, WalletTransaction::count());
        $this->assertSame(0, (int) WalletTransaction::sum('amount_cents'));
    }

    public function test_promo_codes_are_stored_upper_cased(): void
    {
        $code = PromoCode::create([
            'code' => 'welcome100',
            'bonus_amount_cents' => 10_000,
        ]);

        $this->assertSame('WELCOME100', $code->fresh()->code);
    }

    /**
     * Asserts the write failed on one specific uniqueness rule, named by the
     * columns SQLite reports. Without pinning the columns a QueryException
     * from anything at all would make the test pass by accident.
     */
    private function assertViolates(string $constraint, callable $write): void
    {
        try {
            $write();
        } catch (QueryException $e) {
            $this->assertStringContainsString("UNIQUE constraint failed: {$constraint}", $e->getMessage());

            return;
        }

        $this->fail("Expected the write to be rejected by [{$constraint}], but it succeeded.");
    }

    private function ledgerEntry(PromoClaim $claim, WalletTransactionType $type, int $amountCents): WalletTransaction
    {
        return WalletTransaction::create([
            'user_id' => $claim->user_id,
            'promo_claim_id' => $claim->id,
            'type' => $type,
            'amount_cents' => $amountCents,
            'balance_after_cents' => 0,
        ]);
    }
}
