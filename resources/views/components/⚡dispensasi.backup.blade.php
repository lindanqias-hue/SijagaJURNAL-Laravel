<?php

use Livewire\Component;
use App\Models\Siswa;
use App\Models\Dispensasi;
use App\Models\Jadwal;
use App\Models\Pengguna;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class extends Component
{
    public $id_siswa = '';
    public $jenis_dispensasi = 'Per Jam';

    public $kelasNama = '';

    public $jam_ke_mulai = '';
    public $jam_ke_selesai = '';

    public $jam_mulai = '';
    public $jam_selesai = '';

    public $jam_sekarang = '';
    public $tanggal_sekarang = '';

    public $alasan = '';
    public $pesanTerkirim = '';

    public $siswaList = [];
    public $jadwalHariIni = [];
    public $jadwalAktif = null;

    // Link WhatsApp approval Wakasek dari pengajuan terakhir
    public $waLinkWakasek = null;
    public $waLinksWakasek = [];

    public function mount()
    {
        $this->siswaList = Siswa::where('id_kelas', 4)
            ->orderBy('nama_siswa')
            ->get();

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
    | CARI JADWAL YANG SEDANG BERLANGSUNG
    |--------------------------------------------------------------------------
    */

    public function cariJadwalAktif()
    {
        if (!$this->id_siswa) {
            $this->jadwalAktif = null;
            $this->jadwalHariIni = [];

            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';

            return;
        }

        $siswa = Siswa::find($this->id_siswa);

        if (!$siswa) {
            return;
        }

        $now = Carbon::now('Asia/Jakarta');

        $hari = $now->locale('id')->translatedFormat('l');
        $jamSekarang = $now->format('H:i:s');

        /*
         * Ambil semua jadwal kelas siswa hari ini
         */
        $this->jadwalHariIni = Jadwal::where('id_kelas', $siswa->id_kelas)
            ->where('hari', $hari)
            ->orderBy('jam_ke')
            ->get();

        /*
         * Cari jadwal yang sedang berlangsung
         */
        $this->jadwalAktif = $this->jadwalHariIni
            ->first(function ($jadwal) use ($jamSekarang) {
                return $jadwal->jam_mulai <= $jamSekarang
                    && $jadwal->jam_selesai >= $jamSekarang;
            });

        /*
         * Kalau tidak ada jadwal aktif
         */
        if (!$this->jadwalAktif) {
            $this->jam_ke_mulai = '';
            $this->jam_ke_selesai = '';
            $this->jam_mulai = '';
            $this->jam_selesai = '';

            return;
        }

        /*
         * Jam mulai otomatis = jam yang sedang berlangsung
         */
        $this->jam_ke_mulai = $this->jadwalAktif->jam_ke;
        $this->jam_mulai = $this->jadwalAktif->jam_mulai;

        /*
         * Default sampai = jam yang sedang berlangsung
         */
        $this->jam_ke_selesai = $this->jadwalAktif->jam_ke;

        $this->jam_selesai = $this->jadwalAktif->jam_selesai;
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA SISWA DIGANTI
    |--------------------------------------------------------------------------
    */

    public function updatedIdSiswa()
{
    $siswa = Siswa::with('kelas')->find($this->id_siswa);

    if ($siswa && $siswa->kelas) {
        $this->kelasNama = $siswa->kelas->nama_kelas;
    } else {
        $this->kelasNama = '';
    }

    $this->cariJadwalAktif();
}

    /*
    |--------------------------------------------------------------------------
    | KETIKA JENIS DISPENSASI DIGANTI
    |--------------------------------------------------------------------------
    */

    public function updatedJenisDispensasi()
    {
        $this->cariJadwalAktif();
    }

    /*
    |--------------------------------------------------------------------------
    | KETIKA JAM SELESAI DIPILIH
    |--------------------------------------------------------------------------
    */

    public function updatedJamKeSelesai($value)
    {
        if (!$value) {
            return;
        }

        $jadwalSelesai = collect($this->jadwalHariIni)
            ->firstWhere('jam_ke', (int) $value);

        if ($jadwalSelesai) {
            $this->jam_selesai = $jadwalSelesai->jam_selesai;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SIMPAN
    |--------------------------------------------------------------------------
    */

    public function simpan()
{
     $this->pesanTerkirim = '';

    $this->validate([
        'id_siswa' => 'required',
        'jenis_dispensasi' => 'required|in:Per Jam,Sehari Penuh',
        'alasan' => 'required|min:3',
    ], [
        'id_siswa.required' => 'Siswa wajib dipilih.',
        'alasan.required' => 'Alasan dispensasi wajib diisi.',
        'alasan.min' => 'Alasan minimal 3 karakter.',
    ]);

    $siswa = Siswa::findOrFail($this->id_siswa);

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
    | SIMPAN DISPENSASI
    |--------------------------------------------------------------------------
    */

    $dispensasi = Dispensasi::create([
        'id_siswa' => $siswa->id_siswa,
        'id_kelas' => $siswa->id_kelas,

        'jenis_dispensasi' => $this->jenis_dispensasi,

        'tanggal' => Carbon::now('Asia/Jakarta')->toDateString(),

        'jam_ke' => $this->jam_ke_mulai,

        'jam_ke_mulai' => $this->jam_ke_mulai,
        'jam_ke_selesai' => $this->jam_ke_selesai,

        'jam_mulai' => $this->jam_mulai,
        'jam_selesai' => $this->jam_selesai,

        'alasan' => $this->alasan,

        'id_guru_piket' => session('id_pengguna'),

        // Token unik untuk link approval Wakasek (dipakai tanpa perlu login)
        'token' => Str::random(40),

        // Menunggu persetujuan Wakasek dulu sebelum dianggap Aktif/Selesai
        'status' => 'Menunggu Persetujuan',
    ]);

    /*
    |--------------------------------------------------------------------------
    | GENERATE LINK WHATSAPP KE WAKASEK
    |--------------------------------------------------------------------------
    | Ambil akun wakasek (role = wakasek) yang punya nomor HP terisi,
    | lalu buat link wa.me otomatis berisi ringkasan pengajuan + link
    | approval yang mengarah ke halaman approve-dispensasi (token).
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| GENERATE LINK WHATSAPP KE WAKASEK
|--------------------------------------------------------------------------
| Setiap Wakasek mendapatkan link approval masing-masing.
| Link membawa token dispensasi + ID Wakasek.
|--------------------------------------------------------------------------
*/

$this->waLinkWakasek = null;
$this->waLinksWakasek = [];

$wakasekList = Pengguna::where('role', 'wakasek')
    ->whereNotNull('no_hp')
    ->get();

if ($wakasekList->isEmpty()) {

    session()->flash(
        'warning',
        'Dispensasi tersimpan, tetapi akun Wakasek dengan nomor HP belum ditemukan.'
    );

} else {

    // Keterangan waktu dispensasi
    if ($this->jenis_dispensasi === 'Per Jam') {

        $keteranganWaktu = 'Jam ke-' . $this->jam_ke_mulai
            . ' s/d ' . $this->jam_ke_selesai
            . ' (' . Carbon::parse($this->jam_mulai)->format('H:i')
            . ' - ' . Carbon::parse($this->jam_selesai)->format('H:i') . ')';

    } else {

        $keteranganWaktu = 'Sehari penuh';
    }

    // Buat link WhatsApp untuk setiap Wakasek
    foreach ($wakasekList as $wakasek) {

        $noHpWakasek = preg_replace('/[^0-9]/', '', $wakasek->no_hp);

        if (str_starts_with($noHpWakasek, '0')) {
            $noHpWakasek = '62' . substr($noHpWakasek, 1);
        } elseif (!str_starts_with($noHpWakasek, '62')) {
            $noHpWakasek = '62' . $noHpWakasek;
        }

        // Link approval khusus Wakasek ini
       $linkApproval = rtrim(config('app.url'), '/') . route(
    'approve-dispensasi',
    [
        'token' => $dispensasi->token,
        'wakasek' => $wakasek->id_pengguna,
    ],
    false
);
        $pesanWa = "Permohonan Dispensasi Siswa\n\n"
            . "Nama Siswa: {$siswa->nama_siswa}\n"
            . "Kelas: {$this->kelasNama}\n"
            . "Jenis: {$this->jenis_dispensasi}\n"
            . "Waktu: {$keteranganWaktu}\n"
            . "Alasan: {$this->alasan}\n"
            . "Diajukan oleh: " . session('nama') . "\n\n"
            . "Mohon persetujuan Bapak/Ibu Wakasek melalui link berikut:\n"
            . $linkApproval;

        $this->waLinksWakasek[] = [
            'nama' => $wakasek->nama,
            'link' => 'https://web.whatsapp.com/send?phone=' . $noHpWakasek
                . '&text=' . urlencode($pesanWa),
        ];
    }

    // Link pertama dibuka otomatis
    $this->waLinkWakasek = $this->waLinksWakasek[0]['link'];

    $this->dispatch(
        'buka-whatsapp',
        link: $this->waLinkWakasek
    );

    session()->flash(
        'success',
        'Dispensasi berhasil disimpan dan link WhatsApp sudah dibuat untuk semua Wakasek.'
    );
}

   /*
|--------------------------------------------------------------------------
| KIRIM NOTIFIKASI KE GURU MENGAJAR
|--------------------------------------------------------------------------
*/

$now = Carbon::now('Asia/Jakarta');

$hari = $now
    ->locale('id')
    ->translatedFormat('l');

/*
|--------------------------------------------------------------------------
| CARI JADWAL GURU
|--------------------------------------------------------------------------
*/

$query = Jadwal::where('id_kelas', $siswa->id_kelas)
    ->where('hari', $hari);

/*
|--------------------------------------------------------------------------
| PER JAM
|--------------------------------------------------------------------------
*/

if ($this->jenis_dispensasi === 'Per Jam') {

    $query->whereBetween('jam_ke', [
        (int) $this->jam_ke_mulai,
        (int) $this->jam_ke_selesai
    ]);
}

/*
|--------------------------------------------------------------------------
| SEHARI PENUH
|--------------------------------------------------------------------------
| Tidak diberi filter jam,
| jadi semua guru yang mengajar kelas tersebut hari ini
| akan menerima dispensasi.
|--------------------------------------------------------------------------
*/

$guruIds = $query
    ->pluck('id_guru')
    ->unique()
    ->values();

    /*
    |--------------------------------------------------------------------------
    | RESET FORM
    |--------------------------------------------------------------------------
    */

    $this->reset([
        'id_siswa',
        'alasan',
    ]);

    $this->kelasNama = '';

    $this->jenis_dispensasi = 'Per Jam';

    $this->jam_ke_mulai = '';
    $this->jam_ke_selesai = '';
    $this->jam_mulai = '';
    $this->jam_selesai = '';

    $this->jadwalAktif = null;

    /*
    |--------------------------------------------------------------------------
    | PESAN BERHASIL
    |--------------------------------------------------------------------------
    */

    session()->flash(
    'success',
    'Terkirim ke guru mengajar'
);
}
};
?>

<div>

    {{-- HEADER --}}
    <div class="mb-4">

        <h2 class="fw-bold mb-1">
            Dispensasi Siswa
        </h2>

        <p class="text-muted mb-0">
            Buat dispensasi siswa berdasarkan jadwal hari ini.
        </p>

    </div>


    {{-- FORM --}}
    <div class="card border-0 shadow-sm">

        <div class="card-body p-4">

            <div class="row g-3">


                {{-- SISWA --}}
<div class="col-md-6">

    <label class="form-label fw-semibold">
        Nama Siswa
    </label>

    <select
        wire:model.live="id_siswa"
        class="form-select"
    >

        <option value="">
            -- Pilih Siswa --
        </option>

        @foreach ($siswaList as $siswa)

            <option value="{{ $siswa->id_siswa }}">
                {{ $siswa->nama_siswa }}
            </option>

        @endforeach

    </select>

    @error('id_siswa')
        <small class="text-danger">
            {{ $message }}
        </small>
    @enderror

</div>

{{-- KELAS --}}
<div class="col-md-6">

    <label class="form-label fw-semibold">
        Kelas
    </label>

    <input
        type="text"
        class="form-control"
        value="{{ $kelasNama ?: '-' }}"
        readonly
    >

</div>


                {{-- JENIS DISPENSASI --}}
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

                    </select>

                </div>


                {{-- TANGGAL --}}
                <div class="col-md-4">

                    <label class="form-label fw-semibold">
                        Tanggal
                    </label>

                    <div class="form-control bg-light">
                        {{ $tanggal_sekarang }}
                    </div>

                </div>


                {{-- JAM SEKARANG --}}
<div class="col-md-4">

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


                {{-- JAM AKTIF --}}
                <div class="col-md-4">

                    <label class="form-label fw-semibold">
                        Jadwal Aktif
                    </label>

                    <div class="form-control bg-light">

                        @if ($jadwalAktif)

                            Jam ke-{{ $jadwalAktif->jam_ke }}

                        @else

                            Tidak ada jadwal aktif

                        @endif

                    </div>

                </div>


                {{-- PER JAM --}}
                @if ($jenis_dispensasi === 'Per Jam')

                    {{-- MULAI --}}
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

                                -

                            @endif

                        </div>

                    </div>


                    {{-- SELESAI --}}
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

                                    @if ($jadwal->jam_ke >= $jam_ke_mulai)

                                        <option value="{{ $jadwal->jam_ke }}">

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


                    {{-- RENTANG WAKTU --}}
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

                @else

                    {{-- SEHARI PENUH --}}
                    <div class="col-12">

                        <div class="alert alert-info mb-0">

                            <strong>Sehari Penuh</strong>

                            <br>

                            Dispensasi berlaku untuk seluruh jadwal
                            siswa pada hari ini.

                            @if ($jadwalAktif)

                                <br>
                                Saat ini:
                                <strong>
                                    Jam ke-{{ $jadwalAktif->jam_ke }}
                                </strong>
                                —
                                {{ Carbon::parse($jadwalAktif->jam_mulai)->format('H:i') }}
                                -
                                {{ Carbon::parse($jadwalAktif->jam_selesai)->format('H:i') }}

                            @endif

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


                {{-- BUTTON --}}
<div class="col-12 d-flex justify-content-end align-items-center gap-3">

    {{-- NOTIFIKASI TERKIRIM --}}
    @if (session()->has('success'))

        <div class="d-flex align-items-center text-success fw-semibold">
            <span
                class="me-2"
                style="font-size: 20px;"
            >
                ✓
            </span>

            {{ session('success') }}
        </div>

    @endif

    {{-- BUTTON --}}
    <button
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


{{-- PERINGATAN: AKUN WAKASEK BELUM DITEMUKAN --}}
@if (session()->has('warning'))

    <div class="col-12">
        <div class="alert alert-warning mt-3 mb-0">
            ⚠ {{ session('warning') }}
        </div>
    </div>

@endif


{{-- FALLBACK: TOMBOL BUKA WHATSAPP MANUAL --}}
@if ($waLinkWakasek)

    <div class="col-12">
        <div class="alert alert-success mt-3 mb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">

            <div>
                ✓ Dispensasi terkirim. Link WhatsApp ke Wakasek sudah dibuat.
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
    
    <?php

    use Livewire\Component;
    use App\Models\Siswa;
    use App\Models\Kelas;
    use App\Models\Dispensasi;
    use App\Models\Jadwal;
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

        $this->jadwalHariIni = Jadwal::where(
            'id_kelas',
            $this->id_kelas
        )
            ->where('hari', $hari)
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

            $this->jam_ke_mulai = null;
            $this->jam_ke_selesai = null;
            $this->jam_mulai = null;
            $this->jam_selesai = null;

        } else {

            $this->cariJadwalAktif();
        }
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
                'required|in:Per Jam,Sehari Penuh',

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
            $tanggal
        ) {

            foreach ($siswaData as $siswa) {

                DB::table('dispensasi')->insert([
                    'id_siswa' => $siswa->id_siswa,

                    'id_kelas' => $this->id_kelas,

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

                @else

                    <div class="col-12">

                        <div class="alert alert-info mb-0">

                            <strong>Sehari Penuh</strong>

                            <br>

                            Dispensasi berlaku untuk seluruh jadwal
                            siswa pada hari ini.

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

        const jam =
            String(sekarang.getHours())
                .padStart(2, '0');

        const menit =
            String(sekarang.getMinutes())
                .padStart(2, '0');

        const detik =
            String(sekarang.getSeconds())
                .padStart(2, '0');

        const element =
            document.getElementById(
                'jam-sekarang'
            );

        if (element) {

            element.textContent =
                `${jam}:${menit}:${detik}`;
        }
    }

    updateJam();

    setInterval(
        updateJam,
        1000
    );

</script>


@script

<script>

    $wire.on(
        'buka-whatsapp',
        (event) => {

            window.open(
                event.link,
                '_blank'
            );

        }
    );

</script>

@endscript

</div>

<script>
    function updateJam() {
        const sekarang = new Date();

        const jam = String(sekarang.getHours()).padStart(2, '0');
        const menit = String(sekarang.getMinutes()).padStart(2, '0');
        const detik = String(sekarang.getSeconds()).padStart(2, '0');

        document.getElementById('jam-sekarang').textContent =
            `${jam}:${menit}:${detik}`;
    }

    updateJam();
    setInterval(updateJam, 1000);
</script>

@script
<script>
    // Buka tab WhatsApp otomatis begitu link berhasil dibuat di server
    $wire.on('buka-whatsapp', (event) => {
        window.open(event.link, '_blank');
    });
</script>
@endscript
