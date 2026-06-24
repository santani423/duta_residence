<?php

namespace App\Http\Middleware;

use App\Services\AuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            app(AuditService::class)->logRequest(
                $request,
                $response->isSuccessful() ? 'success' : 'failed',
                'API request processed'
            );
        }

        return $response;
    }
}
