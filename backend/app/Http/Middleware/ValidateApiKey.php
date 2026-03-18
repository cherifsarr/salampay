<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKeyHeader = $request->header('X-API-Key');

        if (empty($apiKeyHeader)) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_API_KEY',
                    'message' => 'API key is required',
                ],
            ], 401);
        }

        $apiKey = ApiKey::findByKey($apiKeyHeader);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_API_KEY',
                    'message' => 'Invalid API key',
                ],
            ], 401);
        }

        if (!$apiKey->isActive()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INACTIVE_API_KEY',
                    'message' => 'API key is inactive or expired',
                ],
            ], 401);
        }

        // Check IP restrictions
        if (!$apiKey->isIpAllowed($request->ip())) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'IP_NOT_ALLOWED',
                    'message' => 'Request from this IP is not allowed',
                ],
            ], 403);
        }

        // Attach API key and merchant to request
        $request->merge([
            'api_key' => $apiKey,
            'merchant' => $apiKey->merchant,
        ]);

        // Update last used timestamp
        $apiKey->touch();

        return $next($request);
    }
}
