<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiToken
{
    /**
     * Validate Bearer access token issued by /api/auth/token.
     *
     * Usage: ->middleware('api.token') or ->middleware('api.token:api:write')
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'missing_token',
                'message' => 'Authorization header missing or malformed',
            ], 401);
        }

        $auth = Cache::get("api:access_token:{$token}");

        if (! $auth) {
            return response()->json([
                'error' => 'invalid_token',
                'message' => 'Access token ไม่ถูกต้องหรือหมดอายุ — กรุณาขอ token ใหม่',
            ], 401);
        }

        if ($requiredScope && ! in_array($requiredScope, $auth['scopes'] ?? [], true)) {
            return response()->json([
                'error' => 'insufficient_scope',
                'message' => "Token ขาด scope: {$requiredScope}",
                'required_scope' => $requiredScope,
                'granted_scopes' => $auth['scopes'] ?? [],
            ], 403);
        }

        // Attach auth info for controllers
        $request->attributes->set('api_auth', $auth);

        return $next($request);
    }
}
