<?php

use App\Http\Controllers\ApprovalDispensasiController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'login')->name('login');

// Link approval wakasek dibuka lewat token di WhatsApp/email,
// bukan lewat session login -> sengaja di luar auth.session.
Route::get(
    '/approve-dispensasi/{token}/{wakasek}',
    [ApprovalDispensasiController::class, 'show']
)->name('approve-dispensasi');

Route::get(
    '/surat-dispensasi/{id}',
    [ApprovalDispensasiController::class, 'detail']
)->name('surat-dispensasi.detail');

Route::post(
    '/approve-dispensasi/{token}/{wakasek}/setujui',
    [ApprovalDispensasiController::class, 'setujui']
)->name('approve-dispensasi.setujui');

Route::post(
    '/approve-dispensasi/{token}/{wakasek}/tolak',
    [ApprovalDispensasiController::class, 'tolak']
)->name('approve-dispensasi.tolak');

Route::get('/logout', function () {
    session()->flush();

    return redirect()->route('login');
})->name('logout');

/*
|--------------------------------------------------------------------------
| HALAMAN YANG WAJIB LOGIN
|--------------------------------------------------------------------------
| Sebelumnya route-route ini tidak dilindungi middleware apapun --
| proteksi login cuma mengandalkan cek manual di dalam mount() masing-
| masing komponen, dan ternyata tidak semua komponen punya cek itu.
| Sekarang semua wajib login, dan beberapa dikunci per-role.
*/
Route::middleware('auth.session')->group(function () {

    // Bisa diakses semua role yang login (masing-masing komponen
    // sudah redirect sendiri kalau role-nya tidak cocok)
    Route::livewire('/dashboard', 'dashboard')->name('dashboard');
    Route::livewire('/notifikasi', 'notifikasi')->name('notifikasi');
    Route::livewire('/riwayat', 'riwayat')->name('riwayat');
    Route::livewire('/input-jurnal', 'input-jurnal')->name('input-jurnal');

    Route::middleware('role:admin')->group(function () {
        Route::livewire('/admin', 'admin')->name('admin');
    });

    Route::middleware('role:wakasek')->group(function () {
        Route::livewire('/wakasek', 'wakasek')->name('wakasek');
    });

    // Khusus sekretaris
    Route::middleware('role:sekretaris')->group(function () {
        Route::livewire('/sekretaris', 'sekretaris')->name('sekretaris');
    });

    // Fungsi piket adalah penugasan tambahan untuk akun guru yang sama.
    Route::middleware('role:guru')->group(function () {
        Route::livewire('/guru-piket', 'guru-piket')->name('guru-piket');
        Route::livewire('/dispensasi', 'dispensasi')->name('dispensasi');
        Route::livewire('/rekap-dispensasi', 'rekap-dispensasi')
            ->name('rekap-dispensasi');
    });
});
