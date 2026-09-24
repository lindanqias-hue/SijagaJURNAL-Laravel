<?php

use Livewire\Component;
use App\Models\Kelas;
use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Models\AbsensiSiswa;
use App\Models\KeteranganSiswa;
use App\Models\Siswa;
use App\Services\KehadiranGuruService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;   // <-- baru, dipakai loadJadwal()

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

    // Keterangan bebas untuk siswa berstatus Sakit / Izin / Dispensasi,
    // di-key per id_siswa. (Dulu cuma untuk Dispensasi, sekarang berlaku
    // untuk ketiga status tsb — lihat App\Models\KeteranganSiswa)
    public $keteranganTambahan = [];

    // Status yang wajib diisi keterangannya
    protected const STATUS_BUTUH_KETERANGAN = ['Sakit', 'Izin', 'Dispensasi'];

   public function mount()
{
    // Tanggal selalu otomatis mengikuti hari ini
    $this->tanggal = now()->format('Y-m-d');

    if (request()->has('edit')) {

        $id = request()->get('edit');

        $this->editing = Jurnal::where('id_jurnal', $id)
            ->where('id_guru', session('id_pengguna'))
            ->first();

        if ($this->editing) {

            if (
                !in_array($this->editing->status_validasi, ['Menunggu', 'Divalidasi'], true) ||
                $this->editing->status_konfirmasi_sekretaris !== 'Menunggu'
            ) {
                $this->editing = null;
                return;
            }

            $this->id_kelas = $this->editing->id_kelas;

            $this->tanggal = $this->editing->tanggal->format('Y-m-d');

            $this->jam_ke = $this->editing->jam_ke;
            $this->materi = $this->editing->materi;

            $this->jumlah_tidak_hadir =
                $this->editing->jumlah_tidak_hadir ?? 0;

            $this->catatan =
                $this->editing->catatan ?? '';

            // Ambil mapel guru
            $this->mapelAktif = DB::table('pengguna')
                ->where('id_pengguna', session('id_pengguna'))
                ->value('mapel_diampu') ?? '';

            // Load siswa
            $this->sinkronkanJadwalTerpilih();
            $this->loadSiswa();

            // PENTING: pulihkan status & keterangan yang sudah
            // tersimpan sebelumnya untuk jurnal ini, supaya saat edit
            // tidak balik ke default "Hadir" semua.
            $this->loadAbsensiLama();
        }

    } else {
        $this->mapelAktif = DB::table('pengguna')
            ->where('id_pengguna', session('id_pengguna'))
            ->value('mapel_diampu') ?? '';

        $this->loadJadwal();
    }
}


/**
 * Mengambil jadwal sesuai:
 * - guru yang sedang login
 * - hari sekarang
 * - jam sekarang
 */
