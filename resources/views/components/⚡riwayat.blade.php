```php
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

    // =========================================================
    // MOUNT
    // =========================================================
    public function mount()
    {
        // Belum login
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        // Input type="month" membutuhkan format Y-m
        $this->bulanFilter = now('Asia/Jakarta')->format('Y-m');
    }

    // =========================================================
    // RESET PAGINATION SAAT FILTER BERUBAH
    // =========================================================
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

    // =========================================================
    // CEK ROLE GURU
    // =========================================================
    public function getIsGuruProperty()
    {
        return in_array(
            session('role'),
            ['guru', 'guru_piket']
        );
    }

    // =========================================================
    // CEK ROLE SEKRETARIS
    // =========================================================
    public function getIsSekretarisProperty(): bool
    {
        return session('role') === 'sekretaris';
    }

    // =========================================================
    // QUERY DASAR RIWAYAT
    // =========================================================
    protected function baseQuery()
    {
        $query = Jurnal::query()
            ->with([
                'guru',
                'kelas'
            ]);

        // Guru hanya melihat jurnal miliknya sendiri
        if ($this->isGuru) {
            $query->where(
                'id_guru',
                session('id_pengguna')
            );
        }

        // Sekretaris hanya melihat jurnal kelasnya
        if ($this->isSekretaris) {

            $idKelas = session('id_kelas');

            if ($idKelas) {
                $query->where(
                    'id_kelas',
                    $idKelas
                );
            } else {
                // Kalau sekretaris belum punya kelas,
                // jangan tampilkan jurnal kelas lain.
                $query->whereRaw('1 = 0');
            }
        }

        // Filter status validasi
        if ($this->statusFilter !== 'Semua') {
            $query->where(
                'status_validasi',
                $this->statusFilter
            );
        }

        // Filter kelas
        if ($this->kelasFilter) {
            $query->where(
                'id_kelas',
                $this->kelasFilter
            );
        }

        // Filter bulan
        if ($this->bulanFilter) {

            $parts = explode(
                '-',
                $this->bulanFilter
            );

            if (
                count($parts) === 2 &&
                is_numeric($parts[0]) &&
                is_numeric($parts[1])
            ) {
                $year = (int) $parts[0];
                $month = (int) $parts[1];

                $query
                    ->whereYear('tanggal', $year)
                    ->whereMonth('tanggal', $month);
            }
        }

        // Cari materi
        if ($this->search) {

            $query->where(
                'materi',
                'like',
                '%' . $this->search . '%'
            );
        }

        return $query;
    }

    // =========================================================
    // RIWAYAT JURNAL
    // =========================================================
    public function getRiwayatProperty()
    {
        return $this->baseQuery()
            ->orderByDesc('tanggal')
            ->orderByDesc('jam_ke')
            ->paginate(10);
    }

    // =========================================================
    // STATISTIK
    // =========================================================
    public function getStatsProperty()
    {
        $query = Jurnal::query();

        // Guru
        if ($this->isGuru) {
            $query->where(
                'id_guru',
                session('id_pengguna')
            );
        }

        // Sekretaris
        if ($this->isSekretaris) {

            $idKelas = session('id_kelas');

            if ($idKelas) {
                $query->where(
                    'id_kelas',
                    $idKelas
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Filter kelas
        if ($this->kelasFilter) {
            $query->where(
                'id_kelas',
                $this->kelasFilter
            );
        }

        // Filter bulan
        if ($this->bulanFilter) {

            $parts = explode(
                '-',
                $this->bulanFilter
            );

            if (
                count($parts) === 2 &&
                is_numeric($parts[0]) &&
                is_numeric($parts[1])
            ) {
                $year = (int) $parts[0];
                $month = (int) $parts[1];

                $query
                    ->whereYear('tanggal', $year)
                    ->whereMonth('tanggal', $month);
            }
        }

        // Search materi
        if ($this->search) {
            $query->where(
                'materi',
                'like',
                '%' . $this->search . '%'
            );
        }

        $rows = $query->get([
            'status_validasi'
        ]);

        return [
            'total' => $rows->count(),

            'menunggu' => $rows
                ->where(
                    'status_validasi',
                    'Menunggu'
                )
                ->count(),

            'divalidasi' => $rows
                ->where(
                    'status_validasi',
                    'Divalidasi'
                )
                ->count(),

            'ditolak' => $rows
                ->where(
                    'status_validasi',
                    'Ditolak'
                )
                ->count(),
        ];
    }

    // =========================================================
    // DAFTAR KELAS
    // =========================================================
    public function getKelasListProperty()
    {
        $query = Kelas::orderBy(
            'nama_kelas'
        );

        // Sekretaris hanya melihat kelasnya sendiri
        if ($this->isSekretaris) {

            $idKelas = session('id_kelas');

            if ($idKelas) {
                $query->where(
                    'id_kelas',
                    $idKelas
                );
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        return $query->get();
    }

    // =========================================================
    // LIHAT DETAIL JURNAL
    // =========================================================
    public function lihatDetail($idJurnal)
    {
        $query = Jurnal::query()
            ->whereKey($idJurnal);

        // Guru hanya boleh melihat jurnalnya sendiri
        if ($this->isGuru) {
            $query->where(
                'id_guru',
                session('id_pengguna')
            );
        }

        // Sekretaris hanya boleh melihat kelasnya
        if ($this->isSekretaris) {

            $idKelas = session('id_kelas');

            if (!$idKelas) {
                return;
            }

            $query->where(
                'id_kelas',
                $idKelas
            );
        }

        if (!$query->exists()) {
            return;
        }

        $this->jurnalTerpilih =
            $this->jurnalTerpilih === $idJurnal
                ? null
                : $idJurnal;
    }

    // =========================================================
    // DETAIL ABSENSI
    // =========================================================
    public function getDetailAbsensiProperty()
    {
        if (!$this->jurnalTerpilih) {
            return collect();
        }

        $query = AbsensiSiswa::with([
            'siswa',
            'keteranganSiswa'
        ])->where(
            'id_jurnal',
            $this->jurnalTerpilih
        );

        // Guru hanya boleh melihat absensi jurnal miliknya
        if ($this->isGuru) {

            $query->whereHas(
                'jurnal',
                function ($jurnal) {
                    $jurnal->where(
                        'id_guru',
                        session('id_pengguna')
                    );
                }
            );
        }

        // Sekretaris hanya boleh melihat absensi kelasnya
        if ($this->isSekretaris) {

            $idKelas = session('id_kelas');

            if (!$idKelas) {
                return collect();
            }

            $query->whereHas(
                'jurnal',
                function ($jurnal) use ($idKelas) {
                    $jurnal->where(
                        'id_kelas',
                        $idKelas
                    );
                }
            );
        }

        return $query->get();
    }

    // =========================================================
    // RESET FILTER
    // =========================================================
    public function resetFilter()
    {
        $this->statusFilter = 'Semua';
        $this->kelasFilter = '';
        $this->bulanFilter = now('Asia/Jakarta')
            ->format('Y-m');
        $this->search = '';

        $this->resetPage();
    }
};
?>

<div>

    {{-- =====================================================
         HEADER
    ====================================================== --}}
    <div class="welcome-banner">

        <div
            class="deco-circle"
            style="
                width:180px;
                height:180px;
                top:-60px;
                right:-40px;
            "
        ></div>

        <div
            class="d-flex justify-content-between align-items-center w-100"
            style="position:relative;"
        >

            <div>

                <div
                    style="
                        color:rgba(255,255,255,.6);
                        font-size:13px;
                        margin-bottom:4px;
                    "
                >
                    {{
                        $this->isGuru
                            ? 'Riwayat Saya'
                            : 'Riwayat Kelas'
                    }}
                </div>

                <div
                    class="fw-bold"
                    style="
                        font-size:22px;
                        color:#fff;
                    "
                >
                    {{
                        $this->isGuru
                            ? 'Riwayat Jurnal Mengajar'
                            : 'Riwayat Jurnal Seluruh Kelas'
                    }}
                </div>

                <div
                    style="
                        color:rgba(255,255,255,.65);
                        font-size:13px;
                        margin-top:6px;
                    "
                >
                    {{
                        $this->isGuru
                            ? 'Pantau jurnal dan absensi yang sudah kamu kirim.'
                            : 'Pantau jurnal mengajar yang tercatat di sistem.'
                    }}
                </div>

            </div>


            @if($this->isGuru)

                <a
                    href="{{ route('input-jurnal') }}"
                    class="btn btn-app-primary px-4 py-2"
                >
                    + Input Jurnal
                </a>

            @endif

        </div>

    </div>


    {{-- KEMBALI KE DASHBOARD --}}
    @if($this->isGuru)

        <a
            href="{{ route('dashboard') }}"
            class="btn btn-outline-primary btn-sm fw-semibold mb-3"
        >
            &larr; Kembali ke Dashboard
        </a>

    @endif


    {{-- =====================================================
         STAT CARDS
    ====================================================== --}}
    <div class="row g-3 mb-4">

        {{-- TOTAL --}}
        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:#eff6ff;
                        color:#1d4ed8;
                    "
                >
                    &#128203;
                </div>

                <div>

                    <div class="text-muted small">
                        Total Jurnal
                    </div>

                    <div class="stat-value">
                        {{ $this->stats['total'] }}
                    </div>

                </div>

            </div>

        </div>


        {{-- MENUNGGU --}}
        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:var(--warn-light);
                        color:var(--warn);
                    "
                >
                    &#8987;
                </div>

                <div>

                    <div class="text-muted small">
                        Menunggu
                    </div>

                    <div class="stat-value">
                        {{ $this->stats['menunggu'] }}
                    </div>

                </div>

            </div>

        </div>


        {{-- VALID --}}
        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:var(--accent-light);
                        color:var(--accent);
                    "
                >
                    &#9989;
                </div>

                <div>

                    <div class="text-muted small">
                        Valid
                    </div>

                    <div class="stat-value">
                        {{ $this->stats['divalidasi'] }}
                    </div>

                </div>

            </div>

        </div>


        {{-- DITOLAK --}}
        <div class="col-6 col-lg-3">

            <div class="stat-card">

                <div
                    class="stat-icon"
                    style="
                        background:var(--danger-light);
                        color:var(--danger);
                    "
                >
                    &#10060;
                </div>

                <div>

                    <div class="text-muted small">
                        Ditolak
                    </div>

                    <div class="stat-value">
                        {{ $this->stats['ditolak'] }}
                    </div>

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         FILTER
    ====================================================== --}}
    <div class="card-custom mb-4">

        <div class="card-header-custom">

            <span
                class="fw-bold"
                style="font-size:14px;"
            >
                Filter
            </span>

            <button
                type="button"
                wire:click="resetFilter"
                class="btn btn-outline-secondary btn-sm"
            >
                Reset Filter
            </button>

        </div>


        <div class="p-3">

            <div class="row g-3">

                {{-- STATUS --}}
                <div class="col-md-3">

                    <label class="form-label-sm">
                        Status Validasi
                    </label>

                    <select
                        wire:model.live="statusFilter"
                        class="form-select form-select-sm"
                    >

                        <option value="Semua">
                            Semua Status
                        </option>

                        <option value="Menunggu">
                            Menunggu
                        </option>

                        <option value="Divalidasi">
                            Valid
                        </option>

                        <option value="Ditolak">
                            Ditolak
                        </option>

                    </select>

                </div>


                {{-- KELAS --}}
                @unless($this->isGuru)

                    <div class="col-md-3">

                        <label class="form-label-sm">
                            Kelas
                        </label>

                        <select
                            wire:model.live="kelasFilter"
                            class="form-select form-select-sm"
                        >

                            <option value="">
                                Semua Kelas
                            </option>

                            @foreach($this->kelasList as $k)

                                <option
                                    value="{{ $k->id_kelas }}"
                                >
                                    {{ $k->nama_kelas }}
                                </option>

                            @endforeach

                        </select>

                    </div>

                @endunless


                {{-- BULAN --}}
                <div class="col-md-3">

                    <label class="form-label-sm">
                        Bulan
                    </label>

                    <input
                        type="month"
                        wire:model.live="bulanFilter"
                        class="form-control form-control-sm"
                        @disabled($this->isSekretaris)
                    >

                </div>


                {{-- SEARCH --}}
                <div class="col-md-3">

                    <label class="form-label-sm">
                        Cari Materi
                    </label>

                    <input
                        type="text"
                        wire:model.live.debounce.400ms="search"
                        class="form-control form-control-sm"
                        placeholder="Ketik materi..."
                    >

                </div>

            </div>

        </div>

    </div>


    {{-- =====================================================
         TABLE
    ====================================================== --}}
    <div class="card-custom">

        <div class="card-header-custom">

            <span
                class="fw-bold"
                style="font-size:14px;"
            >

                Daftar Jurnal

                <span class="text-muted fw-normal">
                    ({{ $this->riwayat->total() }} data)
                </span>

            </span>

        </div>


        <div class="table-responsive">

            <table class="table table-custom mb-0">

                <thead>

                    <tr>

                        <th>
                            Tanggal
                        </th>

                        <th>
                            Jam Ke
                        </th>

                        @unless($this->isGuru)

                            <th>
                                Guru
                            </th>

                        @endunless

                        <th>
                            Kelas
                        </th>

                        <th class="text-truncate-cell">
                            Materi
                        </th>

                        <th class="text-center">
                            Kehadiran
                        </th>

                        <th class="text-center">
                            Status
                        </th>

                        <th class="text-center">
                            Aksi
                        </th>

                    </tr>

                </thead>


                <tbody>

                    @forelse($this->riwayat as $jurnal)

                        @php

                            $badgeClass = match(
                                $jurnal->status_validasi
                            ) {

                                'Divalidasi'
                                    => 'badge-status-success',

                                'Ditolak'
                                    => 'badge-status-danger',

                                default
                                    => 'badge-status-warning',

                            };

                        @endphp


                        <tr>

                            {{-- TANGGAL --}}
                            <td>

                                {{
                                    optional(
                                        $jurnal->tanggal
                                    )->format('d M Y')
                                }}

                            </td>


                            {{-- JAM --}}
                            <td>
                                Jam {{ $jurnal->jam_ke }}
                            </td>


                            {{-- GURU --}}
                            @unless($this->isGuru)

                                <td>
                                    {{ $jurnal->guru->nama ?? '-' }}
                                </td>

                            @endunless


                            {{-- KELAS --}}
                            <td>

                                <span class="badge-kelas">
                                    {{ $jurnal->kelas->nama_kelas ?? '-' }}
                                </span>

                            </td>


                            {{-- MATERI --}}
                            <td
                                class="text-truncate-cell"
                                title="{{ $jurnal->materi }}"
                            >
                                {{ $jurnal->materi }}
                            </td>


                            {{-- KEHADIRAN --}}
                            <td class="text-center">

                                {{ $jurnal->jumlah_hadir }}
                                Hadir /

                                {{ $jurnal->jumlah_tidak_hadir }}
                                Tidak Hadir

                            </td>


                            {{-- STATUS --}}
                            <td class="text-center">

                                <span
                                    class="badge-status {{ $badgeClass }}"
                                >

                                    {{
                                        $jurnal->status_validasi ===
                                        'Divalidasi'
                                            ? 'Valid'
                                            : $jurnal->status_validasi
                                    }}

                                </span>

                            </td>


                            {{-- AKSI --}}
                            <td class="text-center">

                                <button
                                    type="button"
                                    wire:click="lihatDetail({{
                                        $jurnal->id_jurnal
                                    }})"
                                    class="btn-edit"
                                >

                                    {{
                                        $jurnalTerpilih ===
                                        $jurnal->id_jurnal
                                            ? 'Tutup'
                                            : 'Detail'
                                    }}

                                </button>


                                @if(
                                    $this->isGuru &&
                                    $jurnal->status_validasi ===
                                    'Menunggu'
                                )

                                    <a
                                        href="{{ route(
                                            'input-jurnal'
                                        ) }}?edit={{
                                            $jurnal->id_jurnal
                                        }}"
                                        class="btn-edit ms-1"
                                    >
                                        Edit
                                    </a>

                                @endif

                            </td>

                        </tr>


                        {{-- =================================================
                             DETAIL ABSENSI
                        ================================================== --}}
                        @if(
                            $jurnalTerpilih ===
                            $jurnal->id_jurnal
                        )

                            <tr>

                                <td
                                    colspan="{{
                                        $this->isGuru
                                            ? 7
                                            : 8
                                    }}"
                                    style="
                                        background:#f8fafc;
                                        padding:16px;
                                    "
                                >

                                    @if(
                                        $jurnal->catatan_validasi
                                    )

                                        <div
                                            class="alert-box alert-warning-box mb-3"
                                        >
                                            <strong>
                                                Catatan Sistem:
                                            </strong>

                                            &nbsp;

                                            {{
                                                $jurnal->catatan_validasi
                                            }}

                                        </div>

                                    @endif


                                    @if(
                                        $this->detailAbsensi->isEmpty()
                                    )

                                        <div
                                            class="text-muted text-center py-2"
                                        >
                                            Belum ada data absensi untuk jurnal ini.
                                        </div>

                                    @else

                                        <div class="table-responsive">

                                            <table
                                                class="table table-sm table-custom mb-0"
                                            >

                                                <thead>

                                                    <tr>

                                                        <th>
                                                            Nama Siswa
                                                        </th>

                                                        <th class="text-center">
                                                            Status
                                                        </th>

                                                        <th>
                                                            Detail
                                                        </th>

                                                    </tr>

                                                </thead>


                                                <tbody>

                                                    @foreach(
                                                        $this->detailAbsensi
                                                        as $absen
                                                    )

                                                        <tr>

                                                            <td>
                                                                {{
                                                                    $absen
                                                                        ->siswa
                                                                        ->nama_siswa
                                                                        ?? '-'
                                                                }}
                                                            </td>

                                                            <td class="text-center">
                                                                {{
                                                                    $absen
                                                                        ->keterangan
                                                                }}
                                                            </td>

                                                            <td>
                                                                {{
                                                                    $absen
                                                                        ->keteranganSiswa
                                                                        ->keterangan
                                                                        ?? '-'
                                                                }}
                                                            </td>

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

                            <td
                                colspan="{{
                                    $this->isGuru
                                        ? 7
                                        : 8
                                }}"
                                class="text-center text-muted py-5"
                            >
                                Belum ada riwayat jurnal yang cocok dengan filter ini.
                            </td>

                        </tr>

                    @endforelse

                </tbody>

            </table>

        </div>


        {{-- PAGINATION --}}
        @if($this->riwayat->hasPages())

            <div class="p-3 border-top">
                {{ $this->riwayat->links() }}
            </div>

        @endif

    </div>

</div>
```
