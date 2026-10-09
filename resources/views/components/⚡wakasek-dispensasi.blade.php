<?php

use App\Models\Dispensasi;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public string $filterStatus = 'Menunggu Persetujuan';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(session('role') === 'wakasek', 403);
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getStatsProperty(): array
    {
        return [
            'menunggu' => Dispensasi::query()->where('status', Dispensasi::STATUS_MENUNGGU)->count(),
            'disetujui' => Dispensasi::query()->where('status', Dispensasi::STATUS_DISETUJUI)->count(),
            'ditolak' => Dispensasi::query()->where('status', Dispensasi::STATUS_DITOLAK)->count(),
        ];
    }

    public function getDispensasiProperty()
    {
        return Dispensasi::query()
            ->with(['siswa', 'kelas', 'guruPiket', 'wakasek', 'guruPengajar'])
            ->where('status', $this->filterStatus)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->whereHas('siswa', fn ($q) => $q->where('nama_siswa', 'like', '%'.$this->search.'%'))
                        ->orWhereHas('kelas', fn ($q) => $q->where('nama_kelas', 'like', '%'.$this->search.'%'))
                        ->orWhere('jenis_surat', 'like', '%'.$this->search.'%')
                        ->orWhere('jenis_dispensasi', 'like', '%'.$this->search.'%')
                        ->orWhere('alasan', 'like', '%'.$this->search.'%');
                });
            })
            ->orderByDesc('tanggal')
            ->orderByDesc('id_dispensasi')
            ->paginate(15);
    }
};
?>

<div>
    {{-- HEADER --}}
    <div class="role-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="page-title">Dispensasi Wakasek</div>
            <div class="role-page-description">
                Tinjau dan setujui pengajuan dispensasi siswa dari guru piket.
            </div>
        </div>
        <a href="{{ route('wakasek') }}" class="btn btn-outline-secondary">&larr; Kembali ke Dashboard</a>
    </div>
    <div class="role-page-actions mb-3"></div>

    {{-- STATISTIK --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--warn);">{{ $this->stats['menunggu'] }}</div>
                <div class="text-muted small">Menunggu Persetujuan</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:#16a34a;">{{ $this->stats['disetujui'] }}</div>
                <div class="text-muted small">Disetujui</div>
            </div>
        </div>
        <div class="col-6 col-md-4">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--danger);">{{ $this->stats['ditolak'] }}</div>
                <div class="text-muted small">Ditolak</div>
            </div>
        </div>
    </div>

    {{-- FILTER --}}
    <div class="card-custom p-3 mb-3">
        <div class="row g-2">
            <div class="col-md-8">
                <input type="search" class="form-control" placeholder="Cari siswa, kelas, jenis surat, atau alasan..."
                    wire:model.live.debounce.300ms="search">
            </div>
            <div class="col-md-4">
                <select class="form-select" wire:model.live="filterStatus">
                    <option value="{{ Dispensasi::STATUS_MENUNGGU }}">Menunggu Persetujuan</option>
                    <option value="{{ Dispensasi::STATUS_DISETUJUI }}">Disetujui</option>
                    <option value="{{ Dispensasi::STATUS_DITOLAK }}">Ditolak</option>
                </select>
            </div>
        </div>
    </div>

    {{-- TABEL --}}
    <div class="card-custom overflow-hidden">
        <div class="card-header-custom">
            Daftar Dispensasi
            <span class="text-muted small fw-normal">({{ $this->dispensasi->total() }} data)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle table-stack">
                <thead class="table-light">
                    <tr>
                        <th>Siswa</th>
                        <th>Kelas</th>
                        <th>Jenis Surat</th>
                        <th>Jenis</th>
                        <th>Tanggal</th>
                        <th>Jam</th>
                        <th>Guru Piket</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->dispensasi as $dispensasi)
                    <tr wire:key="wakasek-dispensasi-{{ $dispensasi->id_dispensasi }}">
                        <td data-label="Siswa">
                            <strong>{{ $dispensasi->siswa?->nama_siswa ?? '-' }}</strong>
                        </td>
                        <td data-label="Kelas">{{ $dispensasi->kelas?->nama_kelas ?? '-' }}</td>
                        <td data-label="Jenis Surat">
                            <span class="badge {{ $dispensasi->jenis_surat === 'Dispensasi' ? 'bg-primary' : 'bg-info text-dark' }}">
                                {{ $dispensasi->jenis_surat ?? 'Dispensasi' }}
                            </span>
                        </td>
                        <td data-label="Jenis">{{ $dispensasi->jenis_dispensasi }}</td>
                        <td data-label="Tanggal">{{ $dispensasi->tanggal?->format('d/m/Y') }}</td>
                        <td data-label="Jam">
                            @if ($dispensasi->jenis_dispensasi === 'Per Jam')
                                Jam ke-{{ $dispensasi->jam_ke_mulai }}
                                @if ($dispensasi->jam_ke_selesai && $dispensasi->jam_ke_selesai != $dispensasi->jam_ke_mulai)
                                    s/d {{ $dispensasi->jam_ke_selesai }}
                                @endif
                            @else
                                Sehari penuh
                            @endif
                        </td>
                        <td data-label="Guru Piket">
                            {{ $dispensasi->guruPiket?->nama ?? '-' }}
                        </td>
                        <td data-label="Aksi" class="text-end text-nowrap">
                            @if ($dispensasi->status === Dispensasi::STATUS_MENUNGGU && $dispensasi->token)
                            <a href="{{ route('approve-dispensasi', ['token' => $dispensasi->token, 'wakasek' => session('id_pengguna')]) }}"
                                class="btn btn-sm btn-app-primary">Validasi</a>
                            @endif
                            <a href="{{ route('dispensasi-detail', $dispensasi->id_dispensasi) }}"
                                class="btn btn-sm btn-outline-secondary">Detail</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                            Tidak ada dispensasi dengan status ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->dispensasi->hasPages())
        <div class="p-3 border-top">{{ $this->dispensasi->links() }}</div>
        @endif
    </div>
</div>