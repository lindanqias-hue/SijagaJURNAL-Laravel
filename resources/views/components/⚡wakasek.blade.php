<?php

use App\Models\Jadwal;
use App\Models\Dispensasi;
use App\Services\KehadiranGuruService;
use Carbon\Carbon;
use Livewire\Component;

new class extends Component
{
    public function mount(): void
    {
        abort_unless(session('role') === 'wakasek', 403);
    }

    public function getStatsProperty(): array
    {
        $monitoring = $this->monitoringGuru;

        return [
            'jurnalHariIni' => $monitoring->whereNotNull('id_jurnal')->count(),
            'valid' => $monitoring->where('status_jurnal', 'Divalidasi')->count(),
            'perluKonfirmasi' => $monitoring
                ->whereNotNull('id_jurnal')
                ->where('status_konfirmasi', 'Menunggu')
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
                $mulai = Carbon::parse($sekarang->toDateString().' '.$jadwal->jam_mulai, 'Asia/Jakarta');
                $selesai = Carbon::parse($sekarang->toDateString().' '.$jadwal->jam_selesai, 'Asia/Jakarta');

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

};
?>

<style>
    [x-cloak] { display: none !important; }
</style>

<div wire:poll.60s
    x-data="{
        activeSection: 'dashboard',
        searchJurnal: '',
        searchGuru: '',
        searchDispensasi: '',
        syncSection() {
            const section = window.location.hash.slice(1);
            this.activeSection = ['monitoring-guru', 'monitoring-jurnal', 'dispensasi'].includes(section)
                ? section
                : 'dashboard';
        }
    }"
    x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())"
