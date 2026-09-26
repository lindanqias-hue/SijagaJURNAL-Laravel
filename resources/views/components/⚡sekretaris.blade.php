<?php

use Livewire\Component;
use App\Models\Jurnal;
use App\Models\Jadwal;
use Carbon\Carbon;

new class extends Component
{
    public $jurnalTerpilih = null;
    public string $catatanSekretaris = '';
    public string $activeSection = 'dashboard';

    public function mount(): void
    {
        $menu = request()->query('menu', 'dashboard');
        $this->activeSection = in_array($menu, ['dashboard', 'validasi-jurnal', 'riwayat-validasi'], true)
            ? $menu
            : 'dashboard';

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
        if (!in_array($section, ['dashboard', 'validasi-jurnal', 'riwayat-validasi'], true)) {
            return;
        }

        $this->activeSection = $section;
        $this->tutupDetail();
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
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->get()
            ->reject(fn($jurnal) => $this->sudahSelesai($jurnal))
            ->values();
    }

    public function getRiwayatProperty()
    {
        return Jurnal::where('id_kelas', session('id_kelas'))
            ->whereIn('status_konfirmasi_sekretaris', ['Sesuai', 'Tidak Sesuai'])
            ->with(['guru', 'kelas'])
            ->orderByDesc('waktu_konfirmasi_sekretaris')
            ->limit(20)
            ->get();
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

        .sekretaris-hero {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border-radius: 18px;
            color: #fff;
            background: linear-gradient(120deg, #10233f, #2563eb 68%, #0f766e);
            box-shadow: 0 14px 32px rgba(16, 35, 63, .16);
        }

        .sekretaris-hero::after {
            content: '';
            position: absolute;
            width: 230px;
            height: 230px;
            right: 4%;
            top: -125px;
            border: 1px solid rgba(255, 255, 255, .18);
            border-radius: 50%;
            box-shadow: 0 0 0 28px rgba(255, 255, 255, .04), 0 0 0 56px rgba(255, 255, 255, .025);
            pointer-events: none;
        }

        .sekretaris-hero>* {
            position: relative;
            z-index: 1;
        }

        .sekretaris-menu-card {
            display: flex;
            flex-direction: column;
            height: 100%;
            padding: 22px;
            border: 1px solid #e2e8f3;
            border-radius: 16px;
            background: #fff;
            color: inherit;
            text-decoration: none;
            box-shadow: 0 5px 18px rgba(22, 33, 62, .045);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }

        .sekretaris-menu-card:hover,
        .sekretaris-menu-card:focus-visible {
            transform: translateY(-4px);
            border-color: #93b4ed;
            box-shadow: 0 14px 28px rgba(37, 99, 235, .12);
            outline: none;
        }

        .sekretaris-menu-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            margin-bottom: 18px;
            border-radius: 14px;
            color: #1d4ed8;
            background: #dbeafe;
            font-size: 22px;
        }

        .sekretaris-menu-card p {
            flex: 1;
            color: #6b7a99;
            font-size: 13px;
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
    @if ($activeSection === 'dashboard')
    <section class="sekretaris-dashboard" id="dashboard">
        <div class="sekretaris-hero mb-4">
            <div class="text-uppercase fw-bold"
                style="font-size:11px; letter-spacing:.14em; color:rgba(255,255,255,.72);">SIJAGA · PANEL SEKRETARIS
            </div>
            <h1 class="fw-bold mt-2 mb-1" style="font-size:clamp(24px, 4vw, 32px);">Selamat datang,
                {{ explode(',', session('nama', 'Sekretaris'))[0] }}
            </h1>
            <p class="mb-0" style="color:rgba(255,255,255,.82);">Pilih menu untuk memeriksa jurnal kelas atau melihat
                riwayat validasi.</p>
        </div>

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('validasi-jurnal')"
                    class="sekretaris-menu-card w-100 text-start">
                    <span class="sekretaris-menu-icon">&#9989;</span>
                    <h2 class="h5 fw-bold">Validasi Jurnal</h2>
                    <p>Periksa jurnal yang masuk dan konfirmasi kehadiran guru di kelas.</p>
                    <span class="fw-bold text-primary">Buka validasi <span aria-hidden="true">→</span></span>
                </button>
            </div>
            <div class="col-12 col-md-6">
                <button type="button" wire:click="bukaMenu('riwayat-validasi')"
                    class="sekretaris-menu-card w-100 text-start">
                    <span class="sekretaris-menu-icon" style="color:#047857;background:#d1fae5;">&#128203;</span>
                    <h2 class="h5 fw-bold">Riwayat Validasi Jurnal</h2>
                    <p>Lihat hasil konfirmasi jurnal yang sudah diproses sekretaris.</p>
                    <span class="fw-bold text-primary">Buka riwayat <span aria-hidden="true">→</span></span>
                </button>
            </div>
        </div>
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
        <div class="sekretaris-hero mb-4">
            <div class="text-uppercase fw-bold"
                style="font-size:11px;letter-spacing:.14em;color:rgba(255,255,255,.72);">PENGELOLAAN JURNAL</div>
            <h1 class="h3 fw-bold mt-2 mb-1">Validasi Jurnal</h1>
            <p class="mb-0" style="color:rgba(255,255,255,.82);">Konfirmasi kehadiran guru setelah jam pelajaran
                selesai.</p>
        </div>
        <button type="button" wire:click="bukaMenu('dashboard')"
            class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div id="rekap" class="row g-3 mb-4">
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
        <div id="jurnal-kelas" class="sekretaris-panel mb-4">
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
    </section>
    @endif

    {{-- RIWAYAT VALIDASI --}}
    @if ($activeSection === 'riwayat-validasi')
    <section id="riwayat-validasi">
        <div class="sekretaris-hero mb-4">
            <div class="text-uppercase fw-bold"
                style="font-size:11px;letter-spacing:.14em;color:rgba(255,255,255,.72);">ARSIP KONFIRMASI</div>
            <h1 class="h3 fw-bold mt-2 mb-1">Riwayat Validasi Jurnal</h1>
            <p class="mb-0" style="color:rgba(255,255,255,.82);">Daftar hasil validasi jurnal yang sudah dikonfirmasi.
            </p>
        </div>
        <button type="button" wire:click="bukaMenu('dashboard')"
            class="btn btn-outline-primary btn-sm fw-semibold mb-3">← Kembali ke Dashboard</button>

        <div class="sekretaris-panel">
            <div style="padding: 20px; border-bottom: 1px solid #ddd;">
                <h3 style="margin: 0; font-size: 1.25rem;">Riwayat Konfirmasi</h3>
            </div>

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