@php
/**
 * Layout halaman detail standar untuk jurnal, dispensasi, piket, dll.
 * Dipakai sebagai pengganti modal untuk konten panjang.
 *
 * Props:
 *  - title (string, wajib)
 *  - subtitle (string, opsional)
 *  - backRoute (string, opsional) - route untuk tombol kembali
 *  - backLabel (string, default "Kembali")
 *  - actions (slot, opsional) - tombol aksi di header
 */
@endphp

@props([
    'title',
    'subtitle' => null,
    'backRoute' => null,
    'backLabel' => 'Kembali',
])

<div class="detail-page">
    {{-- HEADER --}}
    <div class="detail-page-header d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <div class="detail-page-title" data-page-title>{{ $title }}</div>
            @if ($subtitle)
                <div class="detail-page-subtitle text-muted small">{{ $subtitle }}</div>
            @endif
        </div>
        <div class="detail-page-actions d-flex gap-2">
            @if (isset($actions))
                {{ $actions }}
            @endif
            @if ($backRoute)
                <a href="{{ route($backRoute) }}" class="btn btn-outline-secondary">
                    &larr; {{ $backLabel }}
                </a>
            @endif
        </div>
    </div>

    {{-- CONTENT --}}
    <div class="detail-page-body">
        {{ $slot }}
    </div>
</div>

<style>
    .detail-page-header {
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 16px;
    }
    .detail-page-title {
        font-size: 1.5rem;
        font-weight: 700;
        color: #1e293b;
    }
    .detail-page-subtitle {
        margin-top: 4px;
    }
    .detail-page-body {
        animation: fadeIn 0.2s ease-out;
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>