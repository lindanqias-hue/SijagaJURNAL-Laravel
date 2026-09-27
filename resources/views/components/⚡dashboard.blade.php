<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\GuruPiket;
use Illuminate\Support\Facades\Route;

new class extends Component
{
    // =========================================================
    // MOUNT
    // =========================================================
    public function mount()
    {
        // Belum login
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // =====================================================
        // REDIRECT SESUAI ROLE
        // =====================================================

        // Guru Piket
        // Hanya redirect jika route tersedia
        if (
            session('role') === 'guru_piket' &&
            Route::has('guru-piket')
        ) {
            $this->redirectRoute('guru-piket');
            return;
        }

        // Sekretaris
        // Route sekretaris belum tersedia.
        // Untuk sementara tetap di dashboard.
        if (session('role') === 'sekretaris') {
            return;
        }

        // Admin
        // Route admin belum tersedia.
        // Untuk sementara tetap di dashboard.
        if (session('role') === 'admin') {
            return;
        }

        // Wakasek
        // Cek dulu apakah route tersedia.
        if (
            session('role') === 'wakasek' &&
            Route::has('wakasek')
        ) {
            $this->redirectRoute('wakasek');
            return;
        }

        // Guru biasa tetap di dashboard ini.
    }

    // =========================================================
    // DATA JURNAL GURU
    // =========================================================
    public function getJurnalProperty()
    {
        $idPengguna = session('id_pengguna');

        if (!$idPengguna) {
            return collect();
        }

        return Jurnal::where('id_guru', $idPengguna)
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get();
    }

    // =========================================================
    // JUMLAH KELAS UNIK
    // =========================================================
    public function getUniqueKelasProperty()
    {
        return $this->jurnal
            ->pluck('id_kelas')
            ->unique()
            ->count();
    }

    // =========================================================
    // JUMLAH JURNAL YANG SUDAH DIVALIDASI
    // =========================================================
    public function getDivalidasiCountProperty()
    {
        return $this->jurnal
            ->where('status_validasi', 'Divalidasi')
            ->count();
    }

    // =========================================================
    // JUMLAH JURNAL YANG MASIH MENUNGGU
    // =========================================================
    public function getMenungguCountProperty()
    {
        return $this->jurnal
            ->where('status_validasi', 'Menunggu')
            ->count();
    }

    // =========================================================
    // WALI KELAS
    // =========================================================
    public function getWaliKelasProperty()
    {
        return Kelas::where(
            'wali_kelas',
            session('nama')
        )->first();
    }

    // =========================================================
    // JADWAL MENGAJAR HARI INI
    // =========================================================
    public function getJadwalHariIniProperty()
    {
        $idGuru = session('id_pengguna');

        if (!$idGuru) {
            return collect();
        }

        $hariIni = now('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('l');

        $jadwal = Jadwal::where(
                'id_guru',
                $idGuru
            )
            ->where(
                'hari',
                $hariIni
            )
            ->whereHas('kelas')
            ->with('kelas')
            ->orderBy('jam_ke')
            ->get();

        $hasil = collect();

        foreach ($jadwal as $item) {

            $terakhir = $hasil->last();

            // Gabungkan jadwal kalau:
            // - kelas sama
            // - jam ke berurutan
            if (
                $terakhir &&
                $terakhir->id_kelas == $item->id_kelas &&
                $terakhir->jam_ke_selesai + 1 == $item->jam_ke
            ) {
                $terakhir->jam_ke_selesai = $item->jam_ke;
                $terakhir->jam_selesai = $item->jam_selesai;
            } else {
                $item->jam_ke_mulai = $item->jam_ke;
                $item->jam_ke_selesai = $item->jam_ke;

                $hasil->push($item);
            }
        }

        return $hasil;
    }

    // =========================================================
    // TUGAS PIKET HARI INI
    // =========================================================
    public function getTugasPiketHariIniProperty()
    {
        if (!session('is_guru_piket')) {
            return collect();
        }

        $sekarang = now('Asia/Jakarta');

        $hariIni = $sekarang
            ->locale('id')
            ->translatedFormat('l');

        // Jadwal piket mingguan
        $jadwalMingguan = GuruPiket::query()
            ->where(
                'id_pengguna',
                session('id_pengguna')
            )
            ->where(
                'hari',
                $hariIni
            )
            ->where(
                'aktif',
                true
            )
            ->get();

        if ($jadwalMingguan->isNotEmpty()) {
            return $jadwalMingguan;
        }

        // Jadwal piket khusus tanggal tertentu
        return JadwalPiket::query()
            ->where(
                'id_guru',
                session('id_pengguna')
            )
            ->whereDate(
                'tanggal',
                $sekarang->toDateString()
            )
            ->where(
                'status',
                'Aktif'
            )
            ->orderBy('jam_mulai')
            ->get();
    }
};
?>

<div
    x-data="{
        activeSection: window.location.hash === '#jadwal-saya' ? 'jadwal-saya' : 'dashboard',
        syncSection() {
            this.activeSection = window.location.hash === '#jadwal-saya' ? 'jadwal-saya' : 'dashboard';
        }
    }"
    x-init="window.addEventListener('hashchange', () => syncSection())"
