<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiAuthController extends Controller
{
    /**
     * Issue a short-lived access token from client credentials (OAuth2 client_credentials flow).
     *
     * POST /api/auth/token
     * Body: { "client_id": "acs_xxx", "client_secret": "acsk_xxx" }
     * Returns: { "access_token": "...", "token_type": "Bearer", "expires_in": 3600 }
     */
    public function issueToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'client_id' => ['required', 'string', 'max:64'],
            'client_secret' => ['required', 'string', 'max:200'],
        ]);

        $client = ApiClient::where('client_id', $validated['client_id'])
            ->whereNull('revoked_at')
            ->first();

        if (! $client || ! Hash::check($validated['client_secret'], $client->client_secret_hash)) {
            return response()->json([
                'error' => 'invalid_client',
                'message' => 'Client credentials ไม่ถูกต้อง หรือถูก revoke แล้ว',
            ], 401);
        }

        if ($client->allowed_ips) {
            $allowed = array_map('trim', explode(',', $client->allowed_ips));

            if (! in_array($request->ip(), $allowed, true)) {
                return response()->json([
                    'error' => 'ip_not_allowed',
                    'message' => "IP {$request->ip()} ไม่ได้รับอนุญาตให้ใช้ client นี้",
                ], 403);
            }
        }

        $accessToken = 'at_'.Str::random(48);
        $expiresIn = 3600;

        Cache::put("api:access_token:{$accessToken}", [
            'client_id' => $client->client_id,
            'client_pk' => $client->id,
            'user_id' => $client->user_id,
            'scopes' => $client->scopes ?? ['api:write'],
            'issued_at' => now()->toIso8601String(),
        ], $expiresIn);

        $client->update(['last_used_at' => now()]);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scopes' => $client->scopes ?? ['api:write'],
        ]);
    }

    /**
     * Revoke current access token (clears from cache).
     * POST /api/auth/revoke
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            Cache::forget("api:access_token:{$token}");
        }

        return response()->json(['status' => 'revoked']);
    }

    /**
     * Return info about the currently authenticated client.
     * GET /api/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $auth = $request->attributes->get('api_auth');

        return response()->json([
            'client_id' => $auth['client_id'] ?? null,
            'user_id' => $auth['user_id'] ?? null,
            'scopes' => $auth['scopes'] ?? [],
            'issued_at' => $auth['issued_at'] ?? null,
        ]);
    }
}
