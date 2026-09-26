<?php

use Livewire\Component;
use Illuminate\Support\Facades\DB;

new class extends Component
{
    public function getNotifikasiProperty()
    {
        $idGuru = session('id_pengguna');

        if (!$idGuru) {
            return collect();
        }

        return DB::table('dispensasi_penerima')
            ->join(
                'dispensasi',
                'dispensasi_penerima.id_dispensasi',
                '=',
                'dispensasi.id_dispensasi'
            )
            ->join(
                'siswa',
                'dispensasi.id_siswa',
                '=',
                'siswa.id_siswa'
            )
            ->join(
                'kelas',
                'dispensasi.id_kelas',
                '=',
                'kelas.id_kelas'
            )
            ->leftJoin(
                'pengguna as wakasek',
                'dispensasi.id_wakasek',
                '=',
                'wakasek.id_pengguna'
            )
            ->where(
                'dispensasi_penerima.id_guru',
                $idGuru
            )
            ->select(
                'dispensasi_penerima.id_penerima',
                'dispensasi_penerima.dibaca_at',

                'dispensasi.id_dispensasi',
                'dispensasi.jenis_dispensasi',
                'dispensasi.tanggal',
                'dispensasi.jam_ke',
                'dispensasi.jam_ke_mulai',
                'dispensasi.jam_ke_selesai',
                'dispensasi.jam_mulai',
                'dispensasi.jam_selesai',
                'dispensasi.alasan',
                'dispensasi.status',

                'siswa.nama_siswa',
                'kelas.nama_kelas',

                'wakasek.nama as nama_wakasek'
            )
            ->orderByRaw(
                'dispensasi_penerima.dibaca_at IS NULL DESC'
            )
            ->orderByDesc('dispensasi_penerima.created_at')
            ->get();
    }

    public function getJumlahBelumDibacaProperty()
    {
        return $this->notifikasi
            ->whereNull('dibaca_at')
            ->count();
    }

    public function tandaiDibaca($idPenerima)
    {
        DB::table('dispensasi_penerima')
            ->where('id_penerima', $idPenerima)
            ->where('id_guru', session('id_pengguna'))
            ->update([
                'dibaca_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function tandaiSemuaDibaca()
    {
        DB::table('dispensasi_penerima')
            ->where('id_guru', session('id_pengguna'))
            ->whereNull('dibaca_at')
            ->update([
                'dibaca_at' => now(),
                'updated_at' => now(),
            ]);
    }
};
?>

<div>

    {{-- HEADER --}}
    <div class="mb-4">

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>
                <div class="page-title">
                    🔔 Notifikasi
                </div>

                <div
                    class="text-muted mt-1"
                    style="font-size:13px;"
                >
                    Informasi dispensasi siswa yang berkaitan dengan jadwal mengajar Anda.
                </div>
            </div>

            @if ($this->jumlahBelumDibaca > 0)
                <button
                    wire:click="tandaiSemuaDibaca"
                    class="btn btn-sm btn-outline-primary"
                >
                    ✓ Tandai Semua Dibaca
                </button>
            @endif

        </div>

    </div>

    <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-sm fw-semibold mb-3">&larr; Kembali ke Dashboard</a>


    {{-- JUMLAH NOTIFIKASI --}}
    <div class="mb-3">

        @if ($this->jumlahBelumDibaca > 0)

            <div class="alert alert-primary border-0">
                🔔 Ada
                <strong>{{ $this->jumlahBelumDibaca }}</strong>
                notifikasi yang belum dibaca.
            </div>

        @else

            <div class="alert alert-light border">
                ✓ Semua notifikasi sudah dibaca.
            </div>

        @endif

    </div>


    {{-- DAFTAR NOTIFIKASI --}}
    <div class="d-flex flex-column gap-3">

        @forelse ($this->notifikasi as $notif)

            <div
                class="card border-0 shadow-sm"
                style="
                    border-left:
                    4px solid
                    {{ is_null($notif->dibaca_at) ? '#0d6efd' : '#dee2e6' }}
                    !important;
                "
            >

                <div class="card-body">

                    {{-- JUDUL --}}
                    <div class="d-flex justify-content-between align-items-start gap-3">

                        <div>

                            @if (is_null($notif->dibaca_at))
                                <span class="badge bg-primary mb-2">
                                    BARU
                                </span>
                            @endif

                            <h5 class="mb-1">
                                🔔 Siswa Mendapat Dispensasi
                            </h5>

                            <div class="text-muted small">
                                {{ $notif->nama_siswa }}
                                ·
                                {{ $notif->nama_kelas }}
                            </div>

                        </div>

                        <span class="badge bg-success">
                            Disetujui
                        </span>

                    </div>


                    <hr>


                    {{-- INFORMASI SISWA --}}
                    <div class="row g-3">

                        <div class="col-md-6">

                            <div class="text-muted small">
                                Nama Siswa
                            </div>

                            <div class="fw-semibold">
                                {{ $notif->nama_siswa }}
                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="text-muted small">
                                Kelas
                            </div>

                            <div class="fw-semibold">
                                {{ $notif->nama_kelas }}
                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="text-muted small">
                                Jenis Dispensasi
                            </div>

                            <div class="fw-semibold">
                                {{ $notif->jenis_dispensasi }}
                            </div>

                        </div>


                        <div class="col-md-6">

                            <div class="text-muted small">
                                Tanggal
                            </div>

                            <div class="fw-semibold">
                                {{ \Carbon\Carbon::parse($notif->tanggal)->translatedFormat('d F Y') }}
                            </div>

                        </div>


                        {{-- JAM --}}
                        <div class="col-md-6">

                            <div class="text-muted small">
                                Jam
                            </div>

                            <div class="fw-semibold">

                                @if ($notif->jenis_dispensasi === 'Sehari Penuh')

                                    Sehari Penuh

                                @else

                                    Jam ke
                                    {{ $notif->jam_ke_mulai }}

                                    @if ($notif->jam_ke_selesai != $notif->jam_ke_mulai)
                                        - {{ $notif->jam_ke_selesai }}
                                    @endif

                                    @if ($notif->jam_mulai && $notif->jam_selesai)
                                        <span class="text-muted">
                                            ({{ substr($notif->jam_mulai, 0, 5) }}
                                            -
                                            {{ substr($notif->jam_selesai, 0, 5) }})
                                        </span>
                                    @endif

                                @endif

                            </div>

                        </div>


                        {{-- WAKASEK --}}
                        <div class="col-md-6">

                            <div class="text-muted small">
                                Disetujui Oleh
                            </div>

                            <div class="fw-semibold">
                                {{ $notif->nama_wakasek ?? '-' }}
                            </div>

                        </div>


                        {{-- ALASAN --}}
                        <div class="col-12">

                            <div class="text-muted small mb-1">
                                Alasan Dispensasi
                            </div>

                            <div class="p-3 bg-light rounded">
                                {{ $notif->alasan }}
                            </div>

                        </div>

                    </div>


                    {{-- TOMBOL --}}
                    <div class="d-flex justify-content-end gap-2 mt-4 flex-wrap">

                        <a
                            href="{{ route(
                                'surat-dispensasi.detail',
                                $notif->id_dispensasi
                            ) }}"
                            class="btn btn-sm btn-primary"
                        >
                            📄 Lihat Surat
                        </a>


                        @if (is_null($notif->dibaca_at))

                            <button
                                wire:click="tandaiDibaca({{ $notif->id_penerima }})"
                                class="btn btn-sm btn-outline-primary"
                            >
                                ✓ Tandai Dibaca
                            </button>

                        @else

                            <span class="btn btn-sm btn-light disabled">
                                ✓ Sudah Dibaca
                            </span>

                        @endif

                    </div>

                </div>

            </div>

        @empty

            <div class="card border-0 shadow-sm">

                <div class="card-body text-center py-5">

                    <div style="font-size:45px;">
                        🔔
                    </div>

                    <h5 class="mt-3">
                        Belum Ada Notifikasi
                    </h5>

                    <p class="text-muted mb-0">
                        Notifikasi dispensasi siswa akan muncul di sini.
                    </p>

                </div>

            </div>

        @endforelse

    </div>

</div>
