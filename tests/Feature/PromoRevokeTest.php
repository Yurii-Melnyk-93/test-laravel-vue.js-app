<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Enums\RevokeRefusal;
use App\Enums\WalletTransactionType;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PromoRevokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_takes_the_bonus_back_and_returns_the_new_balance(): void
    {
        $player = User::factory()->withBalance(15_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);
        $claim = PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $response = $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke");

        $response->assertOk()
            ->assertJsonPath('balance.cents', 5_000)
            ->assertJsonPath('balance.formatted', '50.00')
            ->assertJsonPath('claim.status', ClaimStatus::Revoked->value)
            ->assertJsonPath('claim.can_revoke', false);

        $this->assertNotNull($response->json('claim.revoked_at'));
        $this->assertSame(5_000, $player->fresh()->balance_cents);

        $claim->refresh();
        $this->assertSame(ClaimStatus::Revoked, $claim->status);
        $this->assertNotNull($claim->revoked_at);
    }

    public function test_revoking_writes_a_negative_ledger_row_that_matches_the_balance(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $claim = $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertOk()
            ->json('claim.id');

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim}/revoke")
            ->assertOk();

        $debit = WalletTransaction::where('type', WalletTransactionType::PromoRevoke)->sole();

        $this->assertSame(-10_000, $debit->amount_cents);
        $this->assertSame(5_000, $debit->balance_after_cents);

        // Opening balance plus every movement still equals the balance.
        $this->assertSame(
            $player->fresh()->balance_cents,
            5_000 + (int) WalletTransaction::where('user_id', $player->id)->sum('amount_cents'),
        );
    }

    public function test_a_second_revoke_is_refused_and_does_not_debit_twice(): void
    {
        $player = User::factory()->withBalance(15_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create();
        $claim = PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('reason', RevokeRefusal::AlreadyRevoked->value);

        $this->assertSame(5_000, $player->fresh()->balance_cents);
        $this->assertSame(1, WalletTransaction::where('type', WalletTransactionType::PromoRevoke)->count());
    }

    /**
     * The status check can be overtaken: two requests may both see "applied"
     * before either has written anything. Slipping a competing ledger row in
     * between the check and the debit reproduces exactly that, and is the only
     * way to reach the branch that turns the unique violation into a 409.
     */
    public function test_losing_a_race_looks_like_an_ordinary_already_revoked_refusal(): void
    {
        $player = User::factory()->withBalance(15_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create();
        $claim = PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $competitorHasWritten = false;

        WalletTransaction::creating(function (WalletTransaction $entry) use (&$competitorHasWritten, $player, $claim) {
            if ($competitorHasWritten) {
                return;
            }

            $competitorHasWritten = true;

            DB::table('wallet_transactions')->insert([
                'user_id' => $player->id,
                'promo_claim_id' => $claim->id,
                'type' => WalletTransactionType::PromoRevoke->value,
                'amount_cents' => -10_000,
                'balance_after_cents' => 5_000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('reason', RevokeRefusal::AlreadyRevoked->value);

        // The loser debited nothing. The competitor's row is gone from the
        // assertion too — it was written inside the transaction that rolled
        // back, which a real competitor on its own connection would not be —
        // but what matters holds either way: this request committed no debit
        // and no balance change.
        $this->assertSame(0, WalletTransaction::where('type', WalletTransactionType::PromoRevoke)->count());
        $this->assertSame(15_000, $player->fresh()->balance_cents);
        $this->assertSame(ClaimStatus::Applied, $claim->fresh()->status);
    }

    public function test_a_rejected_claim_cannot_be_revoked(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        $claim = PromoClaim::factory()->for($player)->rejected(RejectionReason::Expired)->create();

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('reason', RevokeRefusal::NotApplied->value);

        $this->assertSame(5_000, $player->fresh()->balance_cents);
        $this->assertSame(ClaimStatus::Rejected, $claim->fresh()->status);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_revoking_is_refused_when_the_bonus_is_already_spent(): void
    {
        // The bonus landed and most of the balance has since been played out.
        $player = User::factory()->withBalance(2_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create();
        $claim = PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $this->actingAs($player, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(409)
            ->assertJsonPath('reason', RevokeRefusal::InsufficientBalance->value);

        // No negative balance, no partial debit, and the claim stays applied
        // so an operator can retry once the player tops up.
        $this->assertSame(2_000, $player->fresh()->balance_cents);
        $this->assertSame(ClaimStatus::Applied, $claim->fresh()->status);
        $this->assertNull($claim->fresh()->revoked_at);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_another_players_claim_is_not_found_rather_than_forbidden(): void
    {
        $olena = User::factory()->withBalance(15_000)->create();
        $ihor = User::factory()->withBalance(12_550)->create();
        $code = PromoCode::factory()->worth(10_000)->create();
        $claim = PromoClaim::factory()->for($ihor)->for($code)->create(['amount_cents' => 10_000]);

        $this->actingAs($olena, 'sanctum')
            ->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(404);

        $this->assertSame(12_550, $ihor->fresh()->balance_cents);
        $this->assertSame(15_000, $olena->fresh()->balance_cents);
        $this->assertSame(ClaimStatus::Applied, $claim->fresh()->status);
    }

    public function test_revoking_requires_authentication(): void
    {
        $player = User::factory()->withBalance(15_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create();
        $claim = PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $this->patchJson("/api/promo/{$claim->id}/revoke")
            ->assertStatus(401);

        $this->assertSame(15_000, $player->fresh()->balance_cents);
        $this->assertSame(ClaimStatus::Applied, $claim->fresh()->status);
    }
}
