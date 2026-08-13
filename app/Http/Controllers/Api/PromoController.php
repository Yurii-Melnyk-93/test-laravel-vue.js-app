<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClaimPromoRequest;
use App\Http\Requests\PromoHistoryRequest;
use App\Http\Resources\PromoClaimResource;
use App\Services\PromoService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function history(PromoHistoryRequest $request): AnonymousResourceCollection
    {
        // Scoped through the relation, so the query can only ever reach the
        // claims of the player behind the token.
        $claims = $request->user()
            ->promoClaims()
            ->withStatus($request->status())
            ->latest('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return PromoClaimResource::collection($claims);
    }
}
