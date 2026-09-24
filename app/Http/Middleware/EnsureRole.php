<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dipakai setelah EnsureLoggedIn (jadi id_pengguna dianggap sudah ada).
 * Contoh: Route::middleware('role:guru_piket')->group(...)
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            // Mempertahankan pengecekan sesi lama jika user instance null
            if (!session('id_pengguna')) {
                return redirect()->route('login');
            }
        }

        // Ambil role dari object user atau fallback ke session
        $userRole = $user->role ?? session('role');

        if (!$userRole) {
            return redirect('/login');
        }

        // Jika rute butuh akses 'guru', izinkan juga 'guru_piket'
        if (in_array('guru', $roles) && $userRole === 'guru_piket') {
            return $next($request);
        }

        // Pengecekan role standar (mendukung array roles dan pengecekan ketat)
        if (!in_array($userRole, $roles, true)) {
            abort(403, 'Kamu tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
