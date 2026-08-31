<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProviderToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) env('PROVIDER_TOKEN', 'crm-provider-secret-change-me');
        $given = (string) $request->header('X-Provider-Token');

        if (! hash_equals($expected, $given)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}