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
    public $jam_ke = 1;
    public $materi = '';
    public $jumlah_tidak_hadir = 0;
    public $catatan = '';

    public string $cariSiswa = '';

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
    | STATUS
    |--------------------------------------------------------------------------
    */

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

    protected const STATUS_BUTUH_KETERANGAN = [
        'Sakit',
        'Izin',
        'Dispensasi',
    ];

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

    /*
    |--------------------------------------------------------------------------
    | LOAD JADWAL AKTIF
    |--------------------------------------------------------------------------
    */

    public function loadJadwal()
    {
        $idGuru = session('id_pengguna');

        if (!$idGuru) {
            return;
        }

        $hari = $this->namaHariUntukTanggal();

        if (!$hari) {
            return;
        }

        $jamSekarang = Carbon::now('Asia/Jakarta')
            ->format('H:i:s');

        /*
         * Ambil seluruh jadwal guru pada hari tersebut.
         */
        $jadwalHariIni = Jadwal::query()
            ->where(
                'id_guru',
                $idGuru
            )
            ->where(
                'hari',
                $hari
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

        /*
         * HANYA jadwal yang sedang berlangsung.
         *
         * Tidak ada fallback ke jadwal terdekat.
         */
        $this->jadwalAktif =
            $jadwalHariIni->first(
                function ($jadwal) use ($jamSekarang) {

                    return $jadwal->jam_mulai <= $jamSekarang
                        && $jadwal->jam_selesai >= $jamSekarang;
                }
            );

        /*
         * Tidak ada jadwal aktif.
         */
        if (!$this->jadwalAktif) {

            $this->id_kelas = '';
            $this->jam_ke = 1;

            $this->jamMulaiKe = null;
            $this->jamSelesaiKe = null;

            $this->jamMulaiPembelajaran = null;
            $this->jamSelesaiPembelajaran = null;

            $this->siswa = [];
            $this->absensi = [];

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | DATA DARI SERVER
        |--------------------------------------------------------------------------
        */

        $this->id_kelas =
            $this->jadwalAktif->id_kelas;

        $this->jam_ke =
            $this->jadwalAktif->jam_ke;

        $this->jamMulaiKe =
            $this->jadwalAktif->jam_ke;

        $this->jamMulaiPembelajaran =
            $this->jadwalAktif->jam_mulai;

        $this->jamSelesaiKe =
            $this->jadwalAktif->jam_ke;

        $this->jamSelesaiPembelajaran =
            $this->jadwalAktif->jam_selesai;

        /*
         * Cari jam berikutnya yang menyambung.
         */
        $jamSaatIni =
            $this->jadwalAktif;

        while (true) {

            $jadwalBerikutnya = Jadwal::query()
                ->where(
                    'id_guru',
                    $idGuru
                )
                ->where(
                    'id_kelas',
                    $this->jadwalAktif->id_kelas
                )
                ->where(
                    'hari',
                    $hari
                )
                ->where(
                    'jam_ke',
                    $jamSaatIni->jam_ke + 1
                )
                ->where(
                    'jam_mulai',
                    $jamSaatIni->jam_selesai
                )
                ->first();

            if (!$jadwalBerikutnya) {
                break;
            }

            $this->jamSelesaiKe =
                $jadwalBerikutnya->jam_ke;

            $this->jamSelesaiPembelajaran =
                $jadwalBerikutnya->jam_selesai;

            $jamSaatIni =
                $jadwalBerikutnya;
        }

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
    | DAFTAR JAM
    |--------------------------------------------------------------------------
    */

    public function getJamListProperty()
    {
        $hari = $this->namaHariUntukTanggal();

        if (
            !$hari ||
            !session('id_pengguna')
        ) {
            return collect();
        }

        return Jadwal::query()
            ->where(
                'id_guru',
                session('id_pengguna')
            )
            ->where(
                'hari',
                $hari
            )
            ->when(
                $this->id_kelas,
                fn ($query) =>
                    $query->where(
                        'id_kelas',
                        $this->id_kelas
                    )
            )
            ->orderBy('jam_ke')
            ->get([
                'jam_ke',
                'jam_mulai',
                'jam_selesai',
            ]);
    }

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

    public function bukaReview(): void
    {
        $this->showAbsensiSiswa = false;
        $this->showReview = true;
    }

    /*
    |--------------------------------------------------------------------------
    | SET ABSENSI
    |--------------------------------------------------------------------------
    */

    public function setAbsensiSiswa(
        int|string $idSiswa,
        string $status
    ): void {

        $statusDiizinkan = [
            'Hadir',
            'Izin',
            'Sakit',
            'Alpa',
            'Dispensasi',
        ];

        if (!in_array(
            $status,
            $statusDiizinkan,
            true
        )) {
            return;
        }

        $ada = collect($this->siswa)
            ->contains(
                'id_siswa',
                $idSiswa
            );

        if (!$ada) {
            return;
        }

        $this->absensi[$idSiswa] =
            $status;

        /*
         * Kalau kembali Hadir,
         * hapus keterangan tambahan.
         */
        if ($status === 'Hadir') {

            unset(
                $this->keteranganTambahan[
                    $idSiswa
                ]
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | BUTUH KETERANGAN
    |--------------------------------------------------------------------------
    */

    public function butuhKeterangan(
        int|string $idSiswa
    ): bool {

        return in_array(
            $this->absensi[$idSiswa] ?? '',
            self::STATUS_BUTUH_KETERANGAN,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE KELAS
    |--------------------------------------------------------------------------
    */

    public function updatedIdKelas(): void
    {
        /*
         * Property dari browser hanya memengaruhi tampilan.
         * Saat save tetap diverifikasi ulang dari server.
         */

        $this->jumlah_tidak_hadir = 0;

        $jamPertama =
            $this->jamList->first();

        if ($jamPertama) {

            $this->jam_ke =
                $jamPertama->jam_ke;
        }

        $this->loadSiswa();

        $this->sinkronkanJadwalTerpilih();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE JAM
    |--------------------------------------------------------------------------
    */

    public function updatedJamKe(): void
    {
        $this->loadDispensasiDisetujui();

        $this->sinkronkanJadwalTerpilih();
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
    | id_kelas TIDAK dipakai untuk menentukan kelas.
    |
    | Server mencari:
    | - guru dari session
    | - tanggal
    | - hari
    | - jam_ke
    |
    | Kemudian id_kelas diambil dari tabel jadwal.
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

        $this->jamSelesaiKe =
            $this->jadwalAktif->jam_ke;

        $this->jamSelesaiPembelajaran =
            $this->jadwalAktif->jam_selesai;
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

            return;
        }

        $this->siswa = Siswa::query()
            ->where(
                'id_kelas',
                $this->id_kelas
            )
            ->orderBy('id_siswa')
            ->get();

        foreach ($this->siswa as $siswa) {

            if (!isset(
                $this->absensi[
                    $siswa->id_siswa
                ]
            )) {

                $this->absensi[
                    $siswa->id_siswa
                ] = 'Hadir';
            }
        }

        $this->loadDispensasiDisetujui();
    }

    /*
    |--------------------------------------------------------------------------
    | LOAD DISPENSASI
    |--------------------------------------------------------------------------
    */

    public function loadDispensasiDisetujui(): void
    {
        if (
            !$this->id_kelas ||
            !$this->tanggal ||
            !$this->jam_ke
        ) {
            return;
        }

        $dispensasi = DB::table('dispensasi')
            ->where(
                'id_kelas',
                $this->id_kelas
            )
            ->where(
                'tanggal',
                $this->tanggal
            )
            ->where(
                'status',
                'Disetujui'
            )
            ->where(function ($query) {

                $query
                    ->where(
                        'jenis_dispensasi',
                        'Sehari Penuh'
                    )

                    ->orWhere(function ($q) {

                        $q
                            ->where(
                                'jenis_dispensasi',
                                'Per Jam'
                            )
                            ->where(
                                'jam_ke_mulai',
                                '<=',
                                $this->jam_ke
                            )
                            ->where(
                                'jam_ke_selesai',
                                '>=',
                                $this->jam_ke
                            );

                    })

                    ->orWhere(function ($q) {

                        $q
                            ->where(
                                'jenis_dispensasi',
                                'Per Mapel'
                            )
                            ->where(
                                'id_guru',
                                session('id_pengguna')
                            )
                            ->where(
                                'jam_ke_mulai',
                                $this->jam_ke
                            );

                    });

            })
            ->get();

        foreach ($dispensasi as $data) {

            $this->absensi[
                $data->id_siswa
            ] = 'Dispensasi';

            $this->keteranganTambahan[
                $data->id_siswa
            ] = $data->alasan;
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

                'jam_ke' =>
                    'required|integer|min:1|max:12',

                'materi' =>
                    'required|string|max:1000',

                'absensi.*' =>
                    'required|in:Hadir,Izin,Sakit,Alpa,Dispensasi',

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

            /*
            |--------------------------------------------------------------------------
            | AMBIL JADWAL DARI SERVER
            |--------------------------------------------------------------------------
            |
            | INI BAGIAN PALING PENTING.
            |
            | Tidak menggunakan:
            |
            | $this->id_kelas
            |
            | untuk menentukan kelas.
            |
            | Server mencari jadwal berdasarkan:
            |
            | guru + hari + jam_ke
            |
            */

            $jadwalTerpilih =
                $this->findJadwalTerpilih();

            if (!$jadwalTerpilih) {

                $this->addError(
                    'jam_ke',
                    'Jam tersebut tidak terdapat pada jadwal mengajar Anda untuk tanggal tersebut.'
                );

                return;
            }

            /*
             * ID KELAS RESMI DARI DATABASE.
             */
            $idKelasServer =
                $jadwalTerpilih->id_kelas;

            /*
             * Sinkronkan property Livewire
             * dengan nilai dari server.
             */
            $this->id_kelas =
                $idKelasServer;

            $this->jadwalAktif =
                $jadwalTerpilih;

            $this->jamMulaiKe =
                $jadwalTerpilih->jam_ke;

            $this->jamMulaiPembelajaran =
                $jadwalTerpilih->jam_mulai;

            $this->jamSelesaiKe =
                $jadwalTerpilih->jam_ke;

            $this->jamSelesaiPembelajaran =
                $jadwalTerpilih->jam_selesai;

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
            */

            $jurnalDuplikat =
                Jurnal::query()
                    ->where(
                        'id_guru',
                        $idGuru
                    )
                    ->where(
                        'id_kelas',
                        $idKelasServer
                    )
                    ->whereDate(
                        'tanggal',
                        $this->tanggal
                    )
                    ->where(
                        'jam_ke',
                        $jadwalTerpilih->jam_ke
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
                    ->exists();

            if ($jurnalDuplikat) {

                $this->addError(
                    'jam_ke',
                    'Jurnal untuk kelas dan jam ini sudah tercatat.'
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

                if (!$statusSiswa) {

                    $this->addError(
                        'absensi',
                        'Status kehadiran semua siswa harus diisi.'
                    );

                    return;
                }

                /*
                 * Izin, sakit, dispensasi
                 * wajib punya keterangan.
                 */
                if (
                    in_array(
                        $statusSiswa,
                        self::STATUS_BUTUH_KETERANGAN,
                        true
                    )
                    &&
                    trim(
                        $this->keteranganTambahan[
                            $siswa->id_siswa
                        ] ?? ''
                    ) === ''
                ) {

                    $this->addError(
                        'keteranganTambahan.' .
                        $siswa->id_siswa,
                        "Keterangan untuk {$siswa->nama_siswa} ({$statusSiswa}) wajib diisi."
                    );

                    return;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | HITUNG ABSENSI
            |--------------------------------------------------------------------------
            */

            $jumlahHadir =
                collect($this->absensi)
                    ->filter(
                        fn ($status) =>
                            $status === 'Hadir'
                    )
                    ->count();

            $jumlahIzin =
                collect($this->absensi)
                    ->filter(
                        fn ($status) =>
                            $status === 'Izin'
                    )
                    ->count();

            $jumlahSakit =
                collect($this->absensi)
                    ->filter(
                        fn ($status) =>
                            $status === 'Sakit'
                    )
                    ->count();

            $jumlahAlpa =
                collect($this->absensi)
                    ->filter(
                        fn ($status) =>
                            $status === 'Alpa'
                    )
                    ->count();

            $jumlahDispensasi =
                collect($this->absensi)
                    ->filter(
                        fn ($status) =>
                            $status === 'Dispensasi'
                    )
                    ->count();

            $jumlahTidakHadir =
                $jumlahIzin +
                $jumlahSakit +
                $jumlahAlpa +
                $jumlahDispensasi;

            /*
            |--------------------------------------------------------------------------
            | DATA JURNAL
            |--------------------------------------------------------------------------
            |
            | id_kelas berasal dari SERVER.
            | jam_ke juga berasal dari SERVER.
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
                    $jadwalTerpilih->jam_ke,

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
            |--------------------------------------------------------------------------
            | TRANSAKSI DATABASE
            |--------------------------------------------------------------------------
            */

            DB::transaction(
                function () use (
                    $data,
                    $siswaValid,
                    $kelasServer
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | CREATE / UPDATE JURNAL
                    |--------------------------------------------------------------------------
                    */

                    if ($this->editing) {

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
                    |--------------------------------------------------------------------------
                    | SIMPAN ABSENSI SISWA
                    |--------------------------------------------------------------------------
                    */

                    $namaKelas =
                        $kelasServer->nama_kelas
                            ?? '-';

                    foreach (
                        $this->absensi
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
                        |--------------------------------------------------------------------------
                        | SIMPAN KETERANGAN TAMBAHAN
                        |--------------------------------------------------------------------------
                        */

                        if (
                            in_array(
                                $statusSiswa,
                                self::STATUS_BUTUH_KETERANGAN,
                                true
                            )
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
                                        $this
                                            ->keteranganTambahan[
                                                $idSiswa
                                            ] ?? ''
                                    ),

                                'tanggal' =>
                                    $this->tanggal,

                            ]);
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | SINKRON DISPENSASI
                    |--------------------------------------------------------------------------
                    */

                    app(
                        \App\Services\DispensasiJurnalService::class
                    )->syncUntukJurnal(
                        Jurnal::findOrFail(
                            $idJurnal
                        )
                    );
                }
            );

            /*
            |--------------------------------------------------------------------------
            | STATUS KEHADIRAN GURU
            |--------------------------------------------------------------------------
            */

            app(
                KehadiranGuruService::class
            )->statusUntukJadwal(

                $jadwalTerpilih,

                Carbon::now(
                    'Asia/Jakarta'
                ),

                Carbon::parse(
                    $this->tanggal,
                    'Asia/Jakarta'
                )
            );

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
            }

        } finally {

            $this->isSaving = false;
        }
    }
};
?>