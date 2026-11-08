<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Satu akun = satu sesi aktif. Token yang bukan sesi aktif akun (karena login baru di perangkat lain,
 * atau sudah logout) ditolak 401 dengan kode SESSION_REVOKED. Dipasang tepat setelah `auth:sanctum`
 * di setiap grup route terautentikasi, sehingga request lama tidak sampai ke controller.
 */
class EnsureSingleSession
{
    public const CODE = 'SESSION_REVOKED';

    public const MESSAGE = 'Sesi Anda telah berakhir karena akun ini login pada perangkat lain.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        // Hanya personal access token yang punya sesi di database. Autentikasi tanpa token
        // (mis. Sanctum::actingAs di test) tidak punya sesi yang bisa digantikan.
        if ($user instanceof User && $token instanceof PersonalAccessToken && ! $user->hasActiveSession($token)) {
            return response()->json([
                'success' => false,
                'message' => self::MESSAGE,
                'code' => self::CODE,
            ], 401);
        }

        return $next($request);
    }
}
