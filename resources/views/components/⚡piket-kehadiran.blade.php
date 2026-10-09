<?php

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\PiketKehadiranSiswa;
use App\Models\Siswa;
use App\Services\GuruPiketAccessService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

new class extends Component
{
    public string $id_kelas = '';
    public string $tanggal;
    public int $jam_ke = 1;

    /** @var array<int, string> */
    public array $absensi = [];

    /** @var array<int, string> */
    public array $keterangan = [];

    public string $cariSiswa = '';

    public function mount(): void
    {
        abort_unless(session('role') === 'guru', 403);

        if (! session('id_pengguna')) {
            $this->redirectRoute('login');

            return;
        }

        if (! app(GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'))) {
            abort(403, 'Anda tidak memiliki hak akses piket hari ini.');
        }

        $this->tanggal = Carbon::now('Asia/Jakarta')->format('Y-m-d');
        $this->muatKelasPertama();
        $this->muatSiswa();
    }

    public function getBolehInputProperty(): bool
    {
        return session('role') === 'guru'
            && app(GuruPiketAccessService::class)->bertugasHariIni((int) session('id_pengguna'));
    }

    public function getKelasListProperty(): \Illuminate\Support\Collection
    {
        return Kelas::query()->orderBy('nama_kelas')->get();
    }

    /**
     * Kelas yang punya jadwal pada tanggal yang dipilih.
     */
    public function getKelasBerjadwalProperty(): \Illuminate\Support\Collection
    {
        $hari = $this->namaHari();

        return Kelas::query()
            ->whereExists(function ($query) use ($hari): void {
                $query->selectRaw('1')
                    ->from('jadwal')
                    ->whereColumn('jadwal.id_kelas', 'kelas.id_kelas')
                    ->where('jadwal.hari', $hari);
            })
            ->orderBy('nama_kelas')
            ->get();
    }

    public function getJadwalKelasProperty(): \Illuminate\Support\Collection
    {
        if (! $this->id_kelas) {
            return collect();
        }

        return Jadwal::query()
            ->where('id_kelas', $this->id_kelas)
            ->where('hari', $this->namaHari())
            ->orderBy('jam_ke')
            ->get();
    }

    public function getSiswaProperty(): \Illuminate\Support\Collection
    {
        if (! $this->id_kelas) {
            return collect();
        }

        $query = Siswa::query()
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('nama_siswa');

        $pencarian = trim($this->cariSiswa);

        if ($pencarian !== '') {
            $query->where('nama_siswa', 'like', '%'.$pencarian.'%');
        }

        return $query->get();
    }

    public function getRekapProperty(): array
    {
        $siswa = $this->siswa;

        return [
            'total' => $siswa->count(),
            'hadir' => $siswa->filter(fn (Siswa $s): bool => ($this->absensi[$s->id_siswa] ?? '') === 'Hadir')->count(),
            'izin' => $siswa->filter(fn (Siswa $s): bool => ($this->absensi[$s->id_siswa] ?? '') === 'Izin')->count(),
            'sakit' => $siswa->filter(fn (Siswa $s): bool => ($this->absensi[$s->id_siswa] ?? '') === 'Sakit')->count(),
            'alpa' => $siswa->filter(fn (Siswa $s): bool => in_array($this->absensi[$s->id_siswa] ?? '', ['Alpa', 'Tanpa Keterangan'], true))->count(),
        ];
    }

    public function butuhKeterangan(int|string $idSiswa): bool
    {
        return in_array(
            $this->absensi[$idSiswa] ?? '',
            ['Izin', 'Sakit', 'Alpa', 'Tanpa Keterangan'],
            true
        );
    }

    public function updatedIdKelas(): void
    {
        $this->cariSiswa = '';
        $this->jam_ke = 1;
        $this->muatSiswa();
    }

    public function updatedJamKe(): void
    {
        $this->muatSiswa();
    }

    public function updatedTanggal(): void
    {
        $this->jam_ke = 1;
        $this->muatSiswa();
    }

    public function setSemuaHadir(): void
    {
        foreach ($this->siswa as $siswa) {
            $this->absensi[$siswa->id_siswa] = 'Hadir';
            unset($this->keterangan[$siswa->id_siswa]);
        }
    }

    public function simpan(): void
    {
        if (! $this->bolehInput) {
            return;
        }

        $statusDiizinkan = PiketKehadiranSiswa::STATUS;

        $data = [];

        foreach ($this->siswa as $siswa) {
            $status = $this->absensi[$siswa->id_siswa] ?? 'Hadir';

            if (! in_array($status, $statusDiizinkan, true)) {
                continue;
            }

            $keterangan = trim($this->keterangan[$siswa->id_siswa] ?? '');

            if ($status !== 'Hadir' && $keterangan === '') {
                $this->addError(
                    'keterangan.'.$siswa->id_siswa,
                    "Keterangan wajib diisi untuk {$siswa->nama_siswa} ({$status})."
                );

                return;
            }

            $data[(int) $siswa->id_siswa] = [
                'status' => $status,
                'keterangan' => $keterangan !== '' ? $keterangan : null,
            ];
        }

        if ($data === []) {
            return;
        }

        DB::transaction(function () use ($data): void {
            foreach ($data as $idSiswa => $baris) {
                PiketKehadiranSiswa::updateOrCreate(
                    [
                        'id_kelas' => (int) $this->id_kelas,
                        'id_siswa' => $idSiswa,
                        'tanggal' => $this->tanggal,
                        'jam_ke' => $this->jam_ke,
                    ],
                    [
                        'status' => $baris['status'],
                        'keterangan' => $baris['keterangan'],
                        'id_guru_piket' => (int) session('id_pengguna'),
                        'sumber' => 'Piket',
                    ]
                );
            }
        });

        session()->flash('success', 'Kehadiran siswa berhasil disimpan.');
    }

    private function namaHari(): ?string
    {
        if (! $this->tanggal) {
            return null;
        }

        $hari = Carbon::parse($this->tanggal, 'Asia/Jakarta')->format('l');

        return [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ][$hari] ?? null;
    }

    private function muatKelasPertama(): void
    {
        $pertama = $this->kelasBerjadwal->first();

        if ($pertama) {
            $this->id_kelas = (string) $pertama->id_kelas;

            return;
        }

        $pertamaKelas = Kelas::query()->orderBy('nama_kelas')->first();

        if ($pertamaKelas) {
            $this->id_kelas = (string) $pertamaKelas->id_kelas;
        }
    }

    private function muatSiswa(): void
    {
        if (! $this->id_kelas || ! $this->tanggal) {
            $this->absensi = [];
            $this->keterangan = [];

            return;
        }

        $tersimpan = PiketKehadiranSiswa::untukKelas(
            (int) $this->id_kelas,
            $this->tanggal,
            $this->jam_ke
        );

        $this->absensi = [];
        $this->keterangan = [];

        foreach ($this->siswa as $siswa) {
            $this->absensi[$siswa->id_siswa] = $tersimpan[(int) $siswa->id_siswa]['status'] ?? 'Hadir';
            $this->keterangan[$siswa->id_siswa] = $tersimpan[(int) $siswa->id_siswa]['keterangan'] ?? '';
        }
    }
};
?>

<div>
    {{-- HEADER --}}
    <div class="role-page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <div class="page-title">Kehadiran Siswa</div>
            <div class="role-page-description">
                Catat kehadiran siswa per jam. Data ini tampil read-only di form input jurnal guru.
            </div>
        </div>
        <a href="{{ route('guru-piket') }}" class="btn btn-outline-secondary">&larr; Kembali ke Piket</a>
    </div>
    <div class="role-page-actions mb-3"></div>

    @unless ($this->bolehInput)
    <div class="alert alert-warning mb-4" role="status">
        Anda tidak memiliki hak akses piket hari ini.
    </div>
    @endunless

    @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="status">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
    @endif

    {{-- FILTER --}}
    <div class="card-custom p-3 mb-3">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" for="piket-kelas">Kelas</label>
                <select id="piket-kelas" class="form-select" wire:model.live="id_kelas">
                    <option value="">-- Pilih Kelas --</option>
                    @foreach ($this->kelasBerjadwal as $kelas)
                    <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                    @endforeach
                    @foreach ($this->kelasList as $kelas)
                    <option value="{{ $kelas->id_kelas }}" @disabled($this->kelasBerjadwal->contains('id_kelas', $kelas->id_kelas))>
                        {{ $kelas->nama_kelas }} (tidak ada jadwal hari ini)
                    </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" for="piket-tanggal">Tanggal</label>
                <input id="piket-tanggal" type="date" class="form-control" wire:model.live="tanggal">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" for="piket-jam">Jam Ke</label>
                <select id="piket-jam" class="form-select" wire:model.live="jam_ke">
                    @forelse ($this->jadwalKelas as $jadwal)
                    <option value="{{ $jadwal->jam_ke }}">Jam ke-{{ $jadwal->jam_ke }}
                        ({{ substr($jadwal->jam_mulai, 0, 5) }}&ndash;{{ substr($jadwal->jam_selesai, 0, 5) }})</option>
                    @empty
                    <option value="1">Jam ke-1</option>
                    @endforelse
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" class="btn btn-outline-secondary w-100" wire:click="setSemuaHadir">
                    Semua Hadir
                </button>
            </div>
        </div>
    </div>

    @error('keterangan')
    <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    {{-- REKAP --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="stat-card"><div class="stat-value">{{ $this->rekap['total'] }}</div><div class="text-muted small">Total Siswa</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card"><div class="stat-value" style="color:#16a34a;">{{ $this->rekap['hadir'] }}</div><div class="text-muted small">Hadir</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card"><div class="stat-value" style="color:#ca8a04;">{{ $this->rekap['izin'] }}</div><div class="text-muted small">Izin</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-card"><div class="stat-value" style="color:#6d28d9;">{{ $this->rekap['sakit'] }}</div><div class="text-muted small">Sakit</div></div>
        </div>
    </div>

    {{-- DAFTAR SISWA --}}
    <div class="card-custom overflow-hidden">
        <div class="card-header-custom d-flex flex-wrap align-items-center justify-content-between gap-2">
            <span>Daftar Siswa · {{ $this->rekap['alpa'] }} tanpa keterangan</span>
            <input type="search" class="form-control form-control-sm" style="max-width:240px"
                placeholder="Cari siswa..." wire:model.live.debounce.300ms="cariSiswa">
        </div>

        <div class="p-3 border-bottom">
            <input type="search" class="form-control" placeholder="Cari nama siswa..."
                aria-label="Cari siswa" wire:model.live.debounce.300ms="cariSiswa">
        </div>

        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Nama Siswa</th>
                        <th style="width:220px" class="text-center">Status</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->siswa as $siswa)
                    <tr wire:key="piket-kehadiran-{{ $siswa->id_siswa }}">
                        <td>{{ $siswa->nama_siswa }}</td>
                        <td class="text-center">
                            <select class="form-select form-select-sm" wire:model.live="absensi.{{ $siswa->id_siswa }}">
                                @foreach (\App\Models\PiketKehadiranSiswa::STATUS as $status)
                                <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm"
                                placeholder="{{ $this->butuhKeterangan($siswa->id_siswa) ? 'Wajib diisi' : 'Opsional' }}"
                                wire:model.blur="keterangan.{{ $siswa->id_siswa }}">
                            @error('keterangan.'.$siswa->id_siswa)
                            <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted py-4">
                            Belum ada siswa di kelas ini.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->bolehInput)
        <div class="p-3 border-top text-end">
            <button type="button" class="btn btn-app-primary px-4" wire:click="simpan" wire:loading.attr="disabled">
                Simpan Kehadiran Siswa
            </button>
        </div>
        @endif
    </div>
</div>