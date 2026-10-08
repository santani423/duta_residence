<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak setiap request staff dari akun yang sudah dinonaktifkan (atau dihapus) walaupun
 * token lamanya masih ada — login sudah menolak akun non-aktif, middleware ini menutup
 * celah token yang terbit sebelum akun dinonaktifkan.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (! $user->is_active || (method_exists($user, 'trashed') && $user->trashed()))) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda tidak aktif. Silakan hubungi administrator.',
                'errors' => ['code' => 'ACCOUNT_INACTIVE'],
            ], 401);
        }

        return $next($request);
    }
}
