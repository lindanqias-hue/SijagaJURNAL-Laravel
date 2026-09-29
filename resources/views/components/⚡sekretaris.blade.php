<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Dispensasi;
use Illuminate\Support\Collection;
use Carbon\Carbon;

new class extends Component
{
    public $jurnalTerpilih = null;
    public string $catatanSekretaris = '';
    public string $activeSection = 'dashboard';
    public string $tanggalSurat = '';

    public function mount(): void
    {
        $menu = request()->query('menu', 'dashboard');
        $this->activeSection = match ($menu) {
            'jurnal-kelas' => 'validasi-jurnal',
            'riwayat-validasi' => 'rekap',
            'dashboard', 'data-kelas', 'validasi-jurnal', 'tugas-guru', 'kehadiran', 'surat-dispensasi', 'rekap' => $menu,
            default => 'dashboard',
        };
        $this->tanggalSurat = Carbon::now('Asia/Jakarta')->toDateString();

        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        if (session('role') !== 'sekretaris') {
            $this->redirectRoute('dashboard');
        }
    }

    public function bukaMenu(string $section): void
    {
        if (!in_array($section, ['dashboard', 'data-kelas', 'validasi-jurnal', 'tugas-guru', 'kehadiran', 'surat-dispensasi', 'rekap'], true)) {
            return;
        }

        $this->redirectRoute('sekretaris', ['menu' => $section], true, true);
    }

    /*
    |--------------------------------------------------------------------------
    | CEK APAKAH JAM PELAJARAN SUDAH SELESAI
    |--------------------------------------------------------------------------
    */

    private function namaHariIndonesia($tanggal): ?string
    {
        $hariMap = [
            'Monday'    => 'Senin',
            'Tuesday'   => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday'  => 'Kamis',
            'Friday'    => 'Jumat',
            'Saturday'  => 'Sabtu',
            'Sunday'    => 'Minggu',
        ];

        $hariInggris = Carbon::parse($tanggal)->format('l');

        return $hariMap[$hariInggris] ?? null;
    }

    public function jamKeTerakhirBlok(Jurnal $jurnal): int
    {
        $hari = $this->namaHariIndonesia($jurnal->tanggal);

        if (!$hari) {
            return (int) $jurnal->jam_ke;
        }

        $jadwalHariItu = Jadwal::where('id_guru', $jurnal->id_guru)
            ->where('id_kelas', $jurnal->id_kelas)
            ->where('hari', $hari)
            ->orderBy('jam_ke')
            ->get();

        $jamKeTerakhir = (int) $jurnal->jam_ke;

        foreach ($jadwalHariItu as $jadwal) {
            if ((int) $jadwal->jam_ke === $jamKeTerakhir + 1) {
                $jamKeTerakhir = (int) $jadwal->jam_ke;
            }
        }

        return $jamKeTerakhir;
    }

    private function jamSelesaiBlokTerakhir(Jurnal $jurnal): ?string
    {
        $hari = $this->namaHariIndonesia($jurnal->tanggal);

        if (!$hari) {
            return null;
        }

        $jamKeTerakhir = $this->jamKeTerakhirBlok($jurnal);

        $jadwalTerakhir = Jadwal::where('id_guru', $jurnal->id_guru)
            ->where('id_kelas', $jurnal->id_kelas)
            ->where('hari', $hari)
            ->where('jam_ke', $jamKeTerakhir)
            ->first();

        return $jadwalTerakhir?->jam_selesai;
    }

    private function sudahSelesai(Jurnal $jurnal): bool
    {
        $jamSelesaiTerakhir = $this->jamSelesaiBlokTerakhir($jurnal);

        if (!$jamSelesaiTerakhir || !$jurnal->tanggal) {
            return true;
        }

        $tanggalString = $jurnal->tanggal instanceof Carbon
            ? $jurnal->tanggal->format('Y-m-d')
            : Carbon::parse($jurnal->tanggal)->format('Y-m-d');

        $batasSelesai = Carbon::parse($tanggalString . ' ' . $jamSelesaiTerakhir, 'Asia/Jakarta');

        return now('Asia/Jakarta')->greaterThanOrEqualTo($batasSelesai);
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR JURNAL KELAS INI
    |--------------------------------------------------------------------------
    */

    public function getMenungguProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('adalah_pengajuan_izin', false)
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get()
            ->filter(fn($jurnal) => $this->sudahSelesai($jurnal))
            ->values();
    }

    public function getBelumSelesaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('adalah_pengajuan_izin', false)
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get()
            ->reject(fn($jurnal) => $this->sudahSelesai($jurnal))
            ->values();
    }

    public function getSuratDispensasiDisetujuiProperty(): Collection
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return collect();
        }

        return Dispensasi::query()
            ->with(['siswa'])
            ->where('id_kelas', $idKelas)
            ->where('jenis_surat', Dispensasi::JENIS_SURAT_DISPENSASI)
            ->where('status', Dispensasi::STATUS_DISETUJUI)
            ->whereDate('tanggal', $this->tanggalSuratEfektif())
            ->orderBy('tanggal')
            ->orderBy('jam_mulai')
            ->get();
    }

    /**
     * Surat hanya dianggap "berlaku" pada hari yang sama, dan untuk dispensasi
     * per jam hanya selama jendela jam_mulai - 15 menit sampai jam_selesai.
     * Ini hanya untuk penanda di daftar; sekretaris tetap bisa melihat dan
     * mengunduh surat yang sudah lewat.
     */
    public function suratBerlaku(Dispensasi $dispensasi): bool
    {
        $now = Carbon::now('Asia/Jakarta');

        if (! $dispensasi->tanggal || ! $dispensasi->tanggal->isSameDay($now)) {
            return false;
        }

        if ($dispensasi->jenis_dispensasi !== 'Per Jam') {
            return true;
        }

        if (! $dispensasi->jam_mulai || ! $dispensasi->jam_selesai) {
            return false;
        }

        $tanggal = $dispensasi->tanggal->toDateString();
        $mulai = Carbon::parse($tanggal.' '.$dispensasi->jam_mulai, 'Asia/Jakarta')->subMinutes(15);
        $selesai = Carbon::parse($tanggal.' '.$dispensasi->jam_selesai, 'Asia/Jakarta');

        return $now->betweenIncluded($mulai, $selesai);
    }

    public function getTanggalSuratEfektifProperty(): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggalSurat) === 1) {
            return $this->tanggalSurat;
        }

        return Carbon::now('Asia/Jakarta')->toDateString();
    }

    private function tanggalSuratEfektif(): string
    {
        return $this->tanggalSuratEfektif;
    }

    public function getPengajuanIzinDisetujuiProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas', 'validator'])
            ->where('id_kelas', session('id_kelas'))
            ->where('adalah_pengajuan_izin', true)
            ->where('status_validasi', 'Divalidasi')
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->get();
    }

    public function getRiwayatProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->whereIn('status_konfirmasi_sekretaris', ['Sesuai', 'Tidak Sesuai'])
            ->with(['guru', 'kelas'])
            ->orderByDesc('waktu_konfirmasi_sekretaris')
            ->limit(100)
            ->get();
    }

    public function getKelasSekretarisProperty(): ?Kelas
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return null;
        }

        return Kelas::query()->find($idKelas);
    }

    public function getSiswaKelasProperty(): Collection
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return collect();
        }

        return Siswa::query()
            ->where('id_kelas', $idKelas)
            ->orderBy('nama_siswa')
            ->get();
    }

    public function getJadwalKelasProperty(): Collection
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return collect();
        }

        return Jadwal::query()
            ->with('guru')
            ->where('id_kelas', $idKelas)
            ->orderByRaw("
    CASE hari
        WHEN 'Senin' THEN 1
        WHEN 'Selasa' THEN 2
        WHEN 'Rabu' THEN 3
        WHEN 'Kamis' THEN 4
        WHEN 'Jumat' THEN 5
        WHEN 'Sabtu' THEN 6
        ELSE 7
    END
")
            ->orderBy('jam_ke')
            ->get();
    }

    public function getJurnalKelasProperty(): Collection
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return collect();
        }

        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->where('id_kelas', $idKelas)
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->limit(100)
            ->get();
    }

    public function getRingkasanKehadiranProperty(): array
    {
        $idKelas = session('id_kelas');

        if (! $idKelas) {
            return [];
        }

        return Jurnal::query()
            ->where('id_kelas', $idKelas)
            ->selectRaw('status_kehadiran_guru, COUNT(*) as jumlah')
            ->groupBy('status_kehadiran_guru')
            ->pluck('jumlah', 'status_kehadiran_guru')
            ->all();
    }

    public function getJumlahJurnalProperty(): int
    {
        return Jurnal::query()
            ->where('id_kelas', session('id_kelas'))
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | STATISTIK
    |--------------------------------------------------------------------------
    */

    public function getJumlahMenungguProperty()
    {
        return $this->menunggu->count();
    }

    public function getJumlahSesuaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Sesuai')
            ->count();
    }

    public function getJumlahTidakSesuaiProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->where('status_konfirmasi_sekretaris', 'Tidak Sesuai')
            ->count();
    }

    /*
    |--------------------------------------------------------------------------
    | PERIKSA / TUTUP DETAIL
    |--------------------------------------------------------------------------
    */

    public function periksa($id)
    {
        $jurnal = Jurnal::with(['guru', 'kelas'])->findOrFail($id);

        if ((int) $jurnal->id_kelas !== (int) session('id_kelas')) {
            return;
        }

        if (!$this->sudahSelesai($jurnal)) {
            session()->flash(
                'error',
                'Jam pelajaran guru ini belum selesai, belum bisa dikonfirmasi.'
            );
            return;
        }

        $this->activeSection = 'validasi-jurnal';
        $this->jurnalTerpilih = $jurnal;
        $this->catatanSekretaris = '';
        $this->resetErrorBag();

        $this->dispatch('sekretaris-menu-berubah', section: 'validasi-jurnal');
    }

    public function tutupDetail()
    {
        $this->jurnalTerpilih = null;
        $this->catatanSekretaris = '';
        $this->resetErrorBag();
    }

    /*
    |--------------------------------------------------------------------------
    | KONFIRMASI KEHADIRAN GURU
    |--------------------------------------------------------------------------
    */

    public function konfirmasiSesuai()
    {
        $this->simpanKonfirmasi('Sesuai');
    }

    public function konfirmasiTidakSesuai()
    {
        if (trim($this->catatanSekretaris) === '') {
            $this->addError(
                'catatanSekretaris',
                'Catatan wajib diisi jika kehadiran guru dinyatakan tidak sesuai.'
            );

            return;
        }

        $this->simpanKonfirmasi('Tidak Sesuai');
    }

    private function simpanKonfirmasi(string $status)
    {
        if (!$this->jurnalTerpilih) {
            return;
        }

        $this->jurnalTerpilih->update([
            'status_konfirmasi_sekretaris' => $status,
            'id_sekretaris'                 => session('id_pengguna'),
            'waktu_konfirmasi_sekretaris'  => now(),
            'catatan_sekretaris'           => $this->catatanSekretaris ?: null,
        ]);

        $this->jurnalTerpilih = null;
        $this->catatanSekretaris = '';

        session()->flash(
            'success',
            $status === 'Sesuai'
                ? 'Kehadiran guru dikonfirmasi sesuai.'
                : 'Kehadiran guru ditandai tidak sesuai.'
        );
    }
}; ?>

