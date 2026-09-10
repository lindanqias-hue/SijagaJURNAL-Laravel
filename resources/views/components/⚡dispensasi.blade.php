<?php

use Livewire\Component;
use App\Models\Siswa;
use App\Models\Dispensasi;
use App\Models\Jadwal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

        'status' => 'Aktif',
    ]);

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
| SIMPAN PENERIMA
|--------------------------------------------------------------------------
*/

foreach ($guruIds as $idGuru) {

    DB::table('dispensasi_penerima')->insert([
        'id_dispensasi' => $dispensasi->id_dispensasi,
        'id_guru' => $idGuru,
        'dibaca_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

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
            Kirim ke Guru Mengajar
        </span>

        <span wire:loading>
            Mengirim...
        </span>

    </button>

</div>

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

        document.getElementById('jam-sekarang').textContent =
            `${jam}:${menit}:${detik}`;
    }

    updateJam();
    setInterval(updateJam, 1000);
</script>