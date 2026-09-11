<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    /**
     * Handle an incoming API request.
     *
     * Validates that the request includes a valid API key via:
     * - X-API-KEY header
     * - Authorization: Bearer <token>
     * - api_key query parameter
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('services.pmis_api.key', env('PMIS_API_KEY'));

        // If no API key is configured in the environment, safely reject all incoming API requests
        if (empty($configuredKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'API access is not configured on this server. Set PMIS_API_KEY in the environment.',
            ], 503);
        }

        // Check X-API-KEY header, Authorization Bearer token, or query parameter
        $providedKey = $request->header('X-API-KEY')
            ?? $request->bearerToken()
            ?? $request->query('api_key');

        if (! $providedKey || ! hash_equals((string) $configuredKey, (string) $providedKey)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Invalid or missing API key.',
            ], 401);
        }

        return $next($request);
    }
}