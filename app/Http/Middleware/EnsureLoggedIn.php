<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak akses ke halaman manapun kalau session('id_pengguna')
 * belum ada, alih-alih mengandalkan setiap komponen Livewire
 * mengecek sendiri di mount() (yang ternyata tidak konsisten
 * diterapkan di semua halaman).
 */
class EnsureLoggedIn
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('id_pengguna')) {
            return redirect()->route('login')
                ->with('error', 'Silakan login terlebih dahulu.');
        }

        return $next($request);
    }
}
