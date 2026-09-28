<?php

use App\Models\Jadwal;
use App\Models\Dispensasi;
use App\Models\Jurnal;
use App\Services\KehadiranGuruService;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public string $filterJurnal = 'minggu';

    public string $filterBelumMengisi = 'minggu';

    public string $filterKehadiran = 'minggu';

    public string $filterStatusKehadiran = 'semua';

    public string $rekapTerbuka = '';

    public ?int $pengajuanIzinTerpilih = null;

    public string $catatanValidasiIzin = '';

    public function mount(): void
    {
        abort_unless(session('role') === 'wakasek', 403);

        $this->filterJurnal = in_array(request()->query('filterJurnal'), ['minggu', 'bulan', 'tahun', 'terbaru'], true)
            ? request()->query('filterJurnal')
            : $this->filterJurnal;
        $this->filterBelumMengisi = in_array(request()->query('filterBelumMengisi'), ['minggu', 'bulan', 'tahun'], true)
            ? request()->query('filterBelumMengisi')
            : $this->filterBelumMengisi;
        $this->filterKehadiran = in_array(request()->query('filterKehadiran'), ['minggu', 'bulan', 'tahun'], true)
            ? request()->query('filterKehadiran')
            : $this->filterKehadiran;
        $this->filterStatusKehadiran = in_array(request()->query('filterStatusKehadiran'), ['semua', 'Izin', 'Sakit', 'Kepentingan', KehadiranGuruService::STATUS_TANPA_KETERANGAN], true)
            ? request()->query('filterStatusKehadiran')
            : $this->filterStatusKehadiran;
    }

    public function bukaRekap(string $bagian): void
    {
        abort_unless(in_array($bagian, ['jurnal', 'belum-mengisi', 'kehadiran'], true), 404);

        $this->rekapTerbuka = $bagian;
    }

    public function tutupRekap(): void
    {
        $this->rekapTerbuka = '';
    }

    public function getPengajuanIzinMenungguProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->where('adalah_pengajuan_izin', true)
            ->where('status_validasi', 'Menunggu')
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->get();
    }

    public function validasiPengajuanIzin(int $idJurnal, string $status): void
    {
        abort_unless(session('role') === 'wakasek', 403);
        abort_unless(in_array($status, ['Divalidasi', 'Ditolak'], true), 422);

        \Illuminate\Support\Facades\DB::transaction(function () use ($idJurnal, $status): void {
            $jurnal = Jurnal::query()
                ->where('adalah_pengajuan_izin', true)
                ->where('status_validasi', 'Menunggu')
                ->lockForUpdate()
                ->find($idJurnal);

            if (! $jurnal) {
                return;
            }

            $jurnal->update([
                'status_validasi' => $status,
                'id_validator' => session('id_pengguna'),
                'tanggal_validasi' => now('Asia/Jakarta'),
                'catatan_validasi' => trim($this->catatanValidasiIzin) ?: null,
            ]);

            if ($status !== 'Divalidasi') {
                return;
            }

            Jadwal::query()
                ->where('id_guru', $jurnal->id_guru)
                ->where('id_kelas', $jurnal->id_kelas)
                ->where('hari', $jurnal->tanggal->locale('id')->translatedFormat('l'))
                ->get()
                ->each(function (Jadwal $jadwal) use ($jurnal): void {
                    \App\Models\KehadiranGuru::query()->updateOrCreate(
                        ['id_jadwal' => $jadwal->id_jadwal, 'tanggal' => $jurnal->tanggal->toDateString()],
                        ['id_guru' => $jurnal->id_guru, 'status' => $jurnal->status_kehadiran_guru, 'sumber' => 'Sistem', 'catatan' => $jurnal->jenis_izin]
                    );
                });
        });

        $this->pengajuanIzinTerpilih = null;
        $this->catatanValidasiIzin = '';
    }

    public function getStatsProperty(): array
    {
        $monitoring = $this->monitoringGuru;

        return [
            'jurnalHariIni' => $monitoring->whereNotNull('id_jurnal')->count(),
            'valid' => $monitoring->where('status_jurnal', 'Divalidasi')->count(),
            'perluKonfirmasi' => Jurnal::query()
                ->where('status_konfirmasi_sekretaris', 'Menunggu')
                ->where('status_validasi', 'Divalidasi')
                ->count(),
            'dispensasiMenunggu' => Dispensasi::query()
                ->where('status', Dispensasi::STATUS_MENUNGGU)
                ->count(),
            'tanpaKeterangan' => $monitoring->where('status_kehadiran', KehadiranGuruService::STATUS_TANPA_KETERANGAN)->count(),
            'belumIsiJurnal' => $monitoring->whereNull('id_jurnal')->count(),
        ];
    }

    public function getMonitoringGuruProperty()
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $hari = $sekarang->locale('id')->translatedFormat('l');
        $kehadiranGuru = app(KehadiranGuruService::class);

        return Jadwal::query()
            ->with('kelas')
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->leftJoin('jurnal as jurnal_hari_ini', function ($join) use ($sekarang) {
                $join->on('jurnal_hari_ini.id_guru', '=', 'jadwal.id_guru')
                    ->on('jurnal_hari_ini.id_kelas', '=', 'jadwal.id_kelas')
                    ->on('jurnal_hari_ini.jam_ke', '=', 'jadwal.jam_ke')
                    ->whereDate('jurnal_hari_ini.tanggal', $sekarang->toDateString());
            })
            ->where('jadwal.hari', $hari)
            ->select([
                'jadwal.*',
                'pengguna.nama as nama_guru',
                'pengguna.mapel_diampu',
                'jurnal_hari_ini.id_jurnal as jurnal_hari_ini_id',
                'jurnal_hari_ini.status_validasi as jurnal_hari_ini_status',
                'jurnal_hari_ini.status_konfirmasi_sekretaris as jurnal_hari_ini_konfirmasi',
            ])
            ->orderBy('jadwal.jam_ke')
            ->get()
            ->map(function (Jadwal $jadwal) use ($kehadiranGuru, $sekarang) {
                $jadwal->id_jurnal = $jadwal->jurnal_hari_ini_id;
                $jadwal->status_jurnal = $jadwal->jurnal_hari_ini_status;
                $jadwal->status_konfirmasi = $jadwal->jurnal_hari_ini_konfirmasi;
                $jadwal->status_kehadiran = $kehadiranGuru->statusUntukJadwal($jadwal, $sekarang);

                return $jadwal;
            });
    }

    public function getGuruTanpaKeteranganProperty()
    {
        return $this->monitoringGuru
            ->where('status_kehadiran', KehadiranGuruService::STATUS_TANPA_KETERANGAN)
            ->unique('id_guru')
            ->values();
    }

    public function getJadwalMengajarSekarangProperty()
    {
        $sekarang = Carbon::now('Asia/Jakarta');

        return $this->monitoringGuru
            ->filter(function (Jadwal $jadwal) use ($sekarang): bool {
                $mulai = Carbon::parse($sekarang->toDateString() . ' ' . $jadwal->jam_mulai, 'Asia/Jakarta');
                $selesai = Carbon::parse($sekarang->toDateString() . ' ' . $jadwal->jam_selesai, 'Asia/Jakarta');

                return $sekarang->betweenIncluded($mulai, $selesai);
            })
            ->values();
    }

    public function getDispensasiMenungguProperty()
    {
        return Dispensasi::query()
            ->with(['siswa', 'kelas'])
            ->where('status', Dispensasi::STATUS_MENUNGGU)
            ->orderBy('dispensasi.tanggal')
            ->orderBy('id_dispensasi')
            ->get();
    }

    public function getRiwayatJurnalProperty()
    {
        $query = Jurnal::query()
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke');

        if ($this->filterJurnal === 'terbaru') {
            return $query->limit(100)->get();
        }

        [$mulai, $selesai] = $this->rentangTanggal($this->filterJurnal);

        return $query->whereBetween('tanggal', [$mulai, $selesai])->get();
    }

    public function getRekapBelumMengisiProperty()
    {
        [$mulai, $selesai] = $this->rentangTanggal($this->filterBelumMengisi);
        $hariIndonesia = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $hasil = collect();

        for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
            $hari = $hariIndonesia[$tanggal->format('l')];
            $jurnalTerisi = Jurnal::query()
                ->whereDate('tanggal', $tanggal->toDateString())
                ->get()
                ->map(fn(Jurnal $jurnal): string => $jurnal->id_guru . '-' . $jurnal->id_kelas . '-' . $jurnal->jam_ke)
                ->flip();
            $jadwal = Jadwal::query()
                ->with(['kelas', 'guru'])
                ->where('hari', $hari)
                ->get();

            foreach ($jadwal as $item) {
                $sudahMengisi = $jurnalTerisi->has($item->id_guru . '-' . $item->id_kelas . '-' . $item->jam_ke);

                if (! $sudahMengisi) {
                    $item->tanggal_rekap = $tanggal->copy();
                    $hasil->push($item);
                }
            }
        }

        return $hasil->sortByDesc('tanggal_rekap')->values();
    }

    public function getRekapKehadiranProperty()
    {
        [$mulai, $selesai] = $this->rentangTanggal($this->filterKehadiran);
        $hasil = collect();
        $sekarang = Carbon::now('Asia/Jakarta');
        $hariIndonesia = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
        $service = app(KehadiranGuruService::class);
        $pengajuanIzinDisetujui = Jurnal::query()
            ->where('adalah_pengajuan_izin', true)
            ->where('status_validasi', 'Divalidasi')
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])
            ->get()
            ->keyBy(fn (Jurnal $jurnal): string => $jurnal->id_guru.'-'.$jurnal->id_kelas.'-'.$jurnal->tanggal->toDateString());

        for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
            $jadwalHari = Jadwal::query()
                ->with(['kelas', 'guru'])
                ->where('hari', $hariIndonesia[$tanggal->format('l')])
                ->get();

            foreach ($jadwalHari as $item) {
                $item->tanggal_rekap = $tanggal->copy();
                $item->status_kehadiran = $service->statusUntukJadwal($item, $sekarang, $tanggal->copy());

                $pengajuanIzin = $pengajuanIzinDisetujui->get(
                    $item->id_guru.'-'.$item->id_kelas.'-'.$tanggal->toDateString()
                );

                if ($item->status_kehadiran === 'Izin' && $pengajuanIzin) {
                    $jenisIzin = mb_strtolower(trim($pengajuanIzin->jenis_izin ?? ''));
                    $item->status_kehadiran = match ($jenisIzin) {
                        'kepentingan' => 'Kepentingan',
                        'sakit' => 'Sakit',
                        default => 'Izin',
                    };
                }

                $hasil->push($item);
            }
        }

        $hasil = $hasil->sortByDesc('tanggal_rekap')->values();

        return $this->filterStatusKehadiran === 'semua'
            ? $hasil
            : $hasil->where('status_kehadiran', $this->filterStatusKehadiran)->values();
    }

    public function exportCsv(string $jenis): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(session('role') === 'wakasek', 403);

        abort_unless(in_array($jenis, ['jurnal', 'belum-mengisi', 'kehadiran'], true), 404);
        $laporan = $this->laporanUntuk($jenis);
        $data = $laporan['data'];
        $headers = $laporan['kolom'];
        $judul = $laporan['judul'];
        $periode = $laporan['periode'];

        return response()->streamDownload(function () use ($headers, $data, $judul, $periode): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [$judul]);
            fputcsv($handle, ['Periode', $periode]);
            fputcsv($handle, ['Dicetak pada', now('Asia/Jakarta')->format('d/m/Y H:i')]);
            fputcsv($handle, []);
            fputcsv($handle, $headers);
            foreach ($data as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, 'rekap-' . $jenis . '-' . now('Asia/Jakarta')->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getDataCetakProperty(): ?array
    {
        $bagian = request()->query('print');

        if ($bagian === null) {
            return null;
        }

        abort_unless(session('role') === 'wakasek' && in_array($bagian, ['jurnal', 'belum-mengisi', 'kehadiran'], true), 404);

        return $this->laporanUntuk($bagian);
    }

    private function laporanUntuk(string $bagian): array
    {
        $data = match ($bagian) {
            'jurnal' => $this->riwayatJurnal->map(fn(Jurnal $jurnal): array => [$jurnal->tanggal?->format('d/m/Y'), $jurnal->guru?->nama ?? '-', $jurnal->kelas?->nama_kelas ?? '-', $jurnal->jam_ke, $jurnal->materi, $jurnal->jumlah_hadir ?? '-', $jurnal->jumlah_tidak_hadir ?? '-', $jurnal->status_kehadiran_guru, $jurnal->status_validasi]),
            'belum-mengisi' => $this->rekapBelumMengisi->map(fn(Jadwal $jadwal): array => [$jadwal->tanggal_rekap->format('d/m/Y'), $jadwal->guru?->nama ?? '-', $jadwal->kelas?->nama_kelas ?? '-', $jadwal->jam_ke, substr($jadwal->jam_mulai, 0, 5) . '-' . substr($jadwal->jam_selesai, 0, 5)]),
            'kehadiran' => $this->rekapKehadiran->map(fn(Jadwal $jadwal): array => [$jadwal->tanggal_rekap->format('d/m/Y'), $jadwal->guru?->nama ?? '-', $jadwal->kelas?->nama_kelas ?? '-', $jadwal->jam_ke, $jadwal->status_kehadiran]),
            default => abort(404),
        };
        $kolom = match ($bagian) {
            'jurnal' => ['Tanggal', 'Guru', 'Kelas', 'Jam ke', 'Materi', 'Hadir', 'Tidak hadir', 'Kehadiran guru', 'Validasi'],
            'belum-mengisi' => ['Tanggal', 'Guru', 'Kelas', 'Jam ke', 'Waktu'],
            default => ['Tanggal', 'Guru', 'Kelas', 'Jam ke', 'Status kehadiran'],
        };
        [$judul, $periode] = $this->judulDanPeriode($bagian);

        return compact('judul', 'periode', 'kolom', 'data');
    }

    private function judulDanPeriode(string $jenis): array
    {
        $filter = match ($jenis) {
            'jurnal' => $this->filterJurnal,
            'belum-mengisi' => $this->filterBelumMengisi,
            'kehadiran' => $this->filterKehadiran,
            default => $this->filterKehadiran,
        };
        $label = match ($filter) {
            'terbaru' => '100 jurnal terbaru',
            'bulan' => 'Bulan ini',
            'tahun' => 'Tahun ini',
            default => 'Minggu ini',
        };
        $judul = match ($jenis) {
            'jurnal' => 'Riwayat Jurnal Guru',
            'belum-mengisi' => 'Rekap Guru Belum Mengisi Jurnal',
            'kehadiran' => 'Rekap Monitoring Kehadiran Guru',
            default => 'Rekap Guru Tidak Hadir',
        };

        return [$judul, $label];
    }

    private function urlCetak(string $bagian): string
    {
        $parameterFilter = match ($bagian) {
            'jurnal' => 'filterJurnal',
            'belum-mengisi' => 'filterBelumMengisi',
            'kehadiran' => 'filterKehadiran',
            default => 'filterKehadiran',
        };

        return route('wakasek', [
            'print' => $bagian,
            $parameterFilter => match ($bagian) {
                'jurnal' => $this->filterJurnal,
                'belum-mengisi' => $this->filterBelumMengisi,
                'kehadiran' => $this->filterKehadiran,
                default => $this->filterKehadiran,
            },
            'filterStatusKehadiran' => $this->filterStatusKehadiran,
        ]);
    }

    private function rentangTanggal(string $filter): array
    {
        $sekarang = Carbon::now('Asia/Jakarta');

        return match ($filter) {
            'bulan' => [$sekarang->copy()->startOfMonth(), $sekarang->copy()->endOfMonth()],
            'tahun' => [$sekarang->copy()->startOfYear(), $sekarang->copy()->endOfYear()],
            default => [$sekarang->copy()->startOfWeek(), $sekarang->copy()->endOfWeek()],
        };
    }
};
?>

