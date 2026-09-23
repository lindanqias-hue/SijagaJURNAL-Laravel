<?php

use App\Models\Jadwal;
use App\Models\Jurnal;
use App\Models\Pengguna;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $pencarianPengguna = '';
    public string $filterRole = '';

    protected $paginationTheme = 'bootstrap';

    public function mount(): void
    {
        abort_unless(session('role') === 'admin', 403);
    }

    public function getStatsProperty(): array
    {
        return [
            'pengguna' => Pengguna::count(),
            'guru' => Pengguna::where('role', 'guru')->count(),
            'siswa' => DB::table('siswa')->count(),
            'kelas' => DB::table('kelas')->count(),
            'jadwalHariIni' => Jadwal::where('hari', now('Asia/Jakarta')->locale('id')->translatedFormat('l'))->count(),
            'jurnalHariIni' => Jurnal::whereDate('tanggal', now('Asia/Jakarta')->toDateString())->count(),
            'piketHariIni' => DB::table('jadwal_piket')
                ->whereDate('tanggal', now('Asia/Jakarta')->toDateString())
                ->where('status', 'Aktif')
                ->count(),
            'dispensasiMenunggu' => DB::table('dispensasi')
                ->where('status', 'Menunggu Persetujuan')
                ->count(),
        ];
    }

    public function getPenggunaProperty()
    {
        return Pengguna::query()
            ->leftJoin('kelas', 'pengguna.id_kelas', '=', 'kelas.id_kelas')
            ->select([
                'pengguna.id_pengguna',
                'pengguna.nama',
                'pengguna.nip',
                'pengguna.role',
                'pengguna.mapel_diampu',
                'pengguna.status_kepegawaian',
                'pengguna.no_hp',
                'kelas.nama_kelas',
            ])
            ->when($this->pencarianPengguna !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('nama', 'like', '%'.$this->pencarianPengguna.'%')
                        ->orWhere('nip', 'like', '%'.$this->pencarianPengguna.'%');
                });
            })
            ->when($this->filterRole !== '', fn ($query) => $query->where('role', $this->filterRole))
            ->orderBy('role')
            ->orderBy('nama')
            ->paginate(10);
    }

    public function updatingPencarianPengguna(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function getJurnalTerbaruProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->orderByDesc('tanggal')
            ->orderByDesc('id_jurnal')
            ->limit(8)
            ->get();
    }

    public function getJadwalPiketHariIniProperty()
    {
        return DB::table('jadwal_piket')
            ->join('pengguna', 'jadwal_piket.id_guru', '=', 'pengguna.id_pengguna')
            ->whereDate('jadwal_piket.tanggal', now('Asia/Jakarta')->toDateString())
            ->where('jadwal_piket.status', 'Aktif')
            ->select([
                'jadwal_piket.*',
                'pengguna.nama',
            ])
            ->orderBy('jadwal_piket.jam_mulai')
            ->get();
    }

    public function getJadwalMengajarHariIniProperty()
    {
        return Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->join('kelas', 'jadwal.id_kelas', '=', 'kelas.id_kelas')
            ->where('jadwal.hari', now('Asia/Jakarta')->locale('id')->translatedFormat('l'))
            ->select([
                'jadwal.*',
                'pengguna.nama as nama_guru',
                'pengguna.mapel_diampu',
                'kelas.nama_kelas',
            ])
            ->orderBy('jadwal.jam_ke')
            ->get();
    }
};
?>

