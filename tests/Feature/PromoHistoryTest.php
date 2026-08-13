<?php

namespace Tests\Feature;

use App\Enums\ClaimStatus;
use App\Enums\RejectionReason;
use App\Models\PromoClaim;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PromoHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_shows_only_the_claims_of_the_player_behind_the_token(): void
    {
        $olena = User::factory()->create();
        $ihor = User::factory()->create();

        PromoClaim::factory()->count(2)->for($olena)->create();
        PromoClaim::factory()->count(3)->for($ihor)->create();

        $response = $this->actingAs($olena, 'sanctum')
            ->getJson('/api/promo/history')
            ->assertOk();

        $this->assertCount(2, $response->json('data'));

        $ownedIds = $olena->promoClaims()->pluck('id')->all();
        foreach ($response->json('data') as $row) {
            $this->assertContains($row['id'], $ownedIds);
        }
    }

    public function test_a_row_carries_the_date_amount_and_status(): void
    {
        $player = User::factory()->create();
        $code = PromoCode::factory()->worth(10_000)->create(['code' => 'WELCOME100']);

        PromoClaim::factory()->for($player)->for($code)->create(['amount_cents' => 10_000]);

        $row = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history')
            ->assertOk()
            ->json('data.0');

        $this->assertSame('WELCOME100', $row['code']);
        $this->assertSame(ClaimStatus::Applied->value, $row['status']);
        $this->assertSame(10_000, $row['amount']['cents']);
        $this->assertSame('100.00', $row['amount']['formatted']);
        $this->assertNotNull($row['created_at']);
        $this->assertTrue($row['can_revoke']);
    }

    public function test_a_rejected_row_explains_itself_and_carries_no_amount(): void
    {
        $player = User::factory()->create();

        PromoClaim::factory()->for($player)->rejected(RejectionReason::Expired)->create();

        $row = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?status=rejected')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(ClaimStatus::Rejected->value, $row['status']);
        $this->assertSame(RejectionReason::Expired->value, $row['rejection_reason']);
        $this->assertSame(RejectionReason::Expired->message(), $row['rejection_message']);
        $this->assertNull($row['amount']);

        // Nothing was credited, so there is nothing to take back.
        $this->assertFalse($row['can_revoke']);
    }

    public function test_newest_claims_come_first(): void
    {
        $player = User::factory()->create();

        $first = PromoClaim::factory()->for($player)->create();
        $second = PromoClaim::factory()->for($player)->create();
        $third = PromoClaim::factory()->for($player)->create();

        $ids = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history')
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$third->id, $second->id, $first->id], $ids);
    }

    public function test_results_are_paginated(): void
    {
        $player = User::factory()->create();
        PromoClaim::factory()->count(7)->for($player)->create();

        $firstPage = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?per_page=3')
            ->assertOk();

        $this->assertCount(3, $firstPage->json('data'));
        $this->assertSame(7, $firstPage->json('meta.total'));
        $this->assertSame(1, $firstPage->json('meta.current_page'));
        $this->assertSame(3, $firstPage->json('meta.last_page'));

        $lastPage = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?per_page=3&page=3')
            ->assertOk();

        $this->assertCount(1, $lastPage->json('data'));
    }

    /**
     * @return array<string, array{ClaimStatus}>
     */
    public static function statuses(): array
    {
        return [
            'applied' => [ClaimStatus::Applied],
            'rejected' => [ClaimStatus::Rejected],
            'revoked' => [ClaimStatus::Revoked],
        ];
    }

    #[DataProvider('statuses')]
    public function test_the_status_filter_returns_only_that_status(ClaimStatus $status): void
    {
        $player = User::factory()->create();

        PromoClaim::factory()->for($player)->create();
        PromoClaim::factory()->for($player)->rejected()->create();
        PromoClaim::factory()->for($player)->revoked()->create();

        $rows = $this->actingAs($player, 'sanctum')
            ->getJson("/api/promo/history?status={$status->value}")
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $rows);
        $this->assertSame($status->value, $rows[0]['status']);
    }

    public function test_without_a_filter_every_status_is_returned(): void
    {
        $player = User::factory()->create();

        PromoClaim::factory()->for($player)->create();
        PromoClaim::factory()->for($player)->rejected()->create();
        PromoClaim::factory()->for($player)->revoked()->create();

        $rows = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $rows);
    }

    public function test_an_unknown_status_is_rejected_instead_of_silently_ignored(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?status=whatever')
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_per_page_is_capped(): void
    {
        $player = User::factory()->create();

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?per_page=500')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');

        $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?per_page=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors('per_page');
    }

    public function test_the_filter_survives_into_the_pagination_links(): void
    {
        $player = User::factory()->create();
        PromoClaim::factory()->count(4)->for($player)->rejected()->create();

        $next = $this->actingAs($player, 'sanctum')
            ->getJson('/api/promo/history?status=rejected&per_page=2')
            ->assertOk()
            ->json('links.next');

        // Otherwise page two silently drops the filter.
        $this->assertStringContainsString('status=rejected', $next);
    }

    public function test_history_requires_authentication(): void
    {
        $this->getJson('/api/promo/history')->assertStatus(401);
    }
}
