<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyOrbitBriefApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('services.orbit_brief.api_key');

        if ($expected === '') {
            return response()->json(['error' => 'API key is not configured on the server.'], 503);
        }

        $provided = $this->extractBearerToken($request);

        if ($provided === null || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization', '');

        if (is_string($header) && preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
            return trim($matches[1]);
        }

        $apiKey = $request->header('X-Orbit-Api-Key');

        return is_string($apiKey) && $apiKey !== '' ? trim($apiKey) : null;
    }
}