public function loadJadwal()
{
    $idGuru = session('id_pengguna');

    if (!$idGuru) {
        return;
    }

    // Konversi nama hari Inggris -> Indonesia
    $hariInggris = now()->format('l');

    $hariMap = [
        'Monday' => 'Senin',
        'Tuesday' => 'Selasa',
        'Wednesday' => 'Rabu',
        'Thursday' => 'Kamis',
        'Friday' => 'Jumat',
        'Saturday' => 'Sabtu',
        'Sunday' => 'Minggu',
    ];

    $hari = $hariMap[$hariInggris] ?? null;

    if (!$hari) {
        return;
    }

    $jamSekarang = now()->format('H:i:s');

       // Ambil semua jadwal guru ini KHUSUS untuk hari ini saja
    $jadwalHariIni = Jadwal::where('id_guru', $idGuru)
        ->where('hari', $hari)
        ->orderBy('jam_ke')
        ->get();

    // 1. Cari jadwal yang sedang berlangsung tepat sekarang
    $this->jadwalAktif = $jadwalHariIni->first(
        fn ($j) => $j->jam_mulai <= $jamSekarang && $j->jam_selesai >= $jamSekarang
    );

    // 2. Kalau tidak ada jam yang sedang berlangsung,
    // ambil jadwal HARI INI yang jam mulainya paling dekat
    if (!$this->jadwalAktif && $jadwalHariIni->isNotEmpty()) {

        $nowTime = Carbon::createFromFormat('H:i:s', $jamSekarang);

        $this->jadwalAktif = $jadwalHariIni
            ->sortBy(function ($j) use ($nowTime) {
                return abs(
                    Carbon::createFromFormat('H:i:s', $j->jam_mulai)
                        ->diffInSeconds($nowTime)
                );
            })
            ->first();
    }

    if (!$this->jadwalAktif) {
        return;
    }

    // Otomatis isi kelas
    $this->id_kelas = $this->jadwalAktif->id_kelas;

    // Jam mulai
    $this->jamMulaiKe = $this->jadwalAktif->jam_ke;
    $this->jamMulaiPembelajaran = $this->jadwalAktif->jam_mulai;

    // Default selesai = jadwal yang sedang aktif
    $this->jamSelesaiKe = $this->jadwalAktif->jam_ke;
    $this->jamSelesaiPembelajaran = $this->jadwalAktif->jam_selesai;

    /*
     * Cari jam berikutnya yang:
     * - guru sama
     * - kelas sama
     * - jam ke berikutnya
     * - waktu mulai sama dengan waktu selesai sebelumnya
     *
     * Contoh:
     * Jam 5 = 10:00 - 10:35
     * Jam 6 = 10:35 - 11:10
     *
     * Maka hasil:
     * Mulai  = Jam ke-5, 10:00
     * Selesai = Jam ke-6, 11:10
     */

    $jadwalBerikutnya = Jadwal::where('id_guru', $idGuru)
        ->where('id_kelas', $this->jadwalAktif->id_kelas)
        ->where('hari', $hari)
        ->where('jam_ke', $this->jadwalAktif->jam_ke + 1)
        ->where('jam_mulai', $this->jadwalAktif->jam_selesai)
        ->first();

    // Kalau ada jam berikutnya yang menyambung, lanjutkan
    while ($jadwalBerikutnya) {

        $this->jamSelesaiKe = $jadwalBerikutnya->jam_ke;
        $this->jamSelesaiPembelajaran = $jadwalBerikutnya->jam_selesai;

        // Cari jam berikutnya lagi
        $jadwalBerikutnya = Jadwal::where('id_guru', $idGuru)
            ->where('id_kelas', $this->jadwalAktif->id_kelas)
            ->where('hari', $hari)
            ->where('jam_ke', $jadwalBerikutnya->jam_ke + 1)
            ->where('jam_mulai', $this->jamSelesaiPembelajaran)
            ->first();
    }

    // Ambil mapel guru
    $this->mapelAktif = DB::table('pengguna')
        ->where('id_pengguna', $idGuru)
        ->value('mapel_diampu') ?? '';

    // Ambil siswa sesuai kelas
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
        $hari = $this->namaHariUntukTanggal();

        if (!$hari || !session('id_pengguna')) {
            return collect();
        }

        $idKelas = Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('hari', $hari)
            ->pluck('id_kelas');

        return Kelas::query()
            ->whereIn('id_kelas', $idKelas)
            ->orderBy('nama_kelas')
            ->get();
    }

    public function getJamListProperty()
    {
        $hari = $this->namaHariUntukTanggal();

        if (!$hari || !session('id_pengguna')) {
            return collect();
        }

        return Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('hari', $hari)
            ->orderBy('jam_ke')
            ->get(['jam_ke', 'jam_mulai', 'jam_selesai']);
    }


    public function getTotalSiswaProperty()
    {
        if (!$this->id_kelas) {
            return 0;
        }

        return Kelas::where('id_kelas', $this->id_kelas)
            ->value('jumlah_siswa') ?? 0;
    }


    public function getSiswaTersaringProperty()
    {
        $pencarian = trim($this->cariSiswa);

        if ($pencarian === '') {
            return $this->siswa;
        }

        return $this->siswa->filter(
            fn (Siswa $siswa) => str_contains(
                mb_strtolower($siswa->nama_siswa),
                mb_strtolower($pencarian)
            )
        );
    }

    public function butuhKeterangan(int|string $idSiswa): bool
    {
        return in_array(
            $this->absensi[$idSiswa] ?? '',
            self::STATUS_BUTUH_KETERANGAN,
            true
        );
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

    private function namaHariUntukTanggal(): ?string
    {
        $hariInggris = Carbon::parse($this->tanggal)->format('l');

        return [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu',
        ][$hariInggris] ?? null;
    }

    private function findJadwalTerpilih(): ?Jadwal
    {
        $hari = $this->namaHariUntukTanggal();

        if (!$hari || !$this->id_kelas || !$this->jam_ke) {
            return null;
        }

        return Jadwal::query()
            ->where('id_guru', session('id_pengguna'))
            ->where('id_kelas', $this->id_kelas)
            ->where('hari', $hari)
            ->where('jam_ke', $this->jam_ke)
            ->first();
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
            $this->siswa = [];
            $this->absensi = [];
            return;
        }

        $this->siswa = Siswa::where('id_kelas', $this->id_kelas)
            ->orderBy('id_siswa')
            ->get();

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
            return;
        }

        $dispensasi = DB::table('dispensasi')
            ->where('id_kelas', $this->id_kelas)
            ->where('tanggal', $this->tanggal)
            ->where('status', 'Disetujui')
            ->where(function ($query) {
                $query->where('jenis_dispensasi', 'Sehari Penuh')
                    ->orWhere(function ($q) {
                        $q->where('jenis_dispensasi', 'Per Jam')
                            ->where('jam_ke_mulai', '<=', $this->jam_ke)
                            ->where('jam_ke_selesai', '>=', $this->jam_ke);
                    })
                    ->orWhere(function ($q) {
                        $q->where('jenis_dispensasi', 'Per Mapel')
                            ->where('id_guru', session('id_pengguna'))
                            ->where('jam_ke_mulai', $this->jam_ke);
                    });
            })
            ->get();

        foreach ($dispensasi as $data) {
            $this->absensi[$data->id_siswa] = 'Dispensasi';
            $this->keteranganTambahan[$data->id_siswa] = $data->alasan;
        }
    }