<div>
    <style>
        html {
            scroll-behavior: smooth;
        }

        .sekretaris-dashboard {
            color: #16213e;
        }

        .sekretaris-panel {
            overflow: hidden;
            border: 1px solid #e2e8f3;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 5px 18px rgba(22, 33, 62, .04);
        }

        .sekretaris-table th {
            padding: 12px;
            background: #f5f8fd;
            color: #52627d;
            font-size: 12px;
            white-space: nowrap;
        }

        .sekretaris-table td {
            padding: 12px;
            vertical-align: middle;
        }

        .sekretaris-table tbody tr+tr {
            border-top: 1px solid #edf1f7;
        }

        .tr-terpilih {
            background-color: #eff6ff !important;
            border-left: 4px solid #2563eb;
        }

        .btn-link-action {
            display: inline-block;
            padding: 8px 14px;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
        }

        .bg-sesuai {
            background-color: #16a34a;
        }

        .bg-periksa {
            background-color: #2563eb;
        }

        .badge-sesuai {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-tidak-sesuai {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>

    {{-- DASHBOARD --}}
    <nav class="section-tabs" aria-label="Navigasi section sekretaris">
        <div class="tab-pill-group">
            @foreach ([
                'dashboard' => 'Dashboard',
                'data-kelas' => 'Data Kelas',
                'validasi-jurnal' => 'Validasi Jurnal',
                'tugas-guru' => 'Tugas Guru',
                'kehadiran' => 'Kehadiran',
                'surat-dispensasi' => 'Surat Dispensasi',
                'rekap' => 'Rekap',
            ] as $kunciSection => $labelSection)
            <button type="button"
                class="tab-pill {{ $activeSection === $kunciSection ? 'active' : '' }}"
                @if ($activeSection === $kunciSection) aria-current="page" @endif
                wire:click="bukaMenu('{{ $kunciSection }}')"
            >{{ $labelSection }}</button>
            @endforeach
        </div>
    </nav>

    @if ($activeSection === 'dashboard')
    <section class="sekretaris-dashboard" id="dashboard">
        <div class="sekretaris-hero role-page-header">
            <div class="role-page-eyebrow">SIJAGA · PANEL SEKRETARIS
            </div>
            <h1 class="fw-bold mt-2 mb-1">Selamat datang,
                {{ explode(',', session('nama', 'Sekretaris'))[0] }}
            </h1>
            <p class="mb-0">Pilih menu untuk memeriksa jurnal, memantau kehadiran, mengunduh surat dispensasi, atau melihat rekap.</p>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('data-kelas')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#127891;</span>
                    <h2 class="h5 fw-bold">Data Kelas</h2>
                    <p>Lihat informasi kelas, daftar siswa, dan jadwal belajar kelas yang menjadi tanggung jawab Anda.</p>
                    <span class="fw-bold text-primary">Buka data kelas <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('validasi-jurnal')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#9989;</span>
                    <h2 class="h5 fw-bold">Validasi Jurnal</h2>
                    <p>{{ $this->jumlahMenunggu }} jurnal menunggu konfirmasi setelah jam pelajaran selesai.</p>
                    <span class="fw-bold text-primary">Buka validasi <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('kehadiran')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#9989;</span>
                    <h2 class="h5 fw-bold">Kehadiran</h2>
                    <p>Pantau status hadir, izin, sakit, dan tanpa keterangan berdasarkan jurnal kelas.</p>
                    <span class="fw-bold text-primary">Buka kehadiran <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('tugas-guru')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#128221;</span>
                    <h2 class="h5 fw-bold">Tugas Guru Tidak Hadir</h2>
                    <p>{{ $this->pengajuanIzinDisetujui->count() }} tugas dari pengajuan izin guru yang sudah disetujui Wakasek.</p>
                    <span class="fw-bold text-primary">Buka tugas guru <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('surat-dispensasi')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#128196;</span>
                    <h2 class="h5 fw-bold">Surat Dispensasi</h2>
                    <p>{{ $this->suratDispensasiDisetujui->count() }} surat siswa kelas ini disetujui. Pilih tanggal lain untuk melihat arsip surat.</p>
                    <span class="fw-bold text-primary">Buka surat dispensasi <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('rekap')" class="role-menu-card w-100 text-start">
                    <span class="role-menu-icon">&#128202;</span>
                    <h2 class="h5 fw-bold">Rekap</h2>
                    <p>Lihat hasil konfirmasi dan ringkasan jurnal kelas yang sudah diproses.</p>
                    <span class="fw-bold text-primary">Buka rekap <span aria-hidden="true">→</span></span>
                </button>
            </div>
        </div>
    </section>
    @endif

    @if ($activeSection === 'data-kelas')
    <section id="data-kelas">
        <header class="role-page-header">
            <div class="role-page-eyebrow">DATA KELAS</div>
            <h1>Informasi Kelas</h1>
            <p>Data kelas yang ditugaskan kepada akun Sekretaris ini.</p>
        </header>
        <button type="button" wire:click="bukaMenu('dashboard')" class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        @if ($this->kelasSekretaris)
        <div class="row g-3 mb-4">
            <div class="col-12 col-md-6">
                <div class="card-custom p-4 h-100">
                    <div class="text-muted small">Nama Kelas</div>
                    <div class="fs-4 fw-bold text-primary">{{ $this->kelasSekretaris->nama_kelas }}</div>
                    <div class="text-muted mt-3">Wali Kelas</div>
                    <div class="fw-semibold">{{ $this->kelasSekretaris->wali_kelas ?: 'Belum ditentukan' }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card-custom p-4 h-100">
                    <div class="text-muted small">Jumlah Siswa Terdaftar</div>
                    <div class="stat-value text-primary">{{ $this->siswaKelas->count() }}</div>
                    <div class="text-muted mt-2">Daftar siswa dan jadwal kelas ditampilkan di bawah.</div>
                </div>
            </div>
        </div>

        <div class="card-custom overflow-hidden mb-4">
            <div class="card-header-custom">Daftar Siswa</div>
            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle">
                    <thead><tr><th>No.</th><th>Nama Siswa</th></tr></thead>
                    <tbody>
                        @forelse ($this->siswaKelas as $index => $siswa)
                        <tr wire:key="sekretaris-siswa-{{ $siswa->id_siswa }}"><td>{{ $index + 1 }}</td><td>{{ $siswa->nama_siswa }}</td></tr>
                        @empty
                        <tr><td colspan="2" class="text-center text-muted py-4">Belum ada data siswa untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-custom overflow-hidden">
            <div class="card-header-custom">Jadwal Mengajar Kelas</div>
            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle">
                    <thead><tr><th>Hari</th><th>Jam</th><th>Waktu</th><th>Guru</th><th>Mata Pelajaran</th></tr></thead>
                    <tbody>
                        @forelse ($this->jadwalKelas as $jadwal)
                        <tr wire:key="sekretaris-jadwal-{{ $jadwal->id_jadwal }}"><td>{{ $jadwal->hari }}</td><td>{{ $jadwal->jam_ke }}</td><td>{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</td><td>{{ $jadwal->guru?->nama ?? '-' }}</td><td>{{ $jadwal->guru?->mapel_diampu ?? '-' }}</td></tr>
                        @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada jadwal mengajar untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="alert alert-warning">Akun ini belum ditautkan ke kelas. Hubungi administrator untuk mengatur data kelas akun Sekretaris.</div>
        @endif
    </section>
    @endif

    @if (session()->has('success'))
    <div class="alert alert-success shadow-sm my-3" role="status">✓ {{ session('success') }}</div>
    @endif

    @if (session()->has('error'))
    <div class="alert alert-danger shadow-sm my-3" role="alert">✕ {{ session('error') }}</div>
    @endif

    {{-- VALIDASI JURNAL --}}
    @if ($activeSection === 'validasi-jurnal')
    <section id="validasi-jurnal">
        <div class="sekretaris-hero role-page-header">
            <div class="role-page-eyebrow">VALIDASI KEHADIRAN</div>
            <h1 class="h3 fw-bold mt-2 mb-1">Validasi Jurnal</h1>
            <p class="mb-0">Periksa jurnal terbaru dan konfirmasi kehadiran guru setelah jam pelajaran selesai.</p>
        </div>
        <button type="button" wire:click="bukaMenu('dashboard')"
            class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div id="ringkasan-jurnal" class="row g-3 mb-4">
            <div class="col-12 col-sm-4">
                <div class="stat-card p-3 border rounded bg-white">
                    <div>
                        <div class="text-muted small">Menunggu Konfirmasi</div>
                        <div class="stat-value text-primary fs-3 fw-bold">{{ $this->jumlahMenunggu }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="stat-card p-3 border rounded bg-white">
                    <div>
                        <div class="text-muted small">Sesuai</div>
                        <div class="stat-value text-success fs-3 fw-bold">{{ $this->jumlahSesuai }}</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-sm-4">
                <div class="stat-card p-3 border rounded bg-white">
                    <div>
                        <div class="text-muted small">Tidak Sesuai</div>
                        <div class="stat-value text-danger fs-3 fw-bold">{{ $this->jumlahTidakSesuai }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DETAIL & FORM KONFIRMASI (LANGSUNG TAMPIL DI ATAS SAAT TERPILIH) --}}
        @if ($jurnalTerpilih)
        <div id="detail-jurnal" class="sekretaris-panel mb-4" style="border: 2px solid #2563eb;">
            <div
                style="padding: 20px; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; color: #1e40af;">🔍 Detail Jurnal</h3>
                    <p style="margin: 5px 0 0; color: #777; font-size: 0.875rem;">
                        Konfirmasi apakah guru benar-benar hadir langsung di kelas.
                    </p>
                </div>
                <button type="button" wire:click="tutupDetail" style="
                    border: none; background: #e2e8f0; padding: 8px 12px;
                    border-radius: 8px; cursor: pointer; font-weight: 600;
                ">✕ Tutup</button>
            </div>

            <div class="row g-3" style="padding: 20px;">
                <div class="col-12 col-md-6">
                    <strong>Guru</strong>
                    <div style="margin-top: 5px;">{{ $jurnalTerpilih->guru->nama ?? '-' }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <strong>Tanggal</strong>
                    <div style="margin-top: 5px;">
                        {{ \Carbon\Carbon::parse($jurnalTerpilih->tanggal)->translatedFormat('d F Y') }}
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <strong>Jam Ke</strong>
                    <div style="margin-top: 5px;">Jam {{ $jurnalTerpilih->jam_ke }}</div>
                </div>
                <div class="col-12 col-md-6">
                    <strong>Status Kehadiran (Laporan Guru)</strong>
                    <div style="margin-top: 5px;"><span
                            class="badge bg-info text-dark">{{ $jurnalTerpilih->status_kehadiran_guru }}</span></div>
                </div>
                <div class="col-12">
                    <strong>Materi</strong>
                    <div
                        style="margin-top: 5px; padding: 12px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                        {{ $jurnalTerpilih->materi }}
                    </div>
                </div>
            </div>

            <div style="padding: 20px; border-top: 1px solid #eee;">
                <h3 style="margin-top: 0; font-size: 1.1rem;">Konfirmasi Kehadiran</h3>

                <label class="form-label">Catatan (wajib jika "Tidak Sesuai")</label>
                <textarea wire:model="catatanSekretaris" rows="3"
                    placeholder="Contoh: guru tidak masuk kelas, hanya memberi tugas lewat WA, dsb."
                    style="width: 100%; margin-top: 8px; padding: 12px; border: 1px solid #ccc; border-radius: 8px; resize: vertical; box-sizing: border-box;"></textarea>

                @error('catatanSekretaris')
                <div style="color: #dc2626; margin-top: 5px; font-size: 0.875rem;">{{ $message }}</div>
                @enderror

                <div style="margin-top: 15px; display: flex; gap: 10px;">
                    <button type="button" wire:click="tutupDetail" style="
                        padding: 10px 18px; border: 1px solid #ccc; background: white;
                        border-radius: 8px; cursor: pointer;
                    ">Batal</button>

                    <button type="button" wire:click="konfirmasiTidakSesuai" style="
                        padding: 10px 18px; border: none; background: #dc2626;
                        color: white; border-radius: 8px; cursor: pointer;
                    ">✕ Tidak Sesuai</button>

                    <button type="button" wire:click="konfirmasiSesuai" style="
                        padding: 10px 18px; border: none; background: #16a34a;
                        color: white; border-radius: 8px; cursor: pointer;
                    ">✓ Sesuai, Guru Hadir</button>
                </div>
            </div>
        </div>
        @endif

        {{-- DAFTAR JURNAL PERLU DIKONFIRMASI --}}
        <div id="jurnal-perlu-dikonfirmasi" class="sekretaris-panel mb-4">
            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0; font-size: 1.25rem;">🔔 Perlu Dikonfirmasi</h3>
                <p style="margin: 5px 0 0; color: #777; font-size: 0.875rem;">
                    Jurnal di kelas ini yang jam pelajarannya sudah selesai dan siap diperiksa.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table sekretaris-table mb-0">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left;">No</th>
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                            <th style="padding: 12px; text-align: left;">Guru</th>
                            <th style="padding: 12px; text-align: left;">Jam</th>
                            <th style="padding: 12px; text-align: left;">Materi</th>
                            <th style="padding: 12px; text-align: center;">Status Lapor Guru</th>
                            <th style="padding: 12px; text-align: center;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->menunggu as $index => $jurnal)
                        @php $isTerpilih = $jurnalTerpilih && $jurnalTerpilih->id_jurnal === $jurnal->id_jurnal; @endphp
                        <tr class="{{ $isTerpilih ? 'tr-terpilih' : '' }}" style="border-top: 1px solid #eee;">
                            <td style="padding: 12px;">{{ $index + 1 }}</td>
                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                            <td style="padding: 12px;">
                                @php $jamAkhir = $this->jamKeTerakhirBlok($jurnal); @endphp
                                Jam {{ $jurnal->jam_ke }}
                                @if ($jamAkhir > $jurnal->jam_ke)
                                <br><small style="color:#777;">s/d jam {{ $jamAkhir }}</small>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->materi }}</td>
                            <td style="padding: 12px; text-align: center;">
                                <span class="badge bg-info text-dark">{{ $jurnal->status_kehadiran_guru }}</span>
                            </td>
                            <td style="padding: 12px; text-align: center;">
                                <button type="button" wire:click="periksa({{ $jurnal->id_jurnal }})"
                                    class="btn-link-action {{ $isTerpilih ? 'bg-sesuai' : 'bg-periksa' }}">
                                    {{ $isTerpilih ? '✓ Memeriksa' : 'Periksa' }}
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" style="padding: 40px; text-align: center; color: #777;">
                                ✓ Tidak ada jurnal yang perlu dikonfirmasi saat ini.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- JURNAL YANG JAM PELAJARANNYA MASIH BERLANGSUNG --}}
        @if ($this->belumSelesai->count())
        <div class="sekretaris-panel mb-4">
            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0; font-size: 1.25rem;">⏳ Masih Berlangsung</h3>
                <p style="margin: 5px 0 0; color: #777; font-size: 0.875rem;">
                    Jurnal ini baru dapat dikonfirmasi setelah jam pelajaran berakhir.
                </p>
            </div>

            <div class="table-responsive">
                <table class="table sekretaris-table mb-0">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                            <th style="padding: 12px; text-align: left;">Guru</th>
                            <th style="padding: 12px; text-align: left;">Jam</th>
                            <th style="padding: 12px; text-align: left;">Materi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->belumSelesai as $jurnal)
                        <tr style="border-top: 1px solid #eee; color: #999;">
                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                            <td style="padding: 12px;">
                                @php $jamAkhir = $this->jamKeTerakhirBlok($jurnal); @endphp
                                Jam {{ $jurnal->jam_ke }}
                                @if ($jamAkhir > $jurnal->jam_ke)
                                <br><small>menunggu s/d jam {{ $jamAkhir }} selesai</small>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->materi }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        <div class="card-custom overflow-hidden mb-4">
            <div class="card-header-custom">Jurnal Terbaru Kelas {{ $this->kelasSekretaris?->nama_kelas ?? '' }} <span class="text-muted small">Maksimal 100 entri terbaru</span></div>
            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle">
                    <thead><tr><th>Tanggal</th><th>Jam</th><th>Guru</th><th>Materi</th><th>Kehadiran</th><th>Status Jurnal</th><th>Konfirmasi</th></tr></thead>
                    <tbody>
                        @forelse ($this->jurnalKelas as $jurnal)
                        <tr wire:key="sekretaris-jurnal-{{ $jurnal->id_jurnal }}"><td>{{ $jurnal->tanggal?->format('d/m/Y') }}</td><td>{{ $jurnal->jam_ke }}</td><td>{{ $jurnal->guru?->nama ?? '-' }}</td><td>{{ $jurnal->materi }}</td><td>{{ $jurnal->status_kehadiran_guru }}</td><td>{{ $jurnal->status_validasi }}</td><td>{{ $jurnal->status_konfirmasi_sekretaris }}</td></tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada jurnal yang dikirim untuk kelas ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif

    @if ($activeSection === 'tugas-guru')
    <section id="tugas-guru">
        <header class="role-page-header">
            <div class="role-page-eyebrow">TUGAS GURU TIDAK HADIR</div>
            <h1>Tugas Guru Tidak Hadir</h1>
            <p>Daftar titipan tugas dari pengajuan izin guru yang sudah disetujui Wakasek untuk kelas {{ $this->kelasSekretaris?->nama_kelas ?? '-' }}.</p>
        </header>
        <button type="button" wire:click="bukaMenu('dashboard')" class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div class="sekretaris-panel mb-4">
            <div style="padding:20px;border-bottom:1px solid #ddd"><h2 class="h5 mb-1">Izin guru yang sudah disetujui</h2></div>
            <div class="table-responsive">
                <table class="table sekretaris-table mb-0">
                    <thead><tr><th>Tanggal</th><th>Jam</th><th>Guru</th><th>Jenis izin</th><th>Titipan tugas</th><th>Validasi Wakasek</th></tr></thead>
                    <tbody>
                        @forelse ($this->pengajuanIzinDisetujui as $izin)
                        <tr wire:key="sekretaris-izin-{{ $izin->id_jurnal }}"><td>{{ $izin->tanggal?->format('d/m/Y') }}</td><td>Jam {{ $izin->jam_ke }}</td><td>{{ $izin->guru?->nama ?? '-' }}</td><td>{{ $izin->jenis_izin }}</td><td>{{ $izin->materi }}</td><td>{{ $izin->validator?->nama ?? 'Disetujui' }}</td></tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada tugas dari pengajuan izin guru yang disetujui.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif

    @if ($activeSection === 'kehadiran')
    <section id="kehadiran">
        <header class="role-page-header">
            <div class="role-page-eyebrow">KEHADIRAN GURU</div>
            <h1>Rekap Kehadiran · {{ $this->kelasSekretaris?->nama_kelas ?? 'Kelas' }}</h1>
            <p>Ringkasan status kehadiran berdasarkan jurnal guru untuk kelas yang Anda tangani.</p>
        </header>
        <button type="button" wire:click="bukaMenu('dashboard')" class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div class="row g-3 mb-4">
            @foreach ([
                'Hadir' => ['label' => 'Hadir', 'class' => 'text-success'],
                'Izin' => ['label' => 'Izin', 'class' => 'text-primary'],
                'Sakit' => ['label' => 'Sakit', 'class' => 'text-warning'],
                'Tanpa Keterangan' => ['label' => 'Tanpa Keterangan', 'class' => 'text-danger'],
            ] as $status => $info)
            <div class="col-6 col-xl-3">
                <div class="stat-card d-block">
                    <div class="text-muted small">{{ $info['label'] }}</div>
                    <div class="stat-value {{ $info['class'] }}">{{ $this->ringkasanKehadiran[$status] ?? 0 }}</div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="card-custom overflow-hidden">
            <div class="card-header-custom">Riwayat Kehadiran Guru <span class="text-muted small">Maksimal 100 jurnal terbaru</span></div>
            <div class="table-responsive">
                <table class="table table-hover table-custom align-middle">
                    <thead><tr><th>Tanggal</th><th>Jam</th><th>Guru</th><th>Mata Pelajaran</th><th>Status Kehadiran</th><th>Konfirmasi Sekretaris</th></tr></thead>
                    <tbody>
                        @forelse ($this->jurnalKelas as $jurnal)
                        <tr wire:key="sekretaris-kehadiran-{{ $jurnal->id_jurnal }}"><td>{{ $jurnal->tanggal?->format('d/m/Y') }}</td><td>{{ $jurnal->jam_ke }}</td><td>{{ $jurnal->guru?->nama ?? '-' }}</td><td>{{ $jurnal->guru?->mapel_diampu ?? '-' }}</td><td><span @class(['badge-status', 'badge-status-success' => $jurnal->status_kehadiran_guru === 'Hadir', 'badge-status-warning' => in_array($jurnal->status_kehadiran_guru, ['Izin', 'Sakit'], true), 'badge-status-danger' => $jurnal->status_kehadiran_guru === 'Tanpa Keterangan'])>{{ $jurnal->status_kehadiran_guru }}</span></td><td>{{ $jurnal->status_konfirmasi_sekretaris }}</td></tr>
                        @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Belum ada data kehadiran dari jurnal kelas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif

    {{-- SURAT DISPENSASI --}}
    @if ($activeSection === 'surat-dispensasi')
    <section id="surat-dispensasi">
        <div class="sekretaris-hero role-page-header">
            <div class="role-page-eyebrow">SURAT KELAS</div>
            <h1 class="h3 fw-bold mt-2 mb-1">Surat Dispensasi Disetujui</h1>
            <p class="mb-0">Lihat atau unduh surat siswa di kelas Anda. Surat yang sudah lewat jamnya tetap bisa diunduh sebagai arsip.</p>
        </div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <button type="button" wire:click="bukaMenu('dashboard')" class="btn btn-outline-primary btn-sm fw-semibold">← Kembali ke Dashboard</button>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label mb-0 small fw-semibold" for="tanggal-surat-dispen">Tanggal</label>
                <input id="tanggal-surat-dispen" type="date" wire:model.live="tanggalSurat" class="form-control form-control-sm">
            </div>
        </div>

        <div class="row g-3">
            @forelse ($this->suratDispensasiDisetujui as $surat)
            <div class="col-12 col-lg-6" wire:key="surat-dispensasi-{{ $surat->id_dispensasi }}">
                <article class="sekretaris-panel p-3 h-100">
                    <div class="d-flex justify-content-between gap-3 align-items-start">
                        <div>
                            <div class="d-flex flex-wrap gap-1">
                                <span class="badge text-bg-success">Disetujui</span>
                                <span @class(['badge', 'text-bg-primary' => $this->suratBerlaku($surat), 'text-bg-secondary' => ! $this->suratBerlaku($surat)])>{{ $this->suratBerlaku($surat) ? 'Berlaku' : 'Kedaluwarsa' }}</span>
                            </div>
                            <h2 class="h5 fw-bold mt-2 mb-1">{{ $surat->siswa->nama_siswa ?? 'Siswa' }}</h2>
                            <div class="text-muted small">{{ $surat->jenis_surat }} · {{ $surat->jenis_dispensasi }}</div>
                        </div>
                        <span class="badge text-bg-light">{{ $surat->jam_mulai ? substr($surat->jam_mulai, 0, 5) . '–' . substr($surat->jam_selesai, 0, 5) : 'Sehari penuh' }}</span>
                    </div>
                    <div class="text-muted small mt-3">{{ $surat->nomor_surat ?: 'Nomor dibuat saat diunduh' }}</div>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('surat-dispensasi.lihat', $surat->id_dispensasi) }}" target="_blank" rel="noopener" class="btn btn-outline-primary fw-semibold">Lihat surat</a>
                        <a href="{{ route('surat-dispensasi.unduh', $surat->id_dispensasi) }}" class="btn btn-primary fw-semibold">Unduh surat</a>
                    </div>
                </article>
            </div>
            @empty
            <div class="col-12"><div class="sekretaris-panel text-center p-5 text-muted">Belum ada surat dispensasi kelas ini yang disetujui pada {{ $this->tanggalSuratEfektif ? \Carbon\Carbon::parse($this->tanggalSuratEfektif)->translatedFormat('d F Y') : '-' }}.</div></div>
            @endforelse
        </div>
    </section>
    @endif

    {{-- RIWAYAT VALIDASI --}}
    @if ($activeSection === 'rekap')
    <section id="rekap">
        <div class="sekretaris-hero role-page-header">
            <div class="role-page-eyebrow">REKAP KELAS</div>
            <h1 class="h3 fw-bold mt-2 mb-1">Rekap Jurnal & Kehadiran</h1>
            <p class="mb-0">Ringkasan jurnal serta catatan konfirmasi kehadiran guru di kelas ini.
            </p>
        </div>
        <button type="button" wire:click="bukaMenu('dashboard')"
            class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3"><div class="stat-card d-block"><div class="text-muted small">Total Jurnal</div><div class="stat-value text-primary">{{ $this->jumlahJurnal }}</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card d-block"><div class="text-muted small">Menunggu Konfirmasi</div><div class="stat-value text-warning">{{ $this->jumlahMenunggu }}</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card d-block"><div class="text-muted small">Sesuai</div><div class="stat-value text-success">{{ $this->jumlahSesuai }}</div></div></div>
            <div class="col-6 col-xl-3"><div class="stat-card d-block"><div class="text-muted small">Tidak Sesuai</div><div class="stat-value text-danger">{{ $this->jumlahTidakSesuai }}</div></div></div>
        </div>

        <div class="card-custom overflow-hidden">
            <div class="card-header-custom">Riwayat Konfirmasi <span class="text-muted small">100 konfirmasi terbaru</span></div>

            <div class="table-responsive">
                <table class="table sekretaris-table mb-0">
                    <thead>
                        <tr style="background: #f5f5f5;">
                            <th style="padding: 12px; text-align: left;">Tanggal</th>
                            <th style="padding: 12px; text-align: left;">Guru</th>
                            <th style="padding: 12px; text-align: left;">Jam</th>
                            <th style="padding: 12px; text-align: center;">Hasil</th>
                            <th style="padding: 12px; text-align: left;">Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->riwayat as $jurnal)
                        <tr style="border-top: 1px solid #eee;">
                            <td style="padding: 12px;">
                                {{ \Carbon\Carbon::parse($jurnal->tanggal)->format('d/m/Y') }}
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->guru->nama ?? '-' }}</td>
                            <td style="padding: 12px;">Jam {{ $jurnal->jam_ke }}</td>
                            <td style="padding: 12px; text-align: center;">
                                <span
                                    class="d-inline-block px-2 py-1 rounded-pill text-xs {{ $jurnal->status_konfirmasi_sekretaris === 'Sesuai' ? 'badge-sesuai' : 'badge-tidak-sesuai' }}">
                                    {{ $jurnal->status_konfirmasi_sekretaris }}
                                </span>
                            </td>
                            <td style="padding: 12px;">{{ $jurnal->catatan_sekretaris ?? '-' }}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" style="padding: 40px; text-align: center; color: #777;">
                                Belum ada riwayat konfirmasi.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    @endif
</div>