>
    <section x-cloak x-show="activeSection === 'dashboard'" id="wakasek-dashboard">
        <div class="welcome-banner mb-4">
            <div class="fw-bold" style="font-size:22px; color:#fff;">Monitoring Wakasek</div>
            <div style="color:rgba(255,255,255,.7); font-size:13px; margin-top:6px;">
                Pantau jurnal, kehadiran guru, dan dispensasi tanpa mengambil alih tugas guru piket.
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Jurnal Hari Ini</div><div class="stat-value">{{ $this->stats['jurnalHariIni'] }}</div></div></div>
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Valid</div><div class="stat-value">{{ $this->stats['valid'] }}</div></div></div>
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Belum Isi Jurnal</div><div class="stat-value text-warning">{{ $this->stats['belumIsiJurnal'] }}</div></div></div>
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Konfirmasi Sekretaris</div><div class="stat-value">{{ $this->stats['perluKonfirmasi'] }}</div></div></div>
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Dispensasi Menunggu</div><div class="stat-value">{{ $this->stats['dispensasiMenunggu'] }}</div></div></div>
            <div class="col-6 col-lg"><div class="stat-card"><div class="text-muted small">Tanpa Keterangan</div><div class="stat-value text-danger">{{ $this->stats['tanpaKeterangan'] }}</div></div></div>
        </div>
    </section>

    <section x-cloak x-show="activeSection === 'monitoring-jurnal'" id="monitoring-jurnal" class="card-custom overflow-hidden">
        <div class="card-header-custom">Rekap Jurnal Guru Hari Ini</div>
        <div class="p-3">
            <label class="visually-hidden" for="search-monitoring-jurnal">Cari jurnal guru</label>
            <input id="search-monitoring-jurnal" type="search" class="form-control" placeholder="Cari guru, mapel, atau kelas..." x-model="searchJurnal" @input="searchJurnal = $event.target.value.toLocaleLowerCase()">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th><th>Status Jurnal</th><th>Validasi</th></tr></thead>
                <tbody>
                    @forelse ($this->monitoringGuru->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
                        <tr wire:key="monitoring-jurnal-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}" x-show="!searchJurnal || $el.dataset.search.includes(searchJurnal)" data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}">
                            <td>{{ $jadwal->nama_guru }}</td>
                            <td>{{ $jadwal->mapel_diampu ?: '-' }}</td>
                            <td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                            <td>Ke-{{ $jadwal->jam_ke }}</td>
                            <td><span class="badge {{ $jadwal->id_jurnal ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jadwal->id_jurnal ? 'Sudah Mengisi' : 'Belum Mengisi' }}</span></td>
                            <td><span class="badge {{ $jadwal->status_jurnal === 'Divalidasi' ? 'bg-success' : ($jadwal->status_jurnal === 'Ditolak' ? 'bg-danger' : 'bg-warning text-dark') }}">{{ $jadwal->status_jurnal ?? '-' }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal guru hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section x-cloak x-show="activeSection === 'monitoring-guru'" id="monitoring-guru" class="card-custom overflow-hidden">
        <div class="card-header-custom">Jadwal Guru Mengajar Sekarang</div>
        <div class="p-3">
            <label class="visually-hidden" for="search-monitoring-guru">Cari guru</label>
            <input id="search-monitoring-guru" type="search" class="form-control" placeholder="Cari guru, mapel, atau kelas..." x-model="searchGuru" @input="searchGuru = $event.target.value.toLocaleLowerCase()">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th><th>Kehadiran</th></tr></thead>
                <tbody>
                    @forelse ($this->jadwalMengajarSekarang->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
                        <tr wire:key="monitoring-guru-{{ $jadwal->id_guru }}-{{ $jadwal->id_kelas }}-{{ $jadwal->jam_ke }}" x-show="!searchGuru || $el.dataset.search.includes(searchGuru)" data-search="{{ mb_strtolower($jadwal->nama_guru.' '.($jadwal->mapel_diampu ?? '').' '.($jadwal->kelas?->nama_kelas ?? ''), 'UTF-8') }}">
                            <td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                            <td>Ke-{{ $jadwal->jam_ke }}<div class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</div></td>
                            <td><span class="badge {{ in_array($jadwal->status_kehadiran, ['Hadir', 'Izin', 'Sakit'], true) ? 'bg-success' : ($jadwal->status_kehadiran === KehadiranGuruService::STATUS_TANPA_KETERANGAN ? 'bg-danger' : 'bg-secondary') }}">{{ $jadwal->status_kehadiran }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada guru yang sedang mengajar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->guruTanpaKeterangan->isNotEmpty())
            <div class="border-top border-start border-4 border-danger">
                <div class="card-header-custom text-danger">Guru Tanpa Keterangan</div>
                <div class="list-group list-group-flush">
                    @foreach ($this->guruTanpaKeterangan->sortBy('nama_guru', SORT_NATURAL | SORT_FLAG_CASE) as $jadwal)
                        <div class="list-group-item d-flex justify-content-between"><span>{{ $jadwal->nama_guru }} · {{ $jadwal->mapel_diampu ?: '-' }}</span><span class="badge bg-danger">Tanpa Keterangan</span></div>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

    <section x-cloak x-show="activeSection === 'dispensasi'" id="dispensasi" class="card-custom overflow-hidden">
        <div class="card-header-custom">Dispensasi Menunggu Persetujuan</div>
        <div class="p-3">
            <label class="visually-hidden" for="search-dispensasi-wakasek">Cari dispensasi</label>
            <input id="search-dispensasi-wakasek" type="search" class="form-control" placeholder="Cari siswa, kelas, jenis, atau mapel..." x-model="searchDispensasi" @input="searchDispensasi = $event.target.value.toLocaleLowerCase()">
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Mapel</th><th>Tanggal</th><th>Aksi</th></tr></thead>
                <tbody>
                    @forelse ($this->dispensasiMenunggu as $dispensasi)
                        <tr wire:key="wakasek-dispensasi-{{ $dispensasi->id_dispensasi }}" x-show="!searchDispensasi || $el.dataset.search.includes(searchDispensasi)" data-search="{{ mb_strtolower(($dispensasi->siswa?->nama_siswa ?? '').' '.($dispensasi->kelas?->nama_kelas ?? '').' '.$dispensasi->jenis_dispensasi.' '.($dispensasi->mapel ?? ''), 'UTF-8') }}">
                            <td>{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</td>
                            <td>{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</td>
                            <td>{{ $dispensasi->jenis_dispensasi }}</td>
                            <td>{{ $dispensasi->mapel ?: '-' }}</td>
                            <td>{{ $dispensasi->tanggal?->format('d/m/Y') }}</td>
                            <td>
                                @if ($dispensasi->token)
                                    <a href="{{ route('approve-dispensasi', ['token' => $dispensasi->token, 'wakasek' => session('id_pengguna')]) }}" class="btn btn-sm btn-app-primary">Lihat & Validasi</a>
                                @else
                                    <a href="{{ route('surat-dispensasi.detail', $dispensasi->id_dispensasi) }}" class="btn btn-sm btn-outline-secondary">Lihat Detail</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada dispensasi yang menunggu.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
