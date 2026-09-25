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
    public string $pencarianSiswa = '';
    public string $pencarianKelas = '';
    public string $pencarianJadwal = '';
    public string $pencarianPiket = '';
    public string $pencarianJurnal = '';
    public string $pencarianDispensasi = '';

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
        $this->resetPage('guruPage');
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function updatingPencarianSiswa(): void
    {
        $this->resetPage('siswaPage');
    }

    public function updatingPencarianKelas(): void
    {
        $this->resetPage('kelasPage');
    }

    public function updatingPencarianJadwal(): void
    {
        $this->resetPage('jadwalPage');
    }

    public function updatingPencarianPiket(): void
    {
        $this->resetPage('piketPage');
    }

    public function updatingPencarianJurnal(): void
    {
        $this->resetPage('jurnalPage');
    }

    public function updatingPencarianDispensasi(): void
    {
        $this->resetPage('dispensasiPage');
    }

    public function getSiswaProperty()
    {
        return DB::table('siswa')
            ->leftJoin('kelas', 'siswa.id_kelas', '=', 'kelas.id_kelas')
            ->select(['siswa.id_siswa', 'siswa.nama_siswa', 'kelas.nama_kelas'])
            ->when($this->pencarianSiswa !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('siswa.nama_siswa', 'like', '%'.$this->pencarianSiswa.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianSiswa.'%');
                });
            })
            ->orderBy('siswa.nama_siswa')
            ->paginate(10, ['*'], 'siswaPage');
    }

    public function getGuruProperty()
    {
        return Pengguna::query()
            ->where('role', 'guru')
            ->when($this->pencarianPengguna !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('nama', 'like', '%'.$this->pencarianPengguna.'%')
                        ->orWhere('nip', 'like', '%'.$this->pencarianPengguna.'%');
                });
            })
            ->orderBy('nama')
            ->paginate(10, ['*'], 'guruPage');
    }

    public function getKelasProperty()
    {
        return DB::table('kelas')
            ->select(['id_kelas', 'nama_kelas'])
            ->when($this->pencarianKelas !== '', fn ($query) => $query->where('nama_kelas', 'like', '%'.$this->pencarianKelas.'%'))
            ->orderBy('nama_kelas')
            ->paginate(10, ['*'], 'kelasPage');
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
            ->when($this->pencarianPiket !== '', fn ($query) => $query->where('pengguna.nama', 'like', '%'.$this->pencarianPiket.'%'))
            ->orderBy('pengguna.nama')
            ->paginate(10, ['*'], 'piketPage');
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
            ->when($this->pencarianJadwal !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('pengguna.nama', 'like', '%'.$this->pencarianJadwal.'%')
                        ->orWhere('pengguna.mapel_diampu', 'like', '%'.$this->pencarianJadwal.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianJadwal.'%');
                });
            })
            ->orderBy('jadwal.hari')
            ->orderBy('jadwal.jam_ke')
            ->paginate(10, ['*'], 'jadwalPage');
    }
    public function getJurnalProperty()
    {
        return Jurnal::query()
            ->with(['guru', 'kelas'])
            ->when($this->pencarianJurnal !== '', function ($query) {
                $query->where(function ($query) {
                    $query->whereHas('guru', fn ($query) => $query->where('nama', 'like', '%'.$this->pencarianJurnal.'%'))
                        ->orWhereHas('kelas', fn ($query) => $query->where('nama_kelas', 'like', '%'.$this->pencarianJurnal.'%'));
                });
            })
            ->orderBy('tanggal')
            ->orderBy('id_jurnal')
            ->paginate(10, ['*'], 'jurnalPage');
    }

    public function getDispensasiProperty()
    {
        return DB::table('dispensasi')
            ->leftJoin('siswa', 'dispensasi.id_siswa', '=', 'siswa.id_siswa')
            ->leftJoin('kelas', 'dispensasi.id_kelas', '=', 'kelas.id_kelas')
            ->select(['dispensasi.id_dispensasi', 'dispensasi.tanggal', 'dispensasi.jenis_dispensasi', 'dispensasi.status', 'siswa.nama_siswa', 'kelas.nama_kelas'])
            ->when($this->pencarianDispensasi !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('siswa.nama_siswa', 'like', '%'.$this->pencarianDispensasi.'%')
                        ->orWhere('kelas.nama_kelas', 'like', '%'.$this->pencarianDispensasi.'%')
                        ->orWhere('dispensasi.status', 'like', '%'.$this->pencarianDispensasi.'%');
                });
            })
            ->orderBy('dispensasi.tanggal')
            ->orderBy('dispensasi.id_dispensasi')
            ->paginate(10, ['*'], 'dispensasiPage');
    }
};
?>

