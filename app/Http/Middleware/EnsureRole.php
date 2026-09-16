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
        if (!session('id_pengguna')) {
            return redirect()->route('login');
        }

        if (!in_array(session('role'), $roles, true)) {
            abort(403, 'Kamu tidak punya akses ke halaman ini.');
        }

        return $next($request);
    }
}
