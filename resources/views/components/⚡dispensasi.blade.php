<?php

    use Livewire\Component;
    use App\Models\Siswa;
    use App\Models\Kelas;
    use App\Models\Dispensasi;
    use App\Models\Jadwal;
    use App\Models\GuruPiket;
    use App\Models\JadwalPiket;
    use App\Models\Pengguna;
    use Carbon\Carbon;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Str;

    new class extends Component
    {


    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    */

    public $id_kelas = '';

    public $siswaTerpilih = [];

    public $jenis_dispensasi = 'Per Jam';

    public $id_jadwal = '';
    public $mapel = '';

    public $jam_ke_mulai = '';
    public $jam_ke_selesai = '';

    public $jam_mulai = '';
    public $jam_selesai = '';

    public $tanggal_sekarang = '';
    public $jam_sekarang = '';

    public $alasan = '';

    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */

    public $kelasList = [];
    public $siswaList = [];

    public $jadwalHariIni = [];
    public $jadwalAktif = null;

    /*
    |--------------------------------------------------------------------------
    | WHATSAPP
    |--------------------------------------------------------------------------
    */

    public $waLinkWakasek = null;
    public $waLinksWakasek = [];

    /*
    |--------------------------------------------------------------------------
    | MOUNT
    |--------------------------------------------------------------------------
    */

    public function mount()
    {
        $sekarang = Carbon::now('Asia/Jakarta');
        $hariIni = $sekarang->locale('id')->translatedFormat('l');
        $bertugasPiket = GuruPiket::query()
            ->where('id_pengguna', session('id_pengguna'))
            ->where('hari', $hariIni)
            ->where('aktif', true)
            ->exists() || JadwalPiket::query()
            ->where('id_guru', session('id_pengguna'))
            ->whereDate('tanggal', $sekarang->toDateString())
            ->where('status', 'Aktif')
            ->exists();

        if (! $bertugasPiket) {
            abort(403, 'Halaman ini khusus untuk guru yang bertugas piket.');
        }

        $this->kelasList = Kelas::orderBy('nama_kelas')->get();

        $this->updateWaktu();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE WAKTU
    |--------------------------------------------------------------------------
    */

    public function updateWaktu()
    {
        $now = Carbon::now('Asia/Jakarta');

        $this->jam_sekarang = $now->format('H:i:s');
        $this->tanggal_sekarang = $now->translatedFormat('d F Y');

        $this->cariJadwalAktif();
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA KELAS DIPILIH
    |--------------------------------------------------------------------------
    */

    public function updatedIdKelas($value)
    {
        $this->siswaTerpilih = [];

        $this->jadwalAktif = null;
        $this->jadwalHariIni = [];

        $this->jam_ke_mulai = '';
        $this->jam_ke_selesai = '';
        $this->jam_mulai = '';
        $this->jam_selesai = '';
        $this->id_jadwal = '';
        $this->mapel = '';

        if (!$value) {
            $this->siswaList = [];
            return;
        }

        $this->siswaList = Siswa::where('id_kelas', $value)
            ->orderBy('nama_siswa')
            ->get();

        $this->cariJadwalAktif();
    }

    /*
    |--------------------------------------------------------------------------
    | CARI JADWAL
    |--------------------------------------------------------------------------
    */

    public function cariJadwalAktif()
    {
        if (!$this->id_kelas) {
            return;
        }

        $now = Carbon::now('Asia/Jakarta');

        $hari = $now
            ->locale('id')
            ->translatedFormat('l');

        $jamSekarang = $now->format('H:i:s');

        $this->jadwalHariIni = Jadwal::query()
            ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
            ->where('jadwal.id_kelas', $this->id_kelas)
            ->where('hari', $hari)
            ->select('jadwal.*', 'pengguna.mapel_diampu', 'pengguna.nama as nama_guru')
            ->orderBy('jam_ke')
            ->get();

        $this->jadwalAktif = $this->jadwalHariIni
            ->first(function ($jadwal) use ($jamSekarang) {

                return $jadwal->jam_mulai <= $jamSekarang
                    && $jadwal->jam_selesai >= $jamSekarang;
            });

        if (!$this->jadwalAktif) {

            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';

            return;
        }

        $this->jam_ke_mulai =
            $this->jadwalAktif->jam_ke;

        $this->jam_ke_selesai =
            $this->jadwalAktif->jam_ke;

        $this->jam_mulai =
            $this->jadwalAktif->jam_mulai;

        $this->jam_selesai =
            $this->jadwalAktif->jam_selesai;
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA JAM SELESAI BERUBAH
    |--------------------------------------------------------------------------
    */

    public function updatedJamKeSelesai($value)
    {
        if (!$value) {
            return;
        }

        $jadwal = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if ($jadwal) {
            $this->jam_selesai = $jadwal->jam_selesai;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | JENIS DISPENSASI
    |--------------------------------------------------------------------------
    */

    public function updatedJenisDispensasi()
    {
        if ($this->jenis_dispensasi === 'Sehari Penuh') {

            $this->id_jadwal = '';
            $this->mapel = '';
            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;
            $this->jam_mulai = null;
            $this->jam_selesai = null;

        } elseif ($this->jenis_dispensasi === 'Per Mapel') {
            $this->id_jadwal = $this->jadwalAktif?->id_jadwal ?? '';
            $this->updatedIdJadwal($this->id_jadwal);
        } else {

            $this->id_jadwal = '';
            $this->mapel = '';
            $this->cariJadwalAktif();
        }
    }

    public function updatedIdJadwal($value): void
    {
        if (!$value || $this->jenis_dispensasi !== 'Per Mapel') {
            return;
        }

        $jadwal = collect($this->jadwalHariIni)
            ->firstWhere('id_jadwal', (int) $value);

        if (!$jadwal) {
            $this->id_jadwal = '';
            $this->mapel = '';
            return;
        }

        $this->jam_ke_mulai = $jadwal->jam_ke;
        $this->jam_ke_selesai = $jadwal->jam_ke;
        $this->jam_mulai = $jadwal->jam_mulai;
        $this->jam_selesai = $jadwal->jam_selesai;
        $this->mapel = $jadwal->mapel_diampu ?? '';
    }

    /*
    |--------------------------------------------------------------------------
    | NAMA KELAS
    |--------------------------------------------------------------------------
    */

    public function getKelasNamaProperty()
    {
        return collect($this->kelasList)
            ->firstWhere('id_kelas', $this->id_kelas)
            ?->nama_kelas ?? '-';
    }

    /*
    |--------------------------------------------------------------------------
    | SISWA YANG DIPILIH
    |--------------------------------------------------------------------------
    */

    public function getSiswaTerpilihDataProperty()
    {
        if (empty($this->siswaTerpilih)) {
            return collect();
        }

        return Siswa::whereIn(
            'id_siswa',
            $this->siswaTerpilih
        )
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('nama_siswa')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    public function simpan()
    {
        $this->validate([
            'id_kelas' => 'required|exists:kelas,id_kelas',

            'siswaTerpilih' => 'required|array|min:1',

            'siswaTerpilih.*' =>
                'required|exists:siswa,id_siswa',

            'jenis_dispensasi' =>
                'required|in:Per Jam,Sehari Penuh,Per Mapel',

            'id_jadwal' => 'required_if:jenis_dispensasi,Per Mapel|nullable|exists:jadwal,id_jadwal',

            'alasan' =>
                'required|string|min:3|max:1000',
        ], [
            'id_kelas.required' =>
                'Kelas wajib dipilih.',

            'siswaTerpilih.required' =>
                'Minimal pilih satu siswa.',

            'siswaTerpilih.min' =>
                'Minimal pilih satu siswa.',

            'alasan.required' =>
                'Alasan dispensasi wajib diisi.',

            'alasan.min' =>
                'Alasan minimal 3 karakter.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | PASTIKAN SEMUA SISWA MEMANG DARI KELAS TERPILIH
        |--------------------------------------------------------------------------
        */

        $siswaData = Siswa::whereIn(
            'id_siswa',
            $this->siswaTerpilih
        )
            ->where('id_kelas', $this->id_kelas)
            ->orderBy('nama_siswa')
            ->get();

        if ($siswaData->count() !== count($this->siswaTerpilih)) {

            $this->addError(
                'siswaTerpilih',
                'Ada siswa yang tidak sesuai dengan kelas.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | PER JAM
        |--------------------------------------------------------------------------
        */

        if ($this->jenis_dispensasi === 'Per Jam') {

            if (!$this->jadwalAktif) {

                $this->addError(
                    'jam_ke_mulai',
                    'Tidak ada jadwal yang sedang berlangsung.'
                );

                return;
            }

            if (!$this->jam_ke_selesai) {

                $this->addError(
                    'jam_ke_selesai',
                    'Jam selesai wajib dipilih.'
                );

                return;
            }

            if (
                (int) $this->jam_ke_selesai
                < (int) $this->jam_ke_mulai
            ) {

                $this->addError(
                    'jam_ke_selesai',
                    'Jam selesai tidak boleh sebelum jam mulai.'
                );

                return;
            }
        }

        $jadwalMapel = null;
        $guruMapel = null;
        $mapelSnapshot = null;

        if ($this->jenis_dispensasi === 'Per Mapel') {
            $jadwalMapel = Jadwal::query()
                ->join('pengguna', 'jadwal.id_guru', '=', 'pengguna.id_pengguna')
                ->where('jadwal.id_jadwal', $this->id_jadwal)
                ->where('jadwal.id_kelas', $this->id_kelas)
                ->where('jadwal.hari', Carbon::now('Asia/Jakarta')->locale('id')->translatedFormat('l'))
                ->select('jadwal.*', 'pengguna.mapel_diampu')
                ->first();

            if (!$jadwalMapel) {
                $this->addError('id_jadwal', 'Mapel tidak tersedia untuk kelas dan tanggal ini.');
                return;
            }

            $guruMapel = $jadwalMapel->id_guru;
            $mapelSnapshot = $jadwalMapel->mapel_diampu;
            $this->jam_ke_mulai = $jadwalMapel->jam_ke;
            $this->jam_ke_selesai = $jadwalMapel->jam_ke;
            $this->jam_mulai = $jadwalMapel->jam_mulai;
            $this->jam_selesai = $jadwalMapel->jam_selesai;
        }

        /*
        |--------------------------------------------------------------------------
        | SEHARI PENUH
        |--------------------------------------------------------------------------
        */

        if ($this->jenis_dispensasi === 'Sehari Penuh') {

            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;

            $this->jam_mulai = null;
            $this->jam_selesai = null;
        }

        /*
        |--------------------------------------------------------------------------
        | 1 TOKEN UNTUK 1 PENGAJUAN
        |--------------------------------------------------------------------------
        */

        $token = Str::random(40);

        $tanggal = Carbon::now(
            'Asia/Jakarta'
        )->toDateString();

        /*
        |--------------------------------------------------------------------------
        | SIMPAN SEMUA SISWA
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $siswaData,
            $token,
            $tanggal,
            $jadwalMapel,
            $guruMapel,
            $mapelSnapshot
        ) {

            foreach ($siswaData as $siswa) {

                DB::table('dispensasi')->insert([
                    'id_siswa' => $siswa->id_siswa,

                    'id_kelas' => $this->id_kelas,

                    'id_jadwal' => $jadwalMapel?->id_jadwal,

                    'id_guru' => $guruMapel,

                    'mapel' => $mapelSnapshot,

                    'jenis_dispensasi' =>
                        $this->jenis_dispensasi,

                    'tanggal' => $tanggal,

                    'jam_ke' =>
                        $this->jam_ke_mulai,

                    'jam_ke_mulai' =>
                        $this->jam_ke_mulai,

                    'jam_ke_selesai' =>
                        $this->jam_ke_selesai,

                    'jam_mulai' =>
                        $this->jam_mulai,

                    'jam_selesai' =>
                        $this->jam_selesai,

                    'alasan' =>
                        $this->alasan,

                    'id_guru_piket' =>
                        session('id_pengguna'),

                    'token' => $token,

                    'status' =>
                        'Menunggu Persetujuan',

                    'created_at' => now(),

                    'updated_at' => now(),
                ]);
            }
        });

        /*
        |--------------------------------------------------------------------------
        | BUAT PESAN WAKASEK
        |--------------------------------------------------------------------------
        */

        $this->waLinkWakasek = null;
        $this->waLinksWakasek = [];

        $wakasekList = Pengguna::where(
            'role',
            'wakasek'
        )
            ->whereNotNull('no_hp')
            ->get();

        if ($wakasekList->isEmpty()) {

            session()->flash(
                'warning',
                'Dispensasi tersimpan, tetapi akun Wakasek dengan nomor HP belum ditemukan.'
            );

        } else {

            /*
            |------------------------------------------------------------------
            | KETERANGAN WAKTU
            |------------------------------------------------------------------
            */

            if (
                $this->jenis_dispensasi === 'Per Jam'
            ) {

                $keteranganWaktu =
                    'Jam ke-' .
                    $this->jam_ke_mulai .
                    ' s/d ' .
                    $this->jam_ke_selesai .
                    ' (' .
                    Carbon::parse(
                        $this->jam_mulai
                    )->format('H:i') .
                    ' - ' .
                    Carbon::parse(
                        $this->jam_selesai
                    )->format('H:i') .
                    ')';

            } else {

                $keteranganWaktu =
                    'Sehari penuh';
            }

            /*
            |------------------------------------------------------------------
            | DAFTAR NAMA SISWA
            |------------------------------------------------------------------
            */

            $namaSiswa = $siswaData
                ->pluck('nama_siswa')
                ->map(
                    fn ($nama) => '• ' . $nama
                )
                ->implode("\n");

            /*
            |------------------------------------------------------------------
            | LINK APPROVAL
            |------------------------------------------------------------------
            */

            foreach ($wakasekList as $wakasek) {

                $noHpWakasek =
                    preg_replace(
                        '/[^0-9]/',
                        '',
                        $wakasek->no_hp
                    );

                if (
                    str_starts_with(
                        $noHpWakasek,
                        '0'
                    )
                ) {

                    $noHpWakasek =
                        '62' .
                        substr(
                            $noHpWakasek,
                            1
                        );

                } elseif (
                    !str_starts_with(
                        $noHpWakasek,
                        '62'
                    )
                ) {

                    $noHpWakasek =
                        '62' .
                        $noHpWakasek;
                }

                $linkApproval =
                    rtrim(
                        config('app.url'),
                        '/'
                    ) .
                    route(
                        'approve-dispensasi',
                        [
                            'token' => $token,
                            'wakasek' =>
                                $wakasek->id_pengguna,
                        ],
                        false
                    );

                /*
                |------------------------------------------------------------------
                | PESAN WA
                |------------------------------------------------------------------
                */

                $pesanWa =
                    "PENGAJUAN DISPENSASI\n\n" .

                    "Kelas: " .
                    $this->kelasNama .
                    "\n\n" .

                    "Siswa (" .
                    $siswaData->count() .
                    " orang):\n" .
                    $namaSiswa .
                    "\n\n" .

                    "Jenis: " .
                    $this->jenis_dispensasi .
                    "\n" .

                    "Tanggal: " .
                    $this->tanggal_sekarang .
                    "\n" .

                    "Waktu: " .
                    $keteranganWaktu .
                    "\n\n" .

                    "Alasan: " .
                    $this->alasan .
                    "\n\n" .

                    "Diajukan oleh: " .
                    session('nama') .
                    "\n\n" .

                    "Mohon persetujuan Bapak/Ibu Wakasek melalui link berikut:\n" .
                    $linkApproval;

                $this->waLinksWakasek[] = [
                    'nama' =>
                        $wakasek->nama,

                    'link' =>
                        'https://web.whatsapp.com/send?phone=' .
                        $noHpWakasek .
                        '&text=' .
                        urlencode($pesanWa),
                ];
            }

            /*
            |------------------------------------------------------------------
            | BUKA WHATSAPP
            |------------------------------------------------------------------
            */

            $this->waLinkWakasek =
                $this->waLinksWakasek[0]['link'];

            $this->dispatch(
                'buka-whatsapp',
                link: $this->waLinkWakasek
            );

            session()->flash(
                'success',
                'Pengajuan dispensasi berhasil dibuat untuk ' .
                $siswaData->count() .
                ' siswa.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | RESET SISWA SAJA
        |--------------------------------------------------------------------------
        */

        $this->siswaTerpilih = [];
    }
};
?>

<div>

    {{-- HEADER --}}
    <div class="mb-4">

        <h2 class="fw-bold mb-1">
            Pengajuan Dispensasi
        </h2>

        <p class="text-muted mb-0">
            Pilih kelas terlebih dahulu, lalu pilih siswa yang mendapat dispensasi.
        </p>

    </div>


    {{-- FORM --}}
    <div class="card border-0 shadow-sm">

        <div class="card-body p-4">

            <div class="row g-3">


                {{-- KELAS --}}
                <div class="col-12">

                    <label class="form-label fw-semibold">
                        Kelas
                    </label>

                    <select
                        wire:model.live="id_kelas"
                        class="form-select"
                    >

                        <option value="">
                            -- Pilih Kelas --
                        </option>

                        @foreach ($kelasList as $kelas)

                            <option
                                value="{{ $kelas->id_kelas }}"
                            >
                                {{ $kelas->nama_kelas }}
                            </option>

                        @endforeach

                    </select>

                    @error('id_kelas')

                        <small class="text-danger">
                            {{ $message }}
                        </small>

                    @enderror

                </div>


                {{-- SISWA --}}
                @if ($id_kelas)

                    <div class="col-12">

                        <label class="form-label fw-semibold">
                            Siswa yang Mendapat Dispensasi
                        </label>

                        @if ($siswaList->count())

                            <div
                                class="border rounded p-3"
                                style="max-height: 280px; overflow-y: auto;"
                            >

                                @foreach ($siswaList as $siswa)

                                    <div
                                        class="form-check mb-2"
                                    >

                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            value="{{ $siswa->id_siswa }}"
                                            wire:model.live="siswaTerpilih"
                                            id="siswa-{{ $siswa->id_siswa }}"
                                        >

                                        <label
                                            class="form-check-label"
                                            for="siswa-{{ $siswa->id_siswa }}"
                                        >
                                            {{ $siswa->nama_siswa }}
                                        </label>

                                    </div>

                                @endforeach

                            </div>

                            <div class="mt-2">

                                <span class="badge bg-primary">

                                    Siswa:
                                    {{ count($siswaTerpilih) }}
                                    orang

                                </span>

                            </div>

                        @else

                            <div class="alert alert-warning">
                                Belum ada siswa di kelas ini.
                            </div>

                        @endif

                        @error('siswaTerpilih')

                            <small class="text-danger">
                                {{ $message }}
                            </small>

                        @enderror

                    </div>

                @endif


                {{-- JENIS --}}
                <div class="col-md-6">

                    <label class="form-label fw-semibold">
                        Jenis Dispensasi
                    </label>

                    <select
                        wire:model.live="jenis_dispensasi"
                        class="form-select"
                    >

                        <option value="Per Jam">
                            Per Jam
                        </option>

                        <option value="Sehari Penuh">
                            Sehari Penuh
                        </option>

                        <option value="Per Mapel">
                            Per Mapel
                        </option>

                    </select>

                </div>


                {{-- TANGGAL --}}
                <div class="col-md-3">

                    <label class="form-label fw-semibold">
                        Tanggal
                    </label>

                    <div class="form-control bg-light">
                        {{ $tanggal_sekarang }}
                    </div>

                </div>


                {{-- JAM SEKARANG --}}
                <div class="col-md-3">

                    <label class="form-label fw-semibold">
                        Jam Sekarang
                    </label>

                    <div
                        id="jam-sekarang"
                        class="form-control bg-light fw-semibold"
                    >
                        {{ $jam_sekarang }}
                    </div>

                </div>


                {{-- MAPEL --}}
                @if ($jenis_dispensasi === 'Per Mapel')

                    <div class="col-12">
                        <label class="form-label fw-semibold">Mapel yang Diikuti</label>
                        <select wire:model.live="id_jadwal" class="form-select">
                            <option value="">-- Pilih Mapel --</option>
                            @foreach ($jadwalHariIni as $jadwal)
                                <option value="{{ $jadwal->id_jadwal }}">
                                    {{ $jadwal->mapel_diampu ?: 'Mapel belum diisi' }}
                                    · Jam ke-{{ $jadwal->jam_ke }}
                                    ({{ Carbon::parse($jadwal->jam_mulai)->format('H:i') }}-{{ Carbon::parse($jadwal->jam_selesai)->format('H:i') }})
                                    · {{ $jadwal->nama_guru }}
                                </option>
                            @endforeach
                        </select>
                        @error('id_jadwal')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>

                @endif

                {{-- JADWAL --}}
                @if ($jenis_dispensasi === 'Per Jam')

                    <div class="col-md-6">

                        <label class="form-label fw-semibold">
                            Mulai Dispensasi
                        </label>

                        <div class="form-control bg-light">

                            @if ($jam_ke_mulai)

                                Jam ke-{{ $jam_ke_mulai }}
                                —
                                {{ Carbon::parse($jam_mulai)->format('H:i') }}

                            @else

                                Tidak ada jadwal aktif

                            @endif

                        </div>

                    </div>


                    <div class="col-md-6">

                        <label class="form-label fw-semibold">
                            Sampai Jam
                        </label>

                        <select
                            wire:model.live="jam_ke_selesai"
                            class="form-select"
                        >

                            @if ($jadwalAktif)

                                @foreach ($jadwalHariIni as $jadwal)

                                    @if (
                                        $jadwal->jam_ke >= $jam_ke_mulai
                                    )

                                        <option
                                            value="{{ $jadwal->jam_ke }}"
                                        >

                                            Jam ke-{{ $jadwal->jam_ke }}
                                            —
                                            {{ Carbon::parse($jadwal->jam_mulai)->format('H:i') }}
                                            -
                                            {{ Carbon::parse($jadwal->jam_selesai)->format('H:i') }}

                                        </option>

                                    @endif

                                @endforeach

                            @else

                                <option value="">
                                    Tidak ada jadwal
                                </option>

                            @endif

                        </select>

                        @error('jam_ke_selesai')

                            <small class="text-danger">
                                {{ $message }}
                            </small>

                        @enderror

                    </div>


                    <div class="col-12">

                        <label class="form-label fw-semibold">
                            Waktu Dispensasi
                        </label>

                        <div class="form-control bg-light">

                            @if ($jam_mulai && $jam_selesai)

                                {{ Carbon::parse($jam_mulai)->format('H:i') }}
                                -
                                {{ Carbon::parse($jam_selesai)->format('H:i') }}

                            @else

                                -

                            @endif

                        </div>

                    </div>

                @elseif ($jenis_dispensasi === 'Sehari Penuh')

                    <div class="col-12">

                        <div class="alert alert-info mb-0">

                            <strong>Sehari Penuh</strong>

                            <br>

                            Dispensasi berlaku untuk seluruh jadwal
                            siswa pada hari ini.

                        </div>

                    </div>

                @elseif ($jenis_dispensasi === 'Per Mapel')

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Guru Pengajar</label>
                        <div class="form-control bg-light">{{ $id_jadwal ? ($jadwalHariIni->firstWhere('id_jadwal', (int) $id_jadwal)->nama_guru ?? '-') : '-' }}</div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Waktu Mapel</label>
                        <div class="form-control bg-light">
                            {{ $jam_ke_mulai ? 'Jam ke-'.$jam_ke_mulai.' · '.Carbon::parse($jam_mulai)->format('H:i').' - '.Carbon::parse($jam_selesai)->format('H:i') : '-' }}
                        </div>
                    </div>

                @endif


                {{-- ALASAN --}}
                <div class="col-12">

                    <label class="form-label fw-semibold">
                        Alasan Dispensasi
                    </label>

                    <textarea
                        wire:model="alasan"
                        class="form-control"
                        rows="4"
                        placeholder="Masukkan alasan dispensasi..."
                    ></textarea>

                    @error('alasan')

                        <small class="text-danger">
                            {{ $message }}
                        </small>

                    @enderror

                </div>


                {{-- RINGKASAN --}}
                @if ($id_kelas && count($siswaTerpilih))

                    <div class="col-12">

                        <div class="card bg-light border">

                            <div class="card-body">

                                <h5 class="fw-bold mb-3">
                                    Pengajuan Dispensasi
                                </h5>

                                <div class="mb-2">
                                    <strong>Kelas:</strong>
                                    {{ $this->kelasNama }}
                                </div>

                                <div class="mb-2">

                                    <strong>
                                        Siswa:
                                    </strong>

                                    {{ count($siswaTerpilih) }}
                                    orang

                                </div>

                                <div class="mb-3">

                                    @foreach (
                                        $this->siswaTerpilihData
                                        as $siswa
                                    )

                                        <div>
                                            • {{ $siswa->nama_siswa }}
                                        </div>

                                    @endforeach

                                </div>

                                <div class="mb-2">

                                    <strong>Jenis:</strong>
                                    {{ $jenis_dispensasi }}

                                </div>

                                <div class="mb-2">

                                    <strong>Tanggal:</strong>
                                    {{ $tanggal_sekarang }}

                                </div>

                                <div class="mb-2">

                                    <strong>Jam:</strong>

                                    @if (
                                        $jenis_dispensasi === 'Per Jam'
                                    )

                                        Jam ke-{{ $jam_ke_mulai }}
                                        s/d
                                        Jam ke-{{ $jam_ke_selesai }}

                                        @if ($jam_mulai && $jam_selesai)

                                            (
                                            {{ Carbon::parse($jam_mulai)->format('H:i') }}
                                            -
                                            {{ Carbon::parse($jam_selesai)->format('H:i') }}
                                            )

                                        @endif

                                    @else

                                        Sehari penuh

                                    @endif

                                </div>

                                <div>

                                    <strong>Alasan:</strong>
                                    {{ $alasan ?: '-' }}

                                </div>

                            </div>

                        </div>

                    </div>

                @endif


                {{-- BUTTON --}}
                <div class="col-12 d-flex justify-content-end">

                    <button
                        type="button"
                        wire:click="simpan"
                        wire:loading.attr="disabled"
                        class="btn btn-primary px-4"
                    >

                        <span wire:loading.remove>
                            Kirim ke Wakasek
                        </span>

                        <span wire:loading>
                            Mengirim...
                        </span>

                    </button>

                </div>


                {{-- SUCCESS --}}
                @if (session()->has('success'))

                    <div class="col-12">

                        <div class="alert alert-success mb-0">

                            ✓ {{ session('success') }}

                        </div>

                    </div>

                @endif


                {{-- WARNING --}}
                @if (session()->has('warning'))

                    <div class="col-12">

                        <div class="alert alert-warning mb-0">

                            ⚠ {{ session('warning') }}

                        </div>

                    </div>

                @endif


                {{-- WHATSAPP --}}
                @if ($waLinkWakasek)

                    <div class="col-12">

                        <div
                            class="alert alert-success d-flex justify-content-between align-items-center flex-wrap gap-2"
                        >

                            <div>
                                ✓ Link WhatsApp Wakasek sudah dibuat.
                            </div>

                            <a
                                href="{{ $waLinkWakasek }}"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-success btn-sm"
                            >
                                🟢 Buka WhatsApp
                            </a>

                        </div>

                    </div>

                @endif

            </div>

        </div>

    </div>

</div>
<script>
    function updateJam() {
        const sekarang = new Date();

        const jam = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');

        const element = document.getElementById('jam-sekarang');

        if (element) {
            element.textContent = `${jam}:${menit}:${detik}`;
        }
    }

    updateJam();
    setInterval(updateJam, 1000);
</script>

@script
<script>
    $wire.on('buka-whatsapp', (event) => {
        window.open(event.link, '_blank');
    });
</script>
@endscript
