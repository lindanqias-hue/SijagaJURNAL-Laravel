<?php

use Livewire\Component;
use App\Models\Kelas;
use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Models\AbsensiSiswa;
use App\Models\KeteranganSiswa;
use App\Models\Siswa;
use App\Services\DispensasiJurnalService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public $id_kelas = '';
    public $tanggal;
    public $jam_ke = 1;
    public $materi = '';
    public $jumlah_tidak_hadir = 0;
    public $catatan = '';
    public string $cariSiswa = '';

    public $jadwalAktif = null;
    public $mapelAktif = '';

    public $jamMulaiPembelajaran = null;
    public $jamSelesaiPembelajaran = null;
    public $jamMulaiKe = null;
    public $jamSelesaiKe = null;

    public $editing = null;
    public $saved = false;
    public $isSaving = false;

    public $siswa = [];
    public $absensi = [];
    public array $absensiTerikat = [];

    public $keteranganTambahan = [];

    public bool $showAbsensiSiswa = false;
    public bool $showReview = false;

    protected const STATUS_BUTUH_KETERANGAN = ['Sakit', 'Izin', 'Dispensasi'];

    public function mount()
    {
        $this->tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        $this->mapelAktif = DB::table('pengguna')
            ->where('id_pengguna', session('id_pengguna'))
            ->value('mapel_diampu') ?? '';

        if (request()->has('edit')) {
            $id = request()->get('edit');

            $this->editing = Jurnal::where('id_jurnal', $id)
                ->where('id_guru', session('id_pengguna'))
                ->first();

            if ($this->editing) {
                // Hanya izinkan edit jika belum dikonfirmasi sekretaris
                if ($this->editing->status_konfirmasi_sekretaris === 'Dikonfirmasi') {
                    $this->editing = null;
                    return;
                }

                $this->id_kelas = $this->editing->id_kelas;
                $this->tanggal = Carbon::parse($this->editing->tanggal)->toDateString();
                $this->jam_ke = $this->editing->jam_ke;
                $this->materi = $this->editing->materi;
                $this->jumlah_tidak_hadir = $this->editing->jumlah_tidak_hadir ?? 0;
                $this->catatan = $this->editing->catatan ?? '';

                $this->sinkronkanJadwalTerpilih();
                $this->loadSiswa();
                $this->loadAbsensiLama();
                $this->loadDispensasiDisetujui();
            }
        } else {
            $this->loadJadwal();
        }
    }

    public function updatedTanggal(): void
    {
        $this->tanggal = $this->tanggalUntukForm();
        $this->loadJadwal();
    }

    private function tanggalUntukForm(): string
    {
        return $this->editing
            ? Carbon::parse($this->editing->tanggal, 'Asia/Jakarta')->toDateString()
            : Carbon::now('Asia/Jakarta')->toDateString();
    }

    public function loadJadwal()
    {
        $idGuru = session('id_pengguna');

        if (!$idGuru) {
            return;
        }

        $sekarang = Carbon::parse($this->tanggal, 'Asia/Jakarta');
        $hariInggris = $sekarang->format('l');

        $hariMap = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ];

        $hari = $hariMap[$hariInggris] ?? 'Senin';
        $jamSekarang = $sekarang->format('H:i:s');

        $jadwalHariIni = Jadwal::where('id_guru', $idGuru)
            ->where('hari', $hari)
            ->whereExists(function ($query) {
                $query->selectRaw('1')
                    ->from('siswa')
                    ->whereColumn('siswa.id_kelas', 'jadwal.id_kelas');
            })
            ->orderBy('jam_ke')
            ->get();

        if ($jadwalHariIni->isEmpty()) {
            $this->jadwalAktif = Jadwal::where('id_guru', $idGuru)
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('siswa')
                        ->whereColumn('siswa.id_kelas', 'jadwal.id_kelas');
                })
                ->orderBy('id_jadwal')
                ->first();
        } else {
            $this->jadwalAktif = $jadwalHariIni->first(
                fn($j) => $j->jam_mulai <= $jamSekarang && $j->jam_selesai >= $jamSekarang
            ) ?? $jadwalHariIni->first();
        }

        if (!$this->jadwalAktif) {
            return;
        }

        $this->id_kelas = $this->jadwalAktif->id_kelas;
        $this->jam_ke = $this->jadwalAktif->jam_ke;
        $this->sinkronkanJadwalTerpilih();
        $this->loadSiswa();
    }

    public function getKelasAktifProperty()
    {
        if (!$this->id_kelas) {
            return null;
        }

        return Kelas::where('id_kelas', $this->id_kelas)->first();
    }

    public function getKelasListProperty()
    {
        $idGuru = session('id_pengguna');
        if (!$idGuru) {
            return Kelas::all();
        }

        $idKelas = Jadwal::query()
            ->where('id_guru', $idGuru)
            ->pluck('id_kelas');

        return Kelas::query()
            ->whereIn('id_kelas', $idKelas)
            ->orderBy('nama_kelas')
            ->get();
    }

    public function getJamListProperty()
    {
        if (!$this->id_kelas || !session('id_pengguna')) {
            return Jadwal::query()->orderBy('jam_ke')->limit(12)->get(['jam_ke', 'jam_mulai', 'jam_selesai']);
        }

        return Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('jam_ke')
            ->get(['jam_ke', 'jam_mulai', 'jam_selesai']);
    }

    public function getSiswaTersaringProperty()
    {
        $pencarian = trim($this->cariSiswa);

        if ($pencarian === '') {
            return collect($this->siswa);
        }

        return collect($this->siswa)->filter(
            fn($siswa) => str_contains(
                mb_strtolower($siswa->nama_siswa),
                mb_strtolower($pencarian)
            )
        );
    }

    public function bukaAbsensiSiswa(): void
    {
        $this->cariSiswa = '';
        $this->showReview = false;
        $this->showAbsensiSiswa = true;
    }

    public function tutupAbsensiSiswa(): void
    {
        $this->showAbsensiSiswa = false;
    }

    public function updatedIdKelas(): void
    {
        $this->jumlah_tidak_hadir = 0;
        $this->loadSiswa();
        $this->sinkronkanJadwalTerpilih();
    }

    public function updatedJamKe(): void
    {
        $this->loadDispensasiDisetujui();
        $this->sinkronkanJadwalTerpilih();
    }

    private function findJadwalTerpilih(): ?Jadwal
    {
        if (!$this->id_kelas || !$this->jam_ke) {
            return Jadwal::where('id_guru', session('id_pengguna'))->first();
        }

        return Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('id_kelas', $this->id_kelas)
            ->where('jam_ke', $this->jam_ke)
            ->first() ?? Jadwal::where('id_guru', session('id_pengguna'))->first();
    }

    private function sinkronkanJadwalTerpilih(): void
    {
        $this->jadwalAktif = $this->findJadwalTerpilih();

        $this->jamMulaiKe = $this->jadwalAktif?->jam_ke;
        $this->jamMulaiPembelajaran = $this->jadwalAktif?->jam_mulai;
        $this->jamSelesaiKe = $this->jadwalAktif?->jam_ke;
        $this->jamSelesaiPembelajaran = $this->jadwalAktif?->jam_selesai;
    }

    public function loadSiswa(): void
    {
        if (!$this->id_kelas) {
            $this->siswa = Siswa::limit(36)->get();
        } else {
            $this->siswa = Siswa::where('id_kelas', $this->id_kelas)
                ->orderBy('id_siswa')
                ->get();
        }

        foreach ($this->siswa as $siswa) {
            if (!isset($this->absensi[$siswa->id_siswa])) {
                $this->absensi[$siswa->id_siswa] = 'Hadir';
            }
        }

        $this->loadDispensasiDisetujui();
    }

    public function loadDispensasiDisetujui(): void
    {
        if (!$this->id_kelas || !$this->tanggal || !$this->jam_ke) {
            $this->absensiTerikat = [];
            return;
        }

        try {
            $this->absensiTerikat = app(DispensasiJurnalService::class)
                ->statusTerikatUntukJurnal(
                    (int) $this->id_kelas,
                    (string) $this->tanggal,
                    (int) $this->jam_ke,
                    (int) session('id_pengguna')
                )
                ->all();
        } catch (\Throwable $e) {
            $this->absensiTerikat = [];
        }

        foreach ($this->absensiTerikat as $idSiswa => $data) {
            $this->absensi[$idSiswa] = $data['status'];
            $this->keteranganTambahan[$idSiswa] = $data['keterangan'] ?? '';
        }
    }

    public function loadAbsensiLama()
    {
        if (!$this->editing) {
            return;
        }

        $absensiLama = AbsensiSiswa::with('keteranganSiswa')
            ->where('id_jurnal', $this->editing->id_jurnal)
            ->get();

        foreach ($absensiLama as $absen) {
            $this->absensi[$absen->id_siswa] = $absen->keterangan === 'Tanpa Keterangan'
                ? 'Alpa'
                : $absen->keterangan;

            if ($absen->keteranganSiswa) {
                $this->keteranganTambahan[$absen->id_siswa] =
                    $absen->keteranganSiswa->keterangan;
            }
        }
    }

    public function save()
    {
        $this->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',
            'tanggal'  => 'required|date',
            'jam_ke'   => 'required|integer|min:1|max:12',
            'materi'   => 'required|string|max:500',
        ]);

        $jumlahHadir = collect($this->absensi)->filter(fn($status) => $status === 'Hadir')->count();
        $jumlahIzin = collect($this->absensi)->filter(fn($status) => $status === 'Izin')->count();
        $jumlahSakit = collect($this->absensi)->filter(fn($status) => $status === 'Sakit')->count();
        $jumlahAlpa = collect($this->absensi)->filter(fn($status) => $status === 'Alpa')->count();
        $jumlahDispensasi = collect($this->absensi)->filter(fn($status) => $status === 'Dispensasi')->count();

        $jumlahTidakHadir = $jumlahIzin + $jumlahSakit + $jumlahAlpa + $jumlahDispensasi;

        $data = [
            'id_guru'                       => session('id_pengguna'),
            'id_kelas'                      => $this->id_kelas,
            'tanggal'                       => $this->tanggal,
            'jam_ke'                        => $this->jam_ke,
            'materi'                        => $this->materi,
            'jumlah_hadir'                  => $jumlahHadir,
            'jumlah_tidak_hadir'            => $jumlahTidakHadir,
            'status_kehadiran_guru'         => 'Hadir',
            'catatan'                       => $this->catatan ?: null,
            'status_validasi'               => 'Menunggu',
            'status_konfirmasi_sekretaris' => 'Menunggu',
            'catatan_validasi'              => null,
        ];

        DB::transaction(function () use ($data): void {
            if ($this->editing) {
                $this->editing->update($data);
                $idJurnal = $this->editing->id_jurnal;
                AbsensiSiswa::where('id_jurnal', $idJurnal)->delete();
            } else {
                $jurnal = Jurnal::create($data);
                $idJurnal = $jurnal->id_jurnal;
            }

            $namaKelas = $this->kelasAktif->nama_kelas ?? '-';

            foreach ($this->absensi as $idSiswa => $statusSiswa) {
                $absensiSiswa = AbsensiSiswa::create([
                    'id_jurnal'  => $idJurnal,
                    'id_siswa'   => $idSiswa,
                    'keterangan' => $statusSiswa,
                ]);

                if (in_array($statusSiswa, self::STATUS_BUTUH_KETERANGAN, true)) {
                    $siswaData = collect($this->siswa)->firstWhere('id_siswa', $idSiswa);

                    KeteranganSiswa::create([
                        'id_absensi' => $absensiSiswa->id_absensi,
                        'id_siswa'   => $idSiswa,
                        'nama_siswa' => $siswaData->nama_siswa ?? '-',
                        'kelas'      => $namaKelas,
                        'status'     => $statusSiswa,
                        'keterangan' => trim($this->keteranganTambahan[$idSiswa] ?? ''),
                        'tanggal'    => $this->tanggal,
                    ]);
                }
            }
        });

        $this->saved = true;
        return redirect()->route('riwayat')->with('success', 'Jurnal mengajar berhasil disimpan.');
    }
};
?>

