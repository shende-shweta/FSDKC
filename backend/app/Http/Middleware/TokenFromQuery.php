<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TokenFromQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->query('token') && ! $request->hasHeader('Authorization')) {
            $request->headers->set('Authorization', 'Bearer '.$request->query('token'));
        }

        return $next($request);
    }
}
