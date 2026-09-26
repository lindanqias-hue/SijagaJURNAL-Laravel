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

    public string $filterTanpaKeterangan = 'minggu';

    public string $rekapTerbuka = '';

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
        $this->filterTanpaKeterangan = in_array(request()->query('filterTanpaKeterangan'), ['minggu', 'bulan', 'tahun'], true)
            ? request()->query('filterTanpaKeterangan')
            : $this->filterTanpaKeterangan;
    }

    public function bukaRekap(string $bagian): void
    {
        abort_unless(in_array($bagian, ['jurnal', 'belum-mengisi', 'kehadiran', 'tanpa-keterangan'], true), 404);

        $this->rekapTerbuka = $bagian;
    }

    public function tutupRekap(): void
    {
        $this->rekapTerbuka = '';
    }

    public function getStatsProperty(): array
    {
        $monitoring = $this->monitoringGuru;

        return [
            'jurnalHariIni' => $monitoring->whereNotNull('id_jurnal')->count(),
            'valid' => $monitoring->where('status_jurnal', 'Divalidasi')->count(),
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

        for ($tanggal = $mulai->copy(); $tanggal->lte($selesai); $tanggal->addDay()) {
            $jadwalHari = Jadwal::query()
                ->with(['kelas', 'guru'])
                ->where('hari', $hariIndonesia[$tanggal->format('l')])
                ->get();

            foreach ($jadwalHari as $item) {
                $item->tanggal_rekap = $tanggal->copy();
                $item->status_kehadiran = $service->statusUntukJadwal($item, $sekarang, $tanggal->copy());
                $hasil->push($item);
            }
        }

        return $hasil->sortByDesc('tanggal_rekap')->values();
    }

    public function getRekapTanpaKeteranganProperty()
    {
        [$mulai, $selesai] = $this->rentangTanggal($this->filterTanpaKeterangan);

        return $this->rekapKehadiran
            ->where('status_kehadiran', KehadiranGuruService::STATUS_TANPA_KETERANGAN)
            ->filter(fn(Jadwal $jadwal): bool => $jadwal->tanggal_rekap->betweenIncluded($mulai, $selesai))
            ->values();
    }

    public function exportCsv(string $jenis): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(session('role') === 'wakasek', 403);

        abort_unless(in_array($jenis, ['jurnal', 'belum-mengisi', 'kehadiran', 'tanpa-keterangan'], true), 404);
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

        abort_unless(session('role') === 'wakasek' && in_array($bagian, ['jurnal', 'belum-mengisi', 'kehadiran', 'tanpa-keterangan'], true), 404);

        return $this->laporanUntuk($bagian);
    }

    private function laporanUntuk(string $bagian): array
    {
        $data = match ($bagian) {
            'jurnal' => $this->riwayatJurnal->map(fn(Jurnal $jurnal): array => [$jurnal->tanggal?->format('d/m/Y'), $jurnal->guru?->nama ?? '-', $jurnal->kelas?->nama_kelas ?? '-', $jurnal->jam_ke, $jurnal->materi, $jurnal->jumlah_hadir ?? '-', $jurnal->jumlah_tidak_hadir ?? '-', $jurnal->status_kehadiran_guru, $jurnal->status_validasi]),
            'belum-mengisi' => $this->rekapBelumMengisi->map(fn(Jadwal $jadwal): array => [$jadwal->tanggal_rekap->format('d/m/Y'), $jadwal->guru?->nama ?? '-', $jadwal->kelas?->nama_kelas ?? '-', $jadwal->jam_ke, substr($jadwal->jam_mulai, 0, 5) . '-' . substr($jadwal->jam_selesai, 0, 5)]),
            'kehadiran' => $this->rekapKehadiran->map(fn(Jadwal $jadwal): array => [$jadwal->tanggal_rekap->format('d/m/Y'), $jadwal->guru?->nama ?? '-', $jadwal->kelas?->nama_kelas ?? '-', $jadwal->jam_ke, $jadwal->status_kehadiran]),
            'tanpa-keterangan' => $this->rekapTanpaKeterangan->map(fn(Jadwal $jadwal): array => [$jadwal->tanggal_rekap->format('d/m/Y'), $jadwal->guru?->nama ?? '-', $jadwal->kelas?->nama_kelas ?? '-', $jadwal->jam_ke, $jadwal->status_kehadiran]),
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
            default => $this->filterTanpaKeterangan,
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
            default => 'Rekap Guru Tanpa Keterangan',
        };

        return [$judul, $label];
    }

    private function urlCetak(string $bagian): string
    {
        $parameterFilter = match ($bagian) {
            'jurnal' => 'filterJurnal',
            'belum-mengisi' => 'filterBelumMengisi',
            'kehadiran' => 'filterKehadiran',
            default => 'filterTanpaKeterangan',
        };

        return route('wakasek', [
            'print' => $bagian,
            $parameterFilter => match ($bagian) {
                'jurnal' => $this->filterJurnal,
                'belum-mengisi' => $this->filterBelumMengisi,
                'kehadiran' => $this->filterKehadiran,
                default => $this->filterTanpaKeterangan,
            },
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
    [x-cloak] {
        display: none !important;
    }

    .wakasek-dashboard .welcome-banner {
        background: linear-gradient(120deg, #10233f 0%, #1d4ed8 58%, #0f766e 100%);
        box-shadow: 0 14px 32px rgba(16, 35, 63, .16);
    }

    .wakasek-dashboard .welcome-banner::after {
        content: '';
        position: absolute;
        width: 210px;
        height: 210px;
        right: 6%;
        top: -105px;
        border: 1px solid rgba(255, 255, 255, .16);
        border-radius: 50%;
        box-shadow: 0 0 0 24px rgba(255, 255, 255, .035), 0 0 0 48px rgba(255, 255, 255, .025);
        pointer-events: none;
    }

    .wakasek-dashboard .welcome-banner>* {
        position: relative;
        z-index: 1;
    }

    .wakasek-dashboard .wakasek-stat {
        position: relative;
        overflow: hidden;
        min-height: 112px;
        box-shadow: 0 5px 16px rgba(22, 33, 62, .04);
        transition: transform .18s ease, box-shadow .18s ease;
    }

    .wakasek-dashboard .wakasek-stat:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(22, 33, 62, .09);
    }

    .wakasek-dashboard .wakasek-stat-button {
        width: 100%;
        border: 1px solid var(--border);
        cursor: pointer;
        text-align: left;
    }

    .wakasek-dashboard .wakasek-stat::before {
        content: '';
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--stat-color, #2563eb);
    }

    .wakasek-dashboard .wakasek-stat-icon {
        display: grid;
        place-items: center;
        width: 44px;
        height: 44px;
        border-radius: 13px;
        color: var(--stat-color, #2563eb);
        background: var(--stat-tint, #dbeafe);
        font-size: 20px;
        flex: 0 0 auto;
    }

    .wakasek-dashboard .wakasek-summary {
        border-radius: 14px;
        background: linear-gradient(110deg, #fff 0%, #f8fbff 100%);
        border: 1px solid var(--border);
        box-shadow: 0 5px 18px rgba(22, 33, 62, .035);
    }

    .wakasek-dashboard .wakasek-summary-mark {
        width: 42px;
        height: 42px;
        display: grid;
        place-items: center;
        border-radius: 12px;
        background: #e0e7ff;
        color: #4338ca;
        font-size: 20px;
    }

    .wakasek-page-header {
        position: relative;
        overflow: hidden;
        border-radius: 16px;
        padding: 22px 24px;
        color: #fff;
        background: linear-gradient(120deg, #10233f 0%, #2563eb 100%);
        box-shadow: 0 10px 26px rgba(16, 35, 63, .12);
    }

    .wakasek-page-header>* {
        position: relative;
        z-index: 1;
    }

    .wakasek-content-card {
        overflow: hidden;
        box-shadow: 0 6px 20px rgba(22, 33, 62, .045);
    }

    .wakasek-content-card .card-header-custom {
        background: linear-gradient(90deg, #f8fbff, #fff);
        color: #183153;
        font-weight: 700;
    }

    .wakasek-table thead th {
        color: #52627d;
        background: #f4f7fc;
        white-space: nowrap;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .035em;
    }

    .wakasek-table tbody tr:hover {
        background: #f8fbff;
    }

    .rekap-card {
        border-top: 3px solid var(--rekap-color, #2563eb);
        box-shadow: 0 7px 22px rgba(22, 33, 62, .05);
    }

    .rekap-card .card-header-custom {
        background: linear-gradient(100deg, var(--rekap-tint, #eff6ff), #fff 75%);
    }

    .rekap-card .rekap-heading-icon {
        width: 38px;
        height: 38px;
        display: grid;
        place-items: center;
        flex: 0 0 auto;
        border-radius: 11px;
        color: var(--rekap-color, #2563eb);
        background: var(--rekap-tint, #eff6ff);
        font-size: 18px;
    }

    .rekap-card .rekap-toolbar {
        width: 100%;
    }

    .rekap-card .rekap-toolbar select {
        min-width: 145px;
    }

    .rekap-card .rekap-list {
        padding: 14px;
    }

    .rekap-card .rekap-row {
        display: grid;
        grid-template-columns: minmax(86px, .7fr) minmax(140px, 1.3fr) minmax(95px, .8fr) minmax(68px, .55fr) minmax(110px, 1fr);
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        border: 1px solid #e9eef7;
        border-radius: 10px;
        background: #fff;
    }

    .rekap-card .rekap-row+.rekap-row {
        margin-top: 8px;
    }

    .rekap-card .rekap-label {
        display: none;
    }

    .rekap-choice {
        width: 100%;
        min-height: 160px;
        padding: 20px;
        border: 1px solid #dce6f5;
        border-radius: 16px;
        background: linear-gradient(145deg, #fff 0%, #f5f9ff 100%);
        color: #183153;
        text-align: left;
        box-shadow: 0 7px 22px rgba(22, 33, 62, .05);
        transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
    }

    .rekap-choice:hover,
    .rekap-choice:focus-visible {
        transform: translateY(-3px);
        border-color: #8bb5f5;
        box-shadow: 0 14px 28px rgba(37, 99, 235, .12);
        outline: none;
    }

    .rekap-choice-icon {
        width: 48px;
        height: 48px;
        display: grid;
        place-items: center;
        border-radius: 14px;
        color: #1d4ed8;
        background: #dbeafe;
        font-size: 22px;
    }

    .rekap-back {
        border: 0;
        padding: 0;
        color: #2563eb;
        background: transparent;
        font-weight: 700;
    }

    .rekap-back:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .print-report {
        display: none;
    }

    .rekap-back:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .wakasek-back-link {
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 8px;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 10px;
    }

    .wakasek-back-link:hover {
        background: #dbeafe;
        border-color: #93c5fd;
        color: #1e40af;
    }

    .print-report {
        display: none;
    }

    @media (max-width: 767.98px) {
        .wakasek-page-header {
            padding: 20px;
        }

        .rekap-card .card-header-custom {
            align-items: flex-start !important;
        }

        .rekap-card .rekap-toolbar {
            display: grid !important;
            grid-template-columns: 1fr 1fr;
        }

        .rekap-card .rekap-toolbar select {
            grid-column: 1 / -1;
            width: 100%;
        }

        .rekap-card .rekap-toolbar .btn {
            width: 100%;
            white-space: nowrap;
        }

        .rekap-card .rekap-list {
            padding: 10px;
        }

        .rekap-card .rekap-row {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px 12px;
            padding: 12px;
        }

        .rekap-card .rekap-cell {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .rekap-card .rekap-cell:first-child {
            grid-column: 1 / -1;
        }

        .rekap-card .rekap-label {
            display: block;
            color: #71809a;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
        }
    }

    @media print {
        body * {
            visibility: hidden !important;
        }

        .print-report,
        .print-report * {
            visibility: visible !important;
        }

        .print-report {
            display: block !important;
            position: absolute;
            inset: 0;
            width: 100%;
            padding: 12mm;
            color: #172033;
            background: #fff;
            font: 10pt Arial, sans-serif;
        }

        .print-report h1 {
            margin: 0 0 5px;
            font-size: 18pt;
        }

        .print-report .print-meta {
            margin-bottom: 18px;
            color: #52627d;
            font-size: 9pt;
        }

        .print-report table {
            width: 100%;
            border-collapse: collapse;
        }

        .print-report th,
        .print-report td {
            padding: 7px 8px;
            border: 1px solid #cfd7e4;
            text-align: left;
            vertical-align: top;
        }

        .print-report th {
            color: #fff;
            background: #183153 !important;
            print-color-adjust: exact;
        }

        .print-report tr {
            break-inside: avoid;
        }

        .print-report button {
            display: none !important;
        }

        [x-cloak] {
            display: none !important;
        }
    }
</style>

<div x-data="{
        activeSection: 'dashboard',
        searchJurnal: '',
        searchGuru: '',
        searchDispensasi: '',
        syncSection() {
            const section = window.location.hash.slice(1);
            this.activeSection = ['monitoring-guru', 'monitoring-jurnal', 'dispensasi', 'rekap'].includes(section)
                ? section
                : 'dashboard';
        },
        openSection(section) {
            this.activeSection = section;
            window.location.hash = section;
        }
    }" x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">
    @if ($this->dataCetak)
    <div class="print-report" style="display:block;">
        <div
            style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-bottom:3px solid #2563eb;padding-bottom:14px;margin-bottom:14px;">
            <div>
                <div style="font-size:9pt;letter-spacing:.12em;color:#52627d;font-weight:700;">SIJAGA · LAPORAN SEKOLAH
                </div>
                <h1 style="margin-top:6px;">{{ $this->dataCetak['judul'] }}</h1>
                <div class="print-meta">Periode: {{ $this->dataCetak['periode'] }} · Dicetak:
                    {{ now('Asia/Jakarta')->format('d/m/Y H:i') }}
                </div>
            </div>
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak</button>
        </div>
        <table>
            <thead>
                <tr>@foreach ($this->dataCetak['kolom'] as $kolom)<th>{{ $kolom }}</th>@endforeach</tr>
            </thead>
            <tbody>
                @forelse ($this->dataCetak['data'] as $baris)<tr>@foreach ($baris as $nilai)<td>{{ $nilai }}</td>
                    @endforeach</tr>@empty<tr>
                    <td colspan="{{ count($this->dataCetak['kolom']) }}">Tidak ada data pada periode ini.</td>
                </tr>@endforelse
            </tbody>
        </table>
    </div>
    <script>
        window.addEventListener('load', () => window.print());
        window.addEventListener('afterprint', () => window.close());
    </script>
    @else
    <section x-cloak x-show="activeSection === 'dashboard'" :class="{ 'd-none': activeSection !== 'dashboard' }"
        wire:poll.60s id="wakasek-dashboard" class="wakasek-dashboard">
        x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">
        <section x-cloak x-show="activeSection === 'dashboard'" id="wakasek-dashboard">
            <div class="welcome-banner mb-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <div class="text-uppercase fw-bold"
                            style="font-size:11px; letter-spacing:.14em; color:rgba(255,255,255,.72);">SIJAGA · PANEL
                            PIMPINAN</div>
                        <div class="fw-bold mt-1" style="font-size:25px; color:#fff;">Monitoring Wakasek</div>
                        <div style="color:rgba(255,255,255,.82); font-size:13px; margin-top:6px;">
                            Ringkasan jurnal, kehadiran guru, dan dispensasi sekolah.
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <span class="wakasek-summary-mark">&#128202;</span>
                <div>
                    <div class="fw-bold">Ringkasan Hari Ini</div>
                    <div class="text-muted small">Status pemantauan aktivitas sekolah</div>
                </div>
                <div class="mb-3">
                    <div class="fw-bold">Ringkasan Hari Ini</div>
                    <div class="text-muted small">Status pemantauan aktivitas sekolah</div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-xl"><button type="button" class="stat-card wakasek-stat wakasek-stat-button"
                            style="--stat-color:#2563eb;--stat-tint:#dbeafe;"
                            x-on:click="openSection('monitoring-jurnal')">
                            <div>
                                <div class="text-muted small">Jurnal Hari Ini</div>
                                <div class="stat-value" style="color:#1d4ed8;">{{ $this->stats['jurnalHariIni'] }}</div>
                            </div>
                        </button></div>
                    <div class="col-6 col-xl"><button type="button" class="stat-card wakasek-stat wakasek-stat-button"
                            style="--stat-color:#059669;--stat-tint:#d1fae5;"
                            x-on:click="openSection('monitoring-jurnal')">
                            <div>
                                <div class="text-muted small">Jurnal Divalidasi</div>
                                <div class="stat-value" style="color:#047857;">{{ $this->stats['valid'] }}</div>
                            </div>
                        </button></div>
                    <div class="col-6 col-xl"><button type="button" class="stat-card wakasek-stat wakasek-stat-button"
                            style="--stat-color:#d97706;--stat-tint:#fef3c7;"
                            x-on:click="openSection('monitoring-jurnal')">
                            <div>
                                <div class="text-muted small">Belum Isi Jurnal</div>
                                <div class="stat-value" style="color:#b45309;">{{ $this->stats['belumIsiJurnal'] }}
                                </div>
                            </div>
                        </button></div>
                    <div class="col-6 col-xl"><button type="button" class="stat-card wakasek-stat wakasek-stat-button"
                            style="--stat-color:#7c3aed;--stat-tint:#f3e8ff;" x-on:click="openSection('dispensasi')">
                            <div>
                                <div class="text-muted small">Dispensasi Menunggu</div>
                                <div class="stat-value" style="color:#6d28d9;">{{ $this->stats['dispensasiMenunggu'] }}
                                </div>
                            </div>
                        </button></div>
                    <div class="col-6 col-xl"><button type="button" class="stat-card wakasek-stat wakasek-stat-button"
                            style="--stat-color:#dc2626;--stat-tint:#fee2e2;"
                            x-on:click="openSection('monitoring-guru')">
                            <div>
                                <div class="text-muted small">Tanpa Keterangan</div>
                                <div class="stat-value" style="color:#b91c1c;">{{ $this->stats['tanpaKeterangan'] }}
                                </div>
                            </div>
                        </button></div>
                </div>

                <div
                    class="wakasek-summary p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div class="d-flex align-items-center gap-3">
                        <div>
                            <div class="fw-bold">Pantauan sekolah aktif</div>
                            <div class="text-muted small">Data diperbarui otomatis setiap menit.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6 col-xl">
                            <div class="stat-card wakasek-stat" style="--stat-color:#2563eb;--stat-tint:#dbeafe;"><span
                                    class="wakasek-stat-icon">&#128221;</span>
                                <div>
                                    <div class="text-muted small">Jurnal Hari Ini</div>
                                    <div class="stat-value" style="color:#1d4ed8;">{{ $this->stats['jurnalHariIni'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl">
                            <div class="stat-card wakasek-stat" style="--stat-color:#059669;--stat-tint:#d1fae5;"><span
                                    class="wakasek-stat-icon">&#10003;</span>
                                <div>
                                    <div class="text-muted small">Jurnal Divalidasi</div>
                                    <div class="stat-value" style="color:#047857;">{{ $this->stats['valid'] }}</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl">
                            <div class="stat-card wakasek-stat" style="--stat-color:#d97706;--stat-tint:#fef3c7;"><span
                                    class="wakasek-stat-icon">&#9203;</span>
                                <div>
                                    <div class="text-muted small">Belum Isi Jurnal</div>
                                    <div class="stat-value" style="color:#b45309;">{{ $this->stats['belumIsiJurnal'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl">
                            <div class="stat-card wakasek-stat" style="--stat-color:#7c3aed;--stat-tint:#f3e8ff;"><span
                                    class="wakasek-stat-icon">&#128203;</span>
                                <div>
                                    <div class="text-muted small">Dispensasi Menunggu</div>
                                    <div class="stat-value" style="color:#6d28d9;">
                                        {{ $this->stats['dispensasiMenunggu'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6 col-xl">
                            <div class="stat-card wakasek-stat" style="--stat-color:#dc2626;--stat-tint:#fee2e2;"><span
                                    class="wakasek-stat-icon">&#9888;</span>
                                <div>
                                    <div class="text-muted small">Tanpa Keterangan</div>
                                    <div class="stat-value" style="color:#b91c1c;">{{ $this->stats['tanpaKeterangan'] }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="wakasek-summary p-3 p-md-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <span class="wakasek-summary-mark" style="background:#dcfce7;color:#15803d;">&#10003;</span>
                            <div>
                                <div class="fw-bold">Pantauan sekolah aktif</div>
                                <div class="text-muted small">Data diperbarui otomatis setiap menit.</div>
                            </div>
                        </div>
                        <span class="badge rounded-pill px-3 py-2" style="background:#dcfce7;color:#15803d;">PEMANTAUAN
                            AKTIF</span>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Jurnal Hari Ini</div>
                                <div class="stat-value">{{ $this->stats['jurnalHariIni'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Valid</div>
                                <div class="stat-value">{{ $this->stats['valid'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Belum Isi Jurnal</div>
                                <div class="stat-value text-warning">{{ $this->stats['belumIsiJurnal'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Konfirmasi Sekretaris</div>
                                <div class="stat-value">{{ $this->stats['perluKonfirmasi'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Dispensasi Menunggu</div>
                                <div class="stat-value">{{ $this->stats['dispensasiMenunggu'] }}</div>
                            </div>
                        </div>
                        <div class="col-6 col-lg">
                            <div class="stat-card">
                                <div class="text-muted small">Tanpa Keterangan</div>
                                <div class="stat-value text-danger">{{ $this->stats['tanpaKeterangan'] }}</div>
                            </div>
                        </div>
                    </div>
        </section>

        <section x-cloak x-show="activeSection === 'monitoring-jurnal'"
            :class="{ 'd-none': activeSection !== 'monitoring-jurnal' }" id="monitoring-jurnal"
            class="card-custom wakasek-content-card">
            <div class="wakasek-page-header m-3 mb-0">
                <div class="fw-bold fs-5">Monitoring Jurnal</div>
                <div class="small opacity-75 mt-1">Status pengisian dan validasi jurnal guru hari ini.</div>
            </div>
            <div class="card-header-custom">Jurnal Guru Hari Ini</div>
            <div class="p-3">
                <label class="visually-hidden" for="search-monitoring-jurnal">Cari jurnal guru</label>
                <input id="search-monitoring-jurnal" type="search" class="form-control"
                    placeholder="Cari guru, mapel, atau kelas..." x-model="searchJurnal"
                    @input="searchJurnal = $event.target.value.toLocaleLowerCase()">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle wakasek-table">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Mapel</th>
                            <th>Kelas</th>
                            <th>Jam</th>
                            <th>Status Jurnal</th>
                            <th>Validasi</th>
                        </tr>
                    </thead>
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Jam</th>
                                <th>Status Jurnal</th>
                                <th>Validasi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->monitoringGuru->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as
                            $jadwal)
                            <tr wire:key="monitoring-jurnal-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}"
                                x-show="!searchJurnal || $el.dataset.search.includes(searchJurnal)"
                                data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}">
                                <td>{{ $jadwal->nama_guru }}</td>
                                <td>{{ $jadwal->mapel_diampu ?: '-' }}</td>
                                <td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                                <td>Ke-{{ $jadwal->jam_ke }}</td>
                                <td><span
                                        class="badge {{ $jadwal->id_jurnal ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jadwal->id_jurnal ? 'Sudah Mengisi' : 'Belum Mengisi' }}</span>
                                </td>
                                <td><span
                                        class="badge {{ $jadwal->status_jurnal === 'Divalidasi' ? 'bg-success' : ($jadwal->status_jurnal === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $jadwal->status_jurnal ?? '-' }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal guru hari ini.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>
        </section>

        <section x-cloak x-show="activeSection === 'monitoring-guru'"
            :class="{ 'd-none': activeSection !== 'monitoring-guru' }" id="monitoring-guru"
            class="card-custom wakasek-content-card">
            <div class="wakasek-page-header m-3 mb-0">
                <div class="fw-bold fs-5">Monitoring Kehadiran Guru</div>
                <div class="small opacity-75 mt-1">Pantau jadwal yang sedang berlangsung dan status kehadiran.</div>
            </div>
            <div class="card-header-custom">Jadwal Guru Mengajar Sekarang</div>
            <div class="p-3">
                <label class="visually-hidden" for="search-monitoring-guru">Cari guru</label>
                <input id="search-monitoring-guru" type="search" class="form-control"
                    placeholder="Cari guru, mapel, atau kelas..." x-model="searchGuru"
                    @input="searchGuru = $event.target.value.toLocaleLowerCase()">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle wakasek-table">
                    <thead>
                        <tr>
                            <th>Guru</th>
                            <th>Mapel</th>
                            <th>Kelas</th>
                            <th>Jam</th>
                            <th>Kehadiran</th>
                        </tr>
                    </thead>
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Mapel</th>
                                <th>Kelas</th>
                                <th>Jam</th>
                                <th>Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->jadwalMengajarSekarang->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE)
                            as $jadwal)
                            <tr wire:key="monitoring-guru-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}"
                                x-show="!searchGuru || $el.dataset.search.includes(searchGuru)"
                                data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}">
                                <td>{{ $jadwal->nama_guru }}</td>
                                <td>{{ $jadwal->mapel_diampu ?: '-' }}</td>
                                <td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                                <td>Ke-{{ $jadwal->jam_ke }}
                                    <div class="text-muted small">
                                        {{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}
                                    </div>
                                </td>
                                <td><span
                                        class="badge {{ in_array($jadwal->status_kehadiran, ['Hadir', 'Izin', 'Sakit'], true) ? 'bg-success' : ($jadwal->status_kehadiran === KehadiranGuruService::STATUS_TANPA_KETERANGAN ? 'bg-danger' : 'bg-secondary') }}">{{ $jadwal->status_kehadiran }}</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Tidak ada guru yang sedang mengajar.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
            </div>
            @if ($this->guruTanpaKeterangan->isNotEmpty())
            <div class="border-top border-start border-4 border-danger">
                <div class="card-header-custom text-danger">Guru Tanpa Keterangan</div>
                <div class="list-group list-group-flush">
                    @foreach ($this->guruTanpaKeterangan->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
                    <div class="list-group-item d-flex justify-content-between"><span>{{ $jadwal->nama_guru }} ·
                            {{ $jadwal->mapel_diampu ?: '-' }}</span><span class="badge bg-danger">Tanpa
                            Keterangan</span></div>
                    @endforeach
                </div>
            </div>
            @endif
        </section>

        <section x-cloak x-show="activeSection === 'dispensasi'" :class="{ 'd-none': activeSection !== 'dispensasi' }"
            id="dispensasi" class="card-custom wakasek-content-card">
            <div class="wakasek-page-header m-3 mb-0">
                <div class="fw-bold fs-5">Persetujuan Dispensasi</div>
                <div class="small opacity-75 mt-1">Tinjau pengajuan siswa yang menunggu persetujuan.</div>
            </div>
            <div class="card-header-custom">Dispensasi Menunggu Persetujuan</div>
            <div class="p-3">
                <label class="visually-hidden" for="search-dispensasi-wakasek">Cari dispensasi</label>
                <input id="search-dispensasi-wakasek" type="search" class="form-control"
                    placeholder="Cari siswa, kelas, jenis, atau mapel..." x-model="searchDispensasi"
                    @input="searchDispensasi = $event.target.value.toLocaleLowerCase()">
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle wakasek-table">
                    <thead>
                        <tr>
                            <th>Siswa</th>
                            <th>Kelas</th>
                            <th>Jenis</th>
                            <th>Mapel</th>
                            <th>Tanggal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <table class="table table-hover mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Siswa</th>
                                <th>Kelas</th>
                                <th>Jenis</th>
                                <th>Mapel</th>
                                <th>Tanggal</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->dispensasiMenunggu as $dispensasi)
                            <tr wire:key="wakasek-dispensasi-{{ $dispensasi->id_dispensasi }}"
                                x-show="!searchDispensasi || $el.dataset.search.includes(searchDispensasi)"
                                data-search="{{ mb_strtolower(($dispensasi->siswa?->nama_siswa ?? '').' '.($dispensasi->kelas?->nama_kelas ?? '').' '.$dispensasi->jenis_dispensasi.' '.($dispensasi->mapel ?? ''), 'UTF-8') }}">
                                <td>{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</td>
                                <td>{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</td>
                                <td>{{ $dispensasi->jenis_dispensasi }}</td>
                                <td>{{ $dispensasi->mapel ?: '-' }}</td>
                                <td>{{ $dispensasi->tanggal?->format('d/m/Y') }}</td>
                                <td>
                                    @if ($dispensasi->token)
                                    <a href="{{ route('approve-dispensasi', ['token' => $dispensasi->token, 'wakasek' => session('id_pengguna')]) }}"
                                        class="btn btn-sm btn-app-primary">Lihat & Validasi</a>
                                    @else
                                    <a href="{{ route('surat-dispensasi.detail', $dispensasi->id_dispensasi) }}"
                                        class="btn btn-sm btn-outline-secondary">Lihat Detail</a>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Tidak ada dispensasi yang menunggu.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <section x-cloak x-show="activeSection === 'dispensasi'"
                        :class="{ 'd-none': activeSection !== 'dispensasi' }" id="dispensasi"
                        class="card-custom wakasek-content-card">
                        <div class="wakasek-page-header m-3 mb-0">
                            <div class="fw-bold fs-5">Persetujuan Dispensasi</div>
                            <div class="small opacity-75 mt-1">Tinjau pengajuan siswa yang menunggu persetujuan.</div>
                        </div>
                        <div class="px-3 pt-3 pb-2"><a href="{{ route('wakasek') }}"
                                class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>
                        <div class="card-header-custom">Dispensasi Menunggu Persetujuan</div>
                        <div class="p-3">
                            <label class="visually-hidden" for="search-dispensasi-wakasek">Cari dispensasi</label>
                            <input id="search-dispensasi-wakasek" type="search" class="form-control"
                                placeholder="Cari siswa, kelas, jenis, atau mapel..." x-model="searchDispensasi"
                                @input="searchDispensasi = $event.target.value.toLocaleLowerCase()">
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle wakasek-table">
                                <thead>
                                    <tr>
                                        <th>Siswa</th>
                                        <th>Kelas</th>
                                        <th>Jenis</th>
                                        <th>Mapel</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($this->dispensasiMenunggu as $dispensasi)
                                    <tr wire:key="wakasek-dispensasi-{{ $dispensasi->id_dispensasi }}"
                                        x-show="!searchDispensasi || $el.dataset.search.includes(searchDispensasi)"
                                        data-search="{{ mb_strtolower(($dispensasi->siswa?->nama_siswa ?? '').' '.($dispensasi->kelas?->nama_kelas ?? '').' '.$dispensasi->jenis_dispensasi.' '.($dispensasi->mapel ?? ''), 'UTF-8') }}">
                                        <td>{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</td>
                                        <td>{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</td>
                                        <td>{{ $dispensasi->jenis_dispensasi }}</td>
                                        <td>{{ $dispensasi->mapel ?: '-' }}</td>
                                        <td>{{ $dispensasi->tanggal?->format('d/m/Y') }}</td>
                                        <td>
                                            @if ($dispensasi->token)
                                            <a href="{{ route('approve-dispensasi', ['token' => $dispensasi->token, 'wakasek' => session('id_pengguna')]) }}"
                                                class="btn btn-sm btn-app-primary">Lihat & Validasi</a>
                                            @else
                                            <a href="{{ route('surat-dispensasi.detail', $dispensasi->id_dispensasi) }}"
                                                class="btn btn-sm btn-outline-secondary">Lihat Detail</a>
                                            @endif
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">Tidak ada dispensasi yang
                                            menunggu.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section x-cloak x-show="activeSection === 'rekap'" :class="{ 'd-none': activeSection !== 'rekap' }"
                        id="rekap" class="d-grid gap-4">
                        <div class="wakasek-page-header">
                            <div class="text-uppercase fw-bold"
                                style="font-size:11px; letter-spacing:.14em; color:rgba(255,255,255,.72);">LAPORAN
                                SEKOLAH</div>
                            <div class="fw-bold mt-1" style="font-size:24px;">Rekap & Riwayat</div>
                            <div class="small opacity-75 mt-1">Pilih periode, lalu ekspor atau cetak laporan lengkap.
                            </div>
                        </div>
                        <div class="px-3 pt-3 pb-2"><a href="{{ route('wakasek') }}"
                                class="btn btn-outline-primary btn-sm fw-semibold">&larr; Kembali ke Dashboard</a></div>

                        @if ($rekapTerbuka === '')
                        <div class="row g-3">
                            <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                    wire:click="bukaRekap('jurnal')"><span class="d-block fw-bold fs-5">Riwayat
                                        Jurnal</span><span
                                        class="d-block text-muted small mt-1">{{ $this->riwayatJurnal->count() }} entri
                                        pada periode terpilih</span><span
                                        class="d-block text-primary small fw-semibold mt-3">Buka rekap <span
                                            aria-hidden="true">→</span></span></button></div>
                            <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                    wire:click="bukaRekap('belum-mengisi')"><span class="d-block fw-bold fs-5">Guru
                                        Belum Mengisi</span><span
                                        class="d-block text-muted small mt-1">{{ $this->rekapBelumMengisi->count() }}
                                        jadwal tanpa jurnal</span><span
                                        class="d-block text-primary small fw-semibold mt-3">Buka rekap <span
                                            aria-hidden="true">→</span></span></button></div>
                            <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                    wire:click="bukaRekap('kehadiran')"><span class="d-block fw-bold fs-5">Monitoring
                                        Kehadiran Guru</span><span
                                        class="d-block text-muted small mt-1">{{ $this->rekapKehadiran->count() }}
                                        jadwal pada periode terpilih</span><span
                                        class="d-block text-primary small fw-semibold mt-3">Buka rekap <span
                                            aria-hidden="true">→</span></span></button></div>
                            <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                    wire:click="bukaRekap('tanpa-keterangan')"><span class="d-block fw-bold fs-5">Guru
                                        Tanpa Keterangan</span><span
                                        class="d-block text-muted small mt-1">{{ $this->rekapTanpaKeterangan->count() }}
                                        catatan pada periode terpilih</span><span
                                        class="d-block text-primary small fw-semibold mt-3">Buka rekap <span
                                            aria-hidden="true">→</span></span></button></div>
                        </div>
                        @else
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <button type="button" class="rekap-back" wire:click="tutupRekap">← Kembali ke semua
                                rekap</button>
                            <span class="badge rounded-pill px-3 py-2"
                                style="background:#dbeafe;color:#1d4ed8;">TAMPILAN REKAP</span>
                        </div>
                        @endif

                        @if ($rekapTerbuka === 'jurnal')
                        <section class="card-custom rekap-card" id="rekap-jurnal"
                            style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                            <div
                                class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div>
                                    <div class="fw-bold">Riwayat Jurnal</div>
                                    <div class="text-muted small">{{ $this->riwayatJurnal->count() }} entri sesuai
                                        periode</div>
                                </div>
                                <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                    <select class="form-select form-select-sm" aria-label="Filter riwayat jurnal"
                                        wire:model.live="filterJurnal">
                                        <option value="minggu">Minggu ini</option>
                                        <option value="bulan">Bulan ini</option>
                                        <option value="tahun">Tahun ini</option>
                                        <option value="terbaru">Jurnal terbaru (100)</option>
                                    </select>
                                    <button class="btn btn-sm btn-outline-primary" type="button"
                                        wire:click="exportCsv('jurnal')">Ekspor Excel</button>
                                    <a class="btn btn-sm btn-primary" target="_blank"
                                        href="{{ $this->urlCetak('jurnal') }}">Cetak / Print</a>
                                </div>
                            </div>
                        </section>

                        <section x-cloak x-show="activeSection === 'rekap'"
                            :class="{ 'd-none': activeSection !== 'rekap' }" id="rekap" class="d-grid gap-4">
                            <div class="wakasek-page-header">
                                <div class="text-uppercase fw-bold"
                                    style="font-size:11px; letter-spacing:.14em; color:rgba(255,255,255,.72);">LAPORAN
                                    SEKOLAH</div>
                                <div class="fw-bold mt-1" style="font-size:24px;">Rekap & Riwayat</div>
                                <div class="small opacity-75 mt-1">Pilih periode, lalu ekspor atau cetak laporan
                                    lengkap.</div>
                            </div>

                            @if ($rekapTerbuka === '')
                            <div class="row g-3">
                                <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                        wire:click="bukaRekap('jurnal')"><span
                                            class="rekap-choice-icon">&#128221;</span><span
                                            class="d-block fw-bold fs-5 mt-3">Riwayat Jurnal</span><span
                                            class="d-block text-muted small mt-1">{{ $this->riwayatJurnal->count() }}
                                            entri pada periode
                                            terpilih</span><span
                                            class="d-block text-primary small fw-semibold mt-3">Buka rekap <span
                                                aria-hidden="true">→</span></span></button></div>
                                <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                        wire:click="bukaRekap('belum-mengisi')"><span
                                            class="rekap-choice-icon">&#9203;</span><span
                                            class="d-block fw-bold fs-5 mt-3">Guru Belum Mengisi</span><span
                                            class="d-block text-muted small mt-1">{{ $this->rekapBelumMengisi->count() }}
                                            jadwal tanpa
                                            jurnal</span><span class="d-block text-primary small fw-semibold mt-3">Buka
                                            rekap <span aria-hidden="true">→</span></span></button></div>
                                <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                        wire:click="bukaRekap('kehadiran')"><span
                                            class="rekap-choice-icon">&#10003;</span><span
                                            class="d-block fw-bold fs-5 mt-3">Monitoring Kehadiran Guru</span><span
                                            class="d-block text-muted small mt-1">{{ $this->rekapKehadiran->count() }}
                                            jadwal pada
                                            periode terpilih</span><span
                                            class="d-block text-primary small fw-semibold mt-3">Buka rekap
                                            <span aria-hidden="true">→</span></span></button></div>
                                <div class="col-12 col-md-6"><button type="button" class="rekap-choice"
                                        wire:click="bukaRekap('tanpa-keterangan')"><span
                                            class="rekap-choice-icon">&#9888;</span><span
                                            class="d-block fw-bold fs-5 mt-3">Guru Tanpa Keterangan</span><span
                                            class="d-block text-muted small mt-1">{{ $this->rekapTanpaKeterangan->count() }}
                                            catatan
                                            pada periode terpilih</span><span
                                            class="d-block text-primary small fw-semibold mt-3">Buka
                                            rekap <span aria-hidden="true">→</span></span></button></div>
                            </div>
                            @else
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <button type="button" class="rekap-back" wire:click="tutupRekap">← Kembali ke semua
                                    rekap</button>
                                <span class="badge rounded-pill px-3 py-2"
                                    style="background:#dbeafe;color:#1d4ed8;">TAMPILAN
                                    REKAP</span>
                            </div>
                            @endif

                            @if ($rekapTerbuka === 'jurnal')
                            <section class="card-custom rekap-card" id="rekap-jurnal"
                                style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                                <div
                                    class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2"><span
                                            class="rekap-heading-icon">&#128221;</span>
                                        <div>
                                            <div class="fw-bold">Riwayat Jurnal</div>
                                            <div class="text-muted small">{{ $this->riwayatJurnal->count() }} entri
                                                sesuai periode</div>
                                        </div>
                                    </div>
                                    <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                        <select class="form-select form-select-sm" aria-label="Filter riwayat jurnal"
                                            wire:model.live="filterJurnal">
                                            <option value="minggu">Minggu ini</option>
                                            <option value="bulan">Bulan ini</option>
                                            <option value="tahun">Tahun ini</option>
                                            <option value="terbaru">Jurnal terbaru (100)</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="exportCsv('jurnal')">Ekspor Excel</button>
                                        <a class="btn btn-sm btn-primary" target="_blank"
                                            href="{{ $this->urlCetak('jurnal') }}">Cetak /
                                            Print</a>
                                    </div>
                                </div>
                                <div class="rekap-list d-grid gap-2">
                                    @forelse ($this->riwayatJurnal as $jurnal)
                                    <article class="rekap-row" wire:key="rekap-jurnal-{{ $jurnal->id_jurnal }}">
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Tanggal</span>{{ $jurnal->tanggal?->format('d/m/Y') }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Guru</span><strong>{{ $jurnal->guru?->nama ?? '-' }}</strong>
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Kelas</span>{{ $jurnal->kelas?->nama_kelas ?? '-' }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Jam</span>Ke-{{ $jurnal->jam_ke }}</div>
                                        <div class="rekap-cell"><span class="rekap-label">Status</span><span
                                                class="badge {{ $jurnal->status_validasi === 'Divalidasi' ? 'bg-success' : ($jurnal->status_validasi === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $jurnal->status_validasi }}</span>
                                        </div>
                                        <div class="rekap-cell" style="grid-column:1/-1;"><span
                                                class="rekap-label">Materi</span>{{ $jurnal->materi }} · Hadir
                                            {{ $jurnal->jumlah_hadir ?? '-' }}, tidak hadir
                                            {{ $jurnal->jumlah_tidak_hadir ?? '-' }} ·
                                            {{ $jurnal->status_kehadiran_guru }}
                                        </div>
                                    </article>
                                    @empty<div class="text-center text-muted py-4">Tidak ada jurnal untuk filter ini.
                                    </div>@endforelse
                                    @empty<div class="text-center text-muted py-4">Tidak ada jurnal untuk filter ini.
                                    </div>@endforelse
                                </div>
                            </section>
                            @endif

                            @if ($rekapTerbuka === 'belum-mengisi')
                            <section class="card-custom rekap-card" id="rekap-belum-mengisi"
                                style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                                <div
                                    class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div>
                                        <div class="fw-bold">Guru Belum Mengisi Jurnal</div>
                                        <div class="text-muted small">{{ $this->rekapBelumMengisi->count() }} jadwal
                                            belum memiliki jurnal</div>
                                    </div>
                                    <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                        <select class="form-select form-select-sm"
                                            aria-label="Filter guru belum mengisi" wire:model.live="filterBelumMengisi">
                                            <option value="minggu">Minggu ini</option>
                                            <option value="bulan">Bulan ini</option>
                                            <option value="tahun">Tahun ini</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="exportCsv('belum-mengisi')">Ekspor Excel</button>
                                        <a class="btn btn-sm btn-primary" target="_blank"
                                            href="{{ $this->urlCetak('belum-mengisi') }}">Cetak / Print</a>
                                    </div>
                            </section>
                            @endif

                            @if ($rekapTerbuka === 'belum-mengisi')
                            <section class="card-custom rekap-card" id="rekap-belum-mengisi"
                                style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                                <div
                                    class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2"><span
                                            class="rekap-heading-icon">&#9203;</span>
                                        <div>
                                            <div class="fw-bold">Guru Belum Mengisi Jurnal</div>
                                            <div class="text-muted small">{{ $this->rekapBelumMengisi->count() }} jadwal
                                                belum memiliki
                                                jurnal</div>
                                        </div>
                                    </div>
                                    <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                        <select class="form-select form-select-sm"
                                            aria-label="Filter guru belum mengisi" wire:model.live="filterBelumMengisi">
                                            <option value="minggu">Minggu ini</option>
                                            <option value="bulan">Bulan ini</option>
                                            <option value="tahun">Tahun ini</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="exportCsv('belum-mengisi')">Ekspor Excel</button>
                                        <a class="btn btn-sm btn-primary" target="_blank"
                                            href="{{ $this->urlCetak('belum-mengisi') }}">Cetak / Print</a>
                                    </div>
                                </div>
                                <div class="rekap-list d-grid gap-2">
                                    @forelse ($this->rekapBelumMengisi as $jadwal)
                                    <article class="rekap-row"
                                        wire:key="rekap-belum-{{ $jadwal->id_jadwal }}-{{ $jadwal->tanggal_rekap->format('Ymd') }}">
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Tanggal</span>{{ $jadwal->tanggal_rekap->format('d/m/Y') }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Guru</span><strong>{{ $jadwal->guru?->nama ?? '-' }}</strong>
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Kelas</span>{{ $jadwal->kelas?->nama_kelas ?? '-' }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Jam</span>Ke-{{ $jadwal->jam_ke }}</div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Waktu</span>{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}
                                        </div>
                                    </article>
                                    @empty<div class="text-center text-muted py-4">Tidak ada jadwal tanpa jurnal untuk
                                        filter ini.</div>
                                    @endforelse
                                </div>
                            </section>
                            @endif

                            @if ($rekapTerbuka === 'kehadiran')
                            <section class="card-custom rekap-card" id="rekap-kehadiran"
                                style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                                <div
                                    class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2"><span
                                            class="rekap-heading-icon">&#10003;</span>
                                        <div>
                                            <div class="fw-bold">Monitoring Kehadiran Guru</div>
                                            <div class="text-muted small">{{ $this->rekapKehadiran->count() }} jadwal
                                                dalam periode
                                                terpilih</div>
                                        </div>
                                    </div>
                                    <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                        <select class="form-select form-select-sm"
                                            aria-label="Filter monitoring kehadiran" wire:model.live="filterKehadiran">
                                            <option value="minggu">Minggu ini</option>
                                            <option value="bulan">Bulan ini</option>
                                            <option value="tahun">Tahun ini</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="exportCsv('kehadiran')">Ekspor Excel</button>
                                        <a class="btn btn-sm btn-primary" target="_blank"
                                            href="{{ $this->urlCetak('kehadiran') }}">Cetak / Print</a>
                                    </div>
                                </div>
                                <div class="rekap-list d-grid gap-2">
                                    @forelse ($this->rekapKehadiran as $jadwal)
                                    <article class="rekap-row"
                                        wire:key="rekap-hadir-{{ $jadwal->id_jadwal }}-{{ $jadwal->tanggal_rekap->format('Ymd') }}">
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Tanggal</span>{{ $jadwal->tanggal_rekap->format('d/m/Y') }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Guru</span><strong>{{ $jadwal->guru?->nama ?? '-' }}</strong>
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Kelas</span>{{ $jadwal->kelas?->nama_kelas ?? '-' }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Jam</span>Ke-{{ $jadwal->jam_ke }}</div>
                                        <div class="rekap-cell"><span class="rekap-label">Status</span><span
                                                class="badge {{ $jadwal->status_kehadiran === 'Hadir' ? 'bg-success' : ($jadwal->status_kehadiran === 'Tanpa Keterangan' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $jadwal->status_kehadiran }}</span>
                                        </div>
                                    </article>
                                    @empty<div class="text-center text-muted py-4">Tidak ada data kehadiran untuk filter
                                        ini.</div>
                                    @endforelse
                                </div>
                            </section>
                            @endif

                            @if ($rekapTerbuka === 'tanpa-keterangan')
                            <section class="card-custom rekap-card" id="rekap-tanpa-keterangan"
                                style="--rekap-color:#2563eb;--rekap-tint:#eff6ff;">
                                <div
                                    class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3">
                                    <div class="d-flex align-items-center gap-2"><span
                                            class="rekap-heading-icon">&#9888;</span>
                                        <div>
                                            <div class="fw-bold">Guru Tanpa Keterangan</div>
                                            <div class="text-muted small">{{ $this->rekapTanpaKeterangan->count() }}
                                                jadwal tercatat
                                                tanpa keterangan</div>
                                        </div>
                                    </div>
                                    <div class="rekap-toolbar d-flex flex-wrap justify-content-end gap-2">
                                        <select class="form-select form-select-sm" aria-label="Filter tanpa keterangan"
                                            wire:model.live="filterTanpaKeterangan">
                                            <option value="minggu">Minggu ini</option>
                                            <option value="bulan">Bulan ini</option>
                                            <option value="tahun">Tahun ini</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-primary" type="button"
                                            wire:click="exportCsv('tanpa-keterangan')">Ekspor Excel</button>
                                        <a class="btn btn-sm btn-primary" target="_blank"
                                            href="{{ $this->urlCetak('tanpa-keterangan') }}">Cetak / Print</a>
                                    </div>
                                </div>
                                <div class="rekap-list d-grid gap-2">
                                    @forelse ($this->rekapTanpaKeterangan as $jadwal)
                                    <article class="rekap-row"
                                        wire:key="rekap-tanpa-{{ $jadwal->id_jadwal }}-{{ $jadwal->tanggal_rekap->format('Ymd') }}">
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Tanggal</span>{{ $jadwal->tanggal_rekap->format('d/m/Y') }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Guru</span><strong>{{ $jadwal->guru?->nama ?? '-' }}</strong>
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Kelas</span>{{ $jadwal->kelas?->nama_kelas ?? '-' }}
                                        </div>
                                        <div class="rekap-cell"><span
                                                class="rekap-label">Jam</span>Ke-{{ $jadwal->jam_ke }}</div>
                                        <div class="rekap-cell"><span class="rekap-label">Status</span><span
                                                class="badge bg-danger">{{ $jadwal->status_kehadiran }}</span></div>
                                    </article>
                                    @empty<div class="text-center text-muted py-4">Tidak ada guru tanpa keterangan untuk
                                        filter ini.
                                    </div>@endforelse
                                </div>
                            </section>
                            @endif
                        </section>
                        @endif
            </div>
</div>