<div>
    <div class="mb-4">
        <a href="{{ route('riwayat') }}" class="text-decoration-none text-muted d-inline-block mb-2"
            style="font-size:13px;">
            &larr; Kembali ke Riwayat
        </a>

        <div class="page-title">
            {{ $editing ? 'Edit Jurnal Mengajar' : 'Input Jurnal Mengajar' }}
        </div>
    </div>

    <form wire:submit="save" style="width:100%; max-width:none;">
        <div class="form-section mb-4">
            <div class="form-section-title mb-3 fw-bold">Informasi Pembelajaran</div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-sm">Mata Pelajaran</label>
                    <input type="text" value="{{ $mapelAktif ?: '-' }}" readonly
                        class="form-control form-control-custom bg-light">
                </div>

                <div class="col-md-6">
                    <label class="form-label-sm">Kelas</label>
                    <select wire:model.live="id_kelas"
                        class="form-select form-control-custom @error('id_kelas') is-invalid @enderror">
                        <option value="">Pilih Kelas</option>
                        @foreach ($this->kelasList as $kelas)
                        <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>
                    @error('id_kelas')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Tanggal</label>
                    <div class="form-control form-control-custom bg-light text-muted">
                        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Jam Ke</label>
                    <select wire:model.live="jam_ke"
                        class="form-select form-control-custom @error('jam_ke') is-invalid @enderror">
                        @forelse ($this->jamList as $jam)
                        <option value="{{ $jam->jam_ke }}">
                            Jam ke-{{ $jam->jam_ke }}
                            ({{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }})
                        </option>
                        @empty
                        <option value="">Tidak ada jadwal</option>
                        @endforelse
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Jam Mulai</label>
                    <div class="form-control form-control-custom bg-light">
                        {{ $jamMulaiPembelajaran ? substr($jamMulaiPembelajaran, 0, 5) : '-' }}
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Jam Selesai</label>
                    <div class="form-control form-control-custom bg-light">
                        {{ $jamSelesaiPembelajaran ? substr($jamSelesaiPembelajaran, 0, 5) : '-' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="form-section mb-4">
            <div class="form-section-title mb-3 fw-bold">Pembelajaran</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label-sm">Materi Pembelajaran</label>
                    <input type="text" wire:model="materi" placeholder="Contoh: Pengenalan Dasar"
                        class="form-control form-control-custom @error('materi') is-invalid @enderror">
                    @error('materi')
                    <div class="text-danger small">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="form-section mb-4">
            <div class="form-section-title mb-3 fw-bold">Kehadiran & Absensi Siswa</div>
            <div class="mb-3">
                <div
                    class="d-flex flex-wrap justify-content-between align-items-center gap-3 p-3 border rounded-3 bg-light">
                    <div>
                        <div class="fw-semibold">Absensi Siswa (Sinkronisasi Otomatis Piket)</div>
                        <div class="small text-muted">{{ count($siswa) }} siswa terdaftar. Data kehadiran dikelola oleh
                            Petugas Piket.</div>
                    </div>
                    <button type="button" wire:click="bukaAbsensiSiswa" class="btn btn-outline-primary px-4">
                        Lihat Daftar Kehadiran Siswa
                    </button>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-success px-4" wire:loading.attr="disabled">
                Simpan Jurnal
            </button>
        </div>
    </form>

    @if($showAbsensiSiswa)
    <div class="modal show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Daftar Kehadiran Siswa (Dari Piket)</h5>
                    <button type="button" class="btn-close" wire:click="tutupAbsensiSiswa"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-secondary py-2 small" role="alert">
                        ℹ️ Status kehadiran siswa di bawah ini bersumber langsung dari rekapitulasi Petugas Piket dan
                        akan otomatis tersimpan ke dalam jurnal.
                    </div>

                    <div class="mb-3">
                        <input type="text" wire:model.live.debounce.300ms="cariSiswa" class="form-control"
                            placeholder="Cari nama siswa...">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%;">No</th>
                                    <th style="width: 40%;">Nama Siswa</th>
                                    <th style="width: 25%;">Status Kehadiran</th>
                                    <th style="width: 30%;">Keterangan / Catatan</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($this->siswaTersaring as $index => $s)
                                @php
                                $idS = $s->id_siswa;
                                $statusAktif = $absensi[$idS] ?? 'Hadir';

                                $badgeClass = match($statusAktif) {
                                'Hadir' => 'bg-success',
                                'Sakit' => 'bg-warning text-dark',
                                'Izin' => 'bg-info text-dark',
                                'Dispensasi' => 'bg-primary',
                                default => 'bg-danger'
                                };
                                @endphp
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $s->nama_siswa }}</div>
                                    </td>
                                    <td>
                                        <span class="badge {{ $badgeClass }} px-3 py-2 w-100" style="font-size: 12px;">
                                            {{ $statusAktif }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="small text-muted">
                                            {{ $keteranganTambahan[$idS] ?? '-' }}
                                        </span>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Tidak ada siswa ditemukan.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" wire:click="tutupAbsensiSiswa">Tutup</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>