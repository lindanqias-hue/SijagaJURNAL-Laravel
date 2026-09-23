<?php

use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Services\KehadiranGuruService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
            'dispensasiMenunggu' => DB::table('dispensasi')->where('status', 'Menunggu Persetujuan')->count(),
            'tanpaKeterangan' => $monitoring->where('status_kehadiran', 'Tanpa Keterangan')->count(),
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
            ->where('jadwal.hari', $hari)
            ->select([
                'jadwal.*',
                'pengguna.nama as nama_guru',
                'pengguna.mapel_diampu',
            ])
            ->orderBy('jadwal.jam_ke')
            ->get()
            ->map(function (Jadwal $jadwal) use ($kehadiranGuru, $sekarang) {
                $jurnal = Jurnal::query()
                    ->where('id_guru', $jadwal->id_guru)
                    ->where('id_kelas', $jadwal->id_kelas)
                    ->whereDate('tanggal', $sekarang->toDateString())
                    ->where('jam_ke', $jadwal->jam_ke)
                    ->first();

                $jadwal->id_jurnal = $jurnal?->id_jurnal;
                $jadwal->status_jurnal = $jurnal?->status_validasi;
                $jadwal->status_konfirmasi = $jurnal?->status_konfirmasi_sekretaris;
                $jadwal->status_kehadiran = $kehadiranGuru->statusUntukJadwal($jadwal, $sekarang);

                return $jadwal;
            });
    }

    public function getGuruBelumMengisiProperty()
    {
        return $this->monitoringGuru->whereNull('id_jurnal')->unique('id_guru')->values();
    }

    public function getGuruTanpaKeteranganProperty()
    {
        return $this->monitoringGuru
            ->where('status_kehadiran', 'Tanpa Keterangan')
            ->unique('id_guru')
            ->values();
    }

    public function getJurnalTerbaruProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->limit(10)
            ->get();
    }

    public function getDispensasiMenungguProperty()
    {
        return DB::table('dispensasi')
            ->join('siswa', 'dispensasi.id_siswa', '=', 'siswa.id_siswa')
            ->join('kelas', 'dispensasi.id_kelas', '=', 'kelas.id_kelas')
            ->where('dispensasi.status', 'Menunggu Persetujuan')
            ->select([
                'dispensasi.id_dispensasi',
                'dispensasi.tanggal',
                'dispensasi.jenis_dispensasi',
                'dispensasi.mapel',
                'siswa.nama_siswa',
                'kelas.nama_kelas',
            ])
            ->orderBy('dispensasi.tanggal')
            ->limit(10)
            ->get();
    }

};
?>

<div wire:poll.60s>
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

    <div class="row g-3">
        <div class="col-lg-7">
            <div id="monitoring-jurnal" class="card-custom overflow-hidden mb-3">
                <div class="card-header-custom">Jurnal Terbaru</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Tanggal</th><th>Guru</th><th>Kelas</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($this->jurnalTerbaru as $jurnal)
                                <tr>
                                    <td>{{ optional($jurnal->tanggal)->format('d/m/Y') }}</td><td>{{ $jurnal->guru?->nama ?? '-' }}</td><td>{{ $jurnal->kelas?->nama_kelas ?? '-' }}</td>
                                    <td><span class="badge {{ $jurnal->status_validasi === 'Divalidasi' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada jurnal.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div id="monitoring-guru" class="card-custom overflow-hidden">
                <div class="card-header-custom">Guru Belum Mengisi Jurnal</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th></tr></thead>
                        <tbody>
                            @forelse ($this->guruBelumMengisi as $jadwal)
                                <tr><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td><td>Ke-{{ $jadwal->jam_ke }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Semua jadwal sudah memiliki jurnal.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div id="dispensasi" class="card-custom overflow-hidden">
                <div class="card-header-custom">Dispensasi Menunggu Persetujuan</div>
                <div class="list-group list-group-flush">
                    @forelse ($this->dispensasiMenunggu as $dispensasi)
                        <div class="list-group-item">
                            <strong>{{ $dispensasi->nama_siswa }}</strong>
                            <div class="text-muted small">{{ $dispensasi->nama_kelas }} · {{ $dispensasi->jenis_dispensasi }}{{ $dispensasi->mapel ? ' · '.$dispensasi->mapel : '' }}</div>
                            <div class="text-muted small">{{ Carbon::parse($dispensasi->tanggal)->format('d/m/Y') }}</div>
                        </div>
                    @empty
                        <div class="p-3 text-muted">Tidak ada dispensasi yang menunggu.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="card-custom overflow-hidden mt-3">
        <div class="card-header-custom">Monitoring Kehadiran Guru</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Guru</th><th>Mapel</th><th>Kelas</th><th>Jam</th><th>Jurnal</th><th>Kehadiran</th></tr></thead>
                <tbody>
                    @forelse ($this->monitoringGuru as $jadwal)
                        <tr wire:key="wakasek-monitoring-{{ $jadwal->id_jadwal }}">
                            <td>{{ $jadwal->nama_guru }}</td>
                            <td>{{ $jadwal->mapel_diampu ?: '-' }}</td>
                            <td>{{ $jadwal->kelas?->nama_kelas ?: '-' }}</td>
                            <td>Ke-{{ $jadwal->jam_ke }}<div class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</div></td>
                            <td><span class="badge {{ $jadwal->id_jurnal ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jadwal->id_jurnal ? 'Tercatat' : 'Belum Mengisi' }}</span></td>
                            <td><span class="badge {{ in_array($jadwal->status_kehadiran, ['Hadir', 'Izin', 'Sakit'], true) ? 'bg-success' : ($jadwal->status_kehadiran === 'Tanpa Keterangan' ? 'bg-danger' : 'bg-secondary') }}">{{ $jadwal->status_kehadiran }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada jadwal guru hari ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($this->guruTanpaKeterangan->isNotEmpty())
        <div class="card-custom overflow-hidden mt-3 border-start border-4 border-danger">
            <div class="card-header-custom text-danger">Guru Tanpa Keterangan</div>
            <div class="list-group list-group-flush">
                @foreach ($this->guruTanpaKeterangan as $jadwal)
                    <div class="list-group-item d-flex justify-content-between"><span>{{ $jadwal->nama_guru }} · {{ $jadwal->mapel_diampu ?: '-' }}</span><span class="badge bg-danger">Tanpa Keterangan</span></div>
                @endforeach
            </div>
        </div>
    @endif
</div>
