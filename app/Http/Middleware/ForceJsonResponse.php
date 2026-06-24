<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');
        $request->attributes->set('request_id', $request->headers->get('X-Request-ID', (string) Str::uuid()));

        $response = $next($request);
        $response->headers->set('X-Request-ID', $request->attributes->get('request_id'));

        return $response;
    }
}
