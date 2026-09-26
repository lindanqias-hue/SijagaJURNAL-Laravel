<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Jurnal;
use App\Models\Kelas;
use App\Models\AbsensiSiswa;

new class extends Component
{
    use WithPagination;

    protected $paginationTheme = 'bootstrap';

    public $statusFilter = 'Semua';
    public $kelasFilter = '';
    public $bulanFilter = '';
    public $search = '';

    public $jurnalTerpilih = null;

    public function mount()
    {
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // Dikunci otomatis sesuai waktu kini dan tidak dapat diubah manual
        $this->bulanFilter = now()->format('Y-m-d');
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }
    public function updatingKelasFilter()
    {
        $this->resetPage();
    }
    public function updatingBulanFilter()
    {
        $this->resetPage();
    }
    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function getIsGuruProperty()
    {
        return session('role') === 'guru';
    }

    public function getIsSekretarisProperty(): bool
    {
        return session('role') === 'sekretaris';
    }

    protected function baseQuery()
    {
        $query = Jurnal::query()->with(['guru', 'kelas']);

        if ($this->isGuru) {
            $query->where('id_guru', session('id_pengguna'));
        }

        if ($this->isSekretaris) {
            $query->where('id_kelas', session('id_kelas'));
        }

        if ($this->statusFilter !== 'Semua') {
            $query->where('status_validasi', $this->statusFilter);
        }

        if ($this->kelasFilter) {
            $query->where('id_kelas', $this->kelasFilter);
        }

        if ($this->bulanFilter) {
            [$year, $month, $day] = explode('-', $this->bulanFilter);
            $query->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->whereDay('tanggal', $day);
        }

        if ($this->search) {
            $query->where('materi', 'like', '%' . $this->search . '%');
        }

        return $query;
    }

    public function getRiwayatProperty()
    {
        return $this->baseQuery()
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->paginate(10);
    }

    public function getStatsProperty()
    {
        $query = Jurnal::query();

        if ($this->isGuru) {
            $query->where('id_guru', session('id_pengguna'));
        }

        if ($this->isSekretaris) {
            $query->where('id_kelas', session('id_kelas'));
        }
        if ($this->kelasFilter) {
            $query->where('id_kelas', $this->kelasFilter);
        }

        if ($this->bulanFilter) {
            [$year, $month, $day] = explode('-', $this->bulanFilter);
            $query->whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->whereDay('tanggal', $day);
        }

        if ($this->search) {
            $query->where('materi', 'like', '%' . $this->search . '%');
        }

        $rows = $query->get(['status_validasi']);

        return [
            'total' => $rows->count(),
            'menunggu' => $rows->where('status_validasi', 'Menunggu')->count(),
            'divalidasi' => $rows->where('status_validasi', 'Divalidasi')->count(),
            'ditolak' => $rows->where('status_validasi', 'Ditolak')->count(),
        ];
    }

    public function getKelasListProperty()
    {
        $query = Kelas::orderBy('nama_kelas');

        if ($this->isSekretaris) {
            $query->where('id_kelas', session('id_kelas'));
        }

        return $query->get();
    }

    public function lihatDetail($idJurnal)
    {
        if ($this->isSekretaris && ! Jurnal::whereKey($idJurnal)
            ->where('id_kelas', session('id_kelas'))
            ->exists()) {
            return;
        }

        $this->jurnalTerpilih = $this->jurnalTerpilih === $idJurnal
            ? null
            : $idJurnal;
    }

    public function getDetailAbsensiProperty()
    {
        if (!$this->jurnalTerpilih) {
            return collect();
        }

        $query = AbsensiSiswa::with(['siswa', 'keteranganSiswa'])
            ->where('id_jurnal', $this->jurnalTerpilih);

        if ($this->isSekretaris) {
            $query->whereHas(
                'jurnal',
                fn($jurnal) => $jurnal->where('id_kelas', session('id_kelas'))
            );
        }

        return $query->get();
    }

    public function resetFilter()
    {
        $this->statusFilter = 'Semua';
        $this->kelasFilter = '';
        $this->bulanFilter = now()->format('Y-m-d');
        $this->search = '';
        $this->resetPage();
    }
};
?>

