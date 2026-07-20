<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user && $token && $user->active_token_id && (int) $token->id !== (int) $user->active_token_id) {
            $token->delete();

            return response()->json([
                'success' => false,
                'message' => 'Akun Anda sedang digunakan di perangkat lain.',
                'code' => 'SESSION_REVOKED',
            ], 401);
        }

        return $next($request);
    }
}
