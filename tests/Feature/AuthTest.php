<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token_and_the_player_balance(): void
    {
        User::factory()->withBalance(5_000)->create(['email' => 'olena@example.com']);

        $response = $this->postJson('/api/login', [
            'email' => 'olena@example.com',
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'player' => ['id', 'name', 'email', 'balance']])
            ->assertJsonPath('player.balance.cents', 5_000)
            ->assertJsonPath('player.balance.formatted', '50.00');
    }

    public function test_login_never_returns_the_password_hash(): void
    {
        User::factory()->create(['email' => 'olena@example.com']);

        $this->postJson('/api/login', [
            'email' => 'olena@example.com',
            'password' => 'password',
        ])->assertOk()->assertJsonMissingPath('player.password');
    }

    public function test_wrong_password_and_unknown_email_are_indistinguishable(): void
    {
        User::factory()->create(['email' => 'olena@example.com']);

        $wrongPassword = $this->postJson('/api/login', [
            'email' => 'olena@example.com',
            'password' => 'not-the-password',
        ]);

        $unknownEmail = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'password',
        ]);

        $wrongPassword->assertStatus(422);
        $unknownEmail->assertStatus(422);

        // Identical response bodies, otherwise the endpoint leaks which
        // emails are registered.
        $this->assertSame($wrongPassword->json(), $unknownEmail->json());
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_me_requires_a_token(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    public function test_me_returns_the_player_the_token_belongs_to(): void
    {
        $olena = User::factory()->withBalance(5_000)->create();
        User::factory()->withBalance(99_999)->create();

        $this->actingAs($olena, 'sanctum')
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('player.id', $olena->id)
            ->assertJsonPath('player.balance.cents', 5_000);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $player = User::factory()->create();
        $keptToken = $player->createToken('other-device')->plainTextToken;
        $usedToken = $player->createToken('this-device')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$usedToken}")
            ->postJson('/api/logout')
            ->assertOk();

        // The guard caches the resolved user for the lifetime of the test
        // application, so without this the next call would still see the
        // already authenticated player and the assertion would pass falsely.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$usedToken}")
            ->getJson('/api/me')
            ->assertStatus(401);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$keptToken}")
            ->getJson('/api/me')
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'other-device']);
    }
}
