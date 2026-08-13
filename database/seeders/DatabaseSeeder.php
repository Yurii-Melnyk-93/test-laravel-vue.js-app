<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Two players so the demo can show that balance and promo history
     * are isolated per player, not shared.
     */
    public function run(): void
    {
        $this->player('Олена Ковальчук', 'olena@example.com', 5_000);
        $this->player('Ігор Ткаченко', 'ihor@example.com', 12_550);

        $this->call(PromoCodeSeeder::class);
    }

    private function player(string $name, string $email, int $balanceCents): void
    {
        $player = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => Hash::make('password')],
        );

        // balance_cents is guarded, so it is set explicitly rather than
        // passed through mass assignment.
        $player->forceFill(['balance_cents' => $balanceCents])->save();
    }
}
