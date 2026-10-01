<?php

/**
 * Komponen halaman detail — anonim (tanpa class).
 *
 * Gunakan komponen ini untuk halaman informasi panjang yang sebelumnya
 * tidak muat di modal. Modal (x-app-modal) hanya untuk info singkat:
 * konfirmasi, pilihan cepat, ringkasan 1–3 baris. Kalau isinya lebih
 dari itu, buat halaman dengan komponen ini.
 *
 * Props:
 *  - judul       (string, wajib)  Judul besar halaman.
 *  - subjudul    (string, opsional) Baris kedua di bawah judul.
 *  - eyebrow     (string, opsional) Label kecil di atas judul, mis. "Rekap Jurnal".
 *  - routeKembali (string, opsional) Route tombol "← Kembali".
 *  - labelKembali (string, default "Kembali") Teks tombol kembali.
 *
 * Slot default: konten utama.
 * Slot actions: tombol kanan di header (opsional).
 */
use Illuminate\Support\Facades\Route;

$labelKembali = $labelKembali ?? 'Kembali';
?>

<header class="role-page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
    <div>
        @if ($eyebrow)
            <div class="role-page-eyebrow">{{ $eyebrow }}</div>
        @endif
        <h1>{{ $judul }}</h1>
        @if ($subjudul)
            <div class="role-page-description">{{ $subjudul }}</div>
        @endif
    </div>

    <div class="role-page-actions d-flex flex-wrap gap-2">
        @if ($routeKembali && Route::has($routeKembali))
            <a href="{{ route($routeKembali) }}" class="btn-back">
                <span aria-hidden="true">←</span> {{ $labelKembali }}
            </a>
        @endif
        @if (isset($actions))
            {{ $actions }}
        @endif
    </div>
</header>

<div class="card-custom">
    <div class="card-header-custom">
        <div class="fw-bold">{{ $judul }}</div>
        @if ($subjudul)
            <div class="text-muted small">{{ $subjudul }}</div>
        @endif
    </div>
    <div class="p-3">
        {{ $slot }}
    </div>
</div>
