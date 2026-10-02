<?php

use Livewire\Component;
use App\Models\Kelas;
use App\Models\Jurnal;
use App\Models\Jadwal;
use App\Models\AbsensiSiswa;
use App\Models\KeteranganSiswa;
use App\Models\Siswa;
use App\Services\KehadiranGuruService;
use App\Services\DispensasiJurnalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Carbon\Carbon;

new class extends Component
{
    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    */

    // Property ini boleh ada untuk kebutuhan tampilan Livewire.
    // TETAPI saat save, nilainya TIDAK dipercaya sebagai sumber id_kelas.
    public $id_kelas = '';

    public $tanggal;

    #[Locked]
    public $jam_ke = 1;

    public $materi = '';
    public $jumlah_tidak_hadir = 0;
    public string $cariSiswa = '';
    public $catatan = '';


    /*
    |--------------------------------------------------------------------------
    | JADWAL
    |--------------------------------------------------------------------------
    */

    public $jadwalAktif = null;
    public $mapelAktif = '';


    public $jamMulaiPembelajaran = null;
    public $jamSelesaiPembelajaran = null;
    public $jamMulaiKe = null;
    public $jamSelesaiKe = null;

    /*
    |--------------------------------------------------------------------------
    | JAM OTOMATIS
    |--------------------------------------------------------------------------
    |
    | Jam pelajaran tidak pernah dipilih guru.
    | Nilainya selalu dihitung server dari jadwal hari itu.
    |
    */

    #[Locked]
    public array $jamTerpilih = [];

    /*
     * True saat guru sudah memiliki jadwal hari ini
     * tetapi seluruh jamnya sudah terisi jurnal.
     */
    public bool $semuaJamTerisi = false;

    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    protected const STATUS_BUTUH_KETERANGAN = [
        'Izin',
        'Dispensasi',
    ];

    public $editing = null;
    public $saved = false;
    public $isSaving = false;

    /*
    |--------------------------------------------------------------------------
    | SISWA & ABSENSI
    |--------------------------------------------------------------------------
    */

    public $siswa = [];
    public $absensi = [];
    public $keteranganTambahan = [];
    public array $absensiTerkunci = [];

    public bool $showAbsensiSiswa = false;
    public bool $showReview = false;

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount()
    {
        /*
         * Pastikan guru sudah login.
         */
        if (!session('id_pengguna')) {
            $this->redirectRoute('login');
            return;
        }

        /*
         * Gunakan timezone Asia/Jakarta.
         */
        $this->tanggal = Carbon::now('Asia/Jakarta')
            ->format('Y-m-d');

        /*
         * Ambil mapel guru yang sedang login.
         */
        $this->mapelAktif = DB::table('pengguna')
            ->where(
                'id_pengguna',
                session('id_pengguna')
            )
            ->value('mapel_diampu') ?? '';

        /*
        |--------------------------------------------------------------------------
        | MODE EDIT
        |--------------------------------------------------------------------------
        */

        if (request()->has('edit')) {

            $id = request()->get('edit');

            /*
             * Jurnal hanya boleh dicari milik guru yang sedang login.
             */
            $this->editing = Jurnal::query()
                ->where(
                    'id_jurnal',
                    $id
                )
                ->where(
                    'id_guru',
                    session('id_pengguna')
                )
                ->first();

            if (!$this->editing) {
                return;
            }

            /*
             * Jurnal hanya boleh diedit jika:
             * - status validasi masih Menunggu
             * - sekretaris belum mengonfirmasi
             */
            if (
                $this->editing->status_validasi !== 'Menunggu' ||
                $this->editing->status_konfirmasi_sekretaris !== 'Menunggu'
            ) {

                $this->editing = null;

                $this->addError(
                    'editing',
                    'Jurnal sudah tidak dapat diedit karena sudah diproses.'
                );

                return;
            }

            /*
             * Nilai ini hanya untuk tampilan awal.
             * Saat save nanti TETAP diambil ulang dari jadwal server.
             */
            $this->id_kelas =
                $this->editing->id_kelas;

            $this->tanggal = Carbon::parse(
                $this->editing->tanggal,
                'Asia/Jakarta'
            )->format('Y-m-d');

            $this->jam_ke =
                $this->editing->jam_ke;

            $this->jamTerpilih = [
                (int) $this->editing->jam_ke,
            ];

            $this->materi =
                $this->editing->materi ?? '';

            $this->jumlah_tidak_hadir =
                $this->editing->jumlah_tidak_hadir ?? 0;

            $this->catatan =
                $this->editing->catatan ?? '';

            /*
             * Sinkronkan jadwal dari database.
             */
            $this->sinkronkanJadwalTerpilih();

            /*
             * Load siswa berdasarkan kelas dari jadwal.
             */
            if ($this->jadwalAktif) {
                $this->id_kelas =
                    $this->jadwalAktif->id_kelas;
            }

            $this->loadSiswa();

            /*
             * Pulihkan absensi lama.
             */
            $this->loadAbsensiLama();

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | MODE INPUT BARU
        |--------------------------------------------------------------------------
        */

        $this->loadJadwal();
    }

    public function updatedTanggal(): void
    {
        if ($this->editing) {
            return;
        }

        $this->tanggal = Carbon::now('Asia/Jakarta')->toDateString();
        $this->loadJadwal();
        $this->loadDispensasiDisetujui();
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD JADWAL AKTIF
    |--------------------------------------------------------------------------
    */

    public function loadJadwal()
    {
        if (!session('id_pengguna')) {
            return;
        }

        if (!$this->namaHariUntukTanggal()) {
            return;
        }

        $this->terapkanJamOtomatis(
            $this->hitungJamOtomatis()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | JADWAL HARI INI
    |--------------------------------------------------------------------------
    |
    | Seluruh jadwal guru pada hari ini yang kelasnya
    | masih memiliki data siswa.
    |
    */

    private function jadwalHariIni(?int $idKelas = null): Collection
    {
        $idGuru = session('id_pengguna');
        $hari = $this->namaHariUntukTanggal();

        if (!$idGuru || !$hari) {
            return collect();
        }

        return Jadwal::query()
            ->where(
                'id_guru',
                $idGuru
            )
            ->where(
                'hari',
                $hari
            )
            ->when(
                $idKelas,
                fn ($query) =>
                    $query->where(
                        'id_kelas',
                        $idKelas
                    )
            )
            ->whereExists(function ($query) {

                $query->selectRaw('1')
                    ->from('siswa')
                    ->whereColumn(
                        'siswa.id_kelas',
                        'jadwal.id_kelas'
                    );

            })
            ->orderBy('jam_ke')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | HITUNG JAM OTOMATIS
    |--------------------------------------------------------------------------
    |
    | Mengembalikan rentang jam berurutan pertama
    | yang belum memiliki jurnal.
    |
    | Urutan kandidat:
    | - jam yang sedang berlangsung
    | - jam pertama yang belum mulai
    | - jam terakhir pada hari itu
    |
    | Bila rentang tersebut sudah terisi,
    | lompat ke rentang berikutnya sampai
    | tidak ada jam tersisa.
    |
    */

    private function hitungJamOtomatis(?int $idKelas = null): ?Collection
    {
        $jadwals = $this->jadwalHariIni($idKelas);

        if ($jadwals->isEmpty()) {
            return null;
        }

        $jamSekarang = Carbon::now('Asia/Jakarta')
            ->format('H:i:s');

        $kandidat =
            $jadwals->first(
                fn (Jadwal $jadwal): bool =>
                    $jadwal->jam_mulai <= $jamSekarang
                    && $jadwal->jam_selesai >= $jamSekarang
            )
            ?? $jadwals->first(
                fn (Jadwal $jadwal): bool =>
                    $jadwal->jam_mulai > $jamSekarang
            )
            ?? $jadwals->last();

        for ($percobaan = 0; $percobaan < $jadwals->count(); $percobaan++) {

            $rentang = $this->rentangJadwalBerurutan(
                $jadwals,
                $kandidat
            );

            $kosong = $this->rentangKosongPertama($rentang);

            if ($kosong !== null) {
                return $kosong;
            }

            /*
             * Lanjut ke jadwal setelah rentang tadi.
             */
            $index = $jadwals->search(
                fn (Jadwal $jadwal): bool => (int) $jadwal->id_jadwal === (int) $rentang->last()->id_jadwal
            );

            if ($index === false) {
                return null;
            }

            $kandidat = $jadwals->get(
                $index + 1
            );

            if ($kandidat === null) {
                return null;
            }
        }

        return null;
    }

    /**
     * Jam ke pada rentang yang sudah memiliki jurnal.
     *
     * @param  array<int, int>  $jamKe
     * @return array<int, int>
     */
    private function jamSudahTerisi(array $jamKe, int $idKelas): array
    {
        $idGuru = session('id_pengguna');

        if ($jamKe === [] || !$idGuru || !$idKelas) {
            return [];
        }

        return Jurnal::query()
            ->where(
                'id_guru',
                $idGuru
            )
            ->where(
                'id_kelas',
                $idKelas
            )
            ->whereDate(
                'tanggal',
                $this->tanggal
            )
            ->whereIn(
                'jam_ke',
                $jamKe
            )
            ->when(
                $this->editing,
                fn ($query) =>
                    $query->where(
                        'id_jurnal',
                        '!=',
                        $this->editing->id_jurnal
                    )
            )
            ->pluck('jam_ke')
            ->map(fn ($jam): int => (int) $jam)
            ->all();
    }

    /**
     * Rentang jam kosong pertama di dalam satu rentang berurutan.
     */
    private function rentangKosongPertama(Collection $rentang): ?Collection
    {
        $sudahTerisi = $this->jamSudahTerisi(
            $rentang
                ->pluck('jam_ke')
                ->map(fn ($jam): int => (int) $jam)
                ->all(),
            (int) $rentang->first()->id_kelas
        );

        $kosong = collect();

        foreach ($rentang as $jadwal) {

            if (in_array(
                (int) $jadwal->jam_ke,
                $sudahTerisi,
                true
            )) {

                if ($kosong->isNotEmpty()) {
                    break;
                }

                continue;
            }

            $kosong->push($jadwal);
        }

        return $kosong->isEmpty() ? null : $kosong;
    }

    /*
    |--------------------------------------------------------------------------
    | TERAPKAN JAM OTOMATIS
    |--------------------------------------------------------------------------
    |
    | Seluruh state jam berasal dari server.
    | Guru tidak dapat mengubahnya.
    |
    */

    private function terapkanJamOtomatis(?Collection $rentang): void
    {
        $this->jadwalAktif = $rentang?->first();

        $this->semuaJamTerisi =
            $rentang === null
            && $this->jadwalHariIni()->isNotEmpty();

        $this->id_kelas = $this->jadwalAktif?->id_kelas ?? '';
        $this->jam_ke = (int) ($this->jadwalAktif?->jam_ke ?? 1);
        $this->jamTerpilih = $rentang
            ? $rentang
                ->pluck('jam_ke')
                ->map(fn ($jam): int => (int) $jam)
                ->all()
            : [];

        $jadwalTerakhir = $rentang?->last();

        $this->jamMulaiKe = $this->jadwalAktif?->jam_ke;
        $this->jamMulaiPembelajaran = $this->jadwalAktif?->jam_mulai;
        $this->jamSelesaiKe = $jadwalTerakhir?->jam_ke;
        $this->jamSelesaiPembelajaran = $jadwalTerakhir?->jam_selesai;

        $this->loadSiswa();
    }

    /*
    |--------------------------------------------------------------------------
    | KELAS AKTIF
    |--------------------------------------------------------------------------
    */

    public function getKelasAktifProperty()
    {
        if (!$this->id_kelas) {
            return null;
        }

        return Kelas::where(
            'id_kelas',
            $this->id_kelas
        )->first();
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR KELAS GURU
    |--------------------------------------------------------------------------
    */

    public function getKelasListProperty()
    {
        $hari = $this->namaHariUntukTanggal();

        if (
            !$hari ||
            !session('id_pengguna')
        ) {
            return collect();
        }

        $idKelas = Jadwal::query()
            ->where(
                'id_guru',
                session('id_pengguna')
            )
            ->where(
                'hari',
                $hari
            )
            ->pluck('id_kelas')
            ->unique();

        return Kelas::query()
            ->whereIn(
                'id_kelas',
                $idKelas
            )
            ->whereExists(function ($query) {

                $query->selectRaw('1')
                    ->from('siswa')
                    ->whereColumn(
                        'siswa.id_kelas',
                        'kelas.id_kelas'
                    );

            })
            ->orderBy('nama_kelas')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | DAFTAR KELAS UNTUK PENGAJUAN IZIN
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | TOTAL SISWA
    |--------------------------------------------------------------------------
    */

    public function getTotalSiswaProperty()
    {
        if (!$this->id_kelas) {
            return 0;
        }

        return Siswa::where(
            'id_kelas',
            $this->id_kelas
        )->count();
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER SISWA
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | MODAL
    |--------------------------------------------------------------------------
    */

    public function bukaReview(): void
    {
        $this->showReview = true;
    }

    /*
    |--------------------------------------------------------------------------
    | SET ABSENSI
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | BUTUH KETERANGAN
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | UPDATE KELAS
    |--------------------------------------------------------------------------
    */

    public function getSiswaTersaringProperty()
    {
        $pencarian =
            trim($this->cariSiswa);

        if ($pencarian === '') {
            return collect($this->siswa);
        }

        return collect($this->siswa)
            ->filter(
                fn (Siswa $siswa) =>
                    str_contains(
                        mb_strtolower(
                            $siswa->nama_siswa
                        ),
                        mb_strtolower(
                            $pencarian
                        )
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | MODAL
    |--------------------------------------------------------------------------
    */

    public function bukaAbsensiSiswa(): void
    {
        $this->cariSiswa = '';
        $this->showReview = false;
        $this->showAbsensiSiswa = true;
    }

    public function tutupAbsensiSiswa(): void
    {
        $this->showAbsensiSiswa = false;
        $this->cariSiswa = '';
    }

    /*
    |--------------------------------------------------------------------------
    | BUTUH KETERANGAN
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | UPDATE KELAS
    |--------------------------------------------------------------------------
    */

    public function updatedIdKelas(): void
    {
        /*
         * Property dari browser hanya memilih kelas.
         * Jam selalu dihitung ulang dari server.
         */

        $this->jumlah_tidak_hadir = 0;

        $this->terapkanJamOtomatis(
            $this->hitungJamOtomatis(
                $this->id_kelas
                    ? (int) $this->id_kelas
                    : null
            )
        );
    }

    private function jamTerpilihAsArray(): array
    {
        return collect($this->jamTerpilih)
            ->map(fn ($jam): int => (int) $jam)
            ->filter(fn (int $jam): bool => $jam > 0)
            ->unique()
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | NAMA HARI
    |--------------------------------------------------------------------------
    */

    private function namaHariUntukTanggal(): ?string
    {
        if (!$this->tanggal) {
            return null;
        }

        $hariInggris =
            Carbon::parse(
                $this->tanggal,
                'Asia/Jakarta'
            )->format('l');

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

    /*
    |--------------------------------------------------------------------------
    | CARI JADWAL DARI SERVER
    |--------------------------------------------------------------------------
    |
    | PENTING:
    |
    | id_kelas TIDAK menentukan jam.
    |
    | Server mencari:
    | - guru dari session
    | - tanggal
    | - hari
    | - jam_ke
    |
    | id_kelas hanya dipakai sebagai penyaring tambahan
    | agar jam ke-N tidak tertukar antar kelas.
    |
    */

    private function findJadwalTerpilih(): ?Jadwal
    {
        $hari =
            $this->namaHariUntukTanggal();

        $idGuru =
            session('id_pengguna');

        if (
            !$hari ||
            !$this->jam_ke ||
            !$idGuru
        ) {
            return null;
        }

        return Jadwal::query()
            ->where(
                'id_guru',
                $idGuru
            )
            ->where(
                'hari',
                $hari
            )
            ->where(
                'jam_ke',
                $this->jam_ke
            )
            ->when(
                $this->id_kelas,
                fn ($query) =>
                    $query->where(
                        'id_kelas',
                        $this->id_kelas
                    )
            )
            ->whereExists(function ($query) {

                $query->selectRaw('1')
                    ->from('siswa')
                    ->whereColumn(
                        'siswa.id_kelas',
                        'jadwal.id_kelas'
                    );

            })
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | SINKRONKAN JADWAL
    |--------------------------------------------------------------------------
    */

    private function sinkronkanJadwalTerpilih(): void
    {
        $this->jadwalAktif =
            $this->findJadwalTerpilih();

        if (!$this->jadwalAktif) {

            $this->jamMulaiKe = null;
            $this->jamSelesaiKe = null;

            $this->jamMulaiPembelajaran = null;
            $this->jamSelesaiPembelajaran = null;

            return;
        }

        /*
         * ID KELAS DIAMBIL DARI SERVER.
         */
        $this->id_kelas =
            $this->jadwalAktif->id_kelas;

        $this->jamMulaiKe =
            $this->jadwalAktif->jam_ke;

        $this->jamMulaiPembelajaran =
            $this->jadwalAktif->jam_mulai;

        /*
         * Rentang jam mengikuti jam yang dipilih guru.
         */
        $jamTerpilih = $this->jamTerpilihAsArray();

        $jamTerakhir = $this->jadwalUntukJamTerpilih($jamTerpilih)
            ->last();

        if (! $jamTerakhir) {
            $jamTerakhir = $this->jadwalAktif;
        }

        $this->jamSelesaiKe =
            $jamTerakhir->jam_ke;

        $this->jamSelesaiPembelajaran =
            $jamTerakhir->jam_selesai;
    }

    /*
    |--------------------------------------------------------------------------
    | JADWAL UNTUK JAM TERPILIH
    |--------------------------------------------------------------------------
    |
    | Semua jam diverifikasi ulang dari server.
    | Nilai dari browser tidak dipercaya.
    |
    */

    private function rentangJadwalBerurutan(Collection $jadwals, Jadwal $jadwalTerpilih): Collection
    {
        $index = $jadwals->search(
            fn (Jadwal $jadwal): bool => (int) $jadwal->id_jadwal === (int) $jadwalTerpilih->id_jadwal,
        );

        if ($index === false) {
            return collect([$jadwalTerpilih]);
        }

        $awal = (int) $index;
        $akhir = (int) $index;

        while ($awal > 0) {
            $sekarang = $jadwals->get($awal);
            $sebelumnya = $jadwals->get($awal - 1);

            if (
                (int) $sebelumnya->id_kelas !== (int) $sekarang->id_kelas ||
                (int) $sebelumnya->jam_ke + 1 !== (int) $sekarang->jam_ke ||
                $sebelumnya->jam_selesai !== $sekarang->jam_mulai
            ) {
                break;
            }

            $awal--;
        }

        while ($akhir < $jadwals->count() - 1) {
            $sekarang = $jadwals->get($akhir);
            $berikutnya = $jadwals->get($akhir + 1);

            if (
                (int) $sekarang->id_kelas !== (int) $berikutnya->id_kelas ||
                (int) $sekarang->jam_ke + 1 !== (int) $berikutnya->jam_ke ||
                $sekarang->jam_selesai !== $berikutnya->jam_mulai
            ) {
                break;
            }

            $akhir++;
        }

        return $jadwals->slice($awal, $akhir - $awal + 1)->values();
    }

    private function jadwalUntukJamTerpilih(array $jamTerpilih, ?int $idKelas = null)
    {
        $hari = $this->namaHariUntukTanggal();
        $idGuru = session('id_pengguna');

        if ($hari === null || ! $idGuru || $jamTerpilih === []) {
            return collect();
        }

        return Jadwal::query()
            ->where('id_guru', $idGuru)
            ->where('hari', $hari)
            ->whereIn('jam_ke', $jamTerpilih)
            ->when(
                $idKelas,
                fn ($query) =>
                    $query->where(
                        'id_kelas',
                        $idKelas
                    )
            )
            ->whereExists(function ($query) {

                $query->selectRaw('1')
                    ->from('siswa')
                    ->whereColumn(
                        'siswa.id_kelas',
                        'jadwal.id_kelas'
                    );

            })
            ->orderBy('jam_ke')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD SISWA
    |--------------------------------------------------------------------------
    */

    public function loadSiswa(): void
    {
        if (!$this->id_kelas) {

            $this->siswa = [];
            $this->absensi = [];
            $this->keteranganTambahan = [];
            $this->absensiTerkunci = [];

            return;
        }

        $this->siswa = Siswa::query()
            ->where(
                'id_kelas',
                $this->id_kelas
            )
            ->orderBy('id_siswa')
            ->get();

        $idSiswaDiKelas = $this->siswa
            ->pluck('id_siswa')
            ->map(fn ($id): string => (string) $id)
            ->all();

        $this->absensi = collect($this->absensi)
            ->only($idSiswaDiKelas)
            ->all();

        $this->keteranganTambahan = collect($this->keteranganTambahan)
            ->only($idSiswaDiKelas)
            ->all();

        $this->absensiTerkunci = [];

        foreach ($this->siswa as $siswa) {

            if (!isset($this->absensi[$siswa->id_siswa])) {
                $this->absensi[$siswa->id_siswa] = 'Belum tersedia';
            }
        }

        $this->muatAbsensiReadOnlyDariJurnal();
        $this->loadDispensasiDisetujui();
    }

    private function muatAbsensiReadOnlyDariJurnal(): void
    {
        if (!$this->editing) {
            return;
        }

        $absensiJurnal = AbsensiSiswa::query()
            ->with('keteranganSiswa')
            ->where('id_jurnal', $this->editing->id_jurnal)
            ->get();

        foreach ($absensiJurnal as $absensi) {
            $this->absensi[$absensi->id_siswa] = $absensi->keterangan;
            $this->absensiTerkunci[$absensi->id_siswa] = true;
            $this->keteranganTambahan[$absensi->id_siswa] = $absensi->keteranganSiswa?->keterangan ?? '';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD DISPENSASI
    |--------------------------------------------------------------------------
    */

    public function loadDispensasiDisetujui(): void
    {
        foreach (array_keys($this->absensiTerkunci) as $idSiswa) {
            $this->absensi[$idSiswa] = 'Hadir';
            unset($this->keteranganTambahan[$idSiswa]);
        }

        $this->absensiTerkunci = [];

        if (
            !$this->id_kelas ||
            !$this->tanggal ||
            !$this->jam_ke
        ) {
            return;
        }

        $this->terapkanStatusPiket($this->statusPiketTerikatSaatIni());
    }

    private function statusPiketTerikatSaatIni()
    {
        $jadwal = $this->findJadwalTerpilih();

        if (! $jadwal || (int) $jadwal->id_kelas !== (int) $this->id_kelas) {
            return collect();
        }

        return app(DispensasiJurnalService::class)->statusTerikatUntukJurnal(
            (int) $jadwal->id_kelas,
            $this->tanggal,
            (int) $jadwal->jam_ke,
            (int) session('id_pengguna')
        );
    }

    private function terapkanStatusPiket($statusPiket): void
    {
        $idSiswaDiKelas = collect($this->siswa)
            ->pluck('id_siswa')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($statusPiket as $idSiswa => $data) {
            if (! in_array((int) $idSiswa, $idSiswaDiKelas, true)) {
                continue;
            }

            $this->absensi[$idSiswa] = $data['status'];
            $this->keteranganTambahan[$idSiswa] = $data['keterangan'] ?? '';
            $this->absensiTerkunci[$idSiswa] = true;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD ABSENSI LAMA
    |--------------------------------------------------------------------------
    */

    public function loadAbsensiLama()
    {
        if (!$this->editing) {
            return;
        }

        $absensiLama =
            AbsensiSiswa::with(
                'keteranganSiswa'
            )
                ->where(
                    'id_jurnal',
                    $this->editing->id_jurnal
                )
                ->get();

        foreach ($absensiLama as $absen) {

            $status =
                $absen->keterangan ===
                'Tanpa Keterangan'
                    ? 'Alpa'
                    : $absen->keterangan;

            $this->absensi[
                $absen->id_siswa
            ] = $status;

            if ($absen->keteranganSiswa) {

                $this->keteranganTambahan[
                    $absen->id_siswa
                ] =
                    $absen
                        ->keteranganSiswa
                        ->keterangan;
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE
    |--------------------------------------------------------------------------
    */

    public function save()
    {
        /*
         * Cegah submit ganda.
         */
        if ($this->isSaving) {
            return;
        }

        $this->isSaving = true;

        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDASI DASAR
            |--------------------------------------------------------------------------
            |
            | ID KELAS SENGAJA TIDAK divalidasi dari browser.
            |
            | Kelas akan diambil dari jadwal server.
            |
            */

            $this->validate([

                'tanggal' =>
                    'required|date',

                'materi' =>
                    'required|string|max:1000',

            ], [

                'materi.required' =>
                    'Materi wajib diisi.',

            ]);

            /*
            |--------------------------------------------------------------------------
            | CEK LOGIN
            |--------------------------------------------------------------------------
            */

            $idGuru =
                session('id_pengguna');

            if (!$idGuru) {

                $this->addError(
                    'save',
                    'Session guru tidak ditemukan. Silakan login kembali.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CEK EDITING
            |--------------------------------------------------------------------------
            */

            if ($this->editing) {

                $this->editing =
                    Jurnal::query()
                        ->where(
                            'id_jurnal',
                            $this->editing->id_jurnal
                        )
                        ->where(
                            'id_guru',
                            $idGuru
                        )
                        ->where(
                            'status_validasi',
                            'Menunggu'
                        )
                        ->where(
                            'status_konfirmasi_sekretaris',
                            'Menunggu'
                        )
                        ->first();

                if (!$this->editing) {

                    $this->addError(
                        'editing',
                        'Jurnal sudah tidak dapat diedit karena sudah diproses.'
                    );

                    return;
                }
            }

            if (
                !$this->editing &&
                $this->tanggal !== Carbon::now('Asia/Jakarta')->toDateString()
            ) {
                $this->addError(
                    'tanggal',
                    'Tanggal jurnal harus sesuai dengan hari ini.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | TENTUKAN JAM DARI SERVER
            |--------------------------------------------------------------------------
            |
            | Nilai dari browser TIDAK dipakai.
            |
            | Edit: jam terkunci pada jurnal yang diedit.
            | Input baru: jam dihitung otomatis dari jadwal.
            |
            */

            if ($this->editing) {

                $jamTerpilih = [
                    (int) $this->editing->jam_ke,
                ];
            } else {

                $rentang = $this->hitungJamOtomatis(
                    $this->jadwalAktif
                        ? (int) $this->jadwalAktif->id_kelas
                        : null
                );

                if ($rentang === null) {

                    $this->addError(
                        'jadwal',
                        'Semua jam pelajaran pada tanggal ini sudah tercatat.'
                    );

                    return;
                }

                $jamTerpilih = $rentang
                    ->pluck('jam_ke')
                    ->map(fn ($jam): int => (int) $jam)
                    ->all();
            }

            $jadwalTerpilih =
                $this->jadwalUntukJamTerpilih(
                    $jamTerpilih,
                    $this->editing
                        ? null
                        : ($this->jadwalAktif
                            ? (int) $this->jadwalAktif->id_kelas
                            : null)
                );

            if ($jadwalTerpilih->count() !== count($jamTerpilih)) {

                $this->addError(
                    'jadwal',
                    'Jam tersebut tidak terdapat pada jadwal mengajar Anda untuk tanggal tersebut.'
                );

                return;
            }

            /*
             * Pastikan seluruh jam terpilih
             * memang berurutan.
             */
            $jamUrutan =
                $jadwalTerpilih
                    ->pluck('jam_ke')
                    ->map(
                        fn ($jam): int => (int) $jam
                    )
                    ->all();

            $jamHarapan =
                range(
                    $jamUrutan[0],
                    $jamUrutan[0] + count($jamUrutan) - 1
                );

            if ($jamUrutan !== $jamHarapan) {

                $this->addError(
                    'jadwal',
                    'Jam yang dipilih harus berurutan.'
                );

                return;
            }

            $jadwalAwal =
                $jadwalTerpilih->first();

            $jadwalAkhir =
                $jadwalTerpilih->last();

            /*
             * ID KELAS RESMI DARI DATABASE.
             */
            $idKelasServer =
                $jadwalAwal->id_kelas;

            /*
             * Semua jam terpilih harus
             * berada pada kelas yang sama.
             */
            if ($jadwalTerpilih
                ->pluck('id_kelas')
                ->unique()
                ->count() > 1) {

                $this->addError(
                    'jadwal',
                    'Jam yang dipilih harus berasal dari kelas yang sama.'
                );

                return;
            }

            /*
             * Sinkronkan property Livewire
             * dengan nilai dari server.
             */
            $this->jamTerpilih =
                $jamUrutan;

            $this->jam_ke =
                $jadwalAwal->jam_ke;

            $this->id_kelas =
                $idKelasServer;

            $this->jadwalAktif =
                $jadwalAwal;

            $this->jamMulaiKe =
                $jadwalAwal->jam_ke;

            $this->jamMulaiPembelajaran =
                $jadwalAwal->jam_mulai;

            $this->jamSelesaiKe =
                $jadwalAkhir->jam_ke;

            $this->jamSelesaiPembelajaran =
                $jadwalAkhir->jam_selesai;

            /*
            |--------------------------------------------------------------------------
            | PASTIKAN KELAS BENAR-BENAR ADA
            |--------------------------------------------------------------------------
            */

            $kelasServer =
                Kelas::find(
                    $idKelasServer
                );

            if (!$kelasServer) {

                $this->addError(
                    'save',
                    'Kelas pada jadwal tidak ditemukan.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | CEK DUPLIKAT
            |--------------------------------------------------------------------------
            |
            | Gunakan id_kelas dari SERVER,
            | bukan dari browser.
            |
            | Jika salah satu jam terpilih sudah memiliki
            | jurnal, SELURUH penyimpanan dibatalkan.
            |
            */

            $jamSudahTerpakai =
                $this->jamSudahTerisi(
                    $jamUrutan,
                    (int) $idKelasServer
                );

            if ($jamSudahTerpakai !== []) {

                $this->addError(
                    'jadwal',
                    'Jurnal untuk jam ke-' .
                        implode(
                            ', jam ke-',
                            $jamSudahTerpakai
                        ) .
                        ' sudah tercatat.'
                );

                return;
            }


            /*
            |--------------------------------------------------------------------------
            | AMBIL SISWA BERDASARKAN KELAS SERVER
            |--------------------------------------------------------------------------
            */

            $siswaValid =
                Siswa::query()
                    ->where(
                        'id_kelas',
                        $idKelasServer
                    )
                    ->orderBy('id_siswa')
                    ->get();

            if ($siswaValid->isEmpty()) {

                $this->addError(
                    'save',
                    'Kelas tersebut belum memiliki data siswa.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | FILTER ABSENSI
            |--------------------------------------------------------------------------
            |
            | Hanya siswa dari kelas server yang boleh masuk.
            |
            */

            $idSiswaValid =
                $siswaValid
                    ->pluck('id_siswa')
                    ->map(
                        fn ($id) =>
                            (string) $id
                    )
                    ->all();

            $this->absensi =
                collect($this->absensi)
                    ->only($idSiswaValid)
                    ->all();

            $statusPiket = app(DispensasiJurnalService::class)
                ->statusTerikatUntukJurnal(
                    (int) $idKelasServer,
                    $this->tanggal,
                    (int) $jadwalAwal->jam_ke,
                    (int) $idGuru
                );

            foreach ($statusPiket as $idSiswa => $dataStatus) {
                $this->absensi[$idSiswa] = $dataStatus['status'];
                $this->keteranganTambahan[$idSiswa] = $dataStatus['keterangan'] ?? '';
            }

            $this->absensiTerkunci = $statusPiket
                ->keys()
                ->mapWithKeys(fn ($id): array => [(int) $id => true])
                ->all();

            $this->keteranganTambahan = collect($this->keteranganTambahan)
                ->only($idSiswaValid)
                ->all();

            /*
            |--------------------------------------------------------------------------
            | CEK SEMUA ABSENSI
            |--------------------------------------------------------------------------
            */

            foreach ($siswaValid as $siswa) {

                $statusSiswa =
                    $this->absensi[
                        $siswa->id_siswa
                    ] ?? null;

                if (!$statusSiswa || $statusSiswa === 'Belum tersedia') {
                    $this->absensi[$siswa->id_siswa] = 'Hadir';
                }
            }

            /*
            |--------------------------------------------------------------------------
            | TRANSAKSI DATABASE
            |--------------------------------------------------------------------------
            |
            | Satu kali input disimpan untuk semua jam terpilih.
            |
            | Jika salah satu jam gagal, semua perubahan dibatalkan.
            |
            */

            try {

                DB::transaction(
                    function () use (
                        $idGuru,
                        $idKelasServer,
                        $jadwalTerpilih,
                        $siswaValid,
                        $kelasServer
                    ) {

                    $namaKelas =
                        $kelasServer->nama_kelas
                            ?? '-';

                    $dispensasiService =
                        app(
                            DispensasiJurnalService::class
                        );

                    foreach ($jadwalTerpilih as $jadwal) {

                        /*
                        |----------------------------------------------------------
                        | ABSENSI KHUSUS JAM INI
                        |----------------------------------------------------------
                        */

                        $absensiJam = $this->absensi;
                        $keteranganJam =
                            $this->keteranganTambahan;

                        $statusPiketJam =
                            $dispensasiService
                                ->statusTerikatUntukJurnal(
                                    (int) $idKelasServer,
                                    $this->tanggal,
                                    (int) $jadwal->jam_ke,
                                    (int) $idGuru
                                );

                        foreach ($statusPiketJam as $idSiswa => $dataStatus) {
                            $absensiJam[$idSiswa] =
                                $dataStatus['status'];

                            $keteranganJam[$idSiswa] =
                                $dataStatus['keterangan'] ?? '';
                        }

                        /*
                        |----------------------------------------------------------
                        | HITUNG REKAPITULASI
                        |----------------------------------------------------------
                        */

                        $jumlahHadir =
                            collect($absensiJam)
                                ->filter(
                                    fn ($status) =>
                                        $status === 'Hadir'
                                )
                                ->count();

                        $jumlahTidakHadir =
                            collect($absensiJam)
                                ->filter(
                                    fn ($status) =>
                                        $status !== 'Hadir'
                                )
                                ->count();

                        /*
                        |----------------------------------------------------------
                        | DATA JURNAL
                        |----------------------------------------------------------
                        |
                        | id_kelas dan jam_ke berasal dari SERVER.
                        |
                        */

                        $data = [

                            'id_guru' =>
                                $idGuru,

                            'id_kelas' =>
                                $idKelasServer,

                            'tanggal' =>
                                $this->tanggal,

                            'jam_ke' =>
                                $jadwal->jam_ke,

                            'materi' =>
                                trim($this->materi),

                            'jumlah_hadir' =>
                                $jumlahHadir,

                            'jumlah_tidak_hadir' =>
                                $jumlahTidakHadir,

                            'status_kehadiran_guru' =>
                                'Hadir',

                            'catatan' =>
                                $this->catatan ?: null,

                            /*
                             * Guru mengirim jurnal.
                             * Status awal = Menunggu.
                             */
                            'status_validasi' =>
                                'Menunggu',

                            'id_validator' =>
                                null,

                            'tanggal_validasi' =>
                                null,

                            'catatan_validasi' =>
                                null,
                        ];

                        /*
                        |----------------------------------------------------------
                        | CREATE / UPDATE JURNAL
                        |----------------------------------------------------------
                        |
                        | Edit hanya berlaku untuk satu jam.
                        |
                        */

                        if (
                            $this->editing &&
                            (int) $this->editing->jam_ke ===
                                (int) $jadwal->jam_ke
                        ) {

                            $this->editing->update(
                                $data
                            );

                            $idJurnal =
                                $this->editing->id_jurnal;

                            /*
                             * PENTING:
                             * Hapus KeteranganSiswa DULU.
                             *
                             * Jangan hapus AbsensiSiswa terlebih dahulu,
                             * karena KeteranganSiswa masih membutuhkan
                             * relasi tersebut untuk whereHas().
                             */

                            KeteranganSiswa::whereHas(
                                'absensi',
                                function ($query) use (
                                    $idJurnal
                                ) {

                                    $query->where(
                                        'id_jurnal',
                                        $idJurnal
                                    );

                                }
                            )->delete();

                            /*
                             * Baru hapus absensi lama.
                             */
                            AbsensiSiswa::where(
                                'id_jurnal',
                                $idJurnal
                            )->delete();

                        } else {

                            $jurnal =
                                Jurnal::create(
                                    $data
                                );

                            $idJurnal =
                                $jurnal->id_jurnal;
                        }

                        /*
                        |----------------------------------------------------------
                        | SIMPAN ABSENSI SISWA
                        |----------------------------------------------------------
                        */

                        foreach (
                            $absensiJam
                            as $idSiswa => $statusSiswa
                        ) {

                            /*
                             * Pastikan siswa benar-benar
                             * berasal dari kelas server.
                             */
                            $siswaData =
                                $siswaValid->firstWhere(
                                    'id_siswa',
                                    $idSiswa
                                );

                            if (!$siswaData) {
                                continue;
                            }

                            $absensiSiswa =
                                AbsensiSiswa::create([

                                    'id_jurnal' =>
                                        $idJurnal,

                                    'id_siswa' =>
                                        $idSiswa,

                                    'keterangan' =>
                                        $statusSiswa,

                                ]);

                            /*
                            |----------------------------------------------------------
                            | SIMPAN KETERANGAN TAMBAHAN
                            |----------------------------------------------------------
                            */

                            if (
                                $statusSiswa !== 'Hadir'
                            ) {

                                KeteranganSiswa::create([

                                    'id_absensi' =>
                                        $absensiSiswa
                                            ->id_absensi,

                                    'id_siswa' =>
                                        $idSiswa,

                                    'nama_siswa' =>
                                        $siswaData
                                            ->nama_siswa,

                                    'kelas' =>
                                        $namaKelas,

                                    'status' =>
                                        $statusSiswa,

                                    'keterangan' =>
                                        trim(
                                            $keteranganJam[
                                                $idSiswa
                                            ] ?? ''
                                        ),

                                    'tanggal' =>
                                        $this->tanggal,

                                ]);
                            }
                        }

                        /*
                        |----------------------------------------------------------
                        | SINKRON DISPENSASI
                        |----------------------------------------------------------
                        */

                        $dispensasiService->syncUntukJurnal(
                            Jurnal::findOrFail(
                                $idJurnal
                            )
                        );
                    }
                    }
                );

            } catch (\Illuminate\Database\QueryException $exception) {

                /*
                 * Jurnal sudah tercatat oleh permintaan lain.
                 */
                if (str_contains(
                    $exception->getMessage(),
                    'jurnal'
                )) {

                    $this->addError(
                        'jadwal',
                        'Jurnal untuk jam yang dipilih sudah tercatat.'
                    );

                    return;
                }

                throw $exception;
            }

            /*
            |--------------------------------------------------------------------------
            | STATUS KEHADIRAN GURU
            |--------------------------------------------------------------------------
            */

            foreach ($jadwalTerpilih as $jadwal) {

                app(
                    KehadiranGuruService::class
                )->statusUntukJadwal(

                    $jadwal,

                    Carbon::now(
                        'Asia/Jakarta'
                    ),

                    Carbon::parse(
                        $this->tanggal,
                        'Asia/Jakarta'
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | BERHASIL
            |--------------------------------------------------------------------------
            */

            $this->saved = true;

            /*
             * Refresh data jika sedang edit.
             */
            if ($this->editing) {

                $this->editing =
                    Jurnal::find(
                        $this->editing->id_jurnal
                    );
            } else {

                /*
                 * Jam otomatis berpindah ke rentang
                 * berikutnya yang belum terisi.
                 */
                $this->materi = '';
                $this->catatan = '';
                $this->loadJadwal();
            }

        } finally {

            $this->isSaving = false;
        }
    }


};
?>

<div>
    <div class="role-page-header">
        <div class="role-page-eyebrow">Jurnal Guru</div>
        <h1>{{ $editing ? 'Edit Jurnal Mengajar' : 'Input Jurnal Mengajar' }}</h1>
        <div class="role-page-description">Isi jurnal sesuai jadwal mengajar Anda hari ini.</div>
    </div>
    <div class="role-page-actions mb-3">
    </div>

    @if ($saved)
    <div class="alert alert-success" role="status">Jurnal berhasil disimpan.</div>
    @endif

    @if ($errors->has('editing'))
    <div class="alert alert-warning" role="alert">{{ $errors->first('editing') }}</div>
    @endif

    @if ($errors->has('jadwal'))
    <div class="alert alert-warning" role="alert">{{ $errors->first('jadwal') }}</div>
    @endif

        @if (!$jadwalAktif && !$editing)
    <div class="card-custom p-4">
        @if ($semuaJamTerisi)
        <h2 class="h5 fw-bold">Semua jam pelajaran hari ini sudah tercatat</h2>
        <p class="text-muted mb-3">Seluruh jam jadwal mengajar Anda pada tanggal ini sudah memiliki jurnal. Tidak ada jam yang perlu diisi lagi.</p>
        @else
        <h2 class="h5 fw-bold">Tidak ada jadwal mengajar untuk hari ini</h2>
        <p class="text-muted mb-3">Belum ada jadwal guru yang dapat dipilih untuk tanggal ini.</p>
        @endif
        @if (session('is_guru_piket'))
        <a href="{{ route('guru-piket') }}" class="btn btn-app-primary">Buka halaman guru piket</a>
        @endif
        @unless ($semuaJamTerisi)
        <a href="{{ route('izin-guru') }}" class="btn btn-outline-danger">Ajukan Izin Guru</a>
        @endunless
    </div>
    @else
    <div class="mb-3"><a href="{{ route('izin-guru') }}" class="btn btn-outline-danger">Ajukan Izin Guru</a></div>
    <div class="card-custom p-4">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
            <div>
                <div class="text-muted small">Guru</div>
                <div class="fw-semibold">{{ session('nama') }}</div>
            </div>
            <div>
                <div class="text-muted small">Mata Pelajaran</div>
                <div class="fw-semibold">{{ $mapelAktif ?: '-' }}</div>
            </div>
            @if ($jadwalAktif)
            <div>
                <div class="text-muted small">Kelas</div>
                <div class="fw-semibold">{{ $this->kelasAktif?->nama_kelas ?? '-' }}</div>
            </div>
            @endif
            <div>
                <div class="text-muted small">Tanggal</div>
                <div class="fw-semibold">{{ \Carbon\Carbon::parse($tanggal)->locale('id')->translatedFormat('l, d F Y') }}</div>
            </div>
        </div>

        @if ($jadwalAktif)
        <div class="alert alert-info">
            Jam pelajaran mengikuti jadwal: ke-{{ $jamMulaiKe }}{{ $jamSelesaiKe > $jamMulaiKe ? ' sampai ke-'.$jamSelesaiKe : '' }}
            @if ($jamMulaiPembelajaran && $jamSelesaiPembelajaran)
            ({{ substr($jamMulaiPembelajaran, 0, 5) }}–{{ substr($jamSelesaiPembelajaran, 0, 5) }})
            @endif.
            Jam tidak dapat diubah.
        </div>
        @endif

        <form wire:submit="save">
            <div class="row g-3">
                @if (!$editing)
                <div class="col-md-6">
                    <label for="jurnal-kelas" class="form-label fw-semibold">Kelas</label>
                    <select id="jurnal-kelas" wire:model.live="id_kelas" class="form-select">
                        <option value="">Pilih kelas</option>
                        @foreach ($this->kelasList as $kelas)
                        <option value="{{ $kelas->id_kelas }}">{{ $kelas->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-12">
                    <label for="jurnal-materi" class="form-label fw-semibold">Materi pembelajaran</label>
                    <textarea id="jurnal-materi" wire:model="materi" rows="4" maxlength="1000" class="form-control" placeholder="Tuliskan materi yang diajarkan"></textarea>
                    @error('materi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </div>

                <div class="col-12">
                    <label class="form-label fw-semibold d-block mb-2">Kehadiran siswa</label>
                    @error('absensi') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    @if (count($siswa))
                    @php
                    $jumlahHadirRingkasan = collect($absensi)->filter(fn ($status) => $status === 'Hadir')->count();
                    $jumlahTidakHadirRingkasan = collect($absensi)->filter(fn ($status) => $status !== 'Hadir')->count();
                    @endphp
                    <button type="button" wire:click="bukaAbsensiSiswa" aria-haspopup="dialog" aria-expanded="{{ $showAbsensiSiswa ? 'true' : 'false' }}" aria-controls="daftar-siswa-modal" class="card-custom w-100 text-start p-3 mb-3 border">
                        <span class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <span>
                                <strong class="d-block">Kehadiran siswa</strong>
                                <span class="text-muted small">{{ count($siswa) }} siswa · {{ $jumlahHadirRingkasan }} hadir · {{ $jumlahTidakHadirRingkasan }} perlu dicatat</span>
                            </span>
                            <span class="btn btn-sm btn-outline-primary">Lihat status</span>
                        </span>
                    </button>
                    @if ($showAbsensiSiswa)
                    <div class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center p-3" style="z-index:1060;background:rgba(15,23,42,.64);backdrop-filter:blur(3px)" wire:click.self="tutupAbsensiSiswa" wire:keydown.escape.window="tutupAbsensiSiswa">
                        <section id="daftar-siswa-modal" class="card-custom w-100 overflow-hidden shadow-lg" style="max-width:1080px;max-height:90vh;border:1px solid #dbeafe;box-shadow:0 24px 80px rgba(15,23,42,.28)!important" role="dialog" aria-modal="true" aria-labelledby="daftar-siswa-title">
                            <div class="card-header-custom d-flex flex-wrap justify-content-between align-items-center gap-3 px-4 py-3">
                                <div>
                                    <h3 id="daftar-siswa-title" class="h6 mb-0">Daftar siswa dan status</h3>
                                    <span class="small text-muted fw-normal">{{ count($siswa) }} siswa · status hanya dapat dilihat di halaman jurnal</span>
                                </div>
                                <button type="button" class="btn btn-sm fw-bold px-3 py-2" style="color:#1d4ed8;background:#fff;border:1px solid #bfdbfe;border-radius:9px" wire:click="tutupAbsensiSiswa">Tutup Daftar</button>
                            </div>
                            <div class="p-3 bg-light border-bottom">
                                <input type="search" wire:model.live.debounce.250ms="cariSiswa" class="form-control form-control-lg" placeholder="Cari nama siswa..." aria-label="Cari siswa">
                            </div>
                            <div class="table-responsive" style="max-height:calc(90vh - 185px);overflow-y:auto">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead class="table-light" style="position:sticky;top:0;z-index:1;box-shadow:0 1px 0 #dee2e6"><tr><th class="ps-4 py-3">Siswa</th><th class="py-3">Status Kehadiran</th><th class="pe-4 py-3">Keterangan</th></tr></thead>
                                    <tbody>
                                        @foreach ($this->siswaTersaring as $siswaItem)
                                        @php $statusTerkunci = isset($absensiTerkunci[$siswaItem->id_siswa]); @endphp
                                        <tr class="align-middle" wire:key="input-jurnal-siswa-{{ $siswaItem->id_siswa }}">
                                            <td>{{ $siswaItem->nama_siswa }}</td>
                                            <td>
                                                <span class="badge bg-secondary">{{ $absensi[$siswaItem->id_siswa] ?? 'Belum tersedia' }}</span>
                                            </td>
                                            <td>
                                                @if ($statusTerkunci)
                                                <span>{{ $keteranganTambahan[$siswaItem->id_siswa] ?: '—' }}</span>
                                                @else
                                                <span class="text-muted">—</span>
                                                @endif
                                                @error('keteranganTambahan.'.$siswaItem->id_siswa) <div class="text-danger small">{{ $message }}</div> @enderror
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                    @endif
                    @else
                    <div class="alert alert-warning mb-0">Tidak ada data siswa untuk jadwal ini.</div>
                    @endif
                </div>
                @endif

                <div class="col-12">
                    <label for="jurnal-catatan" class="form-label fw-semibold">Catatan (opsional)</label>
                    <textarea id="jurnal-catatan" wire:model="catatan" rows="2" class="form-control" placeholder="Catatan tambahan"></textarea>
                </div>

                <div class="col-12 d-flex flex-wrap justify-content-end gap-2 mt-3">
                    <button type="button" wire:click="bukaReview" class="btn btn-outline-primary" @disabled(!count($siswa))>Tinjau jurnal</button>
                    <button type="submit" class="btn btn-app-primary" wire:loading.attr="disabled" wire:target="save" @disabled(!count($siswa) || !$jadwalAktif)>
                        <span wire:loading.remove wire:target="save">{{ $editing ? 'Simpan Perubahan' : 'Simpan Jurnal' }}</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
                @error('save') <div class="col-12 text-danger">{{ $message }}</div> @enderror
            </div>
        </form>
    </div>
    @endif

    @if ($showReview)
    <div class="modal d-block" tabindex="-1" role="dialog" aria-modal="true" style="background:rgba(15,23,42,.5)">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5">Tinjau Jurnal</h2><button type="button" class="btn-close" wire:click="$set('showReview', false)" aria-label="Tutup"></button></div>
            <div class="modal-body"><p><strong>Kelas:</strong> {{ $this->kelasAktif?->nama_kelas ?? '-' }}</p><p><strong>Jam:</strong> ke-{{ $jamMulaiKe }}{{ $jamSelesaiKe > $jamMulaiKe ? ' s/d ke-'.$jamSelesaiKe : '' }}@if ($jamMulaiPembelajaran && $jamSelesaiPembelajaran) ({{ substr($jamMulaiPembelajaran, 0, 5) }}–{{ substr($jamSelesaiPembelajaran, 0, 5) }})@endif</p><p><strong>Materi:</strong> {{ $materi ?: 'Belum diisi' }}</p><p><strong>Jumlah siswa:</strong> {{ count($siswa) }}</p><p><strong>Tidak hadir:</strong> {{ collect($absensi)->filter(fn ($status) => $status !== 'Hadir')->count() }}</p></div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" wire:click="$set('showReview', false)">Kembali</button><button type="button" class="btn btn-app-primary" wire:click="save">Simpan Jurnal</button></div>
        </div></div>
    </div>
    @endif

</div>
