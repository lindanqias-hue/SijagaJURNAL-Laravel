<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Kelas;

new class extends Component
{
    public function mount()
    {
        // Belum login
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // Guru Piket → Dashboard Guru Piket
        if (session('role') === 'guru_piket') {
            $this->redirectRoute('guru-piket');
            return;
        }

        // Sekretaris → Dashboard Sekretaris
        if (session('role') === 'sekretaris') {
            $this->redirectRoute('sekretaris');
            return;
        }

        // Admin → Dashboard Admin
        if (session('role') === 'admin') {
            $this->redirectRoute('admin');
            return;
        }

        // Kalau role guru → tetap di Dashboard Guru
    }

    public function getJurnalProperty()
    {
        $idPengguna = session('id_pengguna');

        return Jurnal::where('id_guru', $idPengguna)
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get();
    }

    public function getKelasListProperty()
    {
        return Kelas::orderBy('nama_kelas')->get();
    }

    public function getTodayJurnalProperty()
    {
        return $this->jurnal->filter(function ($jurnal) {
            return $jurnal->tanggal &&
                $jurnal->tanggal->format('Y-m-d') === now()->format('Y-m-d');
        });
    }

    public function getUniqueKelasProperty()
    {
        return $this->jurnal
            ->pluck('id_kelas')
            ->unique()
            ->count();
    }

    public function getDivalidasiCountProperty()
    {
        return $this->jurnal
            ->where('status_validasi', 'Divalidasi')
            ->count();
    }

    public function getMenungguCountProperty()
    {
        return $this->jurnal
            ->where('status_validasi', 'Menunggu')
            ->count();
    }

    public function getWaliKelasProperty()
    {
        return Kelas::where(
            'wali_kelas',
            session('nama')
        )->first();
    }

    public function getKelasName($id)
    {
        return $this->kelasList
            ->firstWhere('id_kelas', $id)
            ?->nama_kelas ?? '-';
    }
};
?>

<div>

    {{-- WELCOME --}}
    <div class="welcome-banner">
        <div>
            <div style="color:rgba(255,255,255,.6); font-size:13px; margin-bottom:4px;">
                Selamat datang kembali,
            </div>

            <div class="fw-bold"
                 style="font-size:22px; color:#fff; margin-bottom:12px;">
                {{ session('nama') }}
            </div>

            <div class="d-flex flex-wrap gap-2">

                <span class="pill"
                      style="background:rgba(255,255,255,.12); color:#fff;">
                    &#128206; NIP: {{ session('nip') ?? '-' }}
                </span>

                @if(session('status_kepegawaian'))
                    <span class="badge-status badge-status-secondary pill fw-semibold">
                        {{ session('status_kepegawaian') }}
                    </span>
                @endif

                @if(session('mapel_diampu'))
                    <span class="pill"
                          style="background:rgba(255,255,255,.12); color:#93c5fd;">
                        &#128218; {{ session('mapel_diampu') }}
                    </span>
                @endif

                @if($this->waliKelas)
                    <span class="pill fw-bold"
                          style="background:#fbbf24; color:#451a03;">
                        &#127891;
                        Wali Kelas {{ $this->waliKelas->nama_kelas }}
                    </span>
                @endif

            </div>
        </div>
    </div>


    {{-- STATISTIK --}}
    <div class="row g-3 my-3">

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#2563eb;">
                    {{ $this->jurnal->count() }}
                </div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">
                    Total Jurnal
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#059669;">
                    {{ $this->uniqueKelas }}
                </div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">
                    Kelas Diampu
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#16a34a;">
                    {{ $this->divalidasiCount }}
                </div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">
                    Tervalidasi
                </div>
            </div>
        </div>

        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#ca8a04;">
                    {{ $this->menungguCount }}
                </div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">
                    Menunggu Validasi
                </div>
            </div>
        </div>

    </div>


    {{-- AGENDA HARI INI --}}
    <div class="card-custom">

        <div class="card-header-custom d-flex justify-content-between align-items-center">

            <div class="fw-bold" style="font-size:14px;">
                Agenda Hari Ini
            </div>

            <a href="/input-jurnal"
               class="btn btn-app-primary btn-sm">
                + Input Jurnal
            </a>

        </div>


        @if($this->todayJurnal->isEmpty())

            <div class="text-center text-muted py-4">
                Belum ada jurnal hari ini.
            </div>

        @else

            @foreach($this->todayJurnal as $jurnal)

                <div class="d-flex align-items-center justify-content-between p-3 border-bottom">

                    <div>

                        <div class="fw-semibold">
                            {{ $jurnal->materi }}
                        </div>

                        <div class="text-muted small">
                            {{ $this->getKelasName($jurnal->id_kelas) }}
                            &bull;
                            Jam ke-{{ $jurnal->jam_ke }}
                        </div>

                    </div>

                    <div>

                        @if($jurnal->status_validasi === 'Divalidasi')

                            <span class="badge bg-success">
                                ✓ Divalidasi
                            </span>

                        @elseif($jurnal->status_validasi === 'Ditolak')

                            <span class="badge bg-danger">
                                Ditolak
                            </span>

                        @else

                            <span class="badge bg-warning text-dark">
                                Menunggu
                            </span>

                        @endif

                    </div>

                </div>

            @endforeach

        @endif

    </div>

</div>