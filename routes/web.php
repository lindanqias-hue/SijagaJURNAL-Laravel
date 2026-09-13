<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApprovalDispensasiController;

Route::get('/', function () {
    return \Livewire\Livewire::mount('login');
})->name('login');

Route::livewire('/dashboard', 'dashboard')->name('dashboard');
Route::livewire('/notifikasi', 'notifikasi')->name('notifikasi');
Route::livewire('/sekretaris', 'sekretaris')->name('sekretaris');
Route::livewire('/guru-piket', 'guru-piket')->name('guru-piket');
Route::livewire('/dispensasi', 'dispensasi')->name('dispensasi');

Route::livewire('/rekap-dispensasi', 'rekap-dispensasi')
    ->name('rekap-dispensasi');

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

Route::livewire('/riwayat', 'riwayat')->name('riwayat');
Route::livewire('/input-jurnal', 'input-jurnal')->name('input-jurnal');

Route::get('/logout', function () {
    session()->flush();

    return redirect()->route('login');
})->name('logout');