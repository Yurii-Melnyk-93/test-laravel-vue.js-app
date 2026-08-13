<?php

namespace Database\Factories;

use App\Models\PromoCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PromoCode>
 */
class PromoCodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Matches the format the API accepts: 6-12 latin letters and digits.
            'code' => Str::upper(Str::random(8)),
            'bonus_amount_cents' => $this->faker->numberBetween(1, 200) * 100,
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function expiringAt(\DateTimeInterface $moment): static
    {
        return $this->state(fn () => ['expires_at' => $moment]);
    }

    public function worth(int $cents): static
    {
        return $this->state(fn () => ['bonus_amount_cents' => $cents]);
    }
}
