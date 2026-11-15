<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireServiceToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('services.legacy.service_token');

        // Fail closed if SERVICE_TOKEN is unset or empty
        if (!is_string($expectedToken) || $expectedToken === '') {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Service authentication is not configured.',
                    'details' => [],
                ],
            ], 401);
        }

        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Missing or invalid Authorization bearer token.',
                    'details' => [],
                ],
            ], 401);
        }

        $providedToken = trim(substr($authHeader, 7));

        if (!hash_equals($expectedToken, $providedToken)) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Provided bearer token is unauthorized.',
                    'details' => [],
                ],
            ], 401);
        }

        return $next($request);
    }
}
