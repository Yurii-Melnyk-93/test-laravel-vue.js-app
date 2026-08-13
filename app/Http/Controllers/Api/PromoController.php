<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimPromoRequest;
use App\Http\Resources\PromoClaimResource;
use App\Services\PromoService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;

class PromoController extends Controller
{
    public function __construct(private readonly PromoService $promo) {}

    public function claim(ClaimPromoRequest $request): JsonResponse
    {
        $player = $request->user();

        $claim = $this->promo->claim($player, $request->validated('code'));

        return response()->json([
            'claim' => new PromoClaimResource($claim),
            'bonus_amount' => Money::toArray($claim->amount_cents),
            'balance' => Money::toArray($player->balance_cents),
        ]);
    }
}