/**
 * Dipanggil hanya saat mode edit. Mengambil status absensi &
 * keterangan yang SUDAH TERSIMPAN untuk jurnal ini, lalu menimpa
 * default "Hadir" dari loadSiswa()/loadDispensasiDisetujui() di atas.
 *
 * Tanpa ini, form edit selalu terlihat seolah semua siswa "Hadir",
 * padahal datanya masih ada di absensi_siswa & keterangan_siswa.
 */
public function loadAbsensiLama()
{
    if (!$this->editing) {
        return;
    }

    $absensiLama = AbsensiSiswa::with('keteranganSiswa')
        ->where('id_jurnal', $this->editing->id_jurnal)
        ->get();

    foreach ($absensiLama as $absen) {

        $this->absensi[$absen->id_siswa] = $absen->keterangan;

        if ($absen->keteranganSiswa) {
            $this->keteranganTambahan[$absen->id_siswa] =
                $absen->keteranganSiswa->keterangan;
        }
    }
}
public function save()
{

    // 1. Validasi data terlebih dahulu
    $this->validate([
        'id_kelas' => 'required|exists:kelas,id_kelas',
        'tanggal' => 'required|date',
        'jam_ke' => 'required|integer|min:1|max:12',
        'materi' => 'required|string',
        'absensi.*' => 'required|in:Hadir,Izin,Sakit,Alpa,Dispensasi,Tanpa Keterangan',
    ], [
        'id_kelas.required' => 'Kelas wajib dipilih.',
        'id_kelas.exists' => 'Kelas tidak ditemukan.',
        'materi.required' => 'Materi wajib diisi.',
    ]);

    if ($this->editing) {
        $this->editing = Jurnal::where('id_jurnal', $this->editing->id_jurnal)
            ->where('id_guru', session('id_pengguna'))
            ->whereIn('status_validasi', ['Menunggu', 'Divalidasi'])
            ->where('status_konfirmasi_sekretaris', 'Menunggu')
            ->first();

        if (!$this->editing) {
            $this->addError(
                'editing',
                'Jurnal tidak dapat diubah karena sudah tidak tersedia untuk diedit.'
            );

            return;
        }
    }

    $jadwalTerpilih = $this->findJadwalTerpilih();

    if (!$jadwalTerpilih) {
        $this->addError(
            'id_kelas',
            'Kelas dan jam yang dipilih tidak termasuk jadwal mengajar Anda pada tanggal ini.'
        );

        return;
    }

    $this->jadwalAktif = $jadwalTerpilih;
    $this->jamMulaiKe = $jadwalTerpilih->jam_ke;
    $this->jamMulaiPembelajaran = $jadwalTerpilih->jam_mulai;
    $this->jamSelesaiKe = $jadwalTerpilih->jam_ke;
    $this->jamSelesaiPembelajaran = $jadwalTerpilih->jam_selesai;
    $jurnalDuplikat = Jurnal::query()
        ->where('id_guru', session('id_pengguna'))
        ->where('id_kelas', $this->id_kelas)
        ->whereDate('tanggal', $this->tanggal)
        ->where('jam_ke', $this->jam_ke)
        ->when(
            $this->editing,
            fn ($query) => $query->where('id_jurnal', '!=', $this->editing->id_jurnal)
        )
        ->exists();

    if ($jurnalDuplikat) {
        $this->addError('jam_ke', 'Jurnal untuk kelas dan jam ini sudah tercatat.');

        return;
    }

    // Ambil ulang siswa dari kelas yang dipilih, lalu abaikan ID dari state
    // Livewire yang bukan bagian dari kelas tersebut.
    $this->loadSiswa();
    $idSiswaValid = $this->siswa->pluck('id_siswa')->map(
        fn ($idSiswa) => (string) $idSiswa
    )->all();
    $this->absensi = collect($this->absensi)
        ->only($idSiswaValid)
        ->all();

    // Pastikan semua siswa memiliki status absensi,
    // dan keterangan wajib diisi untuk Sakit/Izin/Dispensasi
    foreach ($this->siswa as $siswa) {
        $statusSiswa = $this->absensi[$siswa->id_siswa] ?? null;

        if (!$statusSiswa) {
            $this->addError(
                'absensi',
                'Status kehadiran semua siswa harus diisi.'
            );
            return;
        }

        if (
            in_array($statusSiswa, self::STATUS_BUTUH_KETERANGAN, true) &&
            trim($this->keteranganTambahan[$siswa->id_siswa] ?? '') === ''
        ) {
            $this->addError(
                'keteranganTambahan.'.$siswa->id_siswa,
                "Keterangan untuk {$siswa->nama_siswa} ({$statusSiswa}) wajib diisi."
            );
            return;
        }
    }

    // Hitung jumlah berdasarkan status siswa
    $jumlahHadir = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Hadir')
        ->count();

    $jumlahIzin = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Izin')
        ->count();

    $jumlahSakit = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Sakit')
        ->count();

    $jumlahAlpa = collect($this->absensi)
    ->filter(fn ($status) => $status === 'Alpa')
    ->count();

$jumlahDispensasi = collect($this->absensi)
    ->filter(fn ($status) => $status === 'Dispensasi')
    ->count();

    $jumlahTanpaKeterangan = collect($this->absensi)
        ->filter(fn ($status) => $status === 'Tanpa Keterangan')
        ->count();

$jumlahTidakHadir =
    $jumlahIzin +
    $jumlahSakit +
    $jumlahAlpa +
    $jumlahDispensasi +
    $jumlahTanpaKeterangan;

    $data = [
        'id_guru' => session('id_pengguna'),
        'id_kelas' => $this->id_kelas,
        'tanggal' => $this->tanggal,
        'jam_ke' => $this->jam_ke,
        'materi' => $this->materi,
        'jumlah_hadir' => $jumlahHadir,
        'jumlah_tidak_hadir' => $jumlahTidakHadir,
        'status_kehadiran_guru' => 'Hadir',
        'catatan' => $this->catatan ?: null,
        'status_validasi' => 'Divalidasi',
        'id_validator' => null,
        'tanggal_validasi' => null,
        'catatan_validasi' => 'Validasi otomatis: jadwal, guru, kelas, tanggal, dan jam sesuai.',
    ];

    DB::transaction(function () use ($data): void {
        // Simpan atau update jurnal
        if ($this->editing) {
            $this->editing->update($data);

            $idJurnal = $this->editing->id_jurnal;

            // Hapus absensi lama jika sedang edit
            AbsensiSiswa::where('id_jurnal', $idJurnal)->delete();
        } else {
            $jurnal = Jurnal::create($data);
            $idJurnal = $jurnal->id_jurnal;
        }

        // Nama kelas dipakai sebagai snapshot di keterangan_siswa
        $namaKelas = $this->kelasAktif->nama_kelas ?? '-';

        // Simpan absensi setiap siswa yang sudah diverifikasi berasal dari kelas.
        foreach ($this->absensi as $idSiswa => $statusSiswa) {
            $absensiSiswa = AbsensiSiswa::create([
                'id_jurnal' => $idJurnal,
                'id_siswa' => $idSiswa,
                'keterangan' => $statusSiswa,
            ]);

            if (in_array($statusSiswa, self::STATUS_BUTUH_KETERANGAN, true)) {
                $siswaData = $this->siswa->firstWhere('id_siswa', $idSiswa);

                KeteranganSiswa::create([
                    'id_absensi' => $absensiSiswa->id_absensi,
                    'id_siswa' => $idSiswa,
                    'nama_siswa' => $siswaData->nama_siswa ?? '-',
                    'kelas' => $namaKelas,
                    'status' => $statusSiswa,
                    'keterangan' => trim($this->keteranganTambahan[$idSiswa] ?? ''),
                    'tanggal' => $this->tanggal,
                ]);
            }
        }
    });

    app(KehadiranGuruService::class)->statusUntukJadwal(
        $jadwalTerpilih,
        Carbon::now('Asia/Jakarta'),
        Carbon::parse($this->tanggal, 'Asia/Jakarta')
    );

    $this->saved = true;
}
};
?>

