<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Enums\WalletTransactionType;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoClaimTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_code_credits_the_bonus_and_returns_the_new_balance(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $response = $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100']);

        $response->assertOk()
            ->assertJsonPath('bonus_amount.cents', 10_000)
            ->assertJsonPath('balance.cents', 15_000)
            ->assertJsonPath('balance.formatted', '150.00')
            ->assertJsonPath('claim.status', ClaimStatus::Applied->value)
            ->assertJsonPath('claim.code', 'WELCOME100')
            ->assertJsonPath('claim.can_revoke', true);

        $this->assertSame(15_000, $player->fresh()->balance_cents);
        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $player->id,
            'promo_code_id' => $code->id,
            'status' => ClaimStatus::Applied->value,
            'amount_cents' => 10_000,
        ]);
    }

    public function test_crediting_writes_a_ledger_row_that_matches_the_balance(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertOk();

        $entry = WalletTransaction::sole();

        $this->assertSame(WalletTransactionType::PromoBonus, $entry->type);
        $this->assertSame(10_000, $entry->amount_cents);
        $this->assertSame(15_000, $entry->balance_after_cents);

        // The invariant that makes the ledger trustworthy: opening balance
        // plus every movement equals the balance on the player.
        $this->assertSame(
            $player->fresh()->balance_cents,
            5_000 + (int) WalletTransaction::where('user_id', $player->id)->sum('amount_cents'),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function malformedCodes(): array
    {
        return [
            'too short' => ['ABC12'],
            'too long' => ['ABCDEFGHIJKLM'],
            'cyrillic' => ['ПРОМОКОД'],
            'with dash' => ['ABC-123'],
            'with space' => ['ABC 123'],
            'empty' => [''],
        ];
    }

    #[DataProvider('malformedCodes')]
    public function test_a_malformed_code_is_rejected_with_422(string $code): void
    {
        $player = User::factory()->withBalance(5_000)->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => $code])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        // A validation failure is not a claim attempt and must not be logged
        // as one, otherwise the history fills up with typos.
        $this->assertSame(0, PromoClaim::count());
        $this->assertSame(5_000, $player->fresh()->balance_cents);
    }

    public function test_an_unknown_code_is_refused_with_409_and_recorded(): void
    {
        $player = User::factory()->withBalance(5_000)->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'NOSUCH99'])
            ->assertStatus(409)
            ->assertJsonPath('reason', RejectionReason::NotFound->value);

        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $player->id,
            'promo_code_id' => null,
            'code_attempted' => 'NOSUCH99',
            'status' => ClaimStatus::Rejected->value,
            'rejection_reason' => RejectionReason::NotFound->value,
            'amount_cents' => null,
        ]);

        $this->assertSame(5_000, $player->fresh()->balance_cents);
        $this->assertSame(0, WalletTransaction::count());
    }

    public function test_an_expired_code_is_refused_with_409_and_recorded(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        $code = PromoCode::factory()->expired()->create(['code' => 'OLDCODE99']);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'OLDCODE99'])
            ->assertStatus(409)
            ->assertJsonPath('reason', RejectionReason::Expired->value);

        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $player->id,
            'promo_code_id' => $code->id,
            'status' => ClaimStatus::Rejected->value,
            'rejection_reason' => RejectionReason::Expired->value,
        ]);

        $this->assertSame(5_000, $player->fresh()->balance_cents);
    }

    public function test_the_same_code_cannot_be_claimed_twice(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertStatus(409)
            ->assertJsonPath('reason', RejectionReason::AlreadyUsed->value);

        // Credited exactly once.
        $this->assertSame(15_000, $player->fresh()->balance_cents);
        $this->assertSame(1, WalletTransaction::count());
        $this->assertSame(1, PromoClaim::where('status', ClaimStatus::Applied)->count());
    }

    public function test_a_revoked_claim_does_not_let_the_code_be_used_again(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        PromoClaim::factory()->for($player)->for($code)->revoked()->create();

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertStatus(409)
            ->assertJsonPath('reason', RejectionReason::AlreadyUsed->value);

        $this->assertSame(5_000, $player->fresh()->balance_cents);
    }

    public function test_codes_are_matched_case_insensitively(): void
    {
        $player = User::factory()->withBalance(0)->create();
        PromoCode::factory()->worth(2_500)->create(['code' => 'SUMMER25']);

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'summer25'])
            ->assertOk()
            ->assertJsonPath('balance.cents', 2_500);
    }

    public function test_one_players_claim_leaves_other_players_untouched(): void
    {
        $olena = User::factory()->withBalance(5_000)->create();
        $ihor = User::factory()->withBalance(12_550)->create();
        PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $this->actingAs($olena, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertOk();

        $this->assertSame(15_000, $olena->fresh()->balance_cents);
        $this->assertSame(12_550, $ihor->fresh()->balance_cents);

        // The same code is still available to everyone else.
        $this->app['auth']->forgetGuards();

        $this->actingAs($ihor, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertOk();

        $this->assertSame(22_550, $ihor->fresh()->balance_cents);
    }

    public function test_the_player_comes_from_the_token_and_never_from_the_payload(): void
    {
        $olena = User::factory()->withBalance(5_000)->create();
        $ihor = User::factory()->withBalance(12_550)->create();
        PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $this->actingAs($olena, 'sanctum')
            ->postJson('/api/promo/claim', [
                'code' => 'WELCOME100',
                'user_id' => $ihor->id,
                'player_id' => $ihor->id,
            ])
            ->assertOk();

        $this->assertSame(15_000, $olena->fresh()->balance_cents);
        $this->assertSame(12_550, $ihor->fresh()->balance_cents);
    }

    /**
     * The check for "already used" can be overtaken: two requests may both
     * pass it before either has written anything. This simulates exactly that
     * by slipping a competing row in between the check and the insert, which
     * is the only way to reach the catch branch that turns the index
     * violation into an ordinary business error.
     */
    public function test_losing_a_race_looks_like_an_ordinary_already_used_refusal(): void
    {
        $player = User::factory()->withBalance(5_000)->create();
        $code = PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        $competitorHasWritten = false;

        PromoClaim::creating(function (PromoClaim $claim) use (&$competitorHasWritten, $player, $code) {
            if ($competitorHasWritten || $claim->status !== ClaimStatus::Applied) {
                return;
            }

            $competitorHasWritten = true;

            DB::table('promo_claims')->insert([
                'user_id' => $player->id,
                'promo_code_id' => $code->id,
                'code_attempted' => 'WELCOME100',
                'status' => ClaimStatus::Applied->value,
                'amount_cents' => 10_000,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->actingAs($player, 'sanctum')
            ->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertStatus(409)
            ->assertJsonPath('reason', RejectionReason::AlreadyUsed->value);

        // The loser credited nothing: no ledger row, balance untouched.
        $this->assertSame(0, WalletTransaction::count());
        $this->assertSame(5_000, $player->fresh()->balance_cents);

        // And the refusal was still recorded for the history.
        $this->assertDatabaseHas('promo_claims', [
            'user_id' => $player->id,
            'status' => ClaimStatus::Rejected->value,
            'rejection_reason' => RejectionReason::AlreadyUsed->value,
        ]);
    }

    public function test_claiming_requires_authentication(): void
    {
        PromoCode::factory()->create(['code' => 'WELCOME100']);

        $this->postJson('/api/promo/claim', ['code' => 'WELCOME100'])
            ->assertStatus(401);

        $this->assertSame(0, PromoClaim::count());
    }
}
