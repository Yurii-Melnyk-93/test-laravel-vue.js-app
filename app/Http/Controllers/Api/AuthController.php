<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\PlayerResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $player = User::where('email', $request->validated('email'))->first();

        // One message for both "no such user" and "wrong password" so the
        // endpoint cannot be used to find out which emails are registered.
        if (! $player || ! Hash::check($request->validated('password'), $player->password)) {
            throw ValidationException::withMessages([
                'email' => 'Невірний email або пароль.',
            ]);
        }

        return response()->json([
            'token' => $player->createToken('promo-spa')->plainTextToken,
            'player' => new PlayerResource($player),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'player' => new PlayerResource($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token that made this call, not every session.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вихід виконано.']);
    }
}