<div>

    <div class="mb-4">

        <a
            href="{{ route('riwayat') }}"
            class="text-decoration-none text-muted d-inline-block mb-2"
            style="font-size:13px;"
        >
            &larr; Kembali ke Riwayat
        </a>

        <div class="page-title">
            {{ $editing ? 'Edit Jurnal Mengajar' : 'Input Jurnal Mengajar' }}
        </div>

        <div
            class="text-muted mt-1"
            style="font-size:13px;"
        >
            {{ $editing
                ? 'Mengedit jurnal ID #' . $editing->id_jurnal
                : 'Catat aktivitas pembelajaran Anda hari ini.'
            }}
        </div>

    </div>


    <form
    wire:submit="save"
    style="width:100%; max-width:none;"
>

        {{-- INFORMASI PEMBELAJARAN --}}
        <div class="form-section">
            <div class="form-section-title">Informasi Pembelajaran</div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-sm">Mata Pelajaran</label>
                    <input type="text" value="{{ $mapelAktif ?: '-' }}" readonly class="form-control form-control-custom readonly-field">
                </div>

                <div class="col-md-6">
                    <label class="form-label-sm">Kelas</label>
                    <select wire:model.live="id_kelas" class="form-select form-control-custom @error('id_kelas') is-invalid @enderror">
                        <option value="">Pilih Kelas</option>
                        @foreach ($this->kelasList as $kelas)
                            <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>
                    @error('id_kelas')
                        <div class="text-danger" style="font-size:12px;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Tanggal</label>
                    <div class="form-control form-control-custom bg-light">
                        {{ \Carbon\Carbon::parse($tanggal)->translatedFormat('d F Y') }}
                    </div>
                </div>

                <div class="col-md-3">
                    <label class="form-label-sm">Jam Ke</label>
                    <select wire:model.live="jam_ke" class="form-select form-control-custom @error('jam_ke') is-invalid @enderror">
                        @forelse ($this->jamList as $jam)
                            <option value="{{ $jam->jam_ke }}">Jam ke-{{ $jam->jam_ke }} ({{ substr($jam->jam_mulai, 0, 5) }}–{{ substr($jam->jam_selesai, 0, 5) }})</option>
                        @empty
                            <option value="">Tidak ada jadwal pada hari ini</option>
                        @endforelse
                    </select>
                    @error('jam_ke')
                        <div class="text-danger" style="font-size:12px;">{{ $message }}</div>
                    @enderror
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

        {{-- PEMBELAJARAN --}}
        <div class="form-section">
            <div class="form-section-title">Pembelajaran</div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label-sm">Materi Pembelajaran</label>
                    <input type="text" wire:model="materi" placeholder="Contoh: Pengenalan DDL dan DML pada Basis Data" class="form-control form-control-custom @error('materi') is-invalid @enderror">
                    @error('materi')
                        <div class="text-danger" style="font-size:12px;">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12">
                    <label class="form-label-sm">Metode Pembelajaran</label>
                    <textarea wire:model="catatan" class="form-control form-control-custom" rows="3" placeholder="Contoh: diskusi kelompok, praktik, atau tugas lanjutan..."></textarea>
                </div>
            </div>
        </div>

        {{-- KEHADIRAN --}}