<style>
    [x-cloak] { display: none !important; }
    .wakasek-rekap-list { display:grid; gap:10px; padding:16px; }
    .wakasek-rekap-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:12px; padding:14px; border:1px solid #e2e8f0; border-radius:12px; background:#fff; }
    .wakasek-rekap-label { display:block; color:#71809a; font-size:10px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
    .wakasek-table th { white-space:nowrap; }
    @media print {
        body * { visibility:hidden !important; }
        .print-report, .print-report * { visibility:visible !important; }
        .print-report { display:block !important; position:absolute; inset:0; width:100%; padding:12mm; color:#172033; background:#fff; font:10pt Arial,sans-serif; }
        .print-report h1 { margin:0 0 5px; font-size:18pt; }
        .print-report th, .print-report td { padding:7px 8px; border:1px solid #cfd7e4; text-align:left; vertical-align:top; }
        .print-report th { color:#fff; background:#183153 !important; print-color-adjust:exact; }
        .print-report tr { break-inside:avoid; }
        .print-report button { display:none !important; }
    }
</style>

<div x-data="{
    activeSection: 'dashboard',
    searchJurnal: '',
    searchGuru: '',
    searchDispensasi: '',
    syncSection() {
        const section = window.location.hash.slice(1);
        this.activeSection = ['monitoring-guru', 'monitoring-jurnal', 'dispensasi', 'pengajuan-izin', 'rekap'].includes(section) ? section : 'dashboard';
    },
    openSection(section) {
        this.activeSection = section;
        window.location.hash = section;
    }
}" x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">
    @if ($this->dataCetak)
    <div class="print-report" style="display:block;">
        <header class="d-flex justify-content-between align-items-start gap-3 border-bottom border-primary border-3 pb-3 mb-3">
            <div>
                <div class="small text-muted fw-bold">SIJAGA · LAPORAN SEKOLAH</div>
                <h1 class="h3">{{ $this->dataCetak['judul'] }}</h1>
                <div>Periode: {{ $this->dataCetak['periode'] }} · Dicetak: {{ now('Asia/Jakarta')->format('d/m/Y H:i') }}</div>
            </div>
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak</button>
        </header>
        <div class="table-responsive"><table class="table table-bordered">
            <thead><tr>@foreach ($this->dataCetak['kolom'] as $kolom)<th>{{ $kolom }}</th>@endforeach</tr></thead>
            <tbody>@forelse ($this->dataCetak['data'] as $baris)<tr>@foreach ($baris as $nilai)<td>{{ $nilai }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($this->dataCetak['kolom']) }}" class="text-center">Tidak ada data pada periode ini.</td></tr>@endforelse</tbody>
        </table></div>
    </div>
    <script>window.addEventListener('load', () => window.print()); window.addEventListener('afterprint', () => window.close());</script>
    @else
    @if (session('success'))
    <div class="alert alert-success m-3" role="status">{{ session('success') }}</div>
    @endif
    @if (session('error'))
    <div class="alert alert-danger m-3" role="alert">{{ session('error') }}</div>
    @endif
    <section x-cloak x-show="activeSection === 'dashboard'" :class="{ 'd-none': activeSection !== 'dashboard' }" wire:poll.60s id="wakasek-dashboard" class="wakasek-dashboard">
        <header class="welcome-banner mb-4">
            <div class="text-uppercase fw-bold small text-white-50">SIJAGA · PANEL PIMPINAN</div>
            <h1 class="h3 fw-bold text-white mt-2 mb-1">Monitoring Wakasek</h1>
            <div class="text-white">Ringkasan jurnal, kehadiran guru, dan dispensasi sekolah.</div>
        </header>
        <section class="card-custom p-4">
            <div class="wakasek-summary mb-4 p-3 d-flex flex-wrap align-items-center gap-3"><span class="wakasek-summary-mark" style="background:#dcfce7;color:#15803d">&#10003;</span><div class="me-auto"><div class="fw-bold">Pantauan sekolah aktif</div><div class="text-muted small">Data diperbarui otomatis setiap menit.</div></div><span class="badge rounded-pill px-3 py-2" style="background:#dcfce7;color:#15803d">PEMANTAUAN AKTIF</span></div>
            <div class="d-flex align-items-center gap-2 mb-3"><span class="wakasek-summary-mark">&#128202;</span><div><h2 class="h5 fw-bold mb-0">Ringkasan Hari Ini</h2><div class="text-muted small">Status pemantauan aktivitas sekolah</div></div></div>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <button type="button" class="role-menu-card w-100 text-start" x-on:click="openSection('pengajuan-izin')">
                        <span class="role-menu-icon">&#128221;</span>
                        <h2 class="h5 fw-bold">Pengajuan Izin Guru</h2>
                        <p><strong class="text-danger">{{ $this->pengajuanIzinMenunggu->count() }}</strong> pengajuan menunggu validasi.</p>
                        <span class="fw-bold text-primary">Buka pengajuan <span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <div class="col-12 col-md-6">
                    <button type="button" class="role-menu-card w-100 text-start" x-on:click="openSection('monitoring-guru')">
                        <span class="role-menu-icon">&#128100;</span>
                        <h2 class="h5 fw-bold">Monitoring Guru</h2>
                        <p><strong class="text-danger">{{ $this->jadwalMengajarSekarang->count() }}</strong> jadwal mengajar saat ini.</p>
                        <span class="fw-bold text-primary">Buka monitoring <span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <div class="col-12 col-md-6">
                    <button type="button" class="role-menu-card w-100 text-start" x-on:click="openSection('monitoring-jurnal')">
                        <span class="role-menu-icon">&#128203;</span>
                        <h2 class="h5 fw-bold">Monitoring Jurnal</h2>
                        <p><strong class="text-primary">{{ $this->stats['jurnalHariIni'] }}</strong> jurnal masuk hari ini.</p>
                        <span class="fw-bold text-primary">Buka monitoring <span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <div class="col-12 col-md-6">
                    <button type="button" class="role-menu-card w-100 text-start" x-on:click="openSection('dispensasi')">
                        <span class="role-menu-icon">&#128196;</span>
                        <h2 class="h5 fw-bold">Dispensasi</h2>
                        <p><strong style="color:#6d28d9">{{ $this->stats['dispensasiMenunggu'] }}</strong> pengajuan menunggu persetujuan.</p>
                        <span class="fw-bold text-primary">Buka dispensasi <span aria-hidden="true">→</span></span>
                    </button>
                </div>
                <div class="col-12 col-md-6">
                    <button type="button" class="role-menu-card w-100 text-start" x-on:click="openSection('rekap')">
                        <span class="role-menu-icon">&#128202;</span>
                        <h2 class="h5 fw-bold">Rekap &amp; Laporan</h2>
                        <p><strong class="text-success">{{ $this->riwayatJurnal->count() }}</strong> jurnal pada periode terpilih.</p>
                        <span class="fw-bold text-primary">Buka rekap <span aria-hidden="true">→</span></span>
                    </button>
                </div>
            </div>
        </section>
    </section>

    <section x-cloak x-show="activeSection === 'monitoring-jurnal'" :class="{ 'd-none': activeSection !== 'monitoring-jurnal' }" id="monitoring-jurnal" class="card-custom wakasek-content-card">
        <div class="wakasek-page-header role-page-header m-3"><h2>Monitoring Jurnal</h2><div class="role-page-description">Status pengisian dan validasi jurnal guru hari ini.</div></div>
        <div class="role-page-actions mx-3 mb-3"><a href="{{ route('wakasek') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
        <div class="card-header-custom">Jurnal Guru Hari Ini</div>
        <div class="p-3"><label class="visually-hidden" for="search-monitoring-jurnal">Cari jurnal guru</label><input id="search-monitoring-jurnal" type="search" class="form-control" placeholder="Cari guru, mapel, atau kelas..." x-model="searchJurnal"></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle wakasek-table"><thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th><th>Status Jurnal</th><th>Validasi</th></tr></thead><tbody>
            @forelse ($this->monitoringGuru->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
            <tr wire:key="monitoring-jurnal-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}" x-show="!searchJurnal || $el.dataset.search.includes(searchJurnal)" data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}"><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td><td>Ke-{{ $jadwal->jam_ke }}</td><td><span class="badge {{ $jadwal->id_jurnal ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jadwal->id_jurnal ? 'Sudah Mengisi' : 'Belum Mengisi' }}</span></td><td><span class="badge {{ $jadwal->status_jurnal === 'Divalidasi' ? 'bg-success' : ($jadwal->status_jurnal === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $jadwal->status_jurnal ?? '-' }}</span></td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal guru hari ini.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section x-cloak x-show="activeSection === 'pengajuan-izin'" :class="{ 'd-none': activeSection !== 'pengajuan-izin' }" class="card-custom wakasek-content-card">
        <div class="wakasek-page-header role-page-header m-3"><h2>Pengajuan Izin Guru</h2><div class="role-page-description">Validasi izin dan titipan tugas sebelum diteruskan ke sekretaris kelas.</div></div>
        <div class="role-page-actions mx-3 mb-3"><a href="{{ route('wakasek') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
        <div class="wakasek-rekap-list">
            @forelse ($this->pengajuanIzinMenunggu as $izin)
            <article class="wakasek-rekap-row" wire:key="pengajuan-izin-{{ $izin->id_jurnal }}">
                <div><span class="wakasek-rekap-label">Tanggal / Jam</span>{{ $izin->tanggal?->format('d/m/Y') }} · Jam ke-{{ $izin->jam_ke }}</div><div><span class="wakasek-rekap-label">Guru</span>{{ $izin->guru?->nama ?? '-' }}</div><div><span class="wakasek-rekap-label">Kelas</span>{{ $izin->kelas?->nama_kelas ?? '-' }}</div><div><span class="wakasek-rekap-label">Jenis izin</span>{{ $izin->jenis_izin }}</div><div class="col-12"><span class="wakasek-rekap-label">Titipan tugas</span>{{ $izin->materi }}</div>
                <div class="col-12"><input type="text" wire:model="catatanValidasiIzin" class="form-control" placeholder="Catatan Wakasek (opsional)"></div>
                <div class="col-12 d-flex gap-2"><button type="button" wire:click="validasiPengajuanIzin({{ $izin->id_jurnal }}, 'Divalidasi')" class="btn btn-success btn-sm">Setujui</button><button type="button" wire:click="validasiPengajuanIzin({{ $izin->id_jurnal }}, 'Ditolak')" class="btn btn-outline-danger btn-sm">Tolak</button></div>
            </article>
            @empty<div class="text-center text-muted py-4">Tidak ada pengajuan izin yang menunggu validasi.</div>@endforelse
        </div>
    </section>

    <section x-cloak x-show="activeSection === 'monitoring-guru'" :class="{ 'd-none': activeSection !== 'monitoring-guru' }" id="monitoring-guru" class="card-custom wakasek-content-card">
        <div class="wakasek-page-header role-page-header m-3"><h2>Monitoring Kehadiran Guru</h2><div class="role-page-description">Pantau jadwal yang sedang berlangsung dan status kehadiran.</div></div>
        <div class="role-page-actions mx-3 mb-3"><a href="{{ route('wakasek') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
        <div class="card-header-custom">Jadwal Guru Mengajar Sekarang</div>
        <div class="p-3"><label class="visually-hidden" for="search-monitoring-guru">Cari guru</label><input id="search-monitoring-guru" type="search" class="form-control" placeholder="Cari guru, mapel, atau kelas..." x-model="searchGuru"></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle wakasek-table"><thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th><th>Kehadiran</th></tr></thead><tbody>
            @forelse ($this->jadwalMengajarSekarang->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
            <tr wire:key="monitoring-guru-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}" x-show="!searchGuru || $el.dataset.search.includes(searchGuru)" data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}"><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td><td>Ke-{{ $jadwal->jam_ke }} <span class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</span></td><td><span class="badge {{ in_array($jadwal->status_kehadiran, ['Hadir', 'Izin', 'Sakit'], true) ? 'bg-success' : ($jadwal->status_kehadiran === KehadiranGuruService::STATUS_TANPA_KETERANGAN ? 'bg-danger' : 'bg-secondary') }}">{{ $jadwal->status_kehadiran }}</span></td></tr>
            @empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada guru yang sedang mengajar.</td></tr>@endforelse
        </tbody></table></div>
        @if ($this->guruTanpaKeterangan->isNotEmpty())<div class="border-top border-start border-4 border-danger"><div class="card-header-custom text-danger">Guru Tanpa Keterangan</div><div class="list-group list-group-flush">@foreach ($this->guruTanpaKeterangan->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)<div class="list-group-item d-flex justify-content-between"><span>{{ $jadwal->nama_guru }} · {{ $jadwal->mapel_diampu ?: '-' }}</span><span class="badge bg-danger">Tanpa Keterangan</span></div>@endforeach</div></div>@endif
    </section>

    <section x-cloak x-show="activeSection === 'dispensasi'" :class="{ 'd-none': activeSection !== 'dispensasi' }" id="dispensasi" class="card-custom wakasek-content-card">
        <div class="wakasek-page-header role-page-header m-3"><h2>Persetujuan Dispensasi</h2><div class="role-page-description">Tinjau pengajuan siswa yang menunggu persetujuan.</div></div>
        <div class="role-page-actions mx-3 mb-3"><a href="{{ route('wakasek') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
        <div class="card-header-custom">Dispensasi Menunggu Persetujuan</div>
        <div class="p-3"><label class="visually-hidden" for="search-dispensasi-wakasek">Cari dispensasi</label><input id="search-dispensasi-wakasek" type="search" class="form-control" placeholder="Cari siswa, kelas, jenis, atau mapel..." x-model="searchDispensasi"></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle wakasek-table"><thead><tr><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Mapel</th><th>Tanggal</th><th>Aksi</th></tr></thead><tbody>
            @forelse ($this->dispensasiMenunggu as $dispensasi)
            <tr wire:key="wakasek-dispensasi-{{ $dispensasi->id_dispensasi }}" x-show="!searchDispensasi || $el.dataset.search.includes(searchDispensasi)" data-search="{{ mb_strtolower(($dispensasi->siswa?->nama_siswa ?? '').' '.($dispensasi->kelas?->nama_kelas ?? '').' '.$dispensasi->jenis_dispensasi.' '.($dispensasi->mapel ?? ''), 'UTF-8') }}"><td>{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</td><td>{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</td><td>{{ $dispensasi->jenis_dispensasi }}</td><td>{{ $dispensasi->mapel ?: '-' }}</td><td>{{ $dispensasi->tanggal?->format('d/m/Y') }}</td><td>@if ($dispensasi->token)<a href="{{ route('approve-dispensasi', ['token' => $dispensasi->token, 'wakasek' => session('id_pengguna')]) }}" class="btn btn-sm btn-app-primary">Lihat & Validasi</a>@else<a href="{{ route('surat-dispensasi.detail', $dispensasi->id_dispensasi) }}" class="btn btn-sm btn-outline-secondary">Lihat Detail</a>@endif</td></tr>
            @empty<tr><td colspan="6" class="text-center text-muted py-4">Tidak ada dispensasi yang menunggu.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section x-cloak x-show="activeSection === 'rekap'" :class="{ 'd-none': activeSection !== 'rekap' }" id="rekap" class="d-grid gap-4">
        <header class="wakasek-page-header role-page-header"><div class="role-page-eyebrow">Laporan Sekolah</div><h2>Rekap & Riwayat</h2><div class="role-page-description">Filter periode, lalu ekspor CSV atau cetak laporan.</div></header>
        <div class="role-page-actions"><a href="{{ route('wakasek') }}" class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
        @if ($rekapTerbuka === '')
        <div class="row g-3">
            <div class="col-12 col-md-6"><button type="button" class="role-menu-card text-start w-100" wire:click="bukaRekap('jurnal')"><span class="role-menu-icon">&#128203;</span><h2 class="h5 fw-bold">Riwayat Jurnal</h2><p>{{ $this->riwayatJurnal->count() }} entri pada periode terpilih.</p><span class="fw-bold text-primary">Buka rekap <span aria-hidden="true">→</span></span></button></div>
            <div class="col-12 col-md-6"><button type="button" class="role-menu-card text-start w-100" wire:click="bukaRekap('belum-mengisi')"><span class="role-menu-icon">&#9203;</span><h2 class="h5 fw-bold">Guru Belum Mengisi</h2><p>{{ $this->rekapBelumMengisi->count() }} jadwal tanpa jurnal.</p><span class="fw-bold text-primary">Buka rekap <span aria-hidden="true">→</span></span></button></div>
            <div class="col-12 col-md-6"><button type="button" class="role-menu-card text-start w-100" wire:click="bukaRekap('kehadiran')"><span class="role-menu-icon">&#9989;</span><h2 class="h5 fw-bold">Monitoring Kehadiran Guru</h2><p>{{ $this->rekapKehadiran->count() }} jadwal pada periode terpilih.</p><span class="fw-bold text-primary">Buka rekap <span aria-hidden="true">→</span></span></button></div>
        </div>
        @else
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2"><button type="button" class="rekap-back" wire:click="tutupRekap">← Kembali ke semua rekap</button><span class="badge rounded-pill px-3 py-2" style="background:#dbeafe;color:#1d4ed8">TAMPILAN REKAP</span></div>
        @endif

        @if ($rekapTerbuka === 'jurnal')
        <section class="card-custom rekap-card"><div class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="fw-bold">Riwayat Jurnal</div><div class="text-muted small">{{ $this->riwayatJurnal->count() }} entri sesuai periode</div></div><div class="rekap-toolbar d-flex flex-wrap gap-2"><select class="form-select form-select-sm" wire:model.live="filterJurnal"><option value="minggu">Minggu ini</option><option value="bulan">Bulan ini</option><option value="tahun">Tahun ini</option><option value="terbaru">Jurnal terbaru (100)</option></select><button class="btn btn-sm btn-outline-primary" type="button" wire:click="exportCsv('jurnal')">Ekspor CSV</button><a class="btn btn-sm btn-primary" target="_blank" href="{{ $this->urlCetak('jurnal') }}">Cetak</a></div></div><div class="wakasek-rekap-list">@forelse ($this->riwayatJurnal as $jurnal)<article class="wakasek-rekap-row" wire:key="rekap-jurnal-{{ $jurnal->id_jurnal }}"><div><span class="wakasek-rekap-label">Tanggal</span>{{ $jurnal->tanggal?->format('d/m/Y') }}</div><div><span class="wakasek-rekap-label">Guru</span>{{ $jurnal->guru?->nama ?? '-' }}</div><div><span class="wakasek-rekap-label">Kelas / Jam</span>{{ $jurnal->kelas?->nama_kelas ?? '-' }} · {{ $jurnal->jam_ke }}</div><div><span class="wakasek-rekap-label">Validasi</span>{{ $jurnal->status_validasi }}</div><div class="col-12"><span class="wakasek-rekap-label">Materi</span>{{ $jurnal->materi }}</div></article>@empty<div class="text-center text-muted py-4">Tidak ada jurnal untuk filter ini.</div>@endforelse</div></section>
        @endif

        @if ($rekapTerbuka === 'belum-mengisi')
        <section class="card-custom rekap-card"><div class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="fw-bold">Guru Belum Mengisi Jurnal</div><div class="text-muted small">{{ $this->rekapBelumMengisi->count() }} jadwal</div></div><div class="rekap-toolbar d-flex flex-wrap gap-2"><select class="form-select form-select-sm" wire:model.live="filterBelumMengisi"><option value="minggu">Minggu ini</option><option value="bulan">Bulan ini</option><option value="tahun">Tahun ini</option></select><button class="btn btn-sm btn-outline-primary" type="button" wire:click="exportCsv('belum-mengisi')">Ekspor CSV</button><a class="btn btn-sm btn-primary" target="_blank" href="{{ $this->urlCetak('belum-mengisi') }}">Cetak</a></div></div><div class="wakasek-rekap-list">@forelse ($this->rekapBelumMengisi as $jadwal)<article class="wakasek-rekap-row" wire:key="rekap-belum-{{ $jadwal->id_jadwal }}-{{ $jadwal->tanggal_rekap->format('Ymd') }}"><div><span class="wakasek-rekap-label">Tanggal</span>{{ $jadwal->tanggal_rekap->format('d/m/Y') }}</div><div><span class="wakasek-rekap-label">Guru</span>{{ $jadwal->guru?->nama ?? '-' }}</div><div><span class="wakasek-rekap-label">Kelas / Jam</span>{{ $jadwal->kelas?->nama_kelas ?? '-' }} · {{ $jadwal->jam_ke }}</div><div><span class="wakasek-rekap-label">Waktu</span>{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</div></article>@empty<div class="text-center text-muted py-4">Tidak ada jadwal tanpa jurnal pada periode ini.</div>@endforelse</div></section>
        @endif

        @if ($rekapTerbuka === 'kehadiran')
        <section class="card-custom rekap-card"><div class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3"><div><div class="fw-bold">Monitoring Kehadiran Guru</div><div class="text-muted small">{{ $this->rekapKehadiran->count() }} jadwal</div></div><div class="rekap-toolbar d-flex flex-wrap gap-2"><select class="form-select form-select-sm" wire:model.live="filterKehadiran"><option value="minggu">Minggu ini</option><option value="bulan">Bulan ini</option><option value="tahun">Tahun ini</option></select><select class="form-select form-select-sm" wire:model.live="filterStatusKehadiran"><option value="semua">Semua status</option><option value="Izin">Izin</option><option value="Sakit">Sakit</option><option value="Kepentingan">Kepentingan</option><option value="Tanpa Keterangan">Tanpa Keterangan</option></select><button class="btn btn-sm btn-outline-primary" type="button" wire:click="exportCsv('kehadiran')">Ekspor CSV</button><a class="btn btn-sm btn-primary" target="_blank" href="{{ $this->urlCetak('kehadiran') }}">Cetak</a></div></div><div class="wakasek-rekap-list">@forelse ($this->rekapKehadiran as $jadwal)<article class="wakasek-rekap-row" wire:key="rekap-hadir-{{ $jadwal->id_jadwal }}-{{ $jadwal->tanggal_rekap->format('Ymd') }}"><div><span class="wakasek-rekap-label">Tanggal</span>{{ $jadwal->tanggal_rekap->format('d/m/Y') }}</div><div><span class="wakasek-rekap-label">Guru</span>{{ $jadwal->guru?->nama ?? '-' }}</div><div><span class="wakasek-rekap-label">Kelas / Jam</span>{{ $jadwal->kelas?->nama_kelas ?? '-' }} · {{ $jadwal->jam_ke }}</div><div><span class="wakasek-rekap-label">Status</span>{{ $jadwal->status_kehadiran }}</div></article>@empty<div class="text-center text-muted py-4">Tidak ada data kehadiran pada periode ini.</div>@endforelse</div></section>
        @endif

    </section>
    @endif
</div>
