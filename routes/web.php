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
)->middleware(['auth.session', 'role:wakasek,guru,sekretaris'])->name('surat-dispensasi.detail');

Route::get(
    '/sekretaris/surat-dispensasi/{id}/unduh',
    [ApprovalDispensasiController::class, 'unduhUntukSekretaris']
)->middleware(['auth.session', 'role:sekretaris'])->name('surat-dispensasi.unduh');

Route::get(
    '/sekretaris/surat-dispensasi/{id}/lihat',
    [ApprovalDispensasiController::class, 'lihatUntukSekretaris']
)->middleware(['auth.session', 'role:sekretaris'])->name('surat-dispensasi.lihat');

Route::get(
    '/surat-dispensasi/{id}/lampiran/{token}',
    [ApprovalDispensasiController::class, 'lampiran']
)->name('surat-dispensasi.lampiran');

Route::get(
    '/surat-dispensasi/{id}/ticket/{ticketToken}',
    [ApprovalDispensasiController::class, 'ticket']
)->name('surat-dispensasi.ticket');

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
    Route::livewire('/riwayat', 'riwayat')->name('riwayat');
    Route::livewire('/input-jurnal', 'input-jurnal')->name('input-jurnal');

    Route::middleware('role:wakasek')->group(function () {
        // approval dispensasi tetap tersedia lewat controller/token lama
    });

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
        Route::livewire('/notifikasi', 'notifikasi')->name('notifikasi');
        Route::livewire('/guru-piket', 'guru-piket')->name('guru-piket');
        Route::livewire('/dispensasi', 'dispensasi')->name('dispensasi');
    });

    Route::middleware('role:guru,wakasek')->group(function () {
        Route::livewire('/rekap-dispensasi', 'rekap-dispensasi')
            ->name('rekap-dispensasi');
    });

    // =========================================================
    // ROUTE BARU — ORANG 5 (navigasi & integrasi)
    // =========================================================

    // Izin Guru: guru mengajukan, wakasek menyetujui.
    // Component: ⚡izin-guru.blade.php (Orang 3)
    Route::middleware('role:guru,wakasek')->group(function () {
        Route::livewire('/izin-guru', 'izin-guru')->name('izin-guru');
    });

    // Kehadiran Siswa untuk guru piket.
    // Component: ⚡piket-kehadiran.blade.php (Orang 1)
    Route::middleware('role:guru')->group(function () {
        Route::livewire('/piket-kehadiran', 'piket-kehadiran')->name('piket-kehadiran');
    });

    // Halaman detail jurnal (dipanggil dari monitoring wakasek).
    // Component: ⚡jurnal-detail.blade.php (Orang 2)
    Route::middleware('role:guru,wakasek,sekretaris')->group(function () {
        Route::livewire('/jurnal/{id}/detail', 'jurnal-detail')->name('jurnal-detail');
    });

    // Halaman detail dispensasi.
    // Component: ⚡dispensasi-detail.blade.php (Orang 4)
    Route::middleware('role:guru,wakasek,sekretaris')->group(function () {
        Route::livewire('/dispensasi/{id}/detail', 'dispensasi-detail')->name('dispensasi-detail');
    });

    // Dispensasi khusus wakasek (juga dipakai sebagai child di ⚡wakasek.blade.php).
    // Component: ⚡wakasek-dispensasi.blade.php (Orang 4)
    Route::middleware('role:wakasek')->group(function () {
        Route::livewire('/wakasek/dispensasi', 'wakasek-dispensasi')->name('wakasek-dispensasi');
    });
});