<style>
    [x-cloak] { display: none !important; }
</style>

<div x-data="{
    activeSection: 'admin',
    syncSection() {
        const section = window.location.hash.slice(1);
        this.activeSection = ['pengguna', 'guru', 'siswa', 'kelas', 'jadwal', 'jadwal-piket', 'jurnal', 'dispensasi'].includes(section) ? section : 'admin';
    }
}" x-init="syncSection(); window.addEventListener('hashchange', () => syncSection())">

    <section id="admin" x-cloak x-show="activeSection === 'admin'">
        <div class="welcome-banner mb-4">
            <div class="fw-bold" style="font-size:22px; color:#fff;">Dashboard Admin</div>
            <div style="color:rgba(255,255,255,.7); font-size:13px; margin-top:6px;">Administrasi pengguna, jadwal, jurnal, dan dispensasi sekolah.</div>
        </div>
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Total Pengguna</div><div class="stat-value">{{ $this->stats['pengguna'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Guru</div><div class="stat-value">{{ $this->stats['guru'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Siswa</div><div class="stat-value">{{ $this->stats['siswa'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Kelas</div><div class="stat-value">{{ $this->stats['kelas'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Dispensasi Menunggu</div><div class="stat-value">{{ $this->stats['dispensasiMenunggu'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Jadwal Hari Ini</div><div class="stat-value">{{ $this->stats['jadwalHariIni'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Jurnal Hari Ini</div><div class="stat-value">{{ $this->stats['jurnalHariIni'] }}</div></div></div>
            <div class="col-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Guru Piket Hari Ini</div><div class="stat-value">{{ $this->stats['piketHariIni'] }}</div></div></div>
        </div>
    </section>

    <section id="pengguna" x-cloak x-show="activeSection === 'pengguna'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Daftar Pengguna</div>
        <div class="p-3 border-bottom"><div class="row g-2">
            <div class="col-md-8"><input type="search" wire:model.live.debounce.300ms="pencarianPengguna" class="form-control" placeholder="Cari nama atau NIP/ID pengguna..."></div>
            <div class="col-md-4"><select wire:model.live="filterRole" class="form-select"><option value="">Semua Role</option><option value="admin">Admin</option><option value="wakasek">Wakasek</option><option value="guru">Guru</option><option value="sekretaris">Sekretaris</option></select></div>
        </div></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>NIP/ID</th><th>Role</th><th>Status Kepegawaian</th><th>No. HP</th><th>Kelas</th><th>Mapel</th></tr></thead><tbody>
            @forelse ($this->pengguna as $user)
                <tr wire:key="admin-user-{{ $user->id_pengguna }}"><td>{{ $user->nama }}</td><td>{{ $user->nip }}</td><td><span class="badge bg-light text-dark">{{ $user->role }}</span></td><td>{{ $user->status_kepegawaian ?? '-' }}</td><td>{{ $user->no_hp ?? '-' }}</td><td>{{ $user->nama_kelas ?? '-' }}</td><td>{{ $user->mapel_diampu ?: '-' }}</td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pengguna yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->pengguna->links() }}</div>
    </section>

    <section id="guru" x-cloak x-show="activeSection === 'guru'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Daftar Guru</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianPengguna" class="form-control" placeholder="Cari nama atau NIP guru..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>NIP</th><th>Status Kepegawaian</th><th>No. HP</th><th>Mapel</th></tr></thead><tbody>
            @forelse ($this->guru as $guru)<tr wire:key="admin-guru-{{ $guru->id_pengguna }}"><td>{{ $guru->nama }}</td><td>{{ $guru->nip }}</td><td>{{ $guru->status_kepegawaian ?? '-' }}</td><td>{{ $guru->no_hp ?? '-' }}</td><td>{{ $guru->mapel_diampu ?: '-' }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada guru yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->guru->links() }}</div>
    </section>

    <section id="siswa" x-cloak x-show="activeSection === 'siswa'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Daftar Siswa</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianSiswa" class="form-control" placeholder="Cari nama siswa atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama</th><th>Kelas</th></tr></thead><tbody>
            @forelse ($this->siswa as $siswa)<tr wire:key="admin-siswa-{{ $siswa->id_siswa }}"><td>{{ $siswa->nama_siswa }}</td><td>{{ $siswa->nama_kelas ?? '-' }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted py-4">Tidak ada siswa yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->siswa->links() }}</div>
    </section>

    <section id="kelas" x-cloak x-show="activeSection === 'kelas'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Daftar Kelas</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianKelas" class="form-control" placeholder="Cari nama kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Nama Kelas</th></tr></thead><tbody>
            @forelse ($this->kelas as $kelas)<tr wire:key="admin-kelas-{{ $kelas->id_kelas }}"><td>{{ $kelas->nama_kelas }}</td></tr>@empty<tr><td class="text-center text-muted py-4">Tidak ada kelas yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->kelas->links() }}</div>
    </section>

    <section id="jadwal" x-cloak x-show="activeSection === 'jadwal'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Jadwal Mengajar Hari Ini</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianJadwal" class="form-control" placeholder="Cari guru, mata pelajaran, atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Hari</th><th>Jam</th><th>Guru</th><th>Mapel</th><th>Kelas</th></tr></thead><tbody>
            @forelse ($this->jadwalMengajarHariIni as $jadwal)<tr wire:key="admin-jadwal-{{ $jadwal->id_jadwal }}"><td>{{ $jadwal->hari }}</td><td>Ke-{{ $jadwal->jam_ke }} <span class="text-muted small">{{ substr($jadwal->jam_mulai, 0, 5) }}–{{ substr($jadwal->jam_selesai, 0, 5) }}</span></td><td>{{ $jadwal->nama_guru }}</td><td>{{ $jadwal->mapel_diampu ?: '-' }}</td><td>{{ $jadwal->nama_kelas }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada jadwal mengajar yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jadwalMengajarHariIni->links() }}</div>
    </section>

    <section id="jadwal-piket" x-cloak x-show="activeSection === 'jadwal-piket'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Jadwal Piket Hari Ini</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianPiket" class="form-control" placeholder="Cari nama guru piket..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Guru</th><th>Jam</th><th>Status</th><th>Keterangan</th></tr></thead><tbody>
            @forelse ($this->jadwalPiketHariIni as $piket)<tr wire:key="admin-piket-{{ $piket->id_jadwal_piket }}"><td>{{ $piket->nama }}</td><td>{{ substr($piket->jam_mulai, 0, 5) }}–{{ substr($piket->jam_selesai, 0, 5) }}</td><td>{{ $piket->status }}</td><td>{{ $piket->keterangan ?: '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">Tidak ada jadwal piket yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jadwalPiketHariIni->links() }}</div>
    </section>

    <section id="jurnal" x-cloak x-show="activeSection === 'jurnal'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Jurnal</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianJurnal" class="form-control" placeholder="Cari guru atau kelas..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Tanggal</th><th>Guru</th><th>Kelas</th><th>Status</th></tr></thead><tbody>
            @forelse ($this->jurnal as $jurnal)<tr wire:key="admin-jurnal-{{ $jurnal->id_jurnal }}"><td>{{ optional($jurnal->tanggal)->format('d/m/Y') }}</td><td>{{ $jurnal->guru?->nama ?? '-' }}</td><td>{{ $jurnal->kelas?->nama_kelas ?? '-' }}</td><td><span class="badge {{ $jurnal->status_validasi === 'Divalidasi' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}</span></td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-4">Belum ada jurnal yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->jurnal->links() }}</div>
    </section>

    <section id="dispensasi" x-cloak x-show="activeSection === 'dispensasi'" class="card-custom overflow-hidden mb-4">
        <div class="card-header-custom">Dispensasi</div><div class="p-3 border-bottom"><input type="search" wire:model.live.debounce.300ms="pencarianDispensasi" class="form-control" placeholder="Cari siswa, kelas, atau status..."></div>
        <div class="table-responsive"><table class="table table-hover mb-0 align-middle"><thead><tr><th>Tanggal</th><th>Siswa</th><th>Kelas</th><th>Jenis</th><th>Status</th></tr></thead><tbody>
            @forelse ($this->dispensasi as $dispensasi)<tr wire:key="admin-dispensasi-{{ $dispensasi->id_dispensasi }}"><td>{{ \Illuminate\Support\Carbon::parse($dispensasi->tanggal)->format('d/m/Y') }}</td><td>{{ $dispensasi->nama_siswa ?? '-' }}</td><td>{{ $dispensasi->nama_kelas ?? '-' }}</td><td>{{ $dispensasi->jenis_dispensasi }}</td><td>{{ $dispensasi->status }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada dispensasi yang cocok.</td></tr>@endforelse
        </tbody></table></div><div class="p-3">{{ $this->dispensasi->links() }}</div>
    </section>
</div>
