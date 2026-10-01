@php
/**
 * Komponen modal bawaan aplikasi (x-app-modal).
 *
 * ATURAN PENGGUNAAN:
 * Modal ini hanya untuk informasi singkat: konfirmasi, pilihan cepat,
 * atau ringkasan 1–3 baris. Jika isi kontennya lebih panjang dari
 * itu — berupa daftar, tabel, atau form berkepanjangan — jangan pakai
 * modal. Buat halaman baru dengan <x-halaman-detail> instead.
 *
 * Prop tidak boleh diubah tanpa memeriksa semua pemakaian:
 *  - ⚡admin.blade.php (2 pemakaian: form create/edit)
 *  - ⚡wakasek.blade.php (2 pemakaian: detail rekap, detail jurnal)
 */
@endphp

@props([
    'id' => 'app-modal',
    'title',
    'subtitle' => null,
    'close',
    'closeParams' => '',
    'size' => 'md',
])

<div class="app-modal__overlay"
    wire:click.self="{{ $close }}({{ $closeParams }})"
    wire:keydown.escape.window="{{ $close }}({{ $closeParams }})">
    <section id="{{ $id }}"
        class="card-custom app-modal app-modal--{{ $size }}"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $id }}-title"
        tabindex="-1"
        x-init="$nextTick(() => $el.focus())">
        <div class="app-modal__head">
            <div class="app-modal__heading">
                <h3 id="{{ $id }}-title">{{ $title }}</h3>
                @if ($subtitle)
                    <div class="text-muted small">{{ $subtitle }}</div>
                @endif
            </div>
            <button type="button" class="btn-close" aria-label="Tutup" wire:click="{{ $close }}({{ $closeParams }})"></button>
        </div>

        <div class="app-modal__body">
            {{ $slot }}
        </div>

        @if (isset($footer))
            <div class="app-modal__foot">
                {{ $footer }}
            </div>
        @endif
    </section>
</div>
