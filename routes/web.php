<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return \Livewire\Livewire::mount('login');
})->name('login');

Route::livewire('/dashboard', 'dashboard')->name('dashboard');
Route::livewire('/sekretaris', 'sekretaris')->name('sekretaris');
Route::livewire('/guru-piket', 'guru-piket')->name('guru-piket');

Route::livewire('/dispensasi', 'dispensasi')->name('dispensasi');
Route::livewire('/rekap-dispensasi', 'rekap-dispensasi')
    ->name('rekap-dispensasi');
Route::livewire('/approve-dispensasi/{token}', 'approve-dispensasi')
    ->name('approve-dispensasi');
    
Route::livewire('/riwayat', 'riwayat')->name('riwayat');
Route::livewire('/input-jurnal', 'input-jurnal')->name('input-jurnal');

Route::get('/logout', function () {
    session()->flush();

    return redirect()->route('login');
})->name('logout');