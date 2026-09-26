<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\Jadwal;
use App\Models\JadwalPiket;
use App\Models\GuruPiket;
use Illuminate\Support\Facades\DB;
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
    // DAFTAR KELAS
    // =========================================================
    public function getKelasListProperty()
    {
        return Kelas::orderBy('nama_kelas')->get();
    }

    // =========================================================
    // JURNAL HARI INI
    // =========================================================
    public function getTodayJurnalProperty()
    {
        return $this->jurnal->filter(function ($jurnal) {
            return $jurnal->tanggal &&
                $jurnal->tanggal->format('Y-m-d') ===
                now('Asia/Jakarta')->format('Y-m-d');
        });
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
    // NAMA KELAS BERDASARKAN ID
    // =========================================================
    public function getKelasName($id)
    {
        return $this->kelasList
            ->firstWhere('id_kelas', $id)
            ?->nama_kelas ?? '-';
    }

    // =========================================================
    // DISPENSASI MASUK
    // =========================================================
    public function getDispensasiMasukProperty()
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
            ->where(
                'dispensasi_penerima.id_guru',
                $idGuru
            )
            ->whereNull(
                'dispensasi_penerima.dibaca_at'
            )
            ->where(
                'dispensasi.tanggal',
                now('Asia/Jakarta')->toDateString()
            )
            ->select(
                'dispensasi_penerima.*',
                'dispensasi.jenis_dispensasi',
                'dispensasi.tanggal',
                'dispensasi.jam_ke_mulai',
                'dispensasi.jam_ke_selesai',
                'dispensasi.jam_mulai',
                'dispensasi.jam_selesai',
                'dispensasi.alasan',
                'dispensasi.status',
                'siswa.nama_siswa',
                'kelas.nama_kelas'
            )
            ->orderByDesc('dispensasi.id_dispensasi')
            ->get();
    }

    // =========================================================
    // TANDAI DISPENSASI SUDAH DIBACA
    // =========================================================
    public function tandaiDibaca($idPenerima)
    {
        DB::table('dispensasi_penerima')
            ->where('id_penerima', $idPenerima)
            ->where(
                'id_guru',
                session('id_pengguna')
            )
            ->update([
                'dibaca_at' => now('Asia/Jakarta'),
                'updated_at' => now('Asia/Jakarta'),
            ]);
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

<div>

    {{-- =====================================================
         WELCOME
    ====================================================== --}}
    <div class="welcome-banner">

        <div class="d-flex justify-content-between align-items-center w-100">

            {{-- DATA GURU --}}
            <div style="min-width:0; flex:1 1 auto;">

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


    {{-- =====================================================
         NAVIGASI CEPAT
    ====================================================== --}}
    <div class="d-flex flex-wrap gap-2 my-3">

        <a
            href="{{ route('input-jurnal') }}"
            class="btn btn-app-primary btn-sm"
        >
            + Input Jurnal
        </a>

        <a
            href="{{ route('riwayat') }}"
            class="btn btn-outline-secondary btn-sm"
        >
            Riwayat
        </a>

        <a
            href="{{ route('notifikasi') }}"
            class="btn btn-outline-secondary btn-sm"
        >
            Notifikasi
        </a>

        @if(session('is_guru_piket'))

            <a
                href="{{ route('guru-piket') }}"
                class="btn btn-outline-secondary btn-sm"
            >
                Kehadiran Guru
            </a>

            <a
                href="{{ route('dispensasi') }}"
                class="btn btn-outline-secondary btn-sm"
            >
                Dispensasi Siswa
            </a>

        @endif

    </div>


    {{-- =====================================================
         TUGAS PIKET HARI INI
    ====================================================== --}}
    @if($this->tugasPiketHariIni->isNotEmpty())

        <div class="card-custom mb-3 border-start border-4 border-primary">

            <div
                class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"
            >

                <div>

                    <div class="text-muted small text-uppercase fw-semibold">
                        Tugas Guru Piket
                    </div>

                    <div class="fw-bold mt-1">
                        Hari ini
                    </div>

                    @foreach($this->tugasPiketHariIni as $piket)

                        <div class="text-muted small">

                            {{ substr($piket->jam_mulai, 0, 5) }}
                            –
                            {{ substr($piket->jam_selesai, 0, 5) }}

                            ·

                            {{ $piket->keterangan ?: 'Guru Piket' }}

                        </div>

                    @endforeach

                </div>

                <div class="d-flex align-items-center gap-2">

                    <span class="badge bg-success">
                        Aktif
                    </span>

                    @if(Route::has('guru-piket'))

                        <a
                            href="{{ route('guru-piket') }}"
                            class="btn btn-sm btn-outline-primary"
                        >
                            Buka Piket
                        </a>

                    @endif

                </div>

            </div>

        </div>

    @endif


    {{-- =====================================================
         STATISTIK
    ====================================================== --}}
    <div class="row g-3 my-3">

        {{-- TOTAL JURNAL --}}
        <div class="col-6 col-md-3">

            <div class="stat-card">

                <div
                    class="stat-value"
                    style="color:#2563eb;"
                >
                    {{ $this->jurnal->count() }}
                </div>

                <div
                    class="text-muted fw-medium"
                    style="font-size:11.5px;"
                >
                    Total Jurnal
                </div>

            </div>

        </div>


        {{-- KELAS DIAMPU --}}
        <div class="col-6 col-md-3">

            <div class="stat-card">

                <div
                    class="stat-value"
                    style="color:#059669;"
                >
                    {{ $this->uniqueKelas }}
                </div>

                <div
                    class="text-muted fw-medium"
                    style="font-size:11.5px;"
                >
                    Kelas Diampu
                </div>

            </div>

        </div>


        {{-- VALID --}}
        <div class="col-6 col-md-3">

            <div class="stat-card">

                <div
                    class="stat-value"
                    style="color:#16a34a;"
                >
                    {{ $this->divalidasiCount }}
                </div>

                <div
                    class="text-muted fw-medium"
                    style="font-size:11.5px;"
                >
                    Valid
                </div>

            </div>

        </div>


        {{-- MENUNGGU --}}
        <div class="col-6 col-md-3">

            <div class="stat-card">

                <div
                    class="stat-value"
                    style="color:#ca8a04;"
                >
                    {{ $this->menungguCount }}
                </div>

                <div
                    class="text-muted fw-medium"
                    style="font-size:11.5px;"
                >
                    Menunggu Konfirmasi
                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         TAB SECTION
    ====================================================== --}}
    <div x-data="{ tabAktif: 'jadwal' }">

        {{-- TAB MENU --}}
        <ul class="nav nav-tabs mb-3">

            {{-- JADWAL --}}
            <li class="nav-item">

                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: tabAktif === 'jadwal' }"
                    @click="tabAktif = 'jadwal'"
                >
                    Jadwal Hari Ini
                </button>

            </li>


            {{-- JURNAL --}}
            <li class="nav-item">

                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: tabAktif === 'jurnal' }"
                    @click="tabAktif = 'jurnal'"
                >
                    Jurnal Saya

                    @if($this->menungguCount > 0)

                        <span class="badge bg-warning text-dark ms-1">
                            {{ $this->menungguCount }}
                        </span>

                    @endif

                </button>

            </li>


            {{-- DISPENSASI --}}
            <li class="nav-item">

                <button
                    type="button"
                    class="nav-link"
                    :class="{ active: tabAktif === 'dispensasi' }"
                    @click="tabAktif = 'dispensasi'"
                >
                    Dispensasi Masuk

                    @if($this->dispensasiMasuk->isNotEmpty())

                        <span class="badge bg-danger ms-1">
                            {{ $this->dispensasiMasuk->count() }}
                        </span>

                    @endif

                </button>

            </li>

        </ul>


        {{-- =====================================================
             TAB 1: JADWAL HARI INI
        ====================================================== --}}
        <div x-show="tabAktif === 'jadwal'">

            <div class="card-custom">

                <div class="card-header-custom">

                    <div
                        class="fw-bold"
                        style="font-size:14px;"
                    >
                        Jadwal Mengajar Hari Ini
                    </div>

                </div>


                @if($this->jadwalHariIni->isEmpty())

                    <div class="text-center text-muted py-4">
                        Tidak ada jadwal mengajar hari ini.
                    </div>

                @else

                    @foreach($this->jadwalHariIni as $jadwal)

                        <div
                            class="d-flex align-items-center justify-content-between p-3 border-bottom"
                        >

                            <div>

                                <div class="fw-semibold">
                                    {{ $jadwal->kelas->nama_kelas ?? '-' }}
                                </div>

                                <div class="text-muted small">

                                    Jam ke-{{ $jadwal->jam_ke_mulai }}

                                    @if(
                                        $jadwal->jam_ke_selesai !=
                                        $jadwal->jam_ke_mulai
                                    )

                                        &ndash;
                                        {{ $jadwal->jam_ke_selesai }}

                                    @endif

                                </div>

                            </div>

                            <div class="text-muted small">

                                {{ substr($jadwal->jam_mulai, 0, 5) }}

                                &ndash;

                                {{ substr($jadwal->jam_selesai, 0, 5) }}

                            </div>

                        </div>

                    @endforeach

                @endif

            </div>

        </div>


        {{-- =====================================================
             TAB 2: JURNAL SAYA
        ====================================================== --}}
        <div x-show="tabAktif === 'jurnal'">

            <div class="card-custom">

                <div
                    class="card-header-custom d-flex justify-content-between align-items-center"
                >

                    <div
                        class="fw-bold"
                        style="font-size:14px;"
                    >
                        Jurnal Saya Hari Ini
                    </div>

                    <a
                        href="{{ route('input-jurnal') }}"
                        class="btn btn-app-primary btn-sm"
                    >
                        + Input Jurnal
                    </a>

                </div>


                @if($this->todayJurnal->isEmpty())

                    <div class="text-center text-muted py-4">
                        Belum ada jurnal hari ini.
                    </div>

                @else

                    @foreach($this->todayJurnal as $jurnal)

                        <div
                            class="d-flex align-items-center justify-content-between p-3 border-bottom"
                        >

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

                                @if(
                                    $jurnal->status_validasi === 'Divalidasi'
                                )

                                    <span class="badge bg-success">
                                        ✓ Valid
                                    </span>

                                @elseif(
                                    $jurnal->status_validasi === 'Ditolak'
                                )

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


        {{-- =====================================================
             TAB 3: DISPENSASI MASUK
        ====================================================== --}}
        <div x-show="tabAktif === 'dispensasi'">

            @if($this->dispensasiMasuk->isEmpty())

                <div class="card-custom">

                    <div class="text-center text-muted py-4">
                        Tidak ada notifikasi dispensasi masuk untuk kelas Anda hari ini.
                    </div>

                </div>

            @else

                <div class="card-custom mb-3">

                    <div
                        class="card-header-custom d-flex justify-content-between align-items-center"
                    >

                        <div
                            class="fw-bold"
                            style="font-size:14px;"
                        >
                            🔔 Dispensasi Siswa
                        </div>

                        <span class="badge bg-danger">
                            {{ $this->dispensasiMasuk->count() }} Baru
                        </span>

                    </div>


                    @foreach($this->dispensasiMasuk as $dispensasi)

                        <div class="p-3 border-bottom">

                            <div
                                class="d-flex justify-content-between align-items-start"
                            >

                                <div>

                                    <div class="fw-bold">
                                        {{ $dispensasi->nama_siswa }}
                                    </div>

                                    <div class="text-muted small">
                                        {{ $dispensasi->nama_kelas }}
                                    </div>

                                </div>


                                <div
                                    class="d-flex flex-column align-items-end gap-1"
                                >

                                    <span class="badge bg-warning text-dark">
                                        {{ $dispensasi->jenis_dispensasi }}
                                    </span>


                                    @if(
                                        $dispensasi->status ===
                                        'Menunggu Persetujuan'
                                    )

                                        <span class="badge bg-warning text-dark">
                                            🟡 Menunggu Persetujuan
                                        </span>

                                    @elseif(
                                        $dispensasi->status ===
                                        'Disetujui'
                                    )

                                        <span class="badge bg-success">
                                            🟢 Disetujui
                                        </span>

                                    @elseif(
                                        $dispensasi->status ===
                                        'Ditolak'
                                    )

                                        <span class="badge bg-danger">
                                            🔴 Ditolak
                                        </span>

                                    @else

                                        <span class="badge bg-secondary">
                                            {{ $dispensasi->status }}
                                        </span>

                                    @endif

                                </div>

                            </div>


                            <div class="mt-2 small">

                                @if(
                                    $dispensasi->jenis_dispensasi ===
                                    'Per Jam'
                                )

                                    <div>
                                        🕐 Jam ke-{{
                                            $dispensasi->jam_ke_mulai
                                        }}

                                        @if(
                                            $dispensasi->jam_ke_selesai !=
                                            $dispensasi->jam_ke_mulai
                                        )

                                            sampai
                                            {{
                                                $dispensasi->jam_ke_selesai
                                            }}

                                        @endif

                                    </div>

                                    <div class="text-muted">

                                        {{
                                            substr(
                                                $dispensasi->jam_mulai,
                                                0,
                                                5
                                            )
                                        }}

                                        -

                                        {{
                                            substr(
                                                $dispensasi->jam_selesai,
                                                0,
                                                5
                                            )
                                        }}

                                    </div>

                                @else

                                    <div>
                                        🕐 Sehari penuh
                                    </div>

                                @endif

                            </div>


                            <div class="mt-2 small">

                                <span class="text-muted">
                                    Alasan:
                                </span>

                                {{ $dispensasi->alasan }}

                            </div>


                            <div class="mt-3 text-end">

                                <div
                                    class="d-flex gap-2 justify-content-end"
                                >

                                    <a
                                        href="{{ route(
                                            'surat-dispensasi.detail',
                                            $dispensasi->id_dispensasi
                                        ) }}"
                                        class="btn btn-sm btn-primary"
                                    >
                                        📄 Lihat Surat
                                    </a>


                                    <button
                                        wire:click="tandaiDibaca({{
                                            $dispensasi->id_penerima
                                        }})"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        ✓ Sudah Dilihat
                                    </button>

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>

            @endif

        </div>

    </div>

</div>