<div class="form-section">

    <div class="form-section-title">
        Kehadiran & Absensi Siswa
    </div>

    <div class="alert alert-info mb-4">
    Kehadiran guru ditentukan otomatis: jurnal yang berhasil dikirim tercatat sebagai <strong>Hadir</strong>.
    Jadwal yang selesai tanpa jurnal akan otomatis tercatat sebagai <strong>Tidak Hadir</strong>.
</div>


    {{-- DAFTAR SISWA --}}
        <div class="mb-3">

            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
                <label class="form-label-sm mb-0">Daftar Kehadiran Siswa per Mata Pelajaran</label>
                <input type="search" wire:model.live.debounce.300ms="cariSiswa" class="form-control" style="max-width:280px" placeholder="Cari nama siswa...">
            </div>

            <div class="table-responsive border rounded">

                <table class="table table-hover mb-0 align-middle">

                    <thead class="table-light">
                        <tr>

                            <th class="text-center" style="width:60px;">
                                No
                            </th>

                            <th>
                                Nama Siswa
                            </th>

                            <th class="text-center" style="width:220px;">
                                Status Kehadiran
                            </th>

                        </tr>
                    </thead>

                    <tbody>

                        @forelse ($this->siswaTersaring as $index => $dataSiswa)

                            <tr wire:key="siswa-{{ $dataSiswa->id_siswa }}">

                                <td class="text-center">
                                    {{ $index + 1 }}
                                </td>

                                <td>
                                    <span class="fw-semibold">
                                        {{ $dataSiswa->nama_siswa }}
                                    </span>
                                </td>

                                <td>

                                    <select
                                        wire:model.live="absensi.{{ $dataSiswa->id_siswa }}"
                                        class="form-select"
                                    >

                                        <option value="Hadir">
                                            Hadir
                                        </option>

                                        <option value="Izin">
                                            Izin
                                        </option>

                                        <option value="Sakit">
                                            Sakit
                                        </option>

                                        <option value="Alpa">
                                            Alpa
                                        </option>

                                        <option value="Dispensasi">
                                            Dispensasi
                                        </option>

                                        <option value="Tanpa Keterangan">
                                            Tanpa Keterangan
                                        </option>

                                    </select>


                                    {{-- KETERANGAN (Sakit / Izin / Dispensasi) --}}
                                    @if ($this->butuhKeterangan($dataSiswa->id_siswa))
                                        <input
                                            type="text"
                                            wire:model.live="keteranganTambahan.{{ $dataSiswa->id_siswa }}"
                                            class="form-control mt-2 @error('keteranganTambahan.'.$dataSiswa->id_siswa) is-invalid @enderror"
                                            placeholder="Keterangan {{ strtolower($absensi[$dataSiswa->id_siswa] ?? '') }}..."
                                        >

                                        @error('keteranganTambahan.'.$dataSiswa->id_siswa)
                                            <div class="invalid-feedback d-block">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="3"
                                    class="text-center text-muted py-4"
                                >
                                    Data siswa belum ditemukan.
                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>

        </div>


        {{-- REKAP OTOMATIS --}}
        @php

            $totalHadir = collect($absensi)
                ->filter(fn ($status) => $status === 'Hadir')
                ->count();

            $totalIzin = collect($absensi)
                ->filter(fn ($status) => $status === 'Izin')
                ->count();

            $totalSakit = collect($absensi)
                ->filter(fn ($status) => $status === 'Sakit')
                ->count();

            $totalAlpa = collect($absensi)
                ->filter(fn ($status) => $status === 'Alpa')
                ->count();

            $totalDispensasi = collect($absensi)
                ->filter(fn ($status) => $status === 'Dispensasi')
                ->count();

            $totalTidakHadir =
                $totalIzin +
                $totalSakit +
                $totalAlpa +
                $totalDispensasi;

        @endphp


        {{-- TOTAL --}}
        <div class="row g-3 mb-3">

            <div class="col-md-6">

                <div class="info-box-total">

                    <div>
                        <div class="text-muted small">
                            TOTAL HADIR
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalHadir }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#9989;
                    </div>

                </div>

            </div>


            <div class="col-md-6">

                <div class="info-box-total">

                    <div>
                        <div class="text-muted small">
                            TOTAL TIDAK HADIR
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalTidakHadir }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#10060;
                    </div>

                </div>

            </div>

        </div>


        {{-- DETAIL REKAP --}}
        <div class="row g-3">

            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            IZIN
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalIzin }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#128203;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            SAKIT
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalSakit }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#129298;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            ALPA
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalAlpa }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#10067;
                    </div>
                </div>

            </div>


            <div class="col-md-3">

                <div class="info-box-total">
                    <div>
                        <div class="text-muted small">
                            DISPENSASI
                        </div>

                        <div class="fw-bold fs-5">
                            {{ $totalDispensasi }} siswa
                        </div>
                    </div>

                    <div style="font-size:24px;">
                        &#128196;
                    </div>
                </div>

            </div>

        </div>

    {{-- TOMBOL KONFIRMASI --}}
    <div class="d-flex justify-content-center gap-3 mt-4">

        <a
            href="{{ route('riwayat') }}"
            class="btn btn-outline-secondary px-4"
        >
            Batal
        </a>

        <button
    type="submit"
    wire:confirm="Yakin jurnal ini sudah benar dan ingin dikirim untuk validasi otomatis?"
    class="btn btn-primary px-4"
    wire:loading.attr="disabled"
    wire:target="save"
>
    <span wire:loading.remove wire:target="save">
        ✓ Simpan & Validasi Otomatis
    </span>

    <span wire:loading wire:target="save">
        ⏳ Mengirim jurnal...
    </span>
</button>

@if ($saved)
    <div class="alert-box alert-success-box mt-3 text-center">
        <strong>✓ Jurnal berhasil disimpan dan divalidasi otomatis.</strong>
        <br>
        Data jurnal siap dikonfirmasi oleh sekretaris kelas.
        <br>
        <a href="{{ route('riwayat') }}" class="mt-1 d-inline-block">
            Lihat Riwayat Jurnal →
        </a>
    </div>
@endif

    </div>
</div>
</form>
</div>