<div>

    {{-- HEADER --}}
    <div class="welcome-banner">

        <div class="deco-circle" style="width:180px; height:180px; top:-60px; right:-40px;"></div>

        <div class="d-flex justify-content-between align-items-center w-100" style="position:relative;">

            <div>
                <div style="color:rgba(255,255,255,.6); font-size:13px; margin-bottom:4px;">
                    {{ $this->isGuru ? 'Riwayat Saya' : 'Riwayat Kelas' }}
                </div>

                <div class="fw-bold" style="font-size:22px; color:#fff;">
                    {{ $this->isGuru ? 'Riwayat Jurnal Mengajar' : 'Riwayat Jurnal Seluruh Kelas' }}
                </div>

                <div style="color:rgba(255,255,255,.65); font-size:13px; margin-top:6px;">
                    {{ $this->isGuru
                        ? 'Pantau jurnal dan absensi yang sudah kamu kirim.'
                        : 'Pantau seluruh jurnal mengajar yang tercatat di sistem.'
                    }}
                </div>
            </div>

            @if ($this->isGuru)
            <a href="{{ route('input-jurnal') }}" class="btn btn-app-primary px-4 py-2">
                + Input Jurnal
            </a>
            @endif

        </div>

    </div>

    @if ($this->isGuru)
        <a href="{{ route('dashboard') }}" class="btn btn-outline-primary btn-sm fw-semibold mb-3">&larr; Kembali ke Dashboard</a>
    @endif

    {{-- STAT CARDS --}}
    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:#eff6ff; color:#1d4ed8;">&#128203;</div>
                <div>
                    <div class="text-muted small">Total Jurnal</div>
                    <div class="stat-value">{{ $this->stats['total'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--warn-light); color:var(--warn);">&#8987;</div>
                <div>
                    <div class="text-muted small">Menunggu</div>
                    <div class="stat-value">{{ $this->stats['menunggu'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--accent-light); color:var(--accent);">&#9989;</div>
                <div>
                    <div class="text-muted small">Valid</div>
                    <div class="stat-value">{{ $this->stats['divalidasi'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="stat-card">
                <div class="stat-icon" style="background:var(--danger-light); color:var(--danger);">&#10060;</div>
                <div>
                    <div class="text-muted small">Ditolak</div>
                    <div class="stat-value">{{ $this->stats['ditolak'] }}</div>
                </div>
            </div>
        </div>

    </div>


    {{-- FILTER --}}
    <div class="card-custom mb-4">

        <div class="card-header-custom">
            <span class="fw-bold" style="font-size:14px;">Filter</span>

            <button type="button" wire:click="resetFilter" class="btn btn-outline-secondary btn-sm">
                Reset Filter
            </button>
        </div>

        <div class="p-3">

            <div class="row g-3">

                <div class="col-md-3">
                    <label class="form-label-sm">Status Validasi</label>
                    <select wire:model.live="statusFilter" class="form-select form-select-sm" @if($this->isGuru)
                        disabled @endif>
                        <option value="Semua">Semua Status</option>
                        <option value="Menunggu">Menunggu</option>
                        <option value="Divalidasi">Valid</option>
                        <option value="Ditolak">Ditolak</option>
                    </select>
                </div>

                @unless ($this->isGuru)
                <div class="col-md-3">
                    <label class="form-label-sm">Kelas</label>
                    <select wire:model.live="kelasFilter" class="form-select form-select-sm">
                        <option value="">Semua Kelas</option>
                        @foreach ($this->kelasList as $k)
                        <option value="{{ $k->id_kelas }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                @endunless

                <div class="col-md-3">
                    <label class="form-label-sm">Tanggal / Waktu Kini</label>
                    <input type="date" wire:model.live="bulanFilter" class="form-control form-control-sm bg-light"
                        disabled>
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Cari Materi</label>
                    <input type="text" wire:model.live.debounce.400ms="search" class="form-control form-control-sm"
                        placeholder="Ketik materi...">
                </div>

            </div>

        </div>

    </div>


    {{-- TABLE --}}
    <div class="card-custom">

        <div class="card-header-custom">
            <span class="fw-bold" style="font-size:14px;">
                Daftar Jurnal
                <span class="text-muted fw-normal">({{ $this->riwayat->total() }} data)</span>
            </span>
        </div>

        <div class="table-responsive">

            <table class="table table-custom mb-0">

                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Jam Ke</th>
                        @unless ($this->isGuru)
                        <th>Guru</th>
                        @endunless
                        <th>Kelas</th>
                        <th class="text-truncate-cell">Materi</th>
                        <th class="text-center">Kehadiran</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    @forelse ($this->riwayat as $jurnal)

                    @php
                    $badgeClass = match ($jurnal->status_validasi) {
                    'Divalidasi' => 'badge-status-success',
                    'Ditolak' => 'badge-status-danger',
                    default => 'badge-status-warning',
                    };
                    @endphp

                    <tr>
                        <td>{{ optional($jurnal->tanggal)->format('d M Y') }}</td>
                        <td>Jam {{ $jurnal->jam_ke }}</td>

                        @unless ($this->isGuru)
                        <td>{{ $jurnal->guru->nama ?? '-' }}</td>
                        @endunless

                        <td><span class="badge-kelas">{{ $jurnal->kelas->nama_kelas ?? '-' }}</span></td>

                        <td class="text-truncate-cell" title="{{ $jurnal->materi }}">
                            {{ $jurnal->materi }}
                        </td>

                        <td class="text-center">
                            {{ $jurnal->jumlah_hadir }} Hadir /
                            {{ $jurnal->jumlah_tidak_hadir }} Tidak Hadir
                        </td>

                        <td class="text-center">
                            <span class="badge-status {{ $badgeClass }}">
                                {{ $jurnal->status_validasi === 'Divalidasi' ? 'Valid' : $jurnal->status_validasi }}
                            </span>
                        </td>

                        <td class="text-center">

                            <button type="button" wire:click="lihatDetail({{ $jurnal->id_jurnal }})" class="btn-edit">
                                {{ $jurnalTerpilih === $jurnal->id_jurnal ? 'Tutup' : 'Detail' }}
                            </button>

                            @if ($this->isGuru && $jurnal->status_validasi === 'Menunggu')
                            <a href="{{ route('input-jurnal') }}?edit={{ $jurnal->id_jurnal }}" class="btn-edit ms-1">
                                Edit
                            </a>
                            @endif

                        </td>
                    </tr>

                    {{-- DETAIL BARIS (per-siswa) --}}
                    @if ($jurnalTerpilih === $jurnal->id_jurnal)
                    <tr>
                        <td colspan="{{ $this->isGuru ? 7 : 8 }}" style="background:#f8fafc; padding:16px;">

                            @if ($jurnal->catatan_validasi)
                            <div class="alert-box alert-warning-box mb-3">
                                <strong>Catatan Sistem:</strong>&nbsp;{{ $jurnal->catatan_validasi }}
                            </div>
                            @endif

                            @if ($this->detailAbsensi->isEmpty())

                            <div class="text-muted text-center py-2">
                                Belum ada data absensi untuk jurnal ini.
                            </div>

                            @else

                            <div class="table-responsive">
                                <table class="table table-sm table-custom mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nama Siswa</th>
                                            <th class="text-center">Status</th>
                                            <th>Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->detailAbsensi as $absen)
                                        <tr>
                                            <td>{{ $absen->siswa->nama_siswa ?? '-' }}</td>
                                            <td class="text-center">{{ $absen->keterangan }}</td>
                                            <td>{{ $absen->keteranganSiswa->keterangan ?? '-' }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @endif

                        </td>
                    </tr>
                    @endif

                    @empty

                    <tr>
                        <td colspan="{{ $this->isGuru ? 7 : 8 }}" class="text-center text-muted py-5">
                            Belum ada riwayat jurnal yang cocok dengan filter ini.
                        </td>
                    </tr>

                    @endforelse

                </tbody>

            </table>

        </div>

        @if ($this->riwayat->hasPages())
        <div class="p-3 border-top">
            {{ $this->riwayat->links() }}
        </div>
        @endif

    </div>

</div>