>
    <section id="dashboard" x-cloak x-show="activeSection === 'dashboard'">

    {{-- =====================================================
         WELCOME
    ====================================================== --}}
    <div class="welcome-banner">

        <div class="d-flex justify-content-between align-items-center w-100">

            {{-- DATA GURU --}}
            <div style="min-width:0; flex:1 1 auto;">

                @if ($this->tugasPiketHariIni->isNotEmpty())
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3" aria-label="Tugas piket hari ini">
                    <span class="badge bg-success">Piket Hari Ini</span>
                    @foreach ($this->tugasPiketHariIni as $piket)
                    <span class="pill" style="background:rgba(255,255,255,.14);color:#fff;">
                        {{ substr($piket->jam_mulai, 0, 5) }}–{{ substr($piket->jam_selesai, 0, 5) }}
                        · {{ $piket->keterangan ?? 'Tugas piket' }}
                    </span>
                    @endforeach
                    <a href="{{ route('guru-piket') }}" class="btn btn-sm btn-light text-primary fw-semibold">Buka Piket</a>
                </div>
                @endif

                <div style="
                    color:rgba(255,255,255,.6);
                    font-size:13px;
                    margin-bottom:4px;
                ">
                    Selamat datang kembali,
                </div>

                <div
                    class="fw-bold"
                    style="
                        font-size:22px;
                        color:#fff;
                        margin-bottom:12px;
                        word-break:break-word;
                    "
                >
                    {{ session('nama') }}
                </div>

                <div class="d-flex flex-wrap gap-2">

                    <span
                        class="pill"
                        style="
                            background:rgba(255,255,255,.12);
                            color:#fff;
                        "
                    >
                        &#128206;
                        NIP: {{ session('nip') ?? '-' }}
                    </span>

                    @if(session('status_kepegawaian'))
                        <span
                            class="badge-status badge-status-secondary pill fw-semibold"
                        >
                            {{ session('status_kepegawaian') }}
                        </span>
                    @endif

                    @if(session('mapel_diampu'))
                        <span
                            class="pill"
                            style="
                                background:rgba(255,255,255,.12);
                                color:#93c5fd;
                            "
                        >
                            &#128218;
                            {{ session('mapel_diampu') }}
                        </span>
                    @endif

                    @if($this->waliKelas)

                        <span
                            class="pill fw-bold"
                            style="
                                background:#fbbf24;
                                color:#451a03;
                            "
                        >
                            &#127891;
                            Wali Kelas
                            {{ $this->waliKelas->nama_kelas }}
                        </span>

                    @endif

                </div>

            </div>

        </div>

    </div>

    {{-- RINGKASAN DASHBOARD --}}
    <div class="row g-3 my-3">
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#2563eb;">{{ $this->jurnal->count() }}</div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">Total Jurnal</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#059669;">{{ $this->uniqueKelas }}</div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">Kelas Diampu</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#16a34a;">{{ $this->divalidasiCount }}</div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">Valid</div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card">
                <div class="stat-value" style="color:#ca8a04;">{{ $this->menungguCount }}</div>
                <div class="text-muted fw-medium" style="font-size:11.5px;">Menunggu Konfirmasi</div>
            </div>
        </div>
    </div>


    {{-- =====================================================
         MENU CEPAT
    ====================================================== --}}
    <div class="row g-3 my-3" aria-label="Menu Guru">
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('dashboard') }}#jadwal-saya" class="role-menu-card">
                <span class="role-menu-icon">&#128197;</span>
                <h2 class="h5 fw-bold">Jadwal Saya</h2>
                <p>Lihat jadwal mengajar dan tugas kelas hari ini.</p>
                <span class="fw-bold text-primary">Buka jadwal <span aria-hidden="true">→</span></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('input-jurnal') }}" class="role-menu-card">
                <span class="role-menu-icon">&#9998;</span>
                <h2 class="h5 fw-bold">Input Jurnal</h2>
                <p>Isi jurnal pembelajaran untuk kelas yang Anda ajar.</p>
                <span class="fw-bold text-primary">Buka input jurnal <span aria-hidden="true">→</span></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('riwayat') }}" class="role-menu-card">
                <span class="role-menu-icon">&#128203;</span>
                <h2 class="h5 fw-bold">Riwayat Saya</h2>
                <p>Tinjau jurnal yang pernah dikirim beserta status validasinya.</p>
                <span class="fw-bold text-primary">Buka riwayat <span aria-hidden="true">→</span></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('notifikasi') }}" class="role-menu-card">
                <span class="role-menu-icon">&#128276;</span>
                <h2 class="h5 fw-bold">Notifikasi</h2>
                <p>Lihat pemberitahuan dispensasi yang berkaitan dengan jadwal Anda.</p>
                <span class="fw-bold text-primary">Buka notifikasi <span aria-hidden="true">→</span></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('dispensasi') }}" class="role-menu-card">
                <span class="role-menu-icon">&#128221;</span>
                <h2 class="h5 fw-bold">Dispensasi</h2>
                <p>Buat dan pantau pengajuan izin atau dispensasi siswa.</p>
                <span class="fw-bold text-primary">Buka dispensasi <span aria-hidden="true">→</span></span>
            </a>
        </div>
        @if (session('is_guru_piket'))
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('guru-piket') }}" class="role-menu-card">
                <span class="role-menu-icon">&#128101;</span>
                <h2 class="h5 fw-bold">Piket Hari Ini</h2>
                <p>Pantau kehadiran guru dan koordinasi tugas piket.</p>
                <span class="fw-bold text-primary">Buka piket <span aria-hidden="true">→</span></span>
            </a>
        </div>
        <div class="col-12 col-sm-6 col-xl-4">
            <a href="{{ route('rekap-dispensasi') }}" class="role-menu-card">
                <span class="role-menu-icon">&#128202;</span>
                <h2 class="h5 fw-bold">Rekapan</h2>
                <p>Lihat rekap dispensasi yang sudah dicatat.</p>
                <span class="fw-bold text-primary">Buka rekapan <span aria-hidden="true">→</span></span>
            </a>
        </div>
        @endif
    </div>

    </section>

    <section id="jadwal-saya" x-cloak x-show="activeSection === 'jadwal-saya'">
        <header class="role-page-header">
            <div class="role-page-eyebrow">Jadwal Guru</div>
            <h1>Jadwal Saya</h1>
            <div class="role-page-description">Jadwal mengajar hari ini.</div>
        </header>

        <div class="role-page-actions mb-3">
            <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a>
        </div>

        <div class="card-custom">
            <div class="card-header-custom">
                <div class="fw-bold" style="font-size:14px;">Jadwal Mengajar Hari Ini</div>
            </div>

            @if ($this->jadwalHariIni->isEmpty())
                <div class="text-center text-muted py-4">
                    Tidak ada jadwal mengajar hari ini.
                </div>
            @else
                @foreach ($this->jadwalHariIni as $jadwal)
                    <div class="d-flex align-items-center justify-content-between p-3 border-bottom">
                        <div>
                            <div class="fw-semibold">{{ $jadwal->kelas->nama_kelas ?? '-' }}</div>
                            <div class="text-muted small">
                                Jam ke-{{ $jadwal->jam_ke_mulai }}
                                @if ($jadwal->jam_ke_selesai != $jadwal->jam_ke_mulai)
                                    &ndash; {{ $jadwal->jam_ke_selesai }}
                                @endif
                            </div>
                        </div>
                        <div class="text-muted small">
                            {{ substr($jadwal->jam_mulai, 0, 5) }} &ndash; {{ substr($jadwal->jam_selesai, 0, 5) }}
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </section>
</div>
