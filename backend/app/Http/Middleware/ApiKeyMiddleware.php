<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('app.api_key');

        if (empty($configuredKey)) {
            return response()->json(['error' => 'API authentication not configured'], 500);
        }

        $token = $request->bearerToken();

        if ($token === null || ! hash_equals($configuredKey, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
