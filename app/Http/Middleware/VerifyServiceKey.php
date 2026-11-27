<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards /api/v1/service/* — read-only endpoints for the CompanyOS hub.
 * Accepts the key as "X-Service-Key: <key>" or "Authorization: Bearer <key>".
 */
class VerifyServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.companyos.service_key');

        if ($expected === '') {
            return response()->json(['error' => ['code' => 'SERVICE_KEY_NOT_CONFIGURED', 'message' => 'Service key is not configured on the server.']], 503);
        }

        $provided = $request->header('X-Service-Key');
        if (! is_string($provided) || $provided === '') {
            $auth = (string) $request->header('Authorization', '');
            $provided = preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m) ? trim($m[1]) : null;
        }

        if (! is_string($provided) || ! hash_equals($expected, trim($provided))) {
            return response()->json(['error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid or missing service key.']], 401);
        }

        return $next($request);
    }
}
