<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use Illuminate\Database\Seeder;

class PromoCodeSeeder extends Seeder
{
    /**
     * Codes covering every branch the reviewer needs to see: a plain valid
     * one, a second valid one to claim after the first, one that expires in
     * the future, and one that is already dead.
     */
    public function run(): void
    {
        $codes = [
            ['code' => 'WELCOME100', 'bonus_amount_cents' => 10_000, 'expires_at' => null],
            ['code' => 'BONUS50', 'bonus_amount_cents' => 5_000, 'expires_at' => null],
            ['code' => 'SUMMER25', 'bonus_amount_cents' => 2_500, 'expires_at' => now()->addMonth()],
            ['code' => 'OLDCODE99', 'bonus_amount_cents' => 9_900, 'expires_at' => now()->subWeek()],
        ];

        foreach ($codes as $code) {
            PromoCode::updateOrCreate(['code' => $code['code']], $code);
        }
    }
}