<div>
    <div class="welcome-banner mb-4">
        <div class="fw-bold" style="font-size:22px; color:#fff;">Dashboard Admin</div>
        <div style="color:rgba(255,255,255,.7); font-size:13px; margin-top:6px;">
            Administrasi pengguna, jadwal, jurnal, dan dispensasi sekolah.
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Total Pengguna</div><div class="stat-value">{{ $this->stats['pengguna'] }}</div></div></div>
        <div id="guru" class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Guru</div><div class="stat-value">{{ $this->stats['guru'] }}</div></div></div>
        <div id="siswa" class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Siswa</div><div class="stat-value">{{ $this->stats['siswa'] }}</div></div></div>
        <div id="kelas" class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Kelas</div><div class="stat-value">{{ $this->stats['kelas'] }}</div></div></div>
        <div id="dispensasi" class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Dispensasi Menunggu</div><div class="stat-value">{{ $this->stats['dispensasiMenunggu'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Jadwal Hari Ini</div><div class="stat-value">{{ $this->stats['jadwalHariIni'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Jurnal Hari Ini</div><div class="stat-value">{{ $this->stats['jurnalHariIni'] }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Guru Piket Hari Ini</div><div class="stat-value">{{ $this->stats['piketHariIni'] }}</div></div></div>
    </div>

    <div id="jurnal" class="row g-3 mb-4">
        <div class="col-lg-12">
            <div class="card-custom overflow-hidden h-100">
                <div class="card-header-custom">Jurnal Terbaru</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Tanggal</th><th>Guru</th><th>Kelas</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($this->jurnalTerbaru as $jurnal)
                                <tr>
                                    <td>{{ optional($jurnal->tanggal)->format('d/m/Y') }}</td>
                                    <td>{{ $jurnal->guru?->nama ?? '-' }}</td>
                                    <td>{{ $jurnal->kelas?->nama_kelas ?? '-' }}</td>
                                    <td><span class="badge {{ $jurnal->status_validasi === 'Divalidasi' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Belum ada jurnal.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <div id="pengguna" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <span>Daftar Pengguna</span>
            <span class="badge bg-secondary">{{ $this->stats['dispensasiMenunggu'] }} dispensasi menunggu</span>
        </div>
        <div class="p-3 border-bottom">
            <div class="row g-2">
                <div class="col-md-8">
                    <input type="search" wire:model.live.debounce.300ms="pencarianPengguna" class="form-control" placeholder="Cari nama atau NIP/ID pengguna...">
                </div>
                <div class="col-md-4">
                    <select wire:model.live="filterRole" class="form-select">
                        <option value="">Semua Role</option>
                        <option value="admin">Admin</option>
                        <option value="wakasek">Wakasek</option>
                        <option value="guru">Guru</option>
                        <option value="sekretaris">Sekretaris</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead><tr><th>Nama</th><th>NIP/ID</th><th>Role</th><th>Status Kepegawaian</th><th>No. HP</th><th>Kelas</th><th>Mapel</th></tr></thead>
                <tbody>
                    @foreach ($this->pengguna as $user)
                        <tr wire:key="admin-user-{{ $user->id_pengguna }}">
                            <td>{{ $user->nama }}</td><td>{{ $user->nip }}</td><td><span class="badge bg-light text-dark">{{ $user->role }}</span></td><td>{{ $user->status_kepegawaian ?? '-' }}</td><td>{{ $user->no_hp ?? '-' }}</td><td>{{ $user->nama_kelas ?? '-' }}</td><td>{{ $user->mapel_diampu ?: '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="p-3">{{ $this->pengguna->links() }}</div>
    </div>

    <div class="row g-3">
        <div id="jadwal" class="col-lg-7">
            <div class="card-custom overflow-hidden h-100">
                <div class="card-header-custom">Jadwal Mengajar Hari Ini</div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead><tr><th>Jam</th><th>Guru</th><th>Mapel</th><th>Kelas</th></tr></thead>
                        <tbody>
                            @forelse ($this->jadwalMengajarHariIni as $jadwal)
                                <tr><td>Ke-{{ $jadwal->jam_ke }}<div class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</div></td><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->nama_kelas }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Tidak ada jadwal mengajar hari ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div id="jadwal-piket" class="col-lg-5">
            <div class="card-custom overflow-hidden h-100">
                <div class="card-header-custom">Jadwal Piket Hari Ini</div>
                <div class="list-group list-group-flush">
                    @forelse ($this->jadwalPiketHariIni as $piket)
                        <div class="list-group-item d-flex justify-content-between align-items-center"><div><strong>{{ $piket->nama }}</strong><div class="text-muted small">{{ substr($piket->jam_mulai, 0, 5) }}–{{ substr($piket->jam_selesai, 0, 5) }}</div></div><span class="badge bg-success">{{ $piket->status }}</span></div>
                    @empty
                        <div class="p-3 text-muted">Tidak ada tugas piket hari ini.